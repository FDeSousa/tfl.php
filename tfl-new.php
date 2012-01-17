<?php
/**
 * tfl.php - TfL API that makes sense
 * @author Filipe De Sousa
 * @version 0.5
 * @license Apache 2.0
 */

/******************************************************************************
 * Copyright 2011 Filipe De Sousa
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

# --------------------------------------------------------------------------------
# Declarations area below
$starttime = microtime(true);

# URL parameter for request of detailed predictions
define("PREDICTION_DETAILED", "predictiondetailed");
# URL parameter for request of summary predictions
define("PREDICTION_SUMMARY", "predictionsummary");
# URL parameter for request of line status
define("LINE_STATUS", "linestatus");
# URL parameter for request of station status
define("STATION_STATUS", "stationstatus");
# URL parameter for request of stations list
define("STATIONS_LIST", "stationslist");
# URL parameter for request of only incident reports
define("INCIDENTS_ONLY", "incidentsonly");

# Base URL for TfL requests:
define("BASE_URL", "http://cloud.tfl.gov.uk/trackernet/");
# Base filename for reading/writing files
define("BASE_FILE", "./cache/");
# Default file extension to write file with
define("FILE_EXTENSION", ".json");

# For stats of stations list, have the list of train lines here
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

# Get the URL parameters:
$request = strtolower($_GET["request"]);
$line = strtolower($_GET["line"]);
$station = strtolower($_GET["station"]);
$incidents_only = (bool) $_GET["incidents"];
$timed = (bool) $_GET["timed"];

# Declarations area finished
# --------------------------------------------------------------------------------
# Command execution area

# Just execute our main function and be done with it
main();

# Takes just one line to get the program started fetching/parsing requests
# --------------------------------------------------------------------------------
# Functions area, beware! Dragons ahead!

/**
 * Main method, determines the request type, echoes the output from the
 * parsing of XML to JSON string
 * @return null
 * @author Filipe De Sousa
 * @version 0.5
 */
function main() {
	# Get some global variables
	global $line, $lines_list, $station, $request, $timed, $starttime;

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
			die("{\"error\":\"No valid request made\"}");
	}

	# Original method of adding the processingtime made invalid JSON (woops!)
	if ($timed) {
		$json_a = json_decode($json_out, true);
		$json_a["processingtime"] = (microtime(true) - $starttime);
		$json_out = json_encode($json_a);
	}

	echobig($json_out);

	echobig($fetcher->fetch());
}

/**
 * Function to improve performance of echoing large strings.
 * As some functions return long JSON strings, may improve echo performance
 * cf. http://wonko.com/post/seeing_poor_performance_using_phps_echo_statement_heres_why
 * @version 0.5
 */
function echobig($string, $buffersize = 8192) {
	$split = str_split($string, $buffersize);

	foreach ($split as $chunk) {
		echo $chunk;
	}
}

/**
 * Base class for all fetcher classes, handling the order of fetching and parsing
 * the XML into JSON format for all of its sub-classes
 * @version 0.5
 */
abstract class TflJsonFetcher {

	protected $expiretime, $out_arr, $filename;

	public function __construct($cache_expire, $request_name, $file_name) {
		# Save the expiration time
		$this->expiretime = $cache_expire;

		# Get the file name for cache reading/writing
		$this->filename = $file_name;

		# Instantiate the array, adding request type and request name
		$this->out_arr = array("requesttype" => "{$request_name}");

		# Set the right header information
		header("Content-Type: application/json");
		header("Cache-Control: public, max-age={$this->expiretime}");
	}

	abstract protected function prepare();
	abstract protected function parse($xml);

	public function fetch() {
		$json = "";
		# Check if the cache is valid
		if ($this->validateCache()) {
			# If so, read in from the cache file
			$json = file_get_contents($this->filename);
		} else {
			# Call the prepare function, fetching the XML
			$xml = $this->prepare();
			# Perform an array merge with out_arr and the output of parse()
			$this->out_arr = array_merge($this->out_arr, $this->parse($xml));
			# Encode the array into JSON
			$json = json_encode($this->out_arr, true);
			# Write newest version to cache
			$this->writeToCacheFile($json);
		}
		return $json;
	}

	/**
	 * Convenience function to check whether the cache file is valid to avoid
	 * additional processing strain. Checks file exists first, then checks its
	 * last edit time, comparing it to the expiry time and current time.
	 * @return boolean - true if cache file is valid and recent, false otherwise
	 * @author Filipe De Sousa
	 * @version 0.5
	 */
	protected final function validateCache() {
		if (file_exists($this->filename)) {
			# File exists, so check if cached file is still valid
			if ((time() - filectime($this->filename)) < $this->expiretime) {
				return true;
			} else {
				return false;
			}
		} else {
			# File doesn't exists, check the directories do
			if (!is_dir(dirname($this->filename))) {
				# If directories don't exist, make them
				mkdir(dirname($this->filename), 0755, true);
			}
			return false;
		}
	}

