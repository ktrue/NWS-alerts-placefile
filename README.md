# metar-placefile
## GRLevelX placefile generator for NWS Alerts from api.weather.gov
## Purpose:

This script set gets JSON alert data from api.weather.gov/alerts and formats a placefile for GRLevelX software
to display either outlines or filled-in areas corresponding to the geography the alert applies to.
With outlines selected, a mouse-over the line will popup a text tooltip with details about the alert.
With areas selected, a mouse-over the associated icon to the alert will popup a text tooltip with details about the alert.

The *NWS_Placefile_Alerts.php* script is to be accessed by including the website URL in the GRLevelX placefile manager window.
There are 'stub' scripts to invoke *NWS_Placefile_Alerts.php* with various options selected:

- *alert-areas.php*  Selects Land alerts and filled-in area displays
- *alert-details.php*  Selects Land alerts with area outlines.
- *alert-marine-areas.php*  Selects Marine alerts with filled-in area displays.
- *alert-marine-details.php*  Selects Marine alerts with area outlines.

For ease of usage, the above 4 scripts should be the end-point targets for the GRLevelX placefile manager window.
 
 
## Scripts:

### *NWS_Placefile_Alerts.php*

This script generates a GRLevelX placefile from the cached JSON  
file from api.weather.gov/alerts on demand by a GRLevel3 instance.
It will return output for GRLevelX to display for alerts 
within 350 miles of the current radar selected in GRLevel3.
It requires the following files:

