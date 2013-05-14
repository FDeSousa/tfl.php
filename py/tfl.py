#!/usr/bin/python

from __future__ import print_function

from datetime import datetime
from status import StatusCodes, RequestError, ResponseError
from wsgiref.handlers import CGIHandler
import query
import types
import urlparse
import logging

# Queries for parse_args
QUERIES = {
    query.PREDICTION_DETAILED:  query.DetailedPredictionQuery,
    query.PREDICTION_SUMMARY:   query.SummaryPredictionQuery,
    query.LINE_STATUS:          query.LineStatusQuery,
    query.STATION_STATUS:       query.StationStatusQuery,
    query.STATIONS_LIST:        query.StationListQuery
}

SEQUENCES_TYPE = (set, dict, list, tuple)

def parse_query(environ):
    form = {}
    query_class = None
    query_string = ''

    content_length = int(environ.get('CONTENT_LENGTH', 0))
    request_method = environ['REQUEST_METHOD']

    if request_method == 'GET':
        query_string = environ['QUERY_STRING'].lower()
    # elif request_method == 'POST':
    #     wsgi_input = environ['wsgi.input']
    #     request  = environ['wsgi.input'].read()
    #     read_s = wsgi_input.read()
    #     decoded = read_s.decode()
    #     logging.info('WSGI input: %s; Read line: %s; Decoded: %s',
    #         wsgi_input, read_s, decoded)
    #     query_string = decoded.lower()
    else:
        raise RequestError(StatusCodes.HTTP_METHOD_NOT_ALLOWED,
            "Only accept 'GET' method, not {}".format(request_method))

    logging.info('Method: %s; Query string: %s', request_method, query_string)

    query_params = urlparse.parse_qs(query_string)
    # Resolve the query class to instantiate and return
    try:
        # Falls back to an empty list so the exception handler catches it
        request = query_params.get(query.REQUEST, [])[0]
        query_class = QUERIES[request]
    except KeyError as ke:
        raise RequestError(StatusCodes.HTTP_BAD_REQUEST,
            "Invalid request '{}'".format(ke.message)
            if ke.message else "Empty request")
    except IndexError as ie:
        raise RequestError(StatusCodes.HTTP_BAD_REQUEST, "Empty request")

    # Resolve the parameters that exist, leave out all others
    for param in query_class.params:
        if param in query_params:
            form[param] = query_params[param][0]

    try:
        query_instance = query_class(form)
    except KeyError as ke:
        raise RequestError(StatusCodes.HTTP_BAD_REQUEST,
            "Missing non-optional parameter '{}'".format(ke.message))

    return (query_instance, form)

def main(environ, start_response):
    logging.basicConfig(filename='tfl.py.log', level=logging.DEBUG,
        format='%(asctime)s: %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

    status_code = StatusCodes.gethttpstatus(StatusCodes.HTTP_INTERNAL_SERVER_ERROR)
    response_headers = [("Content-Type", "application/json; charset=UTF-8")]
    response_body = []
    form = None

    start_time = datetime.now()

    logging.info('Environ: %s', environ)

    try:
        req, form = parse_query(environ)
        response_body = req.fetch()
        status_code = StatusCodes.gethttpstatus(StatusCodes.HTTP_OK)
    except (RequestError, ResponseError) as re:
        if re.status.iserror:
            logging.exception('Error in request or response')
        status_code = re.httpstatus
        response_body = re.message if re.status.canhavebody else ''
    except Exception as e:
        logging.exception('Unknown exception processing request')

    end_time = datetime.now()

    try:
        # Make sure we're passing a sensible sequence
        if not isinstance(response_body, SEQUENCES_TYPE):
            response_body = [response_body]

        # Make sure all elements of body are strings and total the length
        content_length = 0
        for elem in response_body:
            if not isinstance(elem, types.StringTypes):
                elem = str(elem)
            content_length += len(elem)
        response_headers.append(("Content-Length", str(content_length)))

        logging.info('Request: %s; Response: %s; Start time: %s; End time: %s',
                     form, status_code, start_time, end_time)
    except Exception as e:
        logging.exception('Unknown exception sending response')
        status_code = StatusCodes.gethttpstatus(StatusCodes.HTTP_INTERNAL_SERVER_ERROR)
        response_body = ['']
    finally:
        logging.shutdown()

    start_response(status_code, response_headers)
    return response_body

if __name__ == '__main__':
    CGIHandler().run(main)
