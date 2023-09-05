<?php

/*
*	Name:			NWS_Polygon_Alerts_Colored.php
*	Author(s):	        Mike Davis 0617	, Ken True saratoga-weather.org
*	Description:	Reads NWS CAP1.1 ATOM RSS Alert Feeds for one or more states
*                   and displays a GR2 placefile describing affected polygons.
* rewritten to use api.weather.gov/alerts/active JSON from XML by Ken True
*/
# Version 2.00 - 29-Aug-2023 - initial release
# Version 2.01 - 30-Aug-2023 - improved popup display and area display
# Version 2.02 - 01-Sep-2023 - added icons to alert area displays (where available)
# Versoon 2.04 - 02-Sep-2023 - added timezone times to popup display
# Version 2.05 - 05-Sep-2023 - fix unclosed polygon info from shapefile

$Version = "NWS_Placefile_Alerts.php - V2.05 - 05-Sep-2023 - initial release";
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
header("Content-Type: text/plain");

if(isset($doShowDetails)) {$showDetails = $doShowDetails;}
if(isset($doShowMarine))      {$showMarine  = $doShowMarine;}
$titleExtra = ($showDetails)?'Details':'Areas';
if($showMarine) {
	$titleExtra = 'Marine '.$titleExtra;
	$alertsURL = 'https://api.weather.gov/alerts/active?status=actual&message_type=alert&region_type=marine&limit=500';
	$cacheFilename = str_replace('land','marine',$cacheFilename);
}
//*
if(isset($_GET['lat'])) {$latitude = $_GET['lat'];}
if(isset($_GET['lon'])) {$longitude = $_GET['lon'];}
if(isset($_GET['version'])) {$GRversion = $_GET['version'];}

if(isset($latitude) and !is_numeric($latitude)) {
	print "Bad latitude spec.";
	exit;
}
if(isset($latitude) and $latitude >= -90.0 and $latitude <= 90.0) {
	# OK latitude
} else {
	print "Latitude outside range -90.0 to +90.0\n";
	exit;
}

if(isset($longitude) and !is_numeric($longitude)) {
	print "Bad longitude spec.";
	exit;
}
if(isset($longitude) and $longitude >= -180.0 and $longitude <= 180.0) {
	# OK longitude
} else {
	print "Longitude outside range -180.0 to +180.0\n";
	exit;
}	
if(!isset($latitude) or !isset($longitude) or !isset($GRversion)) {
	print "This script only runs via a GRlevelX placefile manager.";
	exit();
}
//*/
if(isset($doLogging) and $doLogging) {
	$fn = "NWS-placefile-log-".gmdate('Y-m-d').'.txt';
	$log = gmdate('H:i:s'). " ";
	$log .= isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']." ":"x.x.x.x ";
	$log .= isset($_SERVER['SCRIPT_URI'])?$_SERVER['SCRIPT_URI']:'(no-URI)';
	$log .= isset($_SERVER['QUERY_STRING'])?"?".$_SERVER['QUERY_STRING']:"?(no-QUERY)";
	file_put_contents($fn,$log.PHP_EOL,FILE_APPEND);
}
// Register autoloader
require_once('php-shapefile/src/Shapefile/ShapefileAutoloader.php');
Shapefile\ShapefileAutoloader::register();

// Import classes
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader;

include_once("NWS-zones-inc.txt"); # Master zone:shapefiles lookup table
global $zoneLookup, $showDetails,$doDebug;

include_once('WWAColors.php');  # colors array indexed by alert headline

date_default_timezone_set($TZ);

# Note: we accumulate placefile output into $output as various parts are appended
#  then the whole shebang is printed as the placefile.
#
$today = date("D M j G:i:s T Y");
$output = "; $Version\n";
$output .= "Title: NWS Alert $titleExtra - $today\n";
$output .= "Refresh: 8\n";
$output .= "Font: 1, 11, 1, \"Arial\"\n\n";
if(!$showDetails) { # display icons in middle of areas
	$output .= "IconFile: 1, 17, 17, 8, 8, alerts-icons.png\n";
}

if($doDebug) { $output .= "; main calling JSONread\n"; }
$output .= JSONread($alertsURL);
if($doDebug) { $output .= "; main ends\n"; }
echo $output;

