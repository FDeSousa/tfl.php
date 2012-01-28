<?php
/**
 * tfl.php - TfL API that makes sense
 * Now with 90% more OO!
 * @author Filipe De Sousa
 * @version 0.5
 * @license Apache 2.0
 **/

/******************************************************************************
 * Copyright 2012 Filipe De Sousa
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *****************************************************************************/

// --------------------------------------------------------------------------------
// Declarations area below
$starttime = microtime(true);

// URL parameter for request of detailed predictions
define("PREDICTION_DETAILED", "predictiondetailed");
// URL parameter for request of summary predictions
define("PREDICTION_SUMMARY", "predictionsummary");
// URL parameter for request of line status
define("LINE_STATUS", "linestatus");
// URL parameter for request of station status
define("STATION_STATUS", "stationstatus");
// URL parameter for request of stations list
define("STATIONS_LIST", "stationslist");
// URL parameter for request of only incident reports
define("INCIDENTS_ONLY", "incidentsonly");

// Base URL for TfL requests:
define("BASE_URL", "http://cloud.tfl.gov.uk/trackernet/");
// Base filename for reading/writing files
define("BASE_FILE", "./cache/");
// Divider character
define("DIV", "/");
// Default file extension to write file with
define("FILE_EXTENSION", ".json");

// For stats of stations list, have the list of train lines here
$lines_list = array("b" => "Bakerloo",
					"c" => "Central",
					"d" => "District",
					"h" => "Hammersmith & Circle",
					"j" => "Jubilee",
					"m" => "Metropolitan",
					"n" => "Northern",
					"p" => "Piccadilly",
					"v" => "Victoria",
					"w" => "Waterloo & City");

// Get the URL parameters:
$request = strtolower($_GET["request"]);
$line = strtolower($_GET["line"]);
$station = strtolower($_GET["station"]);
$incidents_only = (bool) $_GET["incidents"];

// Declarations area finished
// --------------------------------------------------------------------------------
// Command execution area

// Just execute our main function and be done with it
main($request, $line, $station, $incidents_only, $starttime, $lines_list);

// Takes just one line to get the program started fetching/parsing requests
// --------------------------------------------------------------------------------
// Main function executes the choice, returns the output

/**
 * Main method, determines the request type, echoes the output from the
 * parsing of XML to JSON string
 * @param none
 * @return null
 */
function main($request, $line, $station, $incidents_only, $starttime, $lines_list) {
	$fetcher;
	$json_out;

	switch ($request) {
		case PREDICTION_DETAILED:
			$fetcher = new DetailedPredictions($line, $lines_list, $station);
			break;
		case PREDICTION_SUMMARY:
			$fetcher = new SummaryPredictions($line, $lines_list);
			break;
		case LINE_STATUS:
			$fetcher = new LineStatus($incidents_only);
			break;
		case STATION_STATUS:
			$fetcher = new StationStatus($incidents_only);
			break;
		case STATIONS_LIST:
			$fetcher = new StationsList($lines_list);
			break;
		default:
			header(StatusCodes::httpHeaderFor(StatusCodes::HTTP_BAD_REQUEST));
			die("{\"error\":\"No valid request made\"}");
	}

	//	Fetch and parse
	$json_out = $fetcher->fetch();

	// Set the right header information before echoing
	header("Content-Type: application/json");
	echobig($json_out);
}

/**
 * Function to improve performance of echoing large strings.
 * As some functions return long JSON strings, may improve echo performance
 * cf. http://wonko.com/post/seeing_poor_performance_using_phps_echo_statement_heres_why
 * @param $string - string to echo
 * @param $buffersize (optional) - number of bytes to echo at once (default = 8192)
 * @return null
 */
function echobig($string, $buffersize = 8192) {
	$split = str_split($string, $buffersize);

	foreach ($split as $chunk) {
		echo $chunk;
	}
}

// End of functions area
// --------------------------------------------------------------------------------
// Start of class area

