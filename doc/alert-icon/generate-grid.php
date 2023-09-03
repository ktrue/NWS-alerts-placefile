<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Untitled Document</title>
</head>
<body style"font-family: Arial,Helvetica,Sans Serif;background-color: white;">
<?php
$targetW = intval(16/3);
$targetH = intval(16/3);
#$targetH = 40;
$outfile = 'alerts-grid.png';


$offsetH = 0; # pixels to extend down to separate rows
$targetW = 16;
$targetH = 16;

$numRow = 3;
$numCol = 22;

$IMG = imagecreatetruecolor( ($numCol*$targetW)+$numCol+1,$numRow*($targetH+$offsetH)+$numRow+1);

$black = imagecolorallocate($IMG,0,0,0);
$white = imagecolorallocate($IMG,255,255,255);
$yellow = imagecolorallocate($IMG,224,211,0);
$red   = imagecolorallocate($IMG,255,0,0);
$magenta = imagecolorallocate($IMG,255,0,244);

imagefill($IMG,0,0,$white);

#header('Content-type: text/plain,charset=ISO-8859-1');
print "<pre>\n";
$iconNumb = 1;
for ($nRow=0;$nRow<$numRow;$nRow++) {
	
	print "Row $nRow: ";
	
	for ($nCol=0;$nCol<$numCol;$nCol++) {
		
		$topX = ($nCol*$targetW)+$nCol;
		$topY = ($nRow*($targetH+$offsetH))+$nRow;
		imagerectangle($IMG,$topX,$topY,$topX+$targetW,$topY+$targetH,$red);
		

		$iconNumb++;
	}
	print "<br/>\n";
	
}
print "</pre>\n";

imagepng($IMG,$outfile);

$imgW= imagesx($IMG);
$imgH= imagesy($IMG);

imagedestroy($IMG);

print "<p>..Done. $outfile full image is {$imgW}x{$imgH}. icons are {$targetW}x{$targetH}.<br/></p>\n";
print "<img src=\"$outfile\"/>\n";

?>
</body>
</html>