# -----------------------------------------------------
# functions
function JSONread($url) {
	global $zoneLookup, $cacheFilename,$cacheTimeMax,$latitude,$longitude, $maxDistance,$showDetails,$titleExtra,$doDebug ;
  # read alerts.weather.gov for active alerts and process the JSON return
  # author: Ken True - webmaster@saratoga-weather.org
  # Version 1.00 - 24-Aug-2023 - initial release
  $out = '';  #collector for GRLevelX placefile statements
	if($doDebug) {$out .= "; JSONread entered\n"; }
	$STRopts = array(
		'http' => array(
			'method' => "GET",
			'protocol_version' => 1.1,
			'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
				"Cache-control: max-age=0\r\n" . 
				"Connection: close\r\n" . 
				"User-agent: Mozilla/5.0 (NWS_Polygon_Alerts_Colored - saratoga-weather.org)\r\n" . 
				"Accept: application/json,application/xml\r\n"
		) ,
		'ssl' => array(
			'method' => "GET",
			'protocol_version' => 1.1,
			'verify_peer' => false,
			'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
				"Cache-control: max-age=0\r\n" . 
				"Connection: close\r\n" . 
				"User-agent: Mozilla/5.0 (NWS_Polygon_Alerts_Colored - saratoga-weather.org)\r\n" . 
				"Accept: application/json,application/xml\r\n"
		)
	);
	$STRcontext = stream_context_set_default($STRopts);
  
	if(!file_exists($cacheFilename) or
	   (file_exists($cacheFilename) and filemtime($cacheFilename)+$cacheTimeMax < time()) ) {
    $rawJSON = file_get_contents($url);
		if(strpos($rawJSON,'geocode') !== false) {
	    file_put_contents($cacheFilename,$rawJSON);
		  $out .= "; cache file '$cacheFilename' refreshed from $url \n\n";
		} else {
			$out .= "; cache refresh failed to get good content\n";
			if(file_exists($cacheFilename)) {
				$rawJSON = file_get_contents($cacheFilename);
				$out .= "; cache '$cacheFilename' reloaded\n";
			}
		}
	} else {
		$rawJSON = file_get_contents($cacheFilename);
		$age = time()-filemtime($cacheFilename);
		$out .= "; cache file '$cacheFilename' loaded. age=$age seconds.\n\n";
	}
	
  $JSON = json_decode($rawJSON,true,512,JSON_BIGINT_AS_STRING+JSON_OBJECT_AS_ARRAY);
	#var_export($JSON,false);
	#return;
  
  if(function_exists('json_last_error')) {
  switch (json_last_error()) {
  case JSON_ERROR_NONE:
    $JSONerror = '- No errors';
    break;

  case JSON_ERROR_DEPTH:
    $JSONerror = '- Maximum stack depth exceeded';
    break;

  case JSON_ERROR_STATE_MISMATCH:
    $JSONerror = '- Underflow or the modes mismatch';
    break;

  case JSON_ERROR_CTRL_CHAR:
    $JSONerror = '- Unexpected control character found';
    break;

  case JSON_ERROR_SYNTAX:
    $JSONerror = '- Syntax error, malformed JSON';
    break;

  case JSON_ERROR_UTF8:
    $JSONerror = '- Malformed UTF-8 characters, possibly incorrectly encoded';
    break;

  default:
    $JSONerror = '- Unknown error';
    break;
  }
    
  $out .= "; JSON decode - $JSONerror\n";    
  }

  if (!isset($JSON['features'][0])) {
		$out .= "; .. no data found\n";
		if($doDebug) {$out .= "; JSONread returned\n"; }

    return($out);
  }
  
  $out .= "; ".count($JSON['features'])." alerts found\n\n";
  
  foreach ($JSON['features'] as $i => $A) { # do the heavy lifting.. decode each alert
	  if($doDebug) {$out .= "\n; JSONread: i=$i calling decodeAlert\n";}
    $out .= decodeAlert($A);
		if($doDebug) {$out .= ";--------------\n; JSONread: decodeAlert returns\n";}
  }
	if($doDebug) {$out .= "\n; JSONread returns\n"; }

  return($out);  # this will be appended to $output in main
} // end JSONread function

#---------------------------------------------------------------------------

