This directory must contain the unzipped shapefiles from https://www.weather.gov/gis/AWIPSShapefiles

Requires unzipped shapefiles from https://www.weather.gov/gis/AWIPSShapefiles placed
in THIS subdirecory (./shapefiles/). 

  Forecast:  https://www.weather.gov/gis/PublicZones  (z_19se23.zip was used)
  County:    https://www.weather.gov/gis/Counties     (c_19se23.zip was used)
  Fire:      https://www.weather.gov/gis/FireZones    (fz19se23.zip was used)
  Marine:    https://www.weather.gov/gis/MarineZones  (mz19se23.zip was used)

The initial distribution uses the September 19, 2023 dated shapefiles (as show above).

Configure make-NWS-zones-index.php area:

//*  
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

with the new names for each of the zone files and the $outfile name, then run make-NWS-zones-index.php
from the home directory and copy the $outfile name over NWS-zones-inc.txt in the home directory.

You will not need to run make-NWS-zones-index.php unless the NWS releases new shapefile changes.
