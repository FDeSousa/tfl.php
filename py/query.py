#!/usr/bin/python

from __future__ import print_function

from abc import ABCMeta, abstractmethod
import errno
import json
import os
import status
import urllib2
import logging

try:
    import xml.etree.cElementTree as etree
except ImportError:
    import xml.etree.ElementTree as etree


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

# URL and file path shit
BASE_URL = "http://cloud.tfl.gov.uk/trackernet"
BASE_FILE = "cache"
FILE_EXTENSION = ".json"


def _parse_boolean(s_val):
    s_val = s_val.lower()
    b_val = s_val in ('1', 'true', 'yes', 'y', 'on')
    return b_val


class BaseQuery(object):
    __metaclass__ = ABCMeta

    query = ""
    cache_expiry_time = 30
    xmlns = "http://trackernet.lul.co.uk"
    params = (REQUEST, )
    tags = {}

    def __init__(self, form):
        self.form = form
        # Get strings for the namespace-qualified tags
        for key, val in self.tags.items():
            self.tags[key] = etree.QName(self.xmlns, val).text

    @abstractmethod
    def _process_request(self, form):
        return NotImplemented

    @abstractmethod
    def _make_filename(self, form):
        return NotImplemented

    @abstractmethod
    def _parse_xml(self, xml):
        return NotImplemented

    def _request(self):
        req = urllib2.Request(self.request_url)
        res = urllib2.urlopen(req, timeout=10)
        return res

    def _get_xml(self, res):
        xml = etree.parse(res)
        resp = self._parse_xml(xml)
        return resp

    def _get_cache(self):
        try:
            cached = ''
            with open(self.cache_filename) as cf:
                cached = cf.read()
            return cached
        except (IOError, OSError) as e:
            if e.errno == errno.ENOENT:
                return None
            raise e

    def _make_folders(self, filename):
        foldername = os.path.dirname(filename)
        try:
            os.makedirs(foldername)
        except (IOError, OSError) as e:
            pass

    def _write_json(self, json):
        retry = True
        retries = 0

        while retry:
            try:
                with open(self.cache_filename, 'w') as cf:
                    cf.write(json)
                retry = False
            except (IOError, OSError) as e:
                if e.errno == errno.ENOENT:
                    if retries <= 3:
                        retry = True
                        retries += 1
                        self._make_folders(self.cache_filename)
                else:
                    retry = False
                    raise

    def fetch(self):
        self.request_url = self._process_request(self.form)
        self.cache_filename = self._make_filename(self.form)

        resp_json = self._get_cache()

        if not resp_json:
            try:
                res = self._request()

                statuscode = status.StatusCodes.getstatuscode(int(res.getcode()))
                if statuscode.iserror:
                    raise status.ResponseError(statuscode, 'Failed to fetch XML')
                elif not statuscode.canhavebody:
                    raise status.ResponseError(statuscode, 'No content in response')

                resp = self._get_xml(res)
                resp_json = json.dumps(resp)
                self._write_json(resp_json)
            except urllib2.HTTPError as httpe:
                statuscode = status.StatusCodes.getstatuscode(int(httpe.code))
                raise status.RequestError(statuscode)

        return resp_json


class DetailedPredictionQuery(BaseQuery):
    """DetailedPredictionQuery"""
    query = PREDICTION_DETAILED
    params = (REQUEST, LINE, STATION)
    tags = {
        'created_tag': 'WhenCreated',
        'line_tag': 'Line',
        'linename_tag': 'LineName',
        'station_tag': 'S',
        'platform_tag': 'P',
        'train_tag': 'T'
    }

    def _process_request(self, form):
        self.line = form[LINE]
        self.station = form[STATION]

        if self.line not in LINES_LIST:
            raise ValueError("Line code '{}' is not valid".format(self.line))
        if not self.station:
            raise ValueError("Station code is empty")

        url = "{0}/{1}/{2}/{3}".format(BASE_URL, self.query,
                                       self.line, self.station)
        return url

    def _make_filename(self, form):
        cache_filename = os.path.join('.', BASE_FILE, self.query,
                                      self.line, self.station)
        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, xml):
        root = xml.getroot()
        resp = {'information': {
            # Informational parts of response
            'created': root.find(self.tags['created_tag']).text,
            'linecode': root.find(self.tags['line_tag']).text,
            'linename': root.find(self.tags['linename_tag']).text,
            'stations': [{
                # List of Stations
                'stationcode': station.attrib['Code'],
                'stationname': station.attrib['N'],
                'platforms': [{
                    # List of Platforms for a Station
                    'platformname': platform.attrib['N'],
                    'platformnumber': platform.attrib['Num'],
                    'trains': [{
                    # List of Trains for a Platform
                        'lcid': train.attrib['LCID'],
                        'timeto': train.attrib['SecondsTo'],
                        'location': train.attrib['TimeTo'],
                        'destination': train.attrib['Destination'],
                        'destcode': train.attrib['DestCode'],
                        'tripno': train.attrib['TripNo']
                    } for train in platform.findall(self.tags['train_tag'])]}
                    # End of trains list comprehension
                for platform in station.findall(self.tags['platform_tag'])]}
                # End of platforms list comprehension
            for station in root.findall(self.tags['station_tag'])]}
            # End of stations list comprehension
        }
        return resp


class SummaryPredictionQuery(BaseQuery):
    """SummaryPredictionQuery"""
    query = PREDICTION_SUMMARY
    params = (REQUEST, LINE)

    def _process_request(self, form):
        self.line = form[LINE]

        if self.line not in LINES_LIST:
            raise ValueError("Line code '{}' is not valid".format(self.line))

        request_url = "{0}/{1}/{2}".format(BASE_URL, self.query, self.line)
        return request_url

    def _make_filename(self, form):
        cache_filename = os.path.join('.', BASE_FILE, self.query, self.line)
        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, xml):
        pass


class StatusQuery(BaseQuery):
    __metaclass__ = ABCMeta

    params = (REQUEST, INCIDENTS_ONLY)

    def _process_request(self, form):
        self.incidents_only = _parse_boolean(form[INCIDENTS_ONLY])

        request_url = "{0}/{1}".format(BASE_URL, self.query)
        if self.incidents_only:
            request_url = "{0}/{1}".format(request_url, INCIDENTS_ONLY)
        return request_url

    def _make_filename(self, form):
        cache_filename = os.path.join('.', BASE_FILE, self.query,
                                      INCIDENTS_ONLY if self.incidents_only
                                      else 'full')
        return cache_filename + FILE_EXTENSION

    @abstractmethod
    def _parse_xml(self, xml):
        pass


class LineStatusQuery(StatusQuery):
    """LineStatusQuery"""
    query = LINE_STATUS

    def _parse_xml(self, xml):
        pass


class StationStatusQuery(StatusQuery):
    """StationStatusQuery"""
    query = STATION_STATUS

    def _parse_xml(self, xml):
        pass


class StationListQuery(BaseQuery):
    """StationListQuery"""
    query = "stationslist"
    cache_expiry_time = 2419200 # Four weeks in seconds
    params = (REQUEST, )

    def _process_request(self, form):
        pass

    def _make_filename(self, form):
        pass

    def _parse_xml(self, xml):
        pass

