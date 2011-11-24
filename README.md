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
Otherwise, you're all set, just hit the php script from a web browser, or using a HTTP GET, or whatever else you may fancy using, to query the data feeds or get from the cache.

## Other Information
For more detailed information, it's best to [view the project's home page.](http://trains.desousa.com.pt)

### Licensing
The MIT License

Copyright (c) 2011 Filipe De Sousa

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

### Notes
I have no affiliation with Transport for London, and only use their publicly accessible feeds. To gain access to them, visit their developer's area: [TfL developer's area](http://www.tfl.gov.uk/businessandpartners/syndication/default.aspx).  
To use TfL's data and APIs, make sure to register with them.