function decodeAlert($A) {
  global $color,$zoneLookup,$excludeAlerts,$timeFormat,$latitude,$longitude,$maxDistance,$showDetails,$titleExtra,$doDebug;
	global $NWStimeZones;
  # Decode a specific alert 
	# return out as the full entry for the alert to JSONread for appending (uttimately) to $output for printing the placefile
	#
	# depending on $showDetails, either a Line:,coords,End: or a Polygon,coords,End:,Icon: will be returned for
	# each active.
	#
	# Placefile comments (prepended with ;) will be returned for any issues found (expired, not in range, no coords availabl)
	# Input JSON looks like:
  /*
    0 => 
    array (
      'id' => 'https://api.weather.gov/alerts/urn:oid:2.49.0.1.840.0.f00965605f5fc6071fd73d662b77bb966ae93ad5.001.1',
      'type' => 'Feature',
      'geometry' => 
      array (
        'type' => 'Polygon',
        'coordinates' => 
        array (
          0 => 
          array (
            0 => 
            array (
              0 => -80.23,
              1 => 26.59,
            ),
            1 => 
            array (
              0 => -80.12,
              1 => 26.669999999999998,
            ),
            2 => 
            array (
              0 => -79.98,
              1 => 26.52,
            ),
            3 => 
            array (
              0 => -80.12,
              1 => 26.4,
            ),
            4 => 
            array (
              0 => -80.23,
              1 => 26.59,
            ),
          ),
        ),
      ),
      'properties' => 
      array (
        '@id' => 'https://api.weather.gov/alerts/urn:oid:2.49.0.1.840.0.f00965605f5fc6071fd73d662b77bb966ae93ad5.001.1',
        '@type' => 'wx:Alert',
        'id' => 'urn:oid:2.49.0.1.840.0.f00965605f5fc6071fd73d662b77bb966ae93ad5.001.1',
        'areaDesc' => 'Inland Palm Beach County; Metro Palm Beach County; Coastal Palm Beach County',
        'geocode' => 
        array (
          'SAME' => 
          array (
            0 => '012099',
          ),
          'UGC' => 
          array (
            0 => 'FLZ067',
            1 => 'FLZ068',
            2 => 'FLZ168',
          ),
        ),
        'affectedZones' => 
        array (
          0 => 'https://api.weather.gov/zones/forecast/FLZ067',
          1 => 'https://api.weather.gov/zones/forecast/FLZ068',
          2 => 'https://api.weather.gov/zones/forecast/FLZ168',
        ),
        'references' => 
        array (
        ),
        'sent' => '2023-09-01T14:04:00-04:00',
        'effective' => '2023-09-01T14:04:00-04:00',
        'onset' => '2023-09-01T14:04:00-04:00',
        'expires' => '2023-09-01T14:30:00-04:00',
        'ends' => NULL,
        'status' => 'Actual',
        'messageType' => 'Alert',
        'category' => 'Met',
        'severity' => 'Moderate',
        'certainty' => 'Observed',
        'urgency' => 'Expected',
        'event' => 'Special Weather Statement',
        'sender' => 'w-nws.webmaster@noaa.gov',
        'senderName' => 'NWS Miami FL',
        'headline' => 'Special Weather Statement issued September 1 at 2:04PM EDT by NWS Miami FL',
        'description' => 'At 203 PM EDT, National Weather Service meteorologists were tracking
a strong thunderstorm over Greenacres, or near Wellington, moving
southeast at 15 mph.

HAZARD...Winds in excess of 30 mph.

SOURCE...Radar indicated.

IMPACT...Gusty winds could knock down tree limbs and blow around
unsecured objects.

Locations impacted include...
Boca Raton, Boynton Beach, Delray Beach, Lake Worth, Ocean Ridge,
Greenacres, Palm Springs, Lantana, Atlantis, Village Of Golf, Kings
Point, Dunes Road, Hypoluxo, Gulf Stream, Briny Breezes, Manalapan,
Aberdeen, Lake Worth Corridor, Aberdeen Golf Course and Florida
Gardens.',
        'instruction' => 'These winds can down small tree limbs and branches, and blow around
unsecured small objects. Seek shelter in a safe building until the
storm passes.',
        'response' => 'Execute',
        'parameters' => 
        array (
          'AWIPSidentifier' => 
          array (
            0 => 'SPSMFL',
          ),
          'WMOidentifier' => 
          array (
            0 => 'WWUS82 KMFL 011804',
          ),
          'NWSheadline' => 
          array (
            0 => 'A strong thunderstorm will impact portions of east central Palm Beach County through 230 PM EDT',
          ),
          'eventMotionDescription' => 
          array (
            0 => '2023-09-01T18:03:00-00:00...storm...330DEG...13KT...26.6,-80.17',
          ),
          'maxWindGust' => 
          array (
            0 => '30 MPH',
          ),
          'maxHailSize' => 
          array (
            0 => '0.00',
          ),
          'BLOCKCHANNEL' => 
          array (
            0 => 'EAS',
            1 => 'NWEM',
            2 => 'CMAS',
          ),
          'EAS-ORG' => 
          array (
            0 => 'WXR',
          ),
        ),
      ),
    ),
 
  
  
  */
  
/* produce:
Color: 225 185 135
Line: 2, 0, "... <alert text>"
35.18, -83.34
35.22, -83.20
34.97, -82.98
34.88, -83.30
35.18, -83.34
End:

or

Polygon:
35.18, -83.34
35.22, -83.20
34.97, -82.98
34.88, -83.30
35.18, -83.34
End:
Icon: 2, 0, "... <alert text>"


*/


  $isoDate = "Y-m-d\TH:i:s";
  
  $out = ''; # used for accumulation of retrun output
	
	if($doDebug) {$out .= "\n;--------------\n; decodeAlert entered\n"; }

  $P = $A['properties'];  # just to make the following simpler

  # gather common boilerplate
  $event = $P["event"];
  $headline = str_replace("\n",'\n',$P["headline"]);       # convert embedded newline into \n chars
  $headline = str_replace('"','\"',$headline);             # swap embedded " with \"
  $description = str_replace("\n",'\n',$P["description"]); # convert embedded newline into \n chars
  $description = str_replace('"','\"',$description);      # swap embedded " with \"

  $event_onset = $P["onset"];
  $onset = date($timeFormat,strtotime($event_onset));
  $event_expires = $P["expires"];
	$UTCexpires = strtotime($P["expires"]);
  $expires = date($timeFormat,$UTCexpires);
	$severity = $P['severity'];
	$certainty = $P['certainty'];
	$urgency   = $P['urgency'];
	$senderName = $P['senderName'];
	$NWSheadline = isset($P['parameters']['NWSheadline'][0])?
	   str_replace("\n",'\n',join(' ',$P['parameters']['NWSheadline'])):'';
	if(!empty($P['ends'])) {
	  $UTCexpires = strtotime($P["ends"]);
		$ends = date($timeFormat,$UTCexpires);
	} else {
		$ends = $event_expires;
	}

	$cLat = '';
	$cLon = '';
	if($doDebug) {
		$out .= "; decodeAlert: ";
		$out .= isset($A['geometry']['coordinates'])?" geometry polygon exists\n":" geometry polygon not found\n";
		#$out .= "; \$A=".var_export($A,true)."\n;--------------\n";
	}
  if(isset($A['geometry']['coordinates'])) { # not all events include NWS polygons
		$coords = ''; # accumulate list of coordinates
		foreach ($A['geometry']['coordinates'] as $n => $item) {
			foreach ($item as $j => $latlon) {
			 $lat =  sprintf("%01.4f",$latlon[1]);
			 $long = sprintf("%01.4f",$latlon[0]);
			 $coords .= "$lat,$long\n";
			}
    }
	  $nCoords = explode("\n",$coords);
	  $firstCoord = $nCoords[0]; # used for placement of icon if geometry is provided
		if($showDetails) {
	   $coordsFrom = '\n(lines are around NWS alert area)\n';
		} else {
	   $coordsFrom = '\n(shading is NWS alert area)\n';
		}
		if($doDebug) {$out .= "; decodeAlert: ".count($nCoords)." polygon coords found. firstCoord='$firstCoord'\n"; }
	} else { # no geometry provided .. will create later from Zone entries
		$coords = '';
		$nCoords = array();
		$firstCoord = '';
		$coordsFrom = '';
		if($doDebug) {$out .= "; decodeAlert: no polygon coords found\n"; }
	}
	
	# get list of all Zones in the alert
  if(isset($P['geocode']['UGC'][0]) ) {
	  $fZone = $P['geocode']['UGC'][0];
		if(isset($zoneLookup[$fZone])) { # check that we have info on this Zone

#    'FLC133' => 'C|2380|Washington, FL|30.6106|-85.6656|C',
#    'FLZ010' => 'F|2897|Washington|30.6106|-85.6656|C\tW|2397|Washington|30.6106|-85.6656|C',

  		$v = explode("\t",$zoneLookup[$fZone]."\t"); # Forecast and Fire Zones may have been merged
			$v = explode('|',$v[0].'|');
      $cLat = $v[3];
			$cLon = $v[4];
			$TZ   = $v[5];

			# see if we're keeping this alert or not based on distance from current radar selected in GRLevel3
			list($miles,$km,$bearingDeg,$bearingWR) = 
	       GML_distance((float)$latitude, (float)$longitude,(float)$v[3], (float)$v[4]);
			if($miles > $maxDistance) {
			  $out .= "; $headline \n";
        $out .= "; active: $onset to $expires\n";
		    $out .= "; excluded by distance $miles > $maxDistance max.\n\n";
		    if($doDebug) { $out .= "; decodeAlert: returned-#2\n"; }
		    return($out); # nope.. to far away, return with that result.
			}
		}
	}
  
	# see if we need to skip this event type
	if(in_array($event,$excludeAlerts)) {
		$out .= "; $headline \n";
    $out .= "; active: $onset to $expires\n";
		$out .= "; excluded in \$excludeAlerts\n\n";
		if($doDebug) { $out .= "; decodeAlert: returned-#3\n"; }
		return($out); # yes, excluded by event
	}
	
	# see if the event has expired (unlikely, I hope)
	if(time() > $UTCexpires) {
		$out .= "; $headline \n";
    $out .= "; active: $onset to $expires\n";
		foreach (array("sent","effective","onset","expires","ends") as $i => $key) {
			if(isset($P[$key])) {
				$out .= "; ".str_pad("$key:",10)." ".$P[$key]."\n";
			} else {
				$out .= "; ".str_pad("$key:",10)." n/a\n";
			}
		}
    $out .= "; excluded as expired. Now=".gmdate($timeFormat)."\n";
		if($doDebug) { $out .= "; decodeAlert: returned-#4\n"; }
		return($out); # avoid the expired alert
	}

  if(substr($coords,0,1) == ';') { # likely error message from Shapefile listing
	  $tOut .= $coords."\n";
	  if($doDebug) { $tOut .= "; decodeAlert: returned-#5\n"; }
		return($out.$tOut); # can't display due to bad coords
	}
	
	# Now complete decode of event data

	$UGC = '';
	$UGC_array = array();
	# get list of Zones
	if(isset($P['geocode']['UGC'])){
		$UGC = implode(',',$P['geocode']['UGC']);
		$UGC_array = $P['geocode']['UGC'];
	}
	$UGC_list = str_replace(',',', ',$UGC);

	$tOut = '';  # Main event text comments
	
  if(isset($color[$event])) {
    list($css,$colors,$csshex) = $color[$event];
  } else {
    list($css,$colors,$csshex) = array('lightgrey','128 128 128',"F0F0F0");
  }

  $prefix = "; $event\n";
  $prefix .= "; active: $onset to $ends\n";

  $timeMarker = '%times%';
	$popup_template = "$event\\n\\n";
  $popup_template .= $timeMarker."\\n"; # replaced by str_replace('%times',get_popup_local_times($P,$zone),$popup_template);
	
	$popup_template .=	"Zone(s):   $UGC_list\\n".
					"Severity:  $severity\\n".
					"Urgency:   $urgency\\n".
					"Certainty: $certainty\\n".
					"Sender:    $senderName\\n".
          "\\n$headline\\n\\n".
					"$NWSheadline" . "\"\n";
#          "\\n\\n$headline\\n\\n$description"."\"\n";
	
  if($doDebug) {
		$out .= "; decodeAlert: coords length= ".strlen($coords)."\n"; 
		$out .= "; decodeAlert: coords='".str_replace("\n",' ',$coords)."'\n";
	}

	if($doDebug) { $out .= "; decodeAlert: before UGS processing\n"; }
			
  if(strlen($coords) < 10) { # no main coords.. get the Zone coords instead
	  if($doDebug) { $out .= "; decodeAlert: begin UGS processing\n"; }
		$out .= "; $event .. no coords, generating zone coords UGC='$UGC'\n"; 
		foreach ($UGC_array as $k => $zone) {
			if(isset($zoneLookup[$zone])) {
				# would lookup
				$prefix .= "; zone=$zone '".$zoneLookup[$zone]."'\n";
			} else {
				$prefix .= "; zone=$zone not found.\n\n";
				if($doDebug) { $prefix .= "; decodeAlert: returned-#6\n"; }
				return($out.$prefix); # oops.. an Unknown-to-us zone
			}
		}
  }
	
	if($doDebug) { $out .= "; decodeAlert: after UGS processing\n"; }

	if($doDebug) { $out .= "; decodeAlert: before coords processing\n"; }

  if(strlen($coords) > 10) {
		if($doDebug) { $out .= "; decodeAlert: coords processing\n"; }

		if(!$showDetails) { # for areas, change the first coordinate to have the color scheme
			list($cLat,$cLon) = explode(',',$firstCoord);
			$coords = $firstCoord.",".str_replace(' ',',',$colors).",150\n".$coords;
		}
		if(empty($tCmd)) { # tCmd is used for the main command 
			$tCmd = '';
			if($showDetails) { # do Line
				$tCmd .= "Color: $colors\n";
				$tCmd .= 'Line: 2, 0, "' . str_replace($timeMarker,get_popup_local_times($P,$UGC_array[0]),$popup_template);
			} else { # do Polygon
				$colors = str_replace(' ',',',$colors);
				$icon = get_iconnumber($event);
				$tCmd .= "Polygon:\n";
			}
		}

    if($showDetails) { # a Line: command
	    $out .= $prefix.str_replace("\"\n",$coordsFrom."\"\n",$tOut.$tCmd).$coords;
      $out .= "End:\n";
		} else { # a Polygon: command
      $out .= $prefix.$tOut.$tCmd.$coords;
      $out .= "End:\n";
			$popup = str_replace($timeMarker,get_popup_local_times($P,$UGC_array[0]),$popup_template);
			$out .= "Icon: $cLat,$cLon,0,1,$icon,\"".str_replace("\"\n",$coordsFrom."\"\n",$popup);
		}
		$out .= "; end geometry entry\n\n";
		if($doDebug) { $out .= "; decodeAlert returned-#7\n"; }
		return($out); # finished an entry for alert with geometry
	}

	if($doDebug) { $out .= "; decodeAlert: after coords processing\n"; }

  # oops.. havent returned, so handle no coords.. use Zones and generate a dup for each listed zone

	foreach ($UGC_array as $k => $zone) { # loop zones and create entries
#   'FLZ141' => 'F|3729|Coastal Volusia|29.0917|-80.9840|E\tW|3307|Coastal Volusia|29.0917|-80.9840|E',
#   'GMZ335' => 'M|92|Galveston Bay|29.4906|-94.8590|HGX',
		$v = explode("\t",$zoneLookup[$zone]."\t"); # split off forecast/fire zones if needed
		$v = explode('|',$v[0].'|'); # add a delimiter for marine zones with no TZ
		$cLat = $v[3];
		$cLon = $v[4];
		$TZ   = $v[5];
		
		$coords = get_shape_coords($zone); # this does a coordinate retrieval from the shapefile
		$nCoords = explode("\n",$coords);
		if($showDetails) {
		  $coordsFrom = '\n(lines are around Zone '.$zone.')\n';
		} else {
		  $coordsFrom = '\n(shading is Zone '.$zone.')\n';
		}

		$tCmd = "; this zone is $zone with ".count($nCoords)." coordinates.\n";

		if($showDetails) { # do Line: command
			$tCmd .= "Color: $colors\n";
			$popup = str_replace($timeMarker,get_popup_local_times($P,$zone),$popup_template);
			$tCmd .= 'Line: 2, 0, "' . $popup;
		} else { # do Polygon and Icon commands
			$colors = str_replace(' ',',',$colors);
			if(!empty($cLat) and !empty($cLon)) {
				$icon = get_iconnumber($event);
			}
			$tCmd .= "Polygon:\n";
		}

		$firstCoord = $nCoords[0];
		if(count($nCoords) > 9999) {
			$out .= $prefix."; too many coordinates to plot on GRLevel3 for ".$zone." (".count($nCoords).") .. skipping.\n\n";
			unset($nCoords);
			$coords = '';
			continue; # skip to next Zone
		}
		if(count($nCoords) < 2) {
			$out .= $prefix."; too few coordinates to plot on GRLevel3 for ".$UGC_array[0]." (".count($nCoords).") .. skipping.\n\n";
			unset($nCoords);
			continue; # skip to next Zone
		}
		
		unset($nCoords);
		if(!$showDetails) { # Polygon .. needs color info on first coordinate
			$coords = $firstCoord.",".str_replace(' ',',',$colors).",150\n".$coords;
		}
		$out .= $prefix.str_replace("\"\n",$coordsFrom."\"\n",$tOut.$tCmd).$coords;
    $out .= "End:\n";

		if(!$showDetails) { # Polygon.. place Icon after so it will show on top of area in GRLevel3
		  $popup = str_replace($timeMarker,get_popup_local_times($P,$zone),$popup_template);
			$out .= "Icon: $cLat,$cLon,0,1,$icon,\"".str_replace("\"\n",$coordsFrom."\"\n",$popup);
		}
		$out .= "; end end zone $zone\n\n";
		$coords = '';
	
	  
	}# end foreach zones - Rinse and repeat for each zone in alert
  
	if($doDebug) { $out .= "; decodeAlert returned-#at end of script\n"; }
  return($out);
  
} # end decodeAlert

