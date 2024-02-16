<?php
#------------------------------------------------------------------------------
# Program: make-NWS-zones-index.php
#
# Purpose: read the shapefiles for NWS Forecast Zones, County Zones, Marine Zones
#          and Fire Zones and create a PHP associative array for data about the<br />
#          zones for easy access of Polygon info for NWS_Land_Alerts.php GRLevel3<br />
#          placefile generator.
#
# Author:  Ken True - webmaster@saratoga-weather.org
#
# Reguires: installation of php-shapefile from https://gasparesganga.com/labs/php-shapefile/
#           unzipped shapefiles from https://www.weather.gov/gis/AWIPSShapefiles placed<br>
#             in ./shapefiles/ subdirecory. the following files are used:
#           Forecast:  https://www.weather.gov/gis/PublicZones
#           County:    https://www.weather.gov/gis/Counties
#           Fire:      https://www.weather.gov/gis/FireZones
#           Marine:    https://www.weather.gov/gis/MarineZones
#
# Program only has to be run when there are updates to the NWS shapefiles.          
#
# Output:    NWS-zones-inc.php script with $NWSzones array
#
# Version 1.00 - 02-Sep-2023 - initial release
# Version 1.01 - 16-Feb-2024 - update for March 5, 2024 shapefiles
#
#------------------------------------------------------------------------------
# Settings:
#
/* 
// March 08, 2023 files
$toDo = array( # list of shapefiles to index
# ID   Array     .shp file location
	'F|fcstZones|./shapefiles/z_08mr23.shp',
	'C|countyZones|./shapefiles/c_08mr23.shp',
	'M|marineZones|./shapefiles/mz08mr23.shp',
	'W|fireZones|./shapefiles/fz08mr23.shp',
);

$outfile = 'NWS-zones-inc-202303-test.txt';
//*/

/*
// September 19 2023 files 
$toDo = array( # list of shapefiles to index
# ID   Array     .shp file location
	'F|fcstZones|./shapefiles/z_19se23.shp',
	'C|countyZones|./shapefiles/c_19se23.shp',
	'M|marineZones|./shapefiles/mz19se23.shp',
	'W|fireZones|./shapefiles/fz19se23.shp',
);

$outfile = 'NWS-zones-inc-202308-test.txt';


//*/
  
// March 5, 2024 files 
$toDo = array( # list of shapefiles to index
# ID   Array     .shp file location
	'F|fcstZones|./shapefiles/z_05mr24.shp',
	'C|countyZones|./shapefiles/c_05mr24.shp',
	'M|marineZones|./shapefiles/mz05mr24.shp',
	'W|fireZones|./shapefiles/fz05mr24.shp',
);

$outfile = 'NWS-zones-inc-202403-test.txt';
//*/

#
# end of settings
#------------------------------------------------------------------------------
$Version = "make-NWS-zones-index.php - V1.01 - 16-Feb-2024";

header('Content-type: text/plain;charset=ISO-8859-1');
include_once('NWStimeZones.php');

// Register autoloader
require_once('php-shapefile/src/Shapefile/ShapefileAutoloader.php');
Shapefile\ShapefileAutoloader::register();

// Import classes
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader;

date_default_timezone_set('America/Los_Angeles');

global $output,$NWStimeZones;

print "$Version\n";
print "Run on ".date("r")."\n";
print "Output file: $outfile\n\n";

$output = "<?php\n";
$output .= "# combined Forecast, County, Marine and Fire zones index lookup\n";
$output .= "# for use by NWS_Land_Alerts.php placefile generator\n";
$output .= "# Author: Ken True - webmaster@saratoga-weather.org\n";
$output .= "# generated ".date("r")."\n";
$output .= "# by $Version\n";
$output .= "#\n";

$zoneLookup = array();