/**
 * Base class for all fetcher classes, handling the order of fetching and parsing
 * the XML into JSON format for all of its sub-classes
 */
abstract class TflJsonFetcher {

	protected $expiretime, $out_arr, $filename;

	/**
	 * Base constructor for this class and its subclasses
	 * Requires cache expiry time, the name of the request made,
	 * and the file name for access of the cache file.
	 * @param $cache_expire - time since last change to consider cache expired (in seconds)
	 * @param $request_name - human-readable name of the request made for identification
	 * @param $file_name - name of the cache file to read from/write to
	 * @return null
	 */
	public function __construct($cache_expire, $request_name, $file_name) {
		// Save the expiration time
		$this->expiretime = $cache_expire;

		// Get the file name for cache reading/writing
		$this->filename = $file_name;

		// Instantiate the array, adding request type and request name
		$this->out_arr = array("requesttype" => "{$request_name}");
	}

	abstract protected function prepare();
	abstract protected function parse($xml);

	/**
	 * Utility method to automate fetching and parsing of XML into JSON
	 * @return string - JSON-encoded string of the parsed response
	 */
	public function fetch() {
		$json;
		// Check if the cache is valid
		if ($this->validateCache()) {
			// If so, read in from the cache file
			$json = file_get_contents($this->filename);
		} else {
			// Call the prepare function, fetching the XML
			$xml = $this->prepare();
			// Perform an array merge with out_arr and the output of parse()
			$this->out_arr = array_merge($this->out_arr, $this->parse($xml));
			// Encode the array into JSON
			$json = json_encode($this->out_arr, true);
			// Write newest version to cache
			$this->writeToCacheFile($json);
		}
		return $json;
	}

	/**
	 * Convenience function to check whether the cache file is valid to avoid
	 * additional processing strain. Checks file exists first, then checks its
	 * last edit time, comparing it to the expiry time and current time.
	 * @return boolean - true if cache file is valid and recent, false otherwise
	 */
	protected final function validateCache() {
		if (file_exists($this->filename)) {
			// File exists, so check if cached file is still valid
			if ((time() - filectime($this->filename)) < $this->expiretime) {
				return true;
			} else {
				return false;
			}
		} else {
			// File doesn't exists, check the directories do
			if (!is_dir(dirname($this->filename))) {
				// If directories don't exist, make them
				mkdir(dirname($this->filename), 0755, true);
			}
			return false;
		}
	}

	/**
	 * Convenience method to write out JSON to file
	 * Just makes code a little cleaner elsewhere
	 * @return none
	 */
	protected final function writeToCacheFile($json) {
		// Open the file for writing
		$file = fopen($this->filename, "w+");
		// Write out our newest data copy to file
		if (json_decode($json) != null and flock($file, LOCK_EX)) {
			// Perform quick sanity check, and lock the file
			fwrite($file, $json);
		}
		// Always unlock and close the file
		flock($file, LOCK_UN);
		fclose($file);
	}

	/**
	 * Convenience function, download XML from URL, return it in a string
	 * @return string - XML downloaded from the parsed URL
	 */
	protected static final function getXml($url) {
		// Since we must use curl, initialise its handler
		$ch = curl_init();

		// Setup curl with our options
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);

		// Get the source with curl
		$source = curl_exec($ch);

		if (curl_errno($ch)) {
			die("{\"error\":\"" . curl_error($ch) . "\"}");
		} else {
			// Get HTTP response code to check for issues
			$h = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (StatusCodes::isError($h) || !StatusCodes::canHaveBody($h)) {
				$err = StatusCodes::httpHeaderFor($h);
				header($err);
				die("{\"error\":\"" . $err . "\"}");
			}
		}

		// Close up curl, no longer needed
		curl_close($ch);

		// Read the XML string into an object
		$xml = simpleXML_load_string($source);

		// Return it
		return $xml;
	}
}

/**
 * Class to get, parse, and return the TfL XML feed as a JSON string
 * for Detailed Predictions on a given TfL line for a given station.
 */