#---------------------------------------------------------------------------

function get_popup_local_times ($P,$zone) {
  global $zoneLookup,$NWStimeZones,$timeFormat,$doDebug;
	# $P is the locl array of properties for the alert
	$TZ = 'UTC';
	$TZabbrev = '';
	if(isset($zoneLookup[$zone])) {
		$v = explode("\t",$zoneLookup[$zone]."\t"); # split off forecast/fire zones if needed
		$v = explode('|',$v[0].'|'); # add a delimiter for marine zones with no TZ
		$TZabbrev   = $v[5];
		if(isset($NWStimeZones[$TZabbrev])) {
			$TZ = $NWStimeZones[$TZabbrev];
		}
	}
	date_default_timezone_set($TZ);
	
	$popup = '';
	
	if($doDebug) {$popup = "TZabbrev='$TZabbrev' TZ='$TZ' for '$zone'\\n";}
	foreach (array("sent","effective","onset","expires","ends") as $i => $key) {
		if(isset($P[$key])) {
			$timestamp = strtotime($P[$key]);
			$popup .= ucfirst(str_pad("$key:",10))." ".date($timeFormat,$timestamp);
			if($TZ !== 'UTC') {
				$popup .= ' ('.gmdate('Hi',$timestamp).'Z)';
			}
			$popup .= "\\n";
		} else {
			$popup .= ucfirst(str_pad("$key:",10))." n/a\\n";
		}
  }
 return($popup);
}