foreach ($toDo as $k => $what) {
	list($ID,$varname,$DB) = explode('|',$what);

	$zoneLookup['_'.$ID] = $DB;
	
	print "..loading $varname from $DB\n";
	$DBupdated = date("r",filemtime($DB));

	${$varname} = get_shapefile_data($ID,$varname,$DB);
	
	$recs = 0;
	$duplicate = 0;
	foreach (${$varname} as $key => $data) {
		if(!isset($zoneLookup[$key]) ) {
			$zoneLookup[$key] = $ID.'|'.$data;
			$recs++;
		} else {
			$zoneLookup[$key] = $zoneLookup[$key]."\t".$ID.'|'.$data;
			$recs++;
			$duplicate++;
		}
	}
	unset(${$varname});
	print ".. $recs $ID zones added with $duplicate duplicate zones appended.\n\n";
	$output .= "# created from $DB updated $DBupdated.\n";
	$output .= "# use key '_$ID' for $DB lookups\n";
	$output .= "# $recs $ID zones added with $duplicate duplicate zones appended.\n\n";
}

ksort($zoneLookup);

print ".. Done. ".count($zoneLookup)." entries saved to $outfile\n";

$output .= "# '<zone>' => '<DB>|<index>|<name>|<lat>|<long>[|<TZcode>]',\n";
$output .= "\$zoneLookup = ";

file_put_contents($outfile,$output.var_export($zoneLookup,true).";\n# end of output\n");

#------------------------------------------------------------------------------

function get_shapefile_data($ID,$varname,$DB) {
	global $output,$NWStimeZones;
	
	$DATA = array(); 

  try {
    // Open Shapefile
    $Shapefile = new ShapefileReader($DB);
		echo "-------------------------------------------------\n";
		echo "Shapefile: $varname in $DB\n\n";
    
    // Get Shape Type
    echo "Shape Type : ";
    echo $Shapefile->getShapeType() . " - " . $Shapefile->getShapeType(Shapefile::FORMAT_STR);
    echo "\n";
    
    // Get number of Records
    echo "Records : ";
    print_r($Shapefile->getTotRecords());
    echo "\n";
     // Get Charset
    echo "Charset : ";
    print_r($Shapefile->getCharset());
    echo "\n";
   
    // Get Bounding Box
    echo "Bounding Box : ";
    print_r($Shapefile->getBoundingBox());
    echo "\n\n";
    
    // Get PRJ
    echo "Projections : ";
    print_r($Shapefile->getPRJ());
    echo "\n\n";
    
    
    // Get DBF Fields
    echo "DBF Fields : ";
    print json_encode($Shapefile->getFields());
    echo "\n\n";

		$n =0;
		print "Sample records:\n";
		$fields = $Shapefile->getFieldsNames();
		print implode('|',$fields)."\n";
		
		foreach($Shapefile as $index => $Geometry) {
			// Skip the record if marked as "deleted"
			if ($Geometry->isDeleted()) {
					continue;
			}
			$vals = array();
			foreach ($fields as $i => $name) {
				$vals[$name] = $Geometry->getData($name);
			}

			print implode('|',$vals)."\n";
			
			unset($vals);
			$n++;
			if($n > 4) {break;}
		}
		print "\n\n";
		
		$DATA = get_data($Shapefile,$ID);
		
    
  } catch (ShapefileException $e) {
    // Print detailed error information
    echo "Error Type: " . $e->getErrorType()
        . "\nMessage: " . $e->getMessage()
        . "\nDetails: " . $e->getDetails();
		return(array());
  }

	return($DATA);
	
}

#------------------------------------------------------------------------------