class DetailedPredictions extends TflJsonFetcher {
	// Declare expiry time for cache in seconds
	const __expiry_time = 30;
	// Declare some private variables
	private $line, $lines_list, $station;

	/**
	 * Sub-class constructor method.
	 * @param $line - the line code specified in URL request
	 * @param $lines_list - the array of valid line codes
	 * @param $station - the station code specified in URL request
	 */
	public function __construct($line, $lines_list, $station) {
		parent::__construct(self::__expiry_time, "Detailed Predictions", self::make_file_name($line, $lines_list, $station));
		$this->line = $line;
		$this->lines_list = $lines_list;
		$this->station = $station;
	}

	/**
	 * Private function make_file_name doesn't override since defining
	 * several different types in the super-class isn't useful.
	 * Only required to be private either way. Each class implements it
	 * in a completely different manner anyway.
	 * @param $line - line code specified in URL request
	 * @param $lines_list - the array of valid line codes
	 * @param $station - the station code specified in URL request
	 * @return string - file name to be used for reading/writing cache file
	 */
	private static function make_file_name($line, $lines_list, $station) {
		// Construct the filename for output
		$filename = BASE_FILE . PREDICTION_DETAILED . DIV;
		// Check line isn't empty, and line code is valid
		if ($line != null and array_key_exists($line, $lines_list) !== false) {
			$filename .= $line;
		} else { // Fail fast if the line code is invalid/missing
			header(StatusCodes::httpHeaderFor(StatusCodes::HTTP_BAD_REQUEST));
			die("{\"error\":\"Invalid line code\"}");
		}
		// Now add the station code to the filename
		if ($station != null) {
			$filename .= DIV . $station . FILE_EXTENSION;
		} else { // Fail fast if the station code is missing
			header(StatusCodes::httpHeaderFor(StatusCodes::HTTP_BAD_REQUEST));
			die("{\"error\":\"Missing station code\"}");
		}
		return $filename;
	}

	/**
	 * Utility method to prepare the URL and automate fetching the XML
	 * @return string - XML string returned from the generated URL
	 */
	protected function prepare() {
		// Build the url to then fetch the XML
		$url = BASE_URL . PREDICTION_DETAILED . DIV . $this->line . DIV . $this->station;
		// Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for detailed train predictions.
	 * @param $xml - the XML string retrieved with getXml()
	 * @return array - containing values to parse to JSON-encode
	 */
	protected function parse($xml) {
		// We're parsing back an array, so initialise it here with basic information
		$arr = array("information" => array("created" => (string) $xml->WhenCreated,
									"linecode" => (string) $xml->Line,
									"linename" => (string) $xml->LineName),
					"stations" => array());
		// We have several arrays to hold information. stations in the base array

		// stations is built with all information before being placed in main stations array
		foreach ($xml->S as $station) {
			$station_arr = array("stationcode" => (string) $station["Code"],
								"stationname" => (string) $station["N"],
								"platforms" => array());
			// platforms is built with all information before being placed in main platforms array
			foreach ($station->P as $platform) {
				$platform_arr = array("platformname" => (string) $platform["N"],
									"platformnumber" => (int) $platform["Num"],
									"trains" => array());
				// trains built with all information before being placed in main trains array also
				foreach ($platform->T as $train) {
					$train_arr = array("lcid" => (string) $train["LCID"],
									"secondsto" => (string) $train["SecondsTo"],
									"timeto" => (string) $train["TimeTo"],
									"location" => (string) $train["Location"],
									"destination" => (string) $train["Destination"],
									"destcode" => (int) $train["DestCode"],
									"tripno" => (int) $train["TripNo"]);
					// place train array into current platform array
					$platform_arr["trains"][] = $train_arr;
				}
				// place current platform array into current station array
				$station_arr["platforms"][] = $platform_arr;
			}
			// place current station array into main stations array
			$arr["stations"][] = $station_arr;
		}
		return $arr;
	}
}

/**
 * Class to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions for a given TfL line and all its stations.
 */
class SummaryPredictions extends TflJsonFetcher {
	// Declare expiry time for cache in seconds
	const __expiry_time = 30;
	// Declare some private variables
	private $line, $lines_list;