#---------------------------------------------------------------------------

function get_shape_coords ($zone) {
	global $zoneLookup;
	
	if(isset($zoneLookup[$zone])) {
		#     'AKC013' => 'C|3331|Aleutians East, AK|55.4039|-161.8631',
		list($db,$idx,$name,$clat,$clon) = explode('|',$zoneLookup[$zone]);
		
	} else {
		return( "; get_shape_coords: '$zone' not found.\n");
	}

  try {
    // Open Shapefile
		$filename = $zoneLookup['_'.$db];

    $Shapefile = new ShapefileReader($filename, array(
      Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION  => Shapefile::ACTION_FORCE,
      Shapefile::OPTION_SUPPRESS_M => true,
      Shapefile::OPTION_SUPPRESS_Z => true,
     ));
    
		$fields = $Shapefile->getFieldsNames();
		
		try {
				$Shapefile->setCurrentRecord($idx);
				// Fetch a Geometry
				$Geometry = $Shapefile->fetchRecord();
				// Skip deleted records
				if ($Geometry->isDeleted()) {
						return( "; get_shape_coords -- deleted record idx=$idx\n");
				}

				$GDATA = $Geometry->getWKT();
				$vals = array();
        foreach ($fields as $i => $name) {
					$vals[$name] = $Geometry->getData($name);
				}
				$GRdata = convert_to_lines($GDATA);
				
				return($GRdata);
				
		} catch (ShapefileException $e) {
				// Handle some specific errors types or fallback to default
				switch ($e->getErrorType()) {
						// We're crazy and we don't care about those invalid geometries... Let's skip them!
						case Shapefile::ERR_GEOM_RING_AREA_TOO_SMALL:
						case Shapefile::ERR_GEOM_RING_NOT_ENOUGH_VERTICES:
								break;
								
						// Let's handle this case differently... :)
						case Shapefile::ERR_GEOM_POLYGON_WRONG_ORIENTATION:
								return("; get_shape_coords: Do you want the Earth to change its rotation direction?!?\n");
								break;
								
						// A fallback is always a nice idea
						default:
								return(
										"; get_shape_coords: Error Type: "  . $e->getErrorType()
										. " Message: " . $e->getMessage()
										. " Details: " . $e->getDetails() 
										. "\n"
								);
								break;
				}
    }
  } catch (ShapefileException $e) {
    /*
        Something went wrong during Shapefile opening!
    */
		return (
		  "; get_shape_coords: unable to open Shapefile $filename \n"
			);
  }
	
} # end get_shape_coords

