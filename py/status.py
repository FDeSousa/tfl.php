#!/usr/bin/python

from __future__ import print_function

from collections import namedtuple
import types

class StatusCodes(object):
    """StatusCodes enumeration"""
    _sc = namedtuple('StatusCode', ('code', 'message', 'canhavebody', 'iserror'))

    # Informational 1xx
    HTTP_CONTINUE = _sc(100, 'Continue', False, False)
    HTTP_SWITCHING_PROTOCOLS = _sc(101, 'Switching Protocols', False, False)
    # Successful 2xx
    HTTP_OK = _sc(200, 'OK', True, False)
    HTTP_CREATED = _sc(201, 'Created', True, False)
    HTTP_ACCEPTED = _sc(202, 'Accepted', True, False)
    HTTP_NONAUTHORITATIVE_INFORMATION = _sc(203, 'Non-Authoritative Information', True, False)
    HTTP_NO_CONTENT = _sc(204, 'No Content', False, False)
    HTTP_RESET_CONTENT = _sc(205, 'Reset Content', True, False)
    HTTP_PARTIAL_CONTENT = _sc(206, 'Partial Content', True, False)
    # Redirection 3xx
    HTTP_MULTIPLE_CHOICES = _sc(300, 'Multiple Choices', True, False)
    HTTP_MOVED_PERMANENTLY = _sc(301, 'Moved Permanently', True, False)
    HTTP_FOUND = _sc(302, 'Found', True, False)
    HTTP_SEE_OTHER = _sc(303, 'See Other', True, False)
    HTTP_NOT_MODIFIED = _sc(304, 'Not Modified', False, False)
    HTTP_USE_PROXY = _sc(305, 'Use Proxy', True, False)
    HTTP_UNUSED= _sc(306, '(Unused)', True, False)
    HTTP_TEMPORARY_REDIRECT = _sc(307, 'Temporary Redirect', True, False)
    # Client Error 4xx
    HTTP_BAD_REQUEST = _sc(400, 'Bad Request', True, True)
    HTTP_UNAUTHORIZED = _sc(401, 'Unauthorized', True, True)
    HTTP_PAYMENT_REQUIRED = _sc(402, 'Payment Required', True, True)
    HTTP_FORBIDDEN = _sc(403, 'Forbidden', True, True)
    HTTP_NOT_FOUND = _sc(404, 'Not Found', True, True)
    HTTP_METHOD_NOT_ALLOWED = _sc(405, 'Method Not Allowed', True, True)
    HTTP_NOT_ACCEPTABLE = _sc(406, 'Not Acceptable', True, True)
    HTTP_PROXY_AUTHENTICATION_REQUIRED = _sc(407, 'Proxy Authentication Required', True, True)
    HTTP_REQUEST_TIMEOUT = _sc(408, 'Request Timeout', True, True)
    HTTP_CONFLICT = _sc(409, 'Conflict', True, True)
    HTTP_GONE = _sc(410, 'Gone', True, True)
    HTTP_LENGTH_REQUIRED = _sc(411, 'Length Required', True, True)
    HTTP_PRECONDITION_FAILED = _sc(412, 'Precondition Failed', True, True)
    HTTP_REQUEST_ENTITY_TOO_LARGE = _sc(413, 'Request Entity Too Large', True, True)
    HTTP_REQUEST_URI_TOO_LONG = _sc(414, 'Request-URI Too Long', True, True)
    HTTP_UNSUPPORTED_MEDIA_TYPE = _sc(415, 'Unsupported Media Type', True, True)
    HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = _sc(416, 'Requested Range Not Satisfiable', True, True)
    HTTP_EXPECTATION_FAILED = _sc(417, 'Expectation Failed', True, True)
    # Server Error 5xx
    HTTP_INTERNAL_SERVER_ERROR = _sc(500, 'Internal Server Error', True, True)
    HTTP_NOT_IMPLEMENTED = _sc(501, 'Not Implemented', True, True)
    HTTP_BAD_GATEWAY = _sc(502, 'Bad Gateway', True, True)
    HTTP_SERVICE_UNAVAILABLE = _sc(503, 'Service Unavailable', True, True)
    HTTP_GATEWAY_TIMEOUT = _sc(504, 'Gateway Timeout', True, True)
    HTTP_VERSION_NOT_SUPPORTED = _sc(505, 'HTTP Version Not Supported', True, True)

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
        305: HTTP_USE_PROXY, 306: HTTP_UNUSED, 307: HTTP_TEMPORARY_REDIRECT,
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
    def getstatuscode(cls, statuscode):
        if isinstance(statuscode, cls._sc):
            return statuscode
        elif isinstance(statuscode, types.IntType):
            if statuscode in cls.status_codes:
                return cls.status_codes[statuscode]
            else:
                raise ValueError("Not expecting '{}'".format(statuscode))
        else:
            styp = type(statuscode)
            raise TypeError("Expecting status code or int, not {}".format(styp))

    @classmethod
    def gethttpheader(cls, code):
        statuscode = cls.getstatuscode(code)
        header = 'HTTP/1.1 {0.code} {0.message}'.format(statuscode)
        return header

    @classmethod
    def gethttpstatus(cls, code):
        statuscode = cls.getstatuscode(code)
        http_status = '{0.code} {0.message}'.format(statuscode)
        return http_status


# Exceptions
class BaseStatusError(Exception):
    def __init__(self, status=None, message=None):
        self.status = (StatusCodes.getstatuscode(status)
                       or StatusCodes.HTTP_BAD_REQUEST)
        self.httpheader = StatusCodes.gethttpheader(self.status)
        self.httpstatus = StatusCodes.gethttpstatus(self.status)
        self.message = message if self.status.canhavebody else None


class RequestError(BaseStatusError):
    pass


class ResponseError(BaseStatusError):
    pass