	/**
	 * Sub-class constructor method.
	 * @param $line - the line code specified in URL request
	 * @param $lines_list - the array of valid line codes
	 */
	public function __construct($line, $lines_list) {
		parent::__construct(self::__expiry_time, "Summary Predictions", self::make_file_name($line, $lines_list));
		$this->line = $line;
		$this->lines_list = $lines_list;
	}

	/**
	 * Private function make_file_name doesn't override since defining
	 * several different types in the super-class isn't useful.
	 * Only required to be private either way. Each class implements it
	 * in a completely different manner anyway.
	 * @param $line - line code specified in URL request
	 * @param $lines_list - the array of valid line codes
	 * @return string - file name to be used for reading/writing cache file
	 */
	private static function make_file_name($line, $lines_list) {
		// Construct the filename for output
		$filename = BASE_FILE . PREDICTION_SUMMARY . DIV;
		// Check line isn't empty, and line code is valid
		if ($line != null and array_key_exists($line, $lines_list) !== false) {
			$filename .= $line . FILE_EXTENSION;
		} else { // Fail fast if the line code is invalid/missing
			header(StatusCodes::httpHeaderFor(StatusCodes::HTTP_BAD_REQUEST));
			die("{\"error\":\"Invalid line code\"}");
		}
		return $filename;
	}

	/**
	 * Utility method to prepare the URL and automate fetching the XML
	 * @return string - XML string returned from the generated URL
	 */
	protected function prepare() {
		// Build the url to then fetch the XML
		$url = BASE_URL . PREDICTION_SUMMARY . DIV . $this->line;
		// Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for summary train predictions.
	 * @param $xml - the XML string retrieved with getXml()
	 * @return array - containing values to parse to JSON-encode
	 */
	protected function parse($xml) {
		// Get the XML data from the feed, break it down
		$arr = array("created" => (string) $xml->Time["TimeStamp"],
					"stations" => array());

		foreach ($xml->S as $station) {
			$station_arr = array("stationcode" => (string) $station["Code"],
								"stationname" => (string) $station["N"],
								"platforms" => array());
			foreach ($station->P as $platform) {
				$platform_arr = array("platformname" => (string) $platform["N"],
									"platformcode" => (int) $platform["Code"],
									"trains" => array());
				foreach ($platform->T as $train) {
					$train_arr = array("trainnumber" => (int) $train["S"],
									"tripno" => (int) $train["T"],
									"destcode" => (int) $train["D"],
									"destination" => (string) $train["DE"],
									"timeto" => (string) $train["C"],
									"location" => (string) $train["L"]);
					$platform_arr["trains"][] = $train_arr;
				}
				$station_arr["platforms"][] = $platform_arr;
			}
			$arr["stations"][] = $station_arr;
		}
		return $arr;
	}
}

/**
 * Class to get, parse, and return the TfL XML feed as a JSON string
 * for Line Status for all TfL lines. Allows choosing whether to
 * only fetch status for lines with reported incident(s).
 */
class LineStatus extends TflJsonFetcher {
	// Declare expiry time for cache in seconds
	const __expiry_time = 30;
	// Declare some private variables
	private $incidentsonly;

	/**
	 * Sub-class constructor method.
	 * @param $incidents_only - boolean for determining if requesting only with incidents
	 */
	public function __construct($incidents_only) {
		parent::__construct(self::__expiry_time, "Line Status", self::make_file_name($incidents_only));
		$this->incidentsonly = $incidents_only;
	}

	/**
	 * Private function make_file_name doesn't override since defining
	 * several different types in the super-class isn't useful.
	 * Only required to be private either way. Each class implements it
	 * in a completely different manner anyway.
	 * @param $incidents_only - boolean for determining if requesting only with incidents
	 * @return string - file name to be used for reading/writing cache file
	 */
	private static function make_file_name($incidents_only) {
		// Construct the filename for output
		$filename = BASE_FILE . LINE_STATUS;

		if ($incidents_only) {
			$filename .= DIV . INCIDENTS_ONLY;
		} else {
			$filename .= "/full";
		}

		$filename .= FILE_EXTENSION;

		return $filename;
	}

