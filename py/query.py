#!/usr/bin/python

from __future__ import print_function

from abc import ABCMeta, abstractmethod
import errno
import json
import logging
import os
import status
import time
import urllib2

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


def stob(s_val):
    s_val = s_val.lower()
    b_val = s_val in ('1', 'true', 'yes', 'y', 'on')
    return b_val


class BaseQuery(object):
    __metaclass__ = ABCMeta

    query = ""
    cache_expiry_time = 30
    xmlns = ''
    params = (REQUEST, )
    tags = {}

    def __init__(self, form):
        self.form = form
        self.request_url = self._process_request()
        self.cache_filename = self._make_filename()
        # Get strings for the namespace-qualified tags
        if self.xmlns:
            for key, val in self.tags.items():
                self.tags[key] = etree.QName(self.xmlns, val).text

    @abstractmethod
    def _process_request(self):
        return NotImplemented

    @abstractmethod
    def _make_filename(self):
        return NotImplemented

    @abstractmethod
    def _parse_xml(self, root):
        return NotImplemented

    def _request(self):
        req = urllib2.Request(self.request_url)
        res = urllib2.urlopen(req, timeout=10)
        return res

    def _get_xml(self, res):
        try:
            xml = etree.parse(res)
            root = xml.getroot()
            resp = self._parse_xml(root)
            return resp
        except:
            logging.debug('XML: %s', etree.tostring(root))
            raise

    def _get_cache(self):
        cached = ''

        try:
            mtime = os.path.getmtime(self.cache_filename)
            ctime = time.time() - self.cache_expiry_time

            if mtime >= ctime:
                with open(self.cache_filename) as cf:
                    cached = cf.read()
        except (IOError, OSError) as e:
            if e.errno == errno.ENOENT:
                return None
            raise e

        return cached

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
        resp_json = self._get_cache()

        if not resp_json:
            res = None
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
    xmlns = 'http://trackernet.lul.co.uk'

    def _process_request(self):
        self.line = self.form[LINE]
        self.station = self.form[STATION]

        if self.line not in LINES_LIST:
            raise ValueError("Line code '{}' is not valid".format(self.line))
        if not self.station:
            raise ValueError("Station code is empty")

        url = "{0}/{1}/{2}/{3}".format(BASE_URL, self.query,
                                       self.line, self.station)
        return url

    def _make_filename(self):
        cache_filename = os.path.join('.', BASE_FILE, self.query,
                                      self.line, self.station)
        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, root):
        resp = {
            'information': {
            # Informational parts of response
            'created': root.find(self.tags['created_tag']).text,
            'linecode': root.find(self.tags['line_tag']).text,
            'linename': root.find(self.tags['linename_tag']).text,
            'stations': [{
            # List of Stations
                'stationcode': station.attrib.get('Code', ''),
                'stationname': station.attrib.get('N', ''),
                'platforms': [{
                # List of Platforms for a Station
                    'platformname': platform.attrib.get('N', ''),
                    'platformnumber': int(platform.attrib.get('Num', '')),
                    'trains': [{
                    # List of Trains for a Platform
                        'lcid': train.attrib.get('LCID', ''),
                        'timeto': train.attrib.get('TimeTo', ''),
                        'secondsto': train.attrib.get('SecondsTo', ''),
                        'location': train.attrib.get('Location', ''),
                        'destination': train.attrib.get('Destination', ''),
                        'destcode': int(train.attrib.get('DestCode', 0)),
                        'tripno': int(train.attrib.get('TripNo', 0))
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
    tags = {
        'root_tag': 'ROOT',
        'created_tag': 'Time',
        'station_tag': 'S',
        'platform_tag': 'P',
        'train_tag': 'T'
    }

    def _process_request(self):
        self.line = self.form[LINE]

        if self.line not in LINES_LIST:
            raise ValueError("Line code '{}' is not valid".format(self.line))

        request_url = "{0}/{1}/{2}".format(BASE_URL, self.query, self.line)
        return request_url

    def _make_filename(self):
        cache_filename = os.path.join('.', BASE_FILE, self.query, self.line)
        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, root):
        ctime = root.find(self.tags['created_tag']).attrib.get('TimeStamp', '')
        resp = {
            # Informational parts of response
            'created': ctime,
            'stations': [{
            # List of Stations
                'stationcode': station.attrib.get('Code', ''),
                'stationname': station.attrib.get('N', ''),
                'platforms': [{
                # List of Platforms for a Station
                    'platformname': platform.attrib.get('N', ''),
                    'platformcode': int(platform.attrib.get('Code', 0)),
                    'trains': [{
                    # List of Trains for a Platform
                        'trainnumber': int(train.attrib.get('S', 0)),
                        'tripno': int(train.attrib.get('T', 0)),
                        'destcode': int(train.attrib.get('D', 0)),
                        'destination': train.attrib.get('DE', ''),
                        'timeto': train.attrib.get('C', ''),
                        'location': train.attrib.get('L', '')
                    } for train in platform.findall(self.tags['train_tag'])]}
                    # End of trains list comprehension
                for platform in station.findall(self.tags['platform_tag'])]}
                # End of platforms list comprehension
            for station in root.findall(self.tags['station_tag'])]
            # End of stations list comprehension
        }
        return resp


class StatusQuery(BaseQuery):
    __metaclass__ = ABCMeta

    params = (REQUEST, INCIDENTS_ONLY)

    tags = {
        'elemstatus_tag': '',
        'elem_tag': '',
        'status_tag': ''
    }
    prefix = ''
    xmlns = 'http://webservices.lul.co.uk/'

    def _process_request(self):
        incidents_only = self.form.get(INCIDENTS_ONLY, 'no')
        self.incidents_only = stob(incidents_only)

        request_url = "{0}/{1}".format(BASE_URL, self.query)
        if self.incidents_only:
            request_url = "{0}/{1}".format(request_url, INCIDENTS_ONLY)
        return request_url

    def _make_filename(self):
        cache_filename = os.path.join('.', BASE_FILE, self.query,
                                      INCIDENTS_ONLY if self.incidents_only
                                      else 'full')
        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, root):
        elem_tag = self.tags['elem_tag']
        status_tag = self.tags['status_tag']
        elemid = '{}id'.format(self.prefix)
        elemname = '{}name'.format(self.prefix)
        status = []

        for tag in root.findall(self.tags['elemstatus_tag']):
            elem_item = tag.find(elem_tag)
            status_item = tag.find(status_tag)
            item = {
                'id': int(tag.attrib.get('ID', 0)),
                'details': tag.attrib.get('StatusDetails', ''),
                elemid: int(elem_item.attrib.get('ID', 0)),
                elemname: elem_item.attrib.get('Name', ''),
                'statusid': status_item.attrib.get('ID', ''),
                'status': status_item.attrib.get('CssClass', ''),
                'description': status_item.attrib.get('Description', ''),
                'active': stob(status_item.attrib.get('IsActive', ''))
            }
            status.append(item)

        resp = {self.prefix: status}
        return resp


class LineStatusQuery(StatusQuery):
    """LineStatusQuery"""
    query = LINE_STATUS
    tags = {
        'elemstatus_tag': 'LineStatus',
        'elem_tag': 'Line',
        'status_tag': 'Status'
    }
    prefix = 'line'


class StationStatusQuery(StatusQuery):
    """StationStatusQuery"""
    query = STATION_STATUS
    tags = {
        'elemstatus_tag': 'StationStatus',
        'elem_tag': 'Station',
        'status_tag': 'Status'
    }
    prefix = 'station'


class StationListQuery(BaseQuery):
    """StationListQuery"""
    query = STATIONS_LIST
    cache_expiry_time = 4838400 # Four weeks in seconds
    params = (REQUEST, )
    tags = SummaryPredictionQuery.tags
    xmlns = SummaryPredictionQuery.xmlns

    def __init__(self, form=None):
        super(StationListQuery, self).__init__(form)

        self.lines = {}
        for line, name in LINES_LIST.items():
            request = {REQUEST: PREDICTION_SUMMARY, LINE: line}
            spq = SummaryPredictionQuery(request)
            self.lines[line] = (name, spq)

    def _process_request(self):
        # Have to implement, just return None
        return None

    def _make_filename(self):
        cache_filename = os.path.join('.', BASE_FILE, self.query)
        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, root):
        resp = [{
            'stationcode': station.attrib.get('Code', ''),
            'stationname': station.attrib.get('N', '')}
            for station in root.findall(self.tags['station_tag'])
        ]
        return resp

    def fetch_line(self, code, name, spq):
        resp = {
            'linecode': code,
            'linename': name,
            'stations': None
        }
        stations = []

        try:
            res = spq._request()

            statuscode = status.StatusCodes.getstatuscode(int(res.getcode()))
            if statuscode.iserror or not statuscode.canhavebody:
                raise status.ResponseError(statuscode,
                    "Unable to get stations for line '{}'".format(code))

            stations = self._get_xml(res)
            resp['stations'] = stations
        except urllib2.HTTPError as httpe:
            statuscode = status.StatusCodes.getstatuscode(int(httpe.code))
            raise status.RequestError(statuscode)

        return resp

    def fetch(self):
        resp_json = self._get_cache()

        if not resp_json:
            lines = []
            for code, item in self.lines.items():
                name, spq = item
                line = self.fetch_line(code, name, spq)
                lines.append(line)
            resp = {'lines': lines}
            resp_json = json.dumps(resp)
            self._write_json(resp_json)

        return resp_json

