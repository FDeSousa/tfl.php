#!/usr/bin/python

from __future__ import print_function

from datetime import datetime
import cgi
import cgitb
import query
import status

# cgitb.enable()
cgitb.enable(display=1)

# Queries for parse_args
QUERIES = {
    query.PREDICTION_DETAILED:  query.DetailedPredictionQuery,
    query.PREDICTION_SUMMARY:   query.SummaryPredictionQuery,
    query.LINE_STATUS:          query.LineStatusQuery,
    query.STATION_STATUS:       query.StationStatusQuery,
    query.STATIONS_LIST:        query.StationListQuery
}

def parse_query(form):
    request = form.getfirst(query.REQUEST)
    if request:
        start_time = datetime.now()
        query_class = QUERIES.get(request)

        if query_class:
            query_inst = query_class(form)
            return query_inst
        else:
            raise status.RequestError(status.StatusCodes.HTTP_BAD_REQUEST,
                                      "Invalid request '{}'".format(request))
    else:
        raise status.RequestError(status.StatusCodes.HTTP_BAD_REQUEST,
                                  "Invalid empty request")

def print_big(content, buffersize=8192):
    content = str(content)

    for l in range(0, len(content) + 1, buffersize):
        print(content[l:l + buffersize], end='')

def main():
    try:
        form = cgi.FieldStorage()
        req = parse_query(form)
        resp = req.fetch()

        print_big("Content-Type: application/json\n\n" + resp)
    except (status.RequestError, status.ResponseError) as re:
        print_big("Content-Type: text/html\n" + re.httpheader + "\n")
        if re.canhavebody and re.message:
            print_big(re.message)
    except Exception as e:
        cgitb.handler()

if __name__ == '__main__':
    main()