	/**
	 * Utility method to prepare the URL and automate fetching the XML
	 * @return string - XML string returned from the generated URL
	 */
	protected function prepare() {
		// Build the url to then fetch the XML
		$url = BASE_URL . LINE_STATUS;

		if ($this->incidentsonly) {
			$url .= DIV . INCIDENTS_ONLY;
		}

		// Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for line status requests.
	 * @param $xml - the XML string retrieved with getXml()
	 * @return array - containing values to parse to JSON-encode
	 */
	protected function parse($xml) {
		// Get the XML data from the feed, break it down
		$arr = array("lines" => array());

		foreach ($xml->LineStatus as $linestatus) {
			$line_arr = array("id" => (int) $linestatus["ID"],
							"details" => (string) $linestatus["StatusDetails"],
							"lineid" => (int) $linestatus->Line[0]["ID"],
							"linename" => (string) $linestatus->Line[0]["Name"],
							"statusid" => (string) $linestatus->Status[0]["ID"],
							"status" => (string) $linestatus->Status[0]["CssClass"],
							"description" => (string) $linestatus->Status[0]["Description"],
							"active" => (bool) $linestatus->Status[0]["IsActive"]);
			$arr["lines"][] = $line_arr;
		}
		return $arr;
	}
}

/**
 * Class to get, parse, and return the TfL XML feed as a JSON string
 * for Station Status for all TfL stations. Allows choosing whether to
 * only fetch status for stations with reported incident(s).
 */
class StationStatus extends TflJsonFetcher {
	// Declare expiry time for cache in seconds
	const __expiry_time = 30;
	// Declare some private variables
	private $incidentsonly;

	/**
	 * Sub-class constructor method.
	 * @param $incidents_only - boolean for determining if requesting only with incidents
	 */
	public function __construct($incidents_only) {
		parent::__construct(self::__expiry_time, "Station Status", self::make_file_name($incidents_only));
		$this->incidentsonly = $incidents_only;
	}

	/**
	 * Private function make_file_name doesn't override since defining
	 * several different types in the super-class isn't useful.
	 * Only required to be private either way. Each class implements it
	 * in a completely different manner anyway.
	 * @param $incidents_only - boolean for determining if requesting only with incidents
	 * @return string - file name to be used for reading/writing cache file
	 */
	private static function make_file_name($incidents_only) {
		// Construct the filename for output
		$filename = BASE_FILE . STATION_STATUS;

		if ($incidents_only) {
			$filename .= DIV . INCIDENTS_ONLY;
		} else {
			$filename .= "/full";
		}

		$filename .= FILE_EXTENSION;

		return $filename;
	}

	/**
	 * Utility method to prepare the URL and automate fetching the XML
	 * @return string - XML string returned from the generated URL
	 */
	protected function prepare() {
		// Build the url to then fetch the XML
		$url = BASE_URL . STATION_STATUS;

		if ($this->incidentsonly) {
			$url .= DIV . INCIDENTS_ONLY;
		}

		// Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for station status requests.
	 * @param $xml - the XML string retrieved with getXml()
	 * @return array - containing values to parse to JSON-encode
	 */
	protected function parse($xml) {
		// Get the XML data from the feed, break it down
		$arr = array("stations" => array());

		foreach ($xml->StationStatus as $stationstatus) {
			$station_arr = array("id" => (int) $stationstatus["ID"],
							"details" => (string) $stationstatus["StatusDetails"],
							"stationname" => (string) $stationstatus->Station[0]["Name"],
							"statusid" => (string) $stationstatus->Status[0]["ID"],
							"status" => (string) $stationstatus->Status[0]["CssClass"],
							"description" => (string) $stationstatus->Status[0]["Description"],
							"active" => (bool) $stationstatus->Status[0]["IsActive"]);
			$arr["stations"][] = $station_arr;
		}
		return $arr;
	}
}

/**
 * Class to get, parse, and return multiple TfL XML feed as one JSON string
 * with a list of all lines, their respective line codes, and stations which
 * are associated to those lines, with their station codes.
 * Valid line and station codes are required for certain requests, making
 * this request essential for operation.
 */
class StationsList extends TflJsonFetcher {
	// Declare expiry time for cache in seconds
	const __expiry_time = 604800;
	// Declare some private variables
	private $lines_list, $line;