#---------------------------------------------------------------------------

	function convert_to_lines($GDATA) {
		$tstr = str_replace('POLYGON','',$GDATA);
		$tstr = str_replace('MULTI','',$tstr);
		$tstr = str_replace(array('(',')'),array('',''),$tstr);
		$vals = explode(', ',$tstr.', ');
		$out = "";
		if(!isset($vals[0]) ) { return(""); }
		foreach ($vals as $i => $v) {
			list($lon,$lat) = explode(' ',$v.' ');
			
			if(strlen($lat)< 5 or strlen($lon) < 5) { break; }
			$out .= sprintf("%01.6f",$lat).','.sprintf("%01.6f",$lon)."\n";
		}
		# ensure polygon is closed V2.05
		$tCoords = explode("\n",$out);
		$firstCoord = $tCoords[0];
		$lastCoord  = array_pop($tCoords);
		
		if($firstCoord === $lastCoord) {
			#nothing to fix
			return($out);
		} else {
			# not matched .. add first coord to end
			$out .= $firstCoord."\n";
		}
		
		return($out);
	}
	
#---------------------------------------------------------------------------

// ------------ distance calculation function ---------------------
   
    //**************************************
    //     
    // Name: Calculate Distance and Radius u
    //     sing Latitude and Longitude in PHP
    // Description:This function calculates 
    //     the distance between two locations by us
    //     ing latitude and longitude from ZIP code
    //     , postal code or postcode. The result is
    //     available in miles, kilometers or nautic
    //     al miles based on great circle distance 
    //     calculation. 
    // By: ZipCodeWorld
    //
    //This code is copyrighted and has
	// limited warranties.Please see http://
    //     www.Planet-Source-Code.com/vb/scripts/Sh
    //     owCode.asp?txtCodeId=1848&lngWId=8    //for details.    //**************************************
    //     
    /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
    /*:: :*/
    /*:: This routine calculates the distance between two points (given the :*/
    /*:: latitude/longitude of those points). It is being used to calculate :*/
    /*:: the distance between two ZIP Codes or Postal Codes using our:*/
    /*:: ZIPCodeWorld(TM) and PostalCodeWorld(TM) products. :*/
    /*:: :*/
    /*:: Definitions::*/
    /*::South latitudes are negative, east longitudes are positive:*/
    /*:: :*/
    /*:: Passed to function::*/
    /*::lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees) :*/
    /*::lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees) :*/
    /*::unit = the unit you desire for results:*/
    /*::where: 'M' is statute miles:*/
    /*:: 'K' is kilometers (default):*/
    /*:: 'N' is nautical miles :*/
    /*:: United States ZIP Code/ Canadian Postal Code databases with latitude & :*/
    /*:: longitude are available at http://www.zipcodeworld.com :*/
    /*:: :*/
    /*:: For enquiries, please contact sales@zipcodeworld.com:*/
    /*:: :*/
    /*:: Official Web site: http://www.zipcodeworld.com :*/
    /*:: :*/
    /*:: Hexa Software Development Center © All Rights Reserved 2004:*/
    /*:: :*/
    /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/

  function GML_distance($lat1, $lon1, $lat2, $lon2) { 
    $theta = $lon1 - $lon2; 
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)); 
    $dist = acos($dist); 
    $dist = rad2deg($dist); 
    $miles = $dist * 60 * 1.1515;
  	$bearingDeg = fmod((rad2deg(atan2(sin(deg2rad($lon2) - deg2rad($lon1)) * 
	   cos(deg2rad($lat2)), cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - 
	   sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2) - deg2rad($lon1)))) + 360), 360);

	  $bearingWR = GML_direction($bearingDeg);
	
    $km = round($miles * 1.609344); 
    $kts = round($miles * 0.8684);
	  $miles = round($miles);
	return(array($miles,$km,$bearingDeg,$bearingWR));
  }