	/**
	 * Convenience method to write out JSON to file
	 * Just makes code a little cleaner elsewhere
	 * @return nothing
	 * @author Filipe De Sousa
	 * @version 0.5
	 */
	protected final function writeToCacheFile($json) {
		# Open the file for writing
		$file = fopen($this->filename, "w+");
		# Write out our newest data copy to file
		if (json_decode($json) != null and flock($file, LOCK_EX)) {
			# Perform quick sanity check, and lock the file
			fwrite($file, $json);
		}
		# Always unlock and close the file
		flock($file, LOCK_UN);
		fclose($file);
	}

	/**
	 * Convenience function, download XML from URL, return it in a string
	 * @return string XML downloaded from the parsed URL
	 * @author Filipe De Sousa
	 * @version 0.5
	 */
	protected static final function getXml($url) {
		# Since we must use curl, initialise its handler
		$ch = curl_init();
		# Setup curl with our options
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		# Get the source with curl
		$source = curl_exec($ch);
		# Close up curl, no longer needed
		curl_close($ch);
		# Parse our xml
		$xml = simpleXML_load_string($source);
		# Return it
		return $xml;
	}
}

/**
 * Class to get, parse, and return the TfL XML feed as a JSON string
 * for Detailed Predictions on a given TfL line for a given station.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the detailed train predictions
 * @author Filipe De Sousa
 * @version 0.5
 */
class DetailedPredictions extends TflJsonFetcher {
	# Declare expiry time for cache in seconds
	const __expiry_time = 30;
	# Declare some private variables
	private $line, $lines_list, $station;

	public function __construct($line, $lines_list, $station) {
		parent::__construct(self::__expiry_time, "Detailed Predictions", self::make_file_name($line, $lines_list, $station));
		$this->line = $line;
		$this->lines_list = $lines_list;
		$this->station = $station;
	}

	private static function make_file_name($line, $lines_list, $station) {
		# Construct the filename for output
		$filename = BASE_FILE . PREDICTION_DETAILED . "/";
		# Check line isn't empty, and line code is valid
		if ($line != null and array_key_exists($line, $lines_list) !== false) {
			$filename .= $line;
		} else { # Fail fast if the line code is invalid/missing
			die("{\"error\":\"Invalid line code\"}");
		}
		# Now add the station code to the filename
		if ($station != null) {
			$filename .= "/" . $station . FILE_EXTENSION;
		} else { # Fail fast if the station code is missing
			die("{\"error\":\"Missing station code\"}");
		}
		return $filename;
	}

	protected function prepare() {
		# Build the url to then fetch the XML
		$url = BASE_URL . PREDICTION_DETAILED . "/" . $this->line . "/" . $this->station;
		# Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for detailed train predictions.
	 * Only need this function to be used within getDetailedPredictions function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 */
	protected function parse($xml) {
		# We're parsing back an array, so initialise it here with basic information
		$arr = array("information" => array("created" => (string) $xml->WhenCreated,
									"linecode" => (string) $xml->Line,
									"linename" => (string) $xml->LineName),
					"stations" => array());
		# We have several arrays to hold information. stations in the base array

		# stations is built with all information before being placed in main stations array
		foreach ($xml->S as $station) {
			$station_arr = array("stationcode" => (string) $station["Code"],
								"stationname" => (string) $station["N"],
								"platforms" => array());
			# platforms is built with all information before being placed in main platforms array
			foreach ($station->P as $platform) {
				$platform_arr = array("platformname" => (string) $platform["N"],
									"platformnumber" => (int) $platform["Num"],
									"trains" => array());
				# trains built with all information before being placed in main trains array also
				foreach ($platform->T as $train) {
					$train_arr = array("lcid" => (string) $train["LCID"],
									"secondsto" => (string) $train["SecondsTo"],
									"timeto" => (string) $train["TimeTo"],
									"location" => (string) $train["Location"],
									"destination" => (string) $train["Destination"],
									"destcode" => (int) $train["DestCode"],
									"tripno" => (int) $train["TripNo"]);
					# place train array into current platform array
					$platform_arr["trains"][] = $train_arr;
				}
				# place current platform array into current station array
				$station_arr["platforms"][] = $platform_arr;
			}
			# place current station array into main stations array
			$arr["stations"][] = $station_arr;
		}
		return $arr;
	}
}

/**
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * @return string - JSON formatted string containing the summary train predictions
 * @author Filipe De Sousa
 * @version 0.5
 */
class SummaryPredictions extends TflJsonFetcher {
	# Declare expiry time for cache in seconds
	const __expiry_time = 30;
	# Declare some private variables
	private $line, $lines_list;