	public function __construct($lines_list) {
		parent::__construct(self::__expiry_time, "Stations List", self::make_file_name());
		$this->lines_list = $lines_list;
	}

	/**
	 * Private function make_file_name doesn't override since defining
	 * several different types in the super-class isn't useful.
	 * Only required to be private either way. Each class implements it
	 * in a completely different manner anyway.
	 * StationsList always uses the same filename format, so no params
	 * @return string - file name to be used for reading/writing cache file
	 */
	private static function make_file_name() {
		// Construct the filename for output
		$filename = BASE_FILE . STATIONS_LIST . FILE_EXTENSION;

		return $filename;
	}

	/**
	 * Utility method to prepare the URL and automate fetching the XML
	 * @return string - XML string returned from the generated URL
	 */
	protected function prepare() {
		// Build the url to then fetch the XML
		$url = BASE_URL . PREDICTION_SUMMARY . DIV . $this->line;
		// Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for summary predictions, which will be broken down
	 * into station code and station name elements.
	 * @param $xml - the XML string retrieved with getXml()
	 * @return array - containing values to parse to JSON-encode
	 */
	protected function parse($xml) {
		// Get the XML data from the feed, break it down
		$arr = array();

		foreach ($xml->S as $station) {
			$station_arr = array("stationcode" => (string) $station["Code"],
								"stationname" => (string) $station["N"]);
			$arr[] = $station_arr;
		}
		return $arr;
	}

	/**
	 * Utility method to automate fetching and parsing of XML into JSON.
	 * Overridden in StationsList due to being a special-case class.
	 * It requires that multiple files be fetched and parsed for output.
	 * @return string - JSON-encoded string of the parsed response
	 */
	public function fetch() {
		$json;
		// Check if the cache is valid
		if (parent::validateCache()) {
			// If so, read in from the cache file
			$json = file_get_contents($this->filename);
		} else {
			foreach ($this->lines_list as $code => $name) {
				$this->line = $code;
				// Call the prepare function, fetching the XML
				$xml = $this->prepare();
				$line_arr = array("linecode" => $code,
								"linename" => $name,
								"stations" => $this->parse($xml));
				// Add the working array to our lines array
				$this->out_arr["lines"][] = $line_arr;
			}
			// Encode the array into JSON
			$json = json_encode($this->out_arr, true);
			// Write newest version to cache
			$this->writeToCacheFile($json);
		}
		return $json;
	}
}

/* Code from Recess Framework for HTTP return codes */
/**
 * StatusCodes provides named constants for
 * HTTP protocol status codes. Written for the
 * Recess Framework (http://www.recessframework.com/)
 *
 * @author Kris Jordan
 * @license MIT
 * @package recess.http
 */
class StatusCodes {
    // [Informational 1xx]
    const HTTP_CONTINUE = 100, HTTP_SWITCHING_PROTOCOLS = 101;
    // [Successful 2xx]
    const HTTP_OK = 200, HTTP_CREATED = 201, HTTP_ACCEPTED = 202, HTTP_NONAUTHORITATIVE_INFORMATION = 203, HTTP_NO_CONTENT = 204, HTTP_RESET_CONTENT = 205, HTTP_PARTIAL_CONTENT = 206;
    // [Redirection 3xx]
    const HTTP_MULTIPLE_CHOICES = 300, HTTP_MOVED_PERMANENTLY = 301, HTTP_FOUND = 302, HTTP_SEE_OTHER = 303, HTTP_NOT_MODIFIED = 304, HTTP_USE_PROXY = 305, HTTP_UNUSED= 306, HTTP_TEMPORARY_REDIRECT = 307;
    // [Client Error 4xx]
    const errorCodesBeginAt = 400, HTTP_BAD_REQUEST = 400, HTTP_UNAUTHORIZED = 401, HTTP_PAYMENT_REQUIRED = 402, HTTP_FORBIDDEN = 403, HTTP_NOT_FOUND = 404, HTTP_METHOD_NOT_ALLOWED = 405, HTTP_NOT_ACCEPTABLE = 406, HTTP_PROXY_AUTHENTICATION_REQUIRED = 407, HTTP_REQUEST_TIMEOUT = 408, HTTP_CONFLICT = 409, HTTP_GONE = 410, HTTP_LENGTH_REQUIRED = 411, HTTP_PRECONDITION_FAILED = 412, HTTP_REQUEST_ENTITY_TOO_LARGE = 413, HTTP_REQUEST_URI_TOO_LONG = 414, HTTP_UNSUPPORTED_MEDIA_TYPE = 415, HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416, HTTP_EXPECTATION_FAILED = 417;
    // [Server Error 5xx]
    const HTTP_INTERNAL_SERVER_ERROR = 500, HTTP_NOT_IMPLEMENTED = 501, HTTP_BAD_GATEWAY = 502, HTTP_SERVICE_UNAVAILABLE = 503, HTTP_GATEWAY_TIMEOUT = 504, HTTP_VERSION_NOT_SUPPORTED = 505;