- *NWS-zones-inc.txt* (produced by *make-NWS-zones-index.php* for the Zone metadata including Zone name, centerpoint lat/long, index# to shapefile, and timezone abbreviation.

Note: *NWS-zones-inc.txt* file only needs to change when the base shapefiles are updated by the NWS.
There is an included utility script to regenerate the file from the shapefiles (*make-NWS-zones-index.php*)
The distributed file is based on the September 19, 2023 shapefile release by the NWS.

- *WWAColors.php* contains a lookup array for color selection of lines/areas based on the NWS event name in the JSON.
   

The script generates output using 1 icon file (*alerts-icons.png*) for area displays.  The outline display does not use any icons.

If you run the script for debugging **in a browser**, add `?version=1.5&dpi=96&lat={latitude}&lon={longitude}` to
the URL so it knows what to select for display.  The output of the script is always text/plain;charset=ISO-8859-1 for the placefile.
Viewing the placefile in a browser will show GRLevel3 comments (lines starting with ';') showing how each alert is handled.

In the GRLevel3 placefile manager window, just add the script URL without a query string -- GRLevel3 will automatically add those based on the current radar site selected.

Additional documentation is in each script for further modification convenience.

## Installation

Upload the files/directories into a directory *under* the document root of your website.  
(We used 'placefiles' in the examples below)

The *NWS_Placefile_Alerts.php* (and the maintenance utility *make-NWS-zones-index.php*) both rely on the php-shapefile class scripts
which are included in the ./php-shapefile/ directory.  You can upload the full directory, or install the utility directly
from the official distribution at https://github.com/gasparesganga/php-shapefile 
and documentation at https://gasparesganga.com/labs/php-shapefile/

**NOTE:** The ./shapefiles/ directory needs to have the unzipped September 19, 2023 shapefiles uploaded to it.
See the documentation at ./shapefiles/README.txt on detailed instructions about this.
If you use shapefiles other than the September 19, 2023 release, then you'll need to update
*make-NWS-zones-index.php* with the new names and run that script once to replace the *NWS-zones-inc.txt*
to match the actual shapefiles being used.  A sample run of the utility is in the ./doc/ directory.



- *alert-areas.php*
- *alert-details.php*
- *alert-marine-areas.php*
- *alert-marine-details.php*
- *alerts-icons.png*
- *make-NWS-zones-index.php*
- *NWS_Placefile_Alerts.php*
- *NWS-zones-inc.txt*
- *WWAColors.php*
- *./php-shapefile/*
- *./shapefiles/*
  

Then you can test the placefile script by **using your browser** to go to<br>
`https://your.website.com/placefiles/alert-areas.php?version=1.5&dpi=96&lat=37.0&lon=-122.0`

If that returns a placefile, then add your placefile URL (minus the URL query string) into the GRLevelX placefile
manager window.

## Settings in *NWS_Placefile_Alerts.php*

```php
# -----------------------------------------------
# Settings:
# excludes:
$excludeAlerts = array(
"Severe Thunderstorm Warning",
"Severe Weather Statement",
"Tornado Warning",
"Flash Flood Warning"
);
$excludeAlerts = array(); /* debug */
$TZ = 'UTC';                            # default timezone for display
$timeFormat = "d-M-Y g:ia T";           # display format for times
$maxDistance = 350;                     # generate entries only within this distance
$cacheFilename = 'response_land.json';  # store json here
$cacheTimeMax  = 480;                   # number of seconds cache is 'fresh'
$alertsURL = 'https://api.weather.gov/alerts/active?status=actual&message_type=alert&region_type=land&limit=500';
$showDetails = true;                   # =false, show areas only; =true, show lines with popups
$showMarine = true; # =true; for marine alerts, =false for land alerts
#
$latitude = 37.155;
$longitude = -121.898;
$version  = 1.5;
$doLogging = true;
$doDebug = false; # =true; turn on additional display, may break placefile for GRLevelX
# NWS timezone abbreviations used per https://www.weather.gov/gis/Counties
# appears in Forecast, County and Fire zones (not in Marine)
$NWStimeZones = array (
  'A' => 'America/Anchorage', // alaska
  'Ah' => 'America/Anchorage', // alaska islands
  'C' => 'America/Chicago',   // central
  'CE' => 'America/Chicago',  // florida west
  'CM' => 'America/Chicago',  // north dakota
  'E' => 'America/New_York',  // eastern
  'F' => 'Pacific/Fiji',      // Fiji and Yap
  'G' => 'Pacific/Guam',      // guam and marianas
  'H' => 'Pacific/Honolulu',  // hawaii - no DST
  'J' => 'Asia/Tokyo',        // japan
  'K' => 'Pacific/Kwajalein', // Marshall islands
  'M' => 'America/Denver',    // mountain
  'MC' => 'America/Denver',    // nebraska
  'MP' => 'America/Los_Angeles', // idaho - western
  'Mm' => 'America/Denver',    // arizona/reservations with DST
  'P' => 'America/Los_Angeles',// pacific
  'S' => 'Pacific/Pago_Pago', // samoa
  'V' => 'America/St_Thomas', // Puerto Rico/St Thomas etc.
  'h' => 'America/Adak',      // hawaii with DST observed
  'm' => 'America/Phoenix',   // mountain-no DST
);

# -----------------------------------------------

```

Please note that the default installation includes `$doLogging = true;` which enables logging of accesses 
to <br> *NWS-placefile-log-{year}-{month}-{day}.txt* (UTC date) text files to the installation directory.
These files are useful to help debugging, but you may want to switch them off by specifying `$doLogging = false;`.

## Sample *NWS_Placefile_Alerts.php* output:
```
; NWS_Placefile_Alerts.php - V2.03 - 02-Sep-2023 - initial release
Title: NWS Alert Areas - Sun Sep 3 3:15:15 UTC 2023
Refresh: 8
Font: 1, 11, 1, "Arial"

IconFile: 1, 17, 17, 8, 8, alerts-icons.png
; cache file 'response_land.json' refreshed from https://api.weather.gov/alerts/active?status=actual&message_type=alert&region_type=land&limit=500 

; JSON decode - - No errors
; 100 alerts found

; Severe Thunderstorm Warning issued September 2 at 8:01PM PDT until September 2 at 8:30PM PDT by NWS Portland OR 
; active: 03-Sep-2023 3:01am UTC to 03-Sep-2023 3:30am UTC
; excluded by distance 1522 > 350 max.

; Special Weather Statement issued September 2 at 7:54PM PDT by NWS Portland OR 
; active: 03-Sep-2023 2:54am UTC to 03-Sep-2023 3:30am UTC
; excluded by distance 1564 > 350 max.

; Heat Advisory .. no coords, generating zone coords UGC='MNZ060,MNZ061,MNZ062,MNZ063,MNZ068,MNZ069,MNZ070'
; Heat Advisory
; active: 03-Sep-2023 2:04am UTC to 05-Sep-2023 10:00pm UTC
; zone=MNZ060 'F|957|Hennepin|45.0045|-93.4768|C	W|1246|Hennepin|45.0045|-93.4768|C'
; zone=MNZ061 'F|941|Anoka|45.2732|-93.2464|C	W|1231|Anoka|45.2732|-93.2464|C'
; zone=MNZ062 'F|982|Ramsey|45.0171|-93.0994|C	W|1265|Ramsey|45.0171|-93.0994|C'
; zone=MNZ063 'F|999|Washington|45.0383|-92.8839|C	W|1281|Washington|45.0383|-92.8839|C'
; zone=MNZ068 'F|944|Carver|44.8208|-93.8025|C	W|1234|Carver|44.8208|-93.8025|C'
; zone=MNZ069 'F|988|Scott|44.6484|-93.5358|C	W|1270|Scott|44.6484|-93.5358|C'
; zone=MNZ070 'F|949|Dakota|44.6718|-93.0656|C	W|1238|Dakota|44.6718|-93.0656|C'
; this zone is MNZ060 with 311 coordinates.
Polygon:
45.245911,-93.512497,255,127,80,150
45.245911,-93.512497
45.246410,-93.518097
45.244812,-93.521797
...
45.242912,-93.504593
45.245911,-93.512497
End:
Icon: 45.0045,-93.4768,0,1,52,"Heat Advisory\n\nSent:      02-Sep-2023 9:04pm CDT (0204Z)\nEffective: 02-Sep-2023 9:04pm CDT (0204Z)\nOnset:     02-Sep-2023 9:04pm CDT (0204Z)\nExpires:   03-Sep-2023 5:00am CDT (1000Z)\nEnds:      05-Sep-2023 5:00pm CDT (2200Z)\n\nZone(s):   MNZ060, MNZ061, MNZ062, MNZ063, MNZ068, MNZ069, MNZ070\nSeverity:  Moderate\nUrgency:   Expected\nCertainty: Likely\nSender:    NWS Twin Cities/Chanhassen MN\n\nHeat Advisory issued September 2 at 9:04PM CDT until September 5 at 5:00PM CDT by NWS Twin Cities/Chanhassen MN\n\nHEAT ADVISORY REMAINS IN EFFECT UNTIL 5 PM CDT TUESDAY\n(shading is Zone MNZ060)\n"
; end end zone MNZ060

...
```

## Display samples with GRLevel3

![areas](https://github.com/ktrue/NWS-alerts-placefile/assets/17507343/394f60dc-25e1-4bbb-8290-72876aa942b3)

![marine-areas](https://github.com/ktrue/NWS-alerts-placefile/assets/17507343/365109f4-30d1-44db-b742-fc0a948afc57)

## Acknowledgements

Special thanks to Mike Davis, W1ARN of the National Weather Service, Nashville TN office
for his inspiration/testing/feedback during development.
Also thanks to [Gaspare Sganga](https://gasparesganga.com/labs/php-shapefile/) for his excellent *php-shapefile* class scripts which allowed this script set to be built.   