function get_data($Shapefile,$ID) {
	global $output,$NWStimeZones;
	$data = array();
	
	if($ID == 'F') { #Forecast Zone Data
#-------#
		$fields = $Shapefile->getFieldsNames();
		/*
		fields:
		array (
			0 => 'STATE',
			1 => 'CWA',
			2 => 'TIME_ZONE',
			3 => 'FE_AREA',
			4 => 'ZONE',
			5 => 'NAME',
			6 => 'STATE_ZONE',
			7 => 'LON',
			8 => 'LAT',
			9 => 'SHORTNAME',
		)
		idx=1: AL,BMX,C,ec,019,Calhoun,AL019,-8.58261000000e+01,3.37714000000e+01,Calhoun
		*/
		$i = 0;
		$fcstZones = array();
		$timeZones = array();
		foreach($Shapefile as $idx => $Geometry) {
				// Skip the record if marked as "deleted"
				if ($Geometry->isDeleted()) {
						continue;
				}
				$vals = array();
				foreach ($fields as $i => $name) {
					$vals[$name] = $Geometry->getData($name);
				}
				$zone = $vals['STATE'].'Z'.$vals['ZONE'];
				$clat = sprintf("%01.4f",$vals['LAT']);
				$clon = sprintf("%01.4f",$vals['LON']);
				
				$fcstZones[$zone] = implode('|',array($idx,$vals['NAME'],$clat,$clon,$vals['TIME_ZONE']));
				
				if(isset($timeZones[$vals['TIME_ZONE']])) {
					$timeZones[$vals['TIME_ZONE']]++;
				} else {
					$timeZones[$vals['TIME_ZONE']] = 1;
				}
				
				unset($vals);
		}
		ksort($fcstZones);
		ksort($timeZones);
		print "..timezones found/counts:\n";
		print " Code\tCount   as\n";
		foreach ($timeZones as $abbrev => $count) {
			if(isset($NWStimeZones[$abbrev])) {
				print " '$abbrev'\t$count\tas ".$NWStimeZones[$abbrev]."\n";
			} else {
				print " '$abbrev'\t$count\tnot defined in \$NWStimeZones\n";
			}
		}
		print "  ".count($timeZones)." codes found.\n\n";
		return($fcstZones);	
#-------#	
	} # end Forecast Zone data
	
	if($ID == 'C') { #County Zone Data
#-------#	
		$fields = $Shapefile->getFieldsNames();
		/*
		array (
			0 => 'STATE',
			1 => 'CWA',
			2 => 'COUNTYNAME',
			3 => 'FIPS',
			4 => 'TIME_ZONE',
			5 => 'FE_AREA',
			6 => 'LON',
			7 => 'LAT',
		)
		idx=1: ME,CAR,Washington,23029,E,se,-67.63610000000,45.03630000000
		idx=2: GA,CHS,McIntosh,13191,E,se,-81.26460000000,31.53290000000
		idx=3: GA,CHS,Liberty,13179,E,se,-81.21030000000,31.70930000000
		*/
		$i = 0;
    $fcstZones = array();
		$timeZones = array();
		
	  foreach($Shapefile as $idx => $Geometry) {
			// Skip the record if marked as "deleted"
			if ($Geometry->isDeleted()) {
					continue;
			}
			$vals = array();
			foreach ($fields as $i => $name) {
				$vals[$name] = $Geometry->getData($name);
			}
			$zone   = $vals['STATE'].'C'.substr($vals['FIPS'],2,3);
			$clat = sprintf("%01.4f",$vals['LAT']);
			$clon = sprintf("%01.4f",$vals['LON']);
			if(!isset($fcstZones[$zone])) {
				$fcstZones[$zone] = implode('|',array($idx,$vals['COUNTYNAME'].', '.$vals['STATE'],$clat,$clon,$vals['TIME_ZONE']));
			} else {
				$fcstZones[$zone] .= "\t".implode('|',array($idx,$vals['COUNTYNAME'].', '.$vals['STATE'],$clat,$clon,$vals['TIME_ZONE']));
				print ".. $zone added ".$fcstZones[$zone]."\n";
			}
			if(isset($timeZones[$vals['TIME_ZONE']])) {
				$timeZones[$vals['TIME_ZONE']]++;
			} else {
				$timeZones[$vals['TIME_ZONE']] = 1;
			}
	
			unset($vals);
    }

		ksort($fcstZones);
		ksort($timeZones);
		print "..timezones found/counts:\n";
		print " Code\tCount   as\n";
		foreach ($timeZones as $abbrev => $count) {
			if(isset($NWStimeZones[$abbrev])) {
				print " '$abbrev'\t$count\tas ".$NWStimeZones[$abbrev]."\n";
			} else {
				print " '$abbrev'\t$count\tnot defined in \$NWStimeZones\n";
			}
		}
		print "  ".count($timeZones)." codes found.\n\n";
		return($fcstZones);
#-------#	
	} # end County Zones

	if($ID == 'W') { #Fire Zone Data
#-------#	
		$fields = $Shapefile->getFieldsNames();
		/*
		fields:
		array (
			0 => 'STATE',
			1 => 'CWA',
			2 => 'TIME_ZONE',
			3 => 'FE_AREA',
			4 => 'ZONE',
			5 => 'NAME',
			6 => 'STATE_ZONE',
			7 => 'LON',
			8 => 'LAT',
			9 => 'SHORTNAME',
		)
		idx=1: AL,BMX,C,ec,019,Calhoun,AL019,-8.58261000000e+01,3.37714000000e+01,Calhoun
		*/
		$i = 0;
    $fcstZones = array();
		$timeZones = array();
		
	  foreach($Shapefile as $idx => $Geometry) {
			// Skip the record if marked as "deleted"
			if ($Geometry->isDeleted()) {
					continue;
			}
			$vals = array();
			foreach ($fields as $i => $name) {
				$vals[$name] = $Geometry->getData($name);
			}
			$zone = $vals['STATE'].'Z'.$vals['ZONE'];
			$clat = sprintf("%01.4f",$vals['LAT']);
			$clon = sprintf("%01.4f",$vals['LON']);
			if(substr($vals['TIME_ZONE'],0,1) < 'A') { # fix one bad TZ of 'M'
			  print ".. fixed $zone TZ '".$vals['TIME_ZONE']."' to be ";
				$vals['TIME_ZONE'] = substr($vals['TIME_ZONE'],1); 
				print "'".$vals['TIME_ZONE']."' \n";
			}
			
			$fcstZones[$zone] = implode('|',array($idx,$vals['NAME'],$clat,$clon,$vals['TIME_ZONE']));
			#print "idx=$idx: ".implode(',',$vals)."\n";
			if(isset($timeZones[$vals['TIME_ZONE']])) {
				$timeZones[$vals['TIME_ZONE']]++;
			} else {
				$timeZones[$vals['TIME_ZONE']] = 1;
			}
			
			unset($vals);

    }

		ksort($fcstZones);
		ksort($timeZones);
		print "..timezones found/counts:\n";
		print " Code\tCount   as\n";
		foreach ($timeZones as $abbrev => $count) {
			if(isset($NWStimeZones[$abbrev])) {
				print " '$abbrev'\t$count\tas ".$NWStimeZones[$abbrev]."\n";
			} else {
				print " '$abbrev'\t$count\tnot defined in \$NWStimeZones\n";
			}
		}
		print "  ".count($timeZones)." codes found.\n\n";
    return($fcstZones);
#-------#	
	}

	if($ID == 'M') { #Marine Zone Data
#-------#	
		$fields = $Shapefile->getFieldsNames();
		/*
		array (
			0 => 'ID',
			1 => 'WFO',
			2 => 'GL_WFO',
			3 => 'NAME',
			4 => 'LON',
			5 => 'LAT',
		)
		idx=1: PHZ113,HFO,,Kauai Channel,-158.97240000000,21.61690000000
		idx=2: PHZ112,HFO,,Kauai Leeward Waters,-160.23140000000,21.66410000000
		idx=3: GMZ155,BRO,,Coastal waters from Baffin Bay to Port Mansfield TX out 20 NM,-97.15790000000,26.90680000000
		*/
		$i = 0;
    $fcstZones = array();
		
	  foreach($Shapefile as $idx => $Geometry) {
			// Skip the record if marked as "deleted"
			if ($Geometry->isDeleted()) {
					continue;
			}
			$vals = array();
			foreach ($fields as $i => $name) {
				$vals[$name] = $Geometry->getData($name);
			}
			$zone = $vals['ID'];
			$clat = sprintf("%01.4f",$vals['LAT']);
			$clon = sprintf("%01.4f",$vals['LON']);
			
			if(!isset($fcstZones[$zone])) {
				$fcstZones[$zone] = implode('|',array($idx,$vals['NAME'],$clat,$clon,''));
			} else {
				$fcstZones[$zone] .= "\t".implode('|',array($idx,$vals['NAME'],$clat,$clon,''));
				print ".. $zone added ".$fcstZones[$zone]."\n";
			}
			
			unset($vals);
    }
		ksort($fcstZones);
		return($fcstZones);
#-------#	
	} # end Marine Zone
	
	return($data);
}
# end of program