    private static $messages = array(
        // [Informational 1xx]
        100=>'100 Continue', 101=>'101 Switching Protocols',
        // [Successful 2xx]
        200=>'200 OK', 201=>'201 Created', 202=>'202 Accepted', 203=>'203 Non-Authoritative Information', 204=>'204 No Content', 205=>'205 Reset Content', 206=>'206 Partial Content',
        // [Redirection 3xx]
        300=>'300 Multiple Choices', 301=>'301 Moved Permanently', 302=>'302 Found', 303=>'303 See Other', 304=>'304 Not Modified', 305=>'305 Use Proxy', 306=>'306 (Unused)', 307=>'307 Temporary Redirect',
        // [Client Error 4xx]
        400=>'400 Bad Request', 401=>'401 Unauthorized', 402=>'402 Payment Required', 403=>'403 Forbidden', 404=>'404 Not Found', 405=>'405 Method Not Allowed', 406=>'406 Not Acceptable', 407=>'407 Proxy Authentication Required', 408=>'408 Request Timeout', 409=>'409 Conflict', 410=>'410 Gone', 411=>'411 Length Required', 412=>'412 Precondition Failed', 413=>'413 Request Entity Too Large', 414=>'414 Request-URI Too Long', 415=>'415 Unsupported Media Type', 416=>'416 Requested Range Not Satisfiable', 417=>'417 Expectation Failed',
        // [Server Error 5xx]
        500=>'500 Internal Server Error', 501=>'501 Not Implemented', 502=>'502 Bad Gateway', 503=>'503 Service Unavailable', 504=>'504 Gateway Timeout', 505=>'505 HTTP Version Not Supported'
    );

    public static function httpHeaderFor($code) {
        return 'HTTP/1.1 ' . self::$messages[$code];
    }
    public static function getMessageForCode($code) {
        return self::$messages[$code];
    }
    public static function isError($code) {
        return is_numeric($code) && $code >= self::HTTP_BAD_REQUEST;
    }
    public static function canHaveBody($code) {
        return
            // True if not in 100s
            ($code < self::HTTP_CONTINUE || $code >= self::HTTP_OK)
            && // and not 204 NO CONTENT
            $code != self::HTTP_NO_CONTENT
            && // and not 304 NOT MODIFIED
            $code != self::HTTP_NOT_MODIFIED;
    }
}

?>