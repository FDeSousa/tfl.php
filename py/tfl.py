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
    query_string = environ['QUERY_STRING'].lower()
    query_params = urlparse.parse_qs(query_string)
    query_class = None

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

    # Resolve the parameters that exist
    try:
        for param in query_class.params:
            form[param] = query_params[param][0]
    except KeyError as ke:
        raise RequestError(StatusCodes.HTTP_BAD_REQUEST,
                           "Missing parameter '{}'".format(ke.message))

    logging.info('Request: %s', form)
    query_instance = query_class(form)
    return query_instance

def main(environ, start_response):
    logging.basicConfig(filename='tfl.py.log', level=logging.DEBUG,
        format='%(asctime)s: %(message)s', datefmt='%Y-%m-%d %H:%M:%S')

    status_code = StatusCodes.gethttpstatus(StatusCodes.HTTP_INTERNAL_SERVER_ERROR)
    response_headers = [("Content-Type", "application/json; charset=UTF-8")]
    response_body = []

    start_time = datetime.now()

    try:
        req = parse_query(environ)
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

        logging.info('Response: %s; Start time: %s; End time: %s',
                     status_code, start_time, end_time)
        start_response(status_code, response_headers)
        return response_body
    except Exception as e:
        logging.exception('Unknown exception sending response')
    finally:
        logging.shutdown()

if __name__ == '__main__':
    CGIHandler().run(main)
