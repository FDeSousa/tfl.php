<?php header('Content-Type: application/json');

/*
 * tfl.php - TfL API that makes sense
 * Filipe De Sousa
 * November 24, 2011
 * Version 0.3
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
# Declaring some useful constants
# For use in parsing the URL this script was executed from:
define("REQUEST", "request");
define("LINE", "line");
define("STATION", "station");
define("INCIDENTS", "incidents");

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
$request = strtolower($_GET[REQUEST]);
$line = strtolower($_GET[LINE]);
$station = strtolower($_GET[STATION]);
$incidents_only = (bool) $_GET[INCIDENTS];
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
 * @version 0.1.3
 **/
function main() {
	# Get some global variables
	global $request, $timed, $starttime;

	$json_out;

	switch ($request) {
		case PREDICTION_DETAILED:
			$json_out = getDetailedPredictions();
			break;
		case PREDICTION_SUMMARY:
			$json_out = getSummaryPredictions();
			break;
		case LINE_STATUS:
			$json_out = getLineStatus();
			break;
		case STATION_STATUS:
			$json_out = getStationStatus();
			break;
		case STATIONS_LIST:
			$json_out = getStationsList();
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
}

/**
 * Function to improve performance of echoing large strings.
 * As some functions return long JSON strings, may improve echo performance
 * cf. http://wonko.com/post/seeing_poor_performance_using_phps_echo_statement_heres_why
 * @version 0.1.4
 **/
function echobig($string, $buffersize = 8192) {
	$split = str_split($string, $buffersize);

	foreach ($split as $chunk) {
		echo $chunk;
	}
}

/**
 * Convenience function to check whether the cache file is valid to avoid
 * additional processing strain. Checks file exists first, then checks its
 * last edit time, comparing it to the expiry time and current time.
 * @return boolean - true if cache file is valid and recent, false otherwise
 * @author Filipe De Sousa
 * @version 0.1
 **/
function validateCache($filename, $expiretime) {
	if (file_exists($filename)) {
		if ((time() - filectime($filename)) < $expiretime) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * Convenience function, download XML from URL, return it in a string
 * @return string XML downloaded from the parsed URL
 * @author Filipe De Sousa
 * @version 0.1.5
 **/
function getXml($url) {
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

/**
 * Convenience method to write out JSON to file
 * Just makes code a little cleaner elsewhere
 * @return nothing
 * @author Filipe De Sousa
 * @version 0.1.5
 **/
function writeToCacheFile($filename, $json) {
	# Open the file for writing
	$file = fopen($filename, "w+");
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
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Detailed Predictions on a given TfL line for a given station.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the detailed train predictions
 * @author Filipe De Sousa
 * @version 0.1
 **/
function getDetailedPredictions() {
	# Get some global variables
	global $lines_list, $line, $station;

	# Create the output array now, with information on request type
	$out_arr = array("requesttype" => "Detailed Predictions");

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for detailed train predictions.
	 * Only need this function to be used within getDetailedPredictions function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 **/
	function parseDetailedPredictionsXml($xml) {
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

	# Construct the filename for output
	$filename = BASE_FILE . PREDICTION_DETAILED . "/";
	# Check line isn't empty, and line code is valid
	if ($line != null and array_key_exists($line, $lines_list) !== false) {
		$filename .= $line;
	} else {
		# If not, just kill off execution now
		die("{\"error\":\"Invalid line code\"}");
	}
	# Directory has been named, check if it exists, or create it
	if (! is_dir($filename)) {
		# rwx access only for _www, recursively created folders
		mkdir($filename, 0755, true);
	}
	# Now add the station code to the filename
	if ($station != null) {
		# Fail fast if the station code is missing
		$filename .= "/" . $station . FILE_EXTENSION;
	} else {
		die("{\"error\":\"Missing station code\"}");
	}
	# Also make up the URL for the request for later
	$url = BASE_URL . PREDICTION_DETAILED . "/" . $line . "/" . $station;

	# Determine if the cache is valid (30 seconds)
	if (validateCache($filename, 30)) {
		# If so, return the cached file's contents
		return file_get_contents($filename);
	}

	# Otherwise, get XML from the URL
	$xml = getXml($url);

	# Parse the XML into our array
	$out_arr = array_merge($out_arr, parseDetailedPredictionsXml($xml));
	$json_a = json_encode($out_arr, true);

	# Write out data to file
	writeToCacheFile($filename, $json_a);

	# Return the json
	return $json_a;
}

/**
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * @return string - JSON formatted string containing the summary train predictions
 * @author Filipe De Sousa
 * @version 0.1
 **/
function getSummaryPredictions() {
	# Get some global variables
	global $lines_list, $line;

	# Create the output array now, with information on request type
	$out_arr = array("requesttype" => "Summary Predictions");

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for summary train predictions.
	 * Only need this function to be used within getSummaryPredictions function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 **/
	function parseSummaryPredictionsXml($xml) {
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

	# Construct the filename for output
	$filename = BASE_FILE . PREDICTION_SUMMARY;
	# Directory has been named, check if it exists, or create it
	if (! is_dir($filename)) {
		# rwx access only for _www, recursively created folders
		mkdir($filename, 0755, true);
	}
	# Check line isn't empty, and line code is valid
	if ($line != null and array_key_exists($line, $lines_list) !== false) {
		$filename .= "/" . $line . FILE_EXTENSION;
	} else {
		# If not, just kill off execution now
		die("{\"error\":\"Invalid line code\"}");
	}
	$url = BASE_URL . PREDICTION_SUMMARY . "/" . $line;

	# Determine if the cache is valid (30 seconds)
	if (validateCache($filename, 30)) {
		# If so, return the cached file's contents
		return file_get_contents($filename);
	}

	# Otherwise, get XML from the URL
	$xml = getXml($url);

	# Parse the XML into our array
	$out_arr = array_merge($out_arr, parseSummaryPredictionsXml($xml));
	$json_a = json_encode($out_arr, true);

	# Write out data to file
	writeToCacheFile($filename, $json_a);

	# Return the json
	return $json_a;
}

/**
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the line statuses
 * @author Filipe De Sousa
 * @version 0.1
 **/
function getLineStatus() {
	# Get some global variables
	global $incidents_only;

	# Create the output array now, with information on request type
	$out_arr = array("requesttype" => "Line Status");

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for line status.
	 * Only need this function to be used within getLineStatus function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 **/
	function parseLineStatusXml($xml) {
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

	# Construct the filename for output
	$filename = BASE_FILE . LINE_STATUS;
	# At the same time, construct the URL
	$url = BASE_URL . LINE_STATUS;

	# Directory has been named, check if it exists, or create it
	if (! is_dir($filename)) {
		# rwx access only for _www, recursively created folders
		mkdir($filename, 0755, true);
	}
	if ($incidents_only) {
		# If we only want incidents, file name is "incidents.json"
		$filename .= "/incidents";
		# And the URL has /incidentsonly on the end
		$url .= "/" . INCIDENTS_ONLY;
	} else {
		# Otherwise, file name is "full.json"
		$filename .= "/full";
	}
	$filename .= FILE_EXTENSION;

	# Determine if the cache is valid (30 seconds)
	if (validateCache($filename, 30)) {
		# If so, return the cached file's contents
		return file_get_contents($filename);
	}

	# Otherwise, get XML from the URL
	$xml = getXml($url);

	# Parse the XML into our array
	$out_arr = array_merge($out_arr, parseLineStatusXml($xml));
	$json_a = json_encode($out_arr, true);

	# Write out data to file
	writeToCacheFile($filename, $json_a);

	# Return the json
	return $json_a;
}

/**
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the station statuses
 * @author Filipe De Sousa
 * @version 0.1
 **/
function getStationStatus() {
	# Get some global variables
	global $incidents_only;

	# Create the output array now, with information on request type
	$out_arr = array("requesttype" => "Station Status");

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for station status.
	 * Only need this function to be used within getStationStatus function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 **/
	function parseStationStatusXml($xml) {
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

	# Construct the filename for output
	$filename = BASE_FILE . STATION_STATUS;
	# At the same time, construct the URL
	$url = BASE_URL . STATION_STATUS;

	# Directory has been named, check if it exists, or create it
	if (! is_dir($filename)) {
		# rwx access only for _www, recursively created folders
		mkdir($filename, 0755, true);
	}
	if ($incidents_only) {
		# If we only want incidents, file name is "incidents.json"
		$filename .= "/incidents";
		# And the URL has /incidentsonly on the end
		$url .= "/" . INCIDENTS_ONLY;
	} else {
		# Otherwise, file name is "full.json"
		$filename .= "/full";
	}
	$filename .= FILE_EXTENSION;

	# Determine if the cache is valid (30 seconds)
	if (validateCache($filename, 30)) {
		# If so, return the cached file's contents
		return file_get_contents($filename);
	}

	# Otherwise, get XML from the URL
	$xml = getXml($url);

	# Parse the XML into our array
	$out_arr = array_merge($out_arr, parseStationStatusXml($xml));
	$json_a = json_encode($out_arr, true);

	# Write out data to file
	writeToCacheFile($filename, $json_a);

	# Return the json
	return $json_a;
}

/**
 * Method to get, parse, and return the TfL XML feed as a JSON string
 * for Summary Predictions on a given TfL line.
 * Reads from cached file if it was recently updated, fetches latest data otherwise.
 * @return string - JSON formatted string containing the list of TfL train lines and stations
 * @author Filipe De Sousa
 * @version 0.1
 **/
function getStationsList() {
	# Get some global variables
	global $lines_list;

	# Create the output array now, with information on request type
	$out_arr = array("requesttype" => "Stations List",
					"lines" => array());

	/**
	 * Method to get and return the broken-down, edited XML elements from
	 * the TfL feed for summary train predictions. Takes out most of the tags.
	 * Only need this function to be used within getStationsList function
	 * @return Array containing the broken-down XML tags
	 * @author Filipe De Sousa
	 **/
	function parseStationsListXml($xml) {
		// Get the XML data from the feed, break it down
		$arr = array();

		foreach ($xml->S as $station) {
			$station_arr = array("stationcode" => (string) $station["Code"],
								"stationname" => (string) $station["N"]);
			$arr[] = $station_arr;
		}
		return $arr;
	}

	# Construct the filename for output
	$filename = BASE_FILE . STATIONS_LIST . FILE_EXTENSION;

	# Determine if the cache is valid (7 days in seconds)
	if (validateCache($filename, 604800)) {
		# If so, return the cached file's contents
		return file_get_contents($filename);
	}

	foreach ($lines_list as $code => $name) {
		$line = array("linecode" => $code,
					"linename" => $name);
		$url = BASE_URL . PREDICTION_SUMMARY . "/" . $code;
		# Get the XML for parsing
		$xml = getXml($url);
		# Parse it into our working array
		$line["stations"] = parseStationsListXml($xml);
		# Add the working array to our lines array
		$out_arr["lines"][] = $line;
	}
	$json_a = json_encode($out_arr, true);

	# Write out data to file
	writeToCacheFile($filename, $json_a);

	# Return the json
	return $json_a;
}

# End of functions area, you may let down your shield now
# --------------------------------------------------------------------------------
?>