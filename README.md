# tfl.php - TfL API that makes sense

## Introduction
A simple API for getting data from the Transport for London - London Underground (TfLU from now on for my own sanity's sake) feeds in valid JSON format.  
Data comes from the detailed predictions, summary predictions, line status, station status and stations list feeds, updated at most every thirty seconds (limitation of the TfL data feeds).

## How do I access it?
### Basics of it
Maybe not the nicest, most thoughtful of all systems, but it works alright for now; you can get a data feed from [http://trains.desousa.com.pt/tfl.php](http://trains.desousa.com.pt/tfl.php "Base URL for queries") using PHP URL attributes, valid ones listed below.  
Alternatively, download the PHP script from the github repo and host it yourself.

## How can I host my own?
Good question. Get the latest copy of tfl.php here in this github repo.
Check to make sure there aren't references to any of my own hosting pages (I may not have noticed them) and change those.  
Otherwise, you're all set, just hit the script from a web browser, or using a HTTP GET, or whatever else you may fancy using, to query the data feeds or get from the cache.

## Other Information
For more detailed information, it's best to [view the project's home page.](http://trains.desousa.com.pt)

### Licensing
Copyright 2011 Filipe De Sousa

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.  
See the License for the specific language governing permissions and limitations under the License.

### Notes
I have no affiliation with Transport for London, and only use their publicly accessible feeds.  
To gain access to them, visit their developer's area: [TfL developer's area](http://www.tfl.gov.uk/businessandpartners/syndication/default.aspx).  
To use TfL's data and APIs, make sure to register with them.