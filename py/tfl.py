#!/usr/bin/python

from datetime import datetime
import cgi
import cgitb

import query
import status

cgitb.enable()
# cgitb.enable(display=0, logdir="./")

START_TIME = datetime.now()

# Query arguments
REQUEST = "request"
LINE = "line"
STATION = "station"
INCIDENTS_ONLY = "incidentsonly"

# Query types
PREDICTION_DETAILED = "predictiondetailed"
PREDICTION_SUMMARY = "predictionsummary"
LINE_STATUS = "linestatus"
STATION_STATUS = "stationstatus"
STATIONS_LIST = "stationslist"

# URL and file path shit
BASE_URL = "http://cloud.tfl.gov.uk/trackernet/"
BASE_FILE = "./cache/"
DIV = "/"
FILE_EXTENSION = ".json"

# Queries for parse_args
QUERIES = {
    PREDICTION_DETAILED: query.DetailedPredictionQuery,
    PREDICTION_SUMMARY: query.SummaryPredictionQuery,
    LINE_STATUS: query.LineStatusQuery,
    STATION_STATUS: query.StationStatusQuery,
    STATIONS_LIST: query.StationListQuery
}

# Full list of lines and codes
LINES_LIST = {
    'b': 'Bakerloo',
    'c': 'Central',
    'd': 'District',
    'h': 'Hammersmith & Circle',
    'j': 'Jubilee',
    'm': 'Metropolitan',
    'n': 'Northern',
    'p': 'Piccadilly',
    'v': 'Victoria',
    'w': 'Waterloo & City'
}

def parse_query(form):
    request = form.getfirst(REQUEST).value
    query_class = QUERIES.get(request)
    if query_class:
        query_inst = query_class(form)
        return query_inst
    else:
        raise ValueError("Invalid request '{}'".format(request))

def main():
    print "Content-Type: application/json"
    print ""

    # s = '\n\t'.join('{0}: {1}'.format(k, v) for k, v in arguments().items())
    # print '{\n\t', s, '\n}'

    form = cgi.FieldStorage()
    q = parse_query(form)

    # import urllib2
    # req = urllib2.Request(BASE_URL + LINE_STATUS)
    # res = urllib2.urlopen(req)
    # print res.read()

main()
