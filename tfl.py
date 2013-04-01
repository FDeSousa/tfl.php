#!/usr/bin/python
from abc import ABCMeta, abstractmethod
from datetime import datetime
import cgi
import cgitb
import json
cgitb.enable()
# cgitb.enable(display=0, logdir="./")

START_TIME = datetime.now()
PREDICTION_DETAILED = "predictiondetailed"
PREDICTION_SUMMARY = "predictionsummary"
LINE_STATUS = "linestatus"
STATION_STATUS = "stationstatus"
STATIONS_LIST = "stationslist"
INCIDENTS_ONLY = "incidentsonly"
BASE_URL = "http://cloud.tfl.gov.uk/trackernet/"
BASE_FILE = "./cache/"
DIV = "/"
FILE_EXTENSION = ".json"

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

class StatusCodes(object):
    """StatusCodes enumeration"""
    _statuscode = namedtuple('StatusCode', ('code', 'message'))
    # Informational 1xx
    HTTP_CONTINUE = _statuscode(100, 'Continue')
    HTTP_SWITCHING_PROTOCOLS = _statuscode(101, 'Switching Protocols',)
    # Successful 2xx
    HTTP_OK = _statuscode(200, 'OK')
    HTTP_CREATED = _statuscode(201, 'Created')
    HTTP_ACCEPTED = _statuscode(202, 'Accepted')
    HTTP_NONAUTHORITATIVE_INFORMATION = _statuscode(203, 'Non-Authoritative Information')
    HTTP_NO_CONTENT = _statuscode(204, 'No Content')
    HTTP_RESET_CONTENT = _statuscode(205, 'Reset Content')
    HTTP_PARTIAL_CONTENT = _statuscode(206, 'Partial Content')
    # Redirection 3xx
    HTTP_MULTIPLE_CHOICES = _statuscode(300, 'Multiple Choices')
    HTTP_MOVED_PERMANENTLY = _statuscode(301, 'Moved Permanently')
    HTTP_FOUND = _statuscode(302, 'Found')
    HTTP_SEE_OTHER = _statuscode(303, 'See Other')
    HTTP_NOT_MODIFIED = _statuscode(304, 'Not Modified')
    HTTP_USE_PROXY = _statuscode(305, 'Use Proxy')
    HTTP_UNUSED= _statuscode(306, '(Unused)')
    HTTP_TEMPORARY_REDIRECT = _statuscode(307, 'Temporary Redirect',)
    # Client Error 4xx
    ERROR_CODES_BEGIN_AT = 400
    HTTP_BAD_REQUEST = _statuscode(400, 'Bad Request')
    HTTP_UNAUTHORIZED = _statuscode(401, 'Unauthorized')
    HTTP_PAYMENT_REQUIRED = _statuscode(402, 'Payment Required')
    HTTP_FORBIDDEN = _statuscode(403, 'Forbidden')
    HTTP_NOT_FOUND = _statuscode(404, 'Not Found')
    HTTP_METHOD_NOT_ALLOWED = _statuscode(405, 'Method Not Allowed')
    HTTP_NOT_ACCEPTABLE = _statuscode(406, 'Not Acceptable')
    HTTP_PROXY_AUTHENTICATION_REQUIRED = _statuscode(407, 'Proxy Authentication Required')
    HTTP_REQUEST_TIMEOUT = _statuscode(408, 'Request Timeout')
    HTTP_CONFLICT = _statuscode(409, 'Conflict')
    HTTP_GONE = _statuscode(410, 'Gone')
    HTTP_LENGTH_REQUIRED = _statuscode(411, 'Length Required')
    HTTP_PRECONDITION_FAILED = _statuscode(412, 'Precondition Failed')
    HTTP_REQUEST_ENTITY_TOO_LARGE = _statuscode(413, 'Request Entity Too Large')
    HTTP_REQUEST_URI_TOO_LONG = _statuscode(414, 'Request-URI Too Long')
    HTTP_UNSUPPORTED_MEDIA_TYPE = _statuscode(415, 'Unsupported Media Type')
    HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = _statuscode(416, 'Requested Range Not Satisfiable')
    HTTP_EXPECTATION_FAILED = _statuscode(417, 'Expectation Failed',)
    # Server Error 5xx
    HTTP_INTERNAL_SERVER_ERROR = _statuscode(500, 'Internal Server Error')
    HTTP_NOT_IMPLEMENTED = _statuscode(501, 'Not Implemented')
    HTTP_BAD_GATEWAY = _statuscode(502, 'Bad Gateway')
    HTTP_SERVICE_UNAVAILABLE = _statuscode(503, 'Service Unavailable')
    HTTP_GATEWAY_TIMEOUT = _statuscode(504, 'Gateway Timeout')
    HTTP_VERSION_NOT_SUPPORTED = _statuscode(505, 'HTTP Version Not Supported')

    # Dictionary of status codes
    status_codes = {
        # Informational 1xx
        100: HTTP_CONTINUE, 101: HTTP_SWITCHING_PROTOCOLS,
        # Successful 2xx
        200: HTTP_OK, 201: HTTP_CREATED, 202: HTTP_ACCEPTED,
        203: HTTP_NONAUTHORITATIVE_INFORMATION, 204: HTTP_NO_CONTENT,
        205: HTTP_RESET_CONTENT, 206: HTTP_PARTIAL_CONTENT,
        # Redirection 3xx
        300: HTTP_MULTIPLE_CHOICES, 301: HTTP_MOVED_PERMANENTLY,
        302: HTTP_FOUND, 303: HTTP_SEE_OTHER, 304: HTTP_NOT_MODIFIED,
        305: HTTP_USE_PROXY, 306: HTTP_UNUSE, 307: HTTP_TEMPORARY_REDIRECT,
        # Client Error 4xx
        400: HTTP_BAD_REQUEST, 401: HTTP_UNAUTHORIZED,
        402: HTTP_PAYMENT_REQUIRED, 403: HTTP_FORBIDDEN, 404: HTTP_NOT_FOUND,
        405: HTTP_METHOD_NOT_ALLOWED, 406: HTTP_NOT_ACCEPTABLE,
        407: HTTP_PROXY_AUTHENTICATION_REQUIRED, 408: HTTP_REQUEST_TIMEOUT,
        409: HTTP_CONFLICT, 410: HTTP_GONE, 411: HTTP_LENGTH_REQUIRED,
        412: HTTP_PRECONDITION_FAILED, 413: HTTP_REQUEST_ENTITY_TOO_LARGE,
        414: HTTP_REQUEST_URI_TOO_LONG, 415: HTTP_UNSUPPORTED_MEDIA_TYPE,
        416: HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, 417: HTTP_EXPECTATION_FAILED,
        # Server Error 5xx
        500: HTTP_INTERNAL_SERVER_ERROR, 501: HTTP_NOT_IMPLEMENTED,
        502: HTTP_BAD_GATEWAY, 503: HTTP_SERVICE_UNAVAILABLE,
        504: HTTP_GATEWAY_TIMEOUT, 505: HTTP_VERSION_NOT_SUPPORTED
    }

    @classmethod
    def HttpHeaderFor(cls, code):
        if code not in cls.status_codes:
            raise ValueError("Not expecting '{}'".format(code))
        status = cls.status_codes[code]
        header = 'HTTP/1.1 {0.code} {0.message}'.format(status)
        return header

    @classmethod
    def CanHaveBody(cls, code):
        if code not in cls.status_codes:
            raise ValueError("Not expecting '{}'".format(code))
        return ((code < cls.HTTP_CONTINUE.code || code >= cls.HTTP_OK.code) and
                code != cls.HTTP_NO_CONTENT.code &&
                code != cls.HTTP_NOT_MODIFIED.code)

    @classmethod
    def IsError(cls, code):
        if code not in cls.status_codes:
            raise ValueError("Not expecting '{}'".format(code))
        return code >= cls.ERROR_CODES_BEGIN_AT


class BaseQuery(object):
    __metaclass__ = ABCMeta


def arguments():
    d = {
         'request': '',
         'line': '',
         'station': '',
         'incidents': ''
        }
    form = cgi.FieldStorage()   # Parse query
    if 'request' in form and form['request'].value:
        d['request'] = form['request'].value
    if 'line' in form and form['line'].value:
        d['line'] = form['line'].value
    if 'station' in form and form['station'].value:
        d['station'] = form['station'].value
    if 'incidents' in form and form['incidents'].value:
        d['incidents'] = form['incidents'].value
    return d

def main():
    print("Content-Type: application/json")
    print("")

    print('{\n')
    for k, v in arguments().items():
        print('\t{0}: {1}'.format(k, v))
    print('}')

main()
