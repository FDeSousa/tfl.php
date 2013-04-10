#!/usr/bin/python

from datetime import datetime
import cgi
import cgitb

import query
import status

cgitb.enable()
# cgitb.enable(display=0, logdir="./")

START_TIME = datetime.now()


# Queries for parse_args
QUERIES = {
    query.PREDICTION_DETAILED:  query.DetailedPredictionQuery,
    query.PREDICTION_SUMMARY:   query.SummaryPredictionQuery,
    query.LINE_STATUS:          query.LineStatusQuery,
    query.STATION_STATUS:       query.StationStatusQuery,
    query.STATIONS_LIST:        query.StationListQuery
}

def parse_query(form):
    request = form.getfirst(query.REQUEST).value
    query_class = QUERIES.get(request)
    if query_class:
        query_inst = query_class(form)
        return query_inst
    else:
        raise ValueError("Invalid request '{}'".format(request))

def main():
    print "Content-Type: application/json\n"

    form = cgi.FieldStorage()
    req = parse_query(form)
    resp = req.fetch()

    print resp

main()
