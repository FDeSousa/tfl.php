#!/usr/bin/python

from abc import ABCMeta, abstractmethod
import urllib2
import json

try:
    import xml.etree.cElementTree as etree
except ImportError:
    import xml.etree.ElementTree as etree


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
        cached = ''
        with open(self.cache_filename) as cf:
            cached = cf.read()
        return cached

    def _write_json(self, json):
        pass

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
    query = "predictiondetailed"
    cache_expiry_time = 30

    def __init__(self, form):
        super(DetailedPredictionQuery, self).__init__(form)

    def _process_request(self, form):
        pass

    def _make_filename(self, form):
        pass

    def _parse_xml(self, xml):
        pass


class SummaryPredictionQuery(BaseQuery):
    """SummaryPredictionQuery"""
    query = "predictionsummary"
    cache_expiry_time = 30

    def __init__(self, form):
        super(SummaryPredictionQuery, self).__init__(form)

    def _process_request(self, form):
        pass

    def _make_filename(self, form):
        pass

    def _parse_xml(self, xml):
        pass


class LineStatusQuery(BaseQuery):
    """LineStatusQuery"""
    query = "linestatus"
    cache_expiry_time = 30

    def __init__(self, form):
        super(LineStatusQuery, self).__init__(form)

    def _process_request(self, form):
        pass

    def _make_filename(self, form):
        pass

    def _parse_xml(self, xml):
        pass


class StationStatusQuery(BaseQuery):
    """StationStatusQuery"""
    query = "stationstatus"
    cache_expiry_time = 30

    def __init__(self, form):
        super(StationStatusQuery, self).__init__(form)

    def _process_request(self, form):
        pass

    def _make_filename(self, form):
        pass

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