	public function __construct($line, $lines_list) {
		parent::__construct(self::__expiry_time, "Summary Predictions", self::make_file_name($line, $lines_list));
		$this->line = $line;
		$this->lines_list = $lines_list;
	}

	private static function make_file_name($line, $lines_list) {
		# Construct the filename for output
		$filename = BASE_FILE . PREDICTION_SUMMARY . "/";
		# Check line isn't empty, and line code is valid
		if ($line != null and array_key_exists($line, $lines_list) !== false) {
			$filename .= $line . FILE_EXTENSION;
		} else { # Fail fast if the line code is invalid/missing
			die("{\"error\":\"Invalid line code\"}");
		}
		return $filename;
	}

	protected function prepare() {
		# Build the url to then fetch the XML
		$url = BASE_URL . PREDICTION_SUMMARY . "/" . $this->line;
		# Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for summary train predictions.
	 * Only need this function to be used within SummaryPredictions class
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 **/
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
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the line statuses
 * @author Filipe De Sousa
 * @version 0.5
 */
class LineStatus extends TflJsonFetcher {
	# Declare expiry time for cache in seconds
	const __expiry_time = 30;
	# Declare some private variables
	private $incidentsonly;

	public function __construct($incidents_only) {
		parent::__construct(self::__expiry_time, "Line Status", self::make_file_name($incidents_only));
		$this->incidentsonly = $incidents_only;
	}

	private static function make_file_name($incidents_only) {
		# Construct the filename for output
		$filename = BASE_FILE . LINE_STATUS;

		if ($incidents_only) {
			$filename .= "/" . INCIDENTS_ONLY;
		} else {
			$filename .= "/full";
		}

		$filename .= FILE_EXTENSION;

		return $filename;
	}

	protected function prepare() {
		# Build the url to then fetch the XML
		$url = BASE_URL . LINE_STATUS;

		if ($this->incidentsonly) {
			$url .= "/" . INCIDENTS_ONLY;
		}

		# Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for detailed train predictions.
	 * Only need this function to be used within getDetailedPredictions function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
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
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the station statuses
 * @author Filipe De Sousa
 * @version 0.5
 */
class StationStatus extends TflJsonFetcher {
	# Declare expiry time for cache in seconds
	const __expiry_time = 30;
	# Declare some private variables
	private $incidentsonly;

	public function __construct($incidents_only) {
		parent::__construct(self::__expiry_time, "Station Status", self::make_file_name($incidents_only));
		$this->incidentsonly = $incidents_only;
	}

	private static function make_file_name($incidents_only) {
		# Construct the filename for output
		$filename = BASE_FILE . STATION_STATUS;

		if ($incidents_only) {
			$filename .= "/" . INCIDENTS_ONLY;
		} else {
			$filename .= "/full";
		}

		$filename .= FILE_EXTENSION;

		return $filename;
	}

	protected function prepare() {
		# Build the url to then fetch the XML
		$url = BASE_URL . STATION_STATUS;

		if ($this->incidentsonly) {
			$url .= "/" . INCIDENTS_ONLY;
		}

		# Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for detailed train predictions.
	 * Only need this function to be used within getDetailedPredictions function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
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
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the list of TfL train lines and stations
 * @author Filipe De Sousa
 * @version 0.5
 */
class StationsList extends TflJsonFetcher {
	# Declare expiry time for cache in seconds
	const __expiry_time = 604800;
	# Declare some private variables
	private $lines_list, $line;

	public function __construct($lines_list) {
		parent::__construct(self::__expiry_time, "Stations List", self::make_file_name());
		$this->lines_list = $lines_list;
	}

	private static function make_file_name() {
		# Construct the filename for output
		$filename = BASE_FILE . STATIONS_LIST . FILE_EXTENSION;

		return $filename;
	}

	protected function prepare() {
		# Build the url to then fetch the XML
		$url = BASE_URL . PREDICTION_SUMMARY . "/" . $this->line;
		# Now return the result of the call to getXml()
		return parent::getXml($url);
	}

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for detailed train predictions.
	 * Only need this function to be used within getDetailedPredictions function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
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

	# Stations List is a special case, so override it within the class
	public function fetch() {
		$json = "";
		# Check if the cache is valid
		if (parent::validateCache()) {
			# If so, read in from the cache file
			$json = file_get_contents($this->filename);
		} else {
			foreach ($this->lines_list as $code => $name) {
				$this->line = $code;
				# Call the prepare function, fetching the XML
				$xml = $this->prepare();
				$line_arr = array("linecode" => $code,
								"linename" => $name,
								"stations" => $this->parse($xml));
				# Add the working array to our lines array
				$this->out_arr["lines"][] = $line_arr;
			}
			# Encode the array into JSON
			$json = json_encode($this->out_arr, true);
			# Write newest version to cache
			$this->writeToCacheFile($json);
		}
		return $json;
	}
}

# End of functions area, you may let down your shield now
# --------------------------------------------------------------------------------
?>