#---------------------------------------------------------------------------

function GML_direction($degrees) {
   // figure out a text value for compass direction
   // Given the direction, return the text label
   // for that value.  16 point compass
   $winddir = $degrees;
   if ($winddir == "n/a") { return($winddir); }

  if (!isset($winddir)) {
    return "---";
  }
  if (!is_numeric($winddir)) {
	return($winddir);
  }
  $windlabel = array ("N","NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S",
	 "SSW","SW", "WSW", "W", "WNW", "NW", "NNW");
  $dir = $windlabel[ (integer)fmod((($winddir + 11) / 22.5),16) ];
  return($dir);

} // end function GML_direction

#---------------------------------------------------------------------------

function get_iconnumber($event) {

	# return index into alerts-icons.png for the Icon: statement
	# based on Event name
	
	static $iconNumber = array(
		'911 Telephone Outage Emergency' => 44, 
		'Administrative Message' => 65, 
		'Air Quality Alert' => 65,
		'Air Stagnation Advisory' => 65,
		'Arroyo And Small Stream Flood Advisory' => 49,
		'Ashfall Advisory' => 35,
		'Ashfall Warning' => 13,
		'Avalanche Advisory' => 57,
		'Avalanche Warning' => 13,
		'Avalanche Watch' => 35,
		'Beach Hazards Statement' => 55,
		'Blizzard Warning' => 12,
		'Blizzard Watch' => 34,
		'Blowing Dust Advisory' => 35, #blank
		'Blowing Dust Warning' => 13, #blank
		'Brisk Wind Advisory' => 31,
		'Child Abduction Emergency' => 65,
		'Civil Danger Warning' => 13,
		'Civil Emergency Message' => 35,
		'Coastal Flood Advisory' => 55,
		'Coastal Flood Statement' => 33,
		'Coastal Flood Warning' => 11,
		'Coastal Flood Watch' => 33,
		'Dense Fog Advisory' => 59,
		'Dense Smoke Advisory' => 61,
		'Dust Advisory' => 35,
		'Dust Storm Warning' => 13,
		'Earthquake Warning' => 13,
		'Evacuation - Immediate' => 13,
		'Excessive Heat Warning' => 8,
		'Excessive Heat Watch' => 30,
		'Extreme Cold Warning' => 12,
		'Extreme Cold Watch' => 34,
		'Extreme Fire Danger' => 17, 
		'Extreme Wind Warning' => 9,
		'Fire Warning' => 17, 
		'Fire Weather Watch' => 35,
		'Flash Flood Statement' => 49,
		'Flash Flood Warning' => 5,
		'Flash Flood Watch' => 27,
		'Flood Advisory' => 49,
		'Flood Statement' => 49,
		'Flood Warning' => 5,
		'Flood Watch' => 27,
		'Freeze Warning' => 15,
		'Freeze Watch' => 37,
		'Freezing Fog Advisory' => 59,
		'Freezing Rain Advisory' => 64,
		'Freezing Spray Advisory' => 55,
		'Frost Advisory' => 60,
		'Gale Warning' => 10,
		'Gale Watch' => 32,
		'Hard Freeze Warning' => 16,
		'Hard Freeze Watch' => 38,
		'Hazardous Materials Warning' => 35,
		'Hazardous Seas Warning' => 43,
		'Hazardous Seas Watch' => 43,
		'Hazardous Weather Outlook' => 57,
		'Heat Advisory' => 52,
		'Heavy Freezing Spray Warning' => 11,
		'Heavy Freezing Spray Watch' => 33,
		'High Surf Advisory' => 55,
		'High Surf Warning' => 33,
		'High Wind Warning' => 9,
		'High Wind Watch' => 31,
		'Hurricane Force Wind Warning' => 4,
		'Hurricane Force Wind Watch' => 4,
		'Hurricane Local Statement' => 26,
		'Hurricane Warning' => 4,
		'Hurricane Watch' => 26,
		'Hydrologic Advisory' => 49,
		'Hydrologic Outlook' => 49,
		'Ice Storm Warning' => 12,
		'Lake Effect Snow Advisory' => 56,
		'Lake Effect Snow Warning' => 12,
		'Lake Effect Snow Watch' => 34,
		'Lake Wind Advisory' => 29, #blank
		'Lakeshore Flood Advisory' => 55,
		'Lakeshore Flood Statement' => 55,
		'Lakeshore Flood Warning' => 11,
		'Lakeshore Flood Watch' => 33,
		'Law Enforcement Warning' => 35,
		'Local Area Emergency' => 35,
		'Low Water Advisory' => 57,
		'Marine Weather Statement' => 29,
		'Nuclear Power Plant Warning' => 13,
		'Radiological Hazard Warning' => 13,
		'Red Flag Warning' => 17,
		'Rip Current Statement' => 57,
		'Severe Thunderstorm Warning' => 2,
		'Severe Thunderstorm Watch' => 24,
		'Severe Weather Statement' => 46,
		'Shelter In Place Warning' => 13,
		'Short Term Forecast' => 57,
		'Small Craft Advisory' => 51,
		'Small Craft Advisory For Hazardous Seas' => 29,
		'Small Craft Advisory For Rough Bar' => 29,
		'Small Craft Advisory For Winds' => 29,
		'Small Stream Flood Advisory' => 49,
		'Snow Squall Warning' => 12,
		'Special Marine Warning' => 7,
		'Special Weather Statement' => 57,
		'Storm Surge Warning' => 11,
		'Storm Surge Watch' => 33,
		'Storm Warning' => 14,
		'Storm Watch' => 36,
		'Test' => 45, #blank
		'Tornado Warning' => 1,
		'Tornado Watch' => 23,
		'Tropical Depression Local Statement' => 47,
		'Tropical Storm Local Statement' => 47,
		'Tropical Storm Warning' => 3,
		'Tropical Storm Watch' => 25,
		'Tsunami Advisory' => 57,
		'Tsunami Warning' => 13,
		'Tsunami Watch' => 35,
		'Typhoon Local Statement' => 47,
		'Typhoon Warning' => 3,
		'Typhoon Watch' => 25,
		'Urban And Small Stream Flood Advisory' => 49,
		'Volcano Warning' => 13,
		'Wind Advisory' => 31,
		'Wind Chill Advisory' => 18,
		'Wind Chill Warning' => 62,
		'Wind Chill Watch' => 40,
		'Winter Storm Warning' => 12,
		'Winter Storm Watch' => 34, 
		'Winter Weather Advisory' => 56,
	);

  if(isset($iconNumber[$event])) {
		$icon = $iconNumber[$event];
	} else {
		$icon = 45; # return Blank icon
	}
	return($icon);
} # end get_iconnumber

# end of program