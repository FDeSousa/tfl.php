#!/usr/bin/python

from abc import ABCMeta, abstractmethod
import urllib2
import json
import errno

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
    cache_expiry_time = 0

    def __init__(self, form):
        self.request_url = self._process_request(form)
        self.cache_filename = self._make_filename(form)

    @abstractmethod
    def _process_request(self, form):
        return NotImplemented

    @abstractmethod
    def _make_filename(self, form):
        return NotImplemented

    @abstractmethod
    def _parse_xml(self, xml):
        return NotImplemented

    def _get_xml(self):
        req = urllib2.Request(self.request_url)
        res = urllib2.urlopen(req)
        xml = etree.parse(res)
        resp = self._parse_xml(xml)
        return resp

    def _get_cache(self):
        try:
            cached = ''
            with open(self.cache_filename) as cf:
                cached = cf.read()
            return cached
        except IOError as ioe:
            if ioe.errno == errno.ENOENT:
                return None
            else:
                raise

    def _make_folders(self, filename):
        foldername = os.path.dirname(filename)
        try:
            os.makedirs(foldername)
        except IOError as ioe:
            pass

    def _write_json(self, json):
        retry = True
        attempts = 0

        while retry:
            try:
                with open(self.cache_filename, 'w') as cf:
                    cf.write(json)
                retry = False
            except IOError as ioe:
                if ioe.errno == errno.ENOENT:
                    if attempts < 3:
                        retry = True
                        attempts += 1
                        self._make_folders(self.cache_filename)
                else:
                    retry = False
                    raise

    def fetch(self):
        cached = self._get_cache()

        if cached is None:
            resp = self._get_xml()
            resp_json = json.dumps(resp)
            self._write_json(resp_json)
        else:
            resp_json = cached

        return resp_json


class DetailedPredictionQuery(BaseQuery):
    """DetailedPredictionQuery"""
    query = PREDICTION_DETAILED
    cache_expiry_time = 30

    def __init__(self, form):
        super(DetailedPredictionQuery, self).__init__(form)

    def _process_request(self, form):
        request = form.getfirst(REQUEST).value
        line = form.getfirst(LINE).value
        station = form.getfirst(STATION).value

        if line not in LINES_LIST:
            raise ValueError("Line code '%s' is not valid" % line)
        if station: # No stations list to check against, so just check not-empty
            raise ValueError("Station code is empty")

        request_url = "{0}/{1}/{2}/{3}".format(BASE_URL, self.query,
                                               line, station)
        return request_url

    def _make_filename(self, form):
        line = form.getfirst(LINE).value
        station = form.getfirst(STATION).value

        if line not in LINES_LIST:
            raise ValueError("Line code '%s' is not valid" % line)
        if station: # No stations list to check against, so just check not-empty
            raise ValueError("Station code is empty")

        cache_filename = os.path.join('.', BASE_FILE, self.query, line, station)

        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, xml):
        pass


class SummaryPredictionQuery(BaseQuery):
    """SummaryPredictionQuery"""
    query = PREDICTION_SUMMARY
    cache_expiry_time = 30

    def __init__(self, form):
        super(SummaryPredictionQuery, self).__init__(form)

    def _process_request(self, form):
        request = form.getfirst(REQUEST).value
        line = form.getfirst(LINE).value

        if line not in LINES_LIST:
            raise ValueError("Line code '%s' is not valid" % line)

        request_url = "{0}/{1}/{2}".format(BASE_URL, PREDICTION_SUMMARY, line)
        return request_url

    def _make_filename(self, form):
        line = form.getfirst(LINE).value

        if line not in LINES_LIST:
            raise ValueError("Line code '%s' is not valid" % line)

        cache_filename = os.path.join('.', BASE_FILE, self.query, line)

        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, xml):
        pass


class LineStatusQuery(BaseQuery):
    """LineStatusQuery"""
    query = LINE_STATUS
    cache_expiry_time = 30

    def __init__(self, form):
        super(LineStatusQuery, self).__init__(form)

    def _process_request(self, form):
        incidents_only = form.getfirst(INCIDENTS_ONLY).value
        incidents_only = _parse_boolean(incidents_only)

        request_url = "{0}/{1}".format(BASE_URL, LINE_STATUS)
        if incidents_only:
            request_url = "{0}/{1}".format(request_url, INCIDENTS_ONLY)
        return request_url

    def _make_filename(self, form):
        incidents_only = form.getfirst(INCIDENTS_ONLY).value
        incidents_only = _parse_boolean(incidents_only)

        cache_filename = os.path.join('.', BASE_FILE, self.query,
                         INCIDENTS_ONLY if incidents_only else 'full')

        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, xml):
        pass


class StationStatusQuery(BaseQuery):
    """StationStatusQuery"""
    query = STATION_STATUS
    cache_expiry_time = 30

    def __init__(self, form):
        super(StationStatusQuery, self).__init__(form)

    def _process_request(self, form):
        incidents_only = form.getfirst(INCIDENTS_ONLY).value
        incidents_only = _parse_boolean(incidents_only)

        request_url = "{0}/{1}".format(BASE_URL, STATION_STATUS)
        if incidents_only:
            request_url = "{0}/{1}".format(request_url, INCIDENTS_ONLY)
        return request_url

    def _make_filename(self, form):
        incidents_only = form.getfirst(INCIDENTS_ONLY).value
        incidents_only = _parse_boolean(incidents_only)

        cache_filename = os.path.join('.', BASE_FILE, self.query,
                         INCIDENTS_ONLY if incidents_only else 'full')

        return cache_filename + FILE_EXTENSION

    def _parse_xml(self, xml):
        pass


class StationListQuery(BaseQuery):
    """StationListQuery"""
    query = "stationslist"
    cache_expiry_time = 2419200 # Four weeks in seconds

    def __init__(self, form):
        super(StationListQuery, self).__init__(form)

    def _process_request(self, form):
        pass

    def _make_filename(self, form):
        pass

    def _parse_xml(self, xml):
        pass

