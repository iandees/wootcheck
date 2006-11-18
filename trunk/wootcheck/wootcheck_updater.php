<?php
/**
 * Config stuff:
 */
// The MySQL database host
$db_hostname = "localhost";
// The MySQL database user
$db_user     = "user";
// The MySQL password
$db_password = "password";
// The MySQL database
$db_database = "database";

// Woot.com's IP address:
$woot_ip = "66.201.98.200";

$refresh=30;
if(isset($_GET['refresh']))
	$refresh=$_GET['refresh'];
if($refresh<10)
	$refresh=10;

$ch = curl_init();
$timeout = 10; // set to zero for no timeout
curl_setopt ($ch, CURLOPT_URL, 'http://'.$woot_ip.'/');
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);
$file = curl_exec($ch);
curl_close($ch);

$message1="";
if(strlen($file)==0)
{
	$refresh=15;
	$contents=file_get_contents("status.inc");
	if(strlen($message1)<25) {
		$message1=$message1." Last Update Failed";
		$refresh = 3;
	}
}
else
{
	$value=0;
	$product="";
	$picURL="";
	$wantOneURL="0";

	
	for($i=0;$i<strlen($file)-10;$i++)
	{
		if(substr($file,$i,19)=="bar\" style='width: ")
		{
			$tfile=substr($file,$i+19);
			for($k=0;$k<strlen($tfile);$k++)
				if(substr($tfile,$k,3)=="'><")
				{
					$value=substr($tfile,0,$k);	
					$k = strlen($tfile);
				}
		}

		if(substr($file,$i,14)=="_TitleHeader\">" && $product == "")
		{
			$sfile=substr($file,$i+14);
			for($l=0;$l<strlen($sfile);$l++)
			{
				if(substr($sfile,$l,5)=="</h3>" && $product == "")
				{
					$product=substr($sfile,0,$l);
					$l = strlen($sfile);
				}
			}
		}

		if(substr($file,$i,24)=="onclick=\"popImage(&quot;" && $picURL =="")
		{
			$rfile=substr($file,$i+24);
			for($m=0;$m<strlen($rfile);$m++)
			{
				if(substr($rfile,$m,7)=="&quot;," && $picURL == "")
				{
					$picURL=substr($rfile,0,$m);
					$m = strlen($rfile);
				}
			}
		}

		if(substr($file,$i,20)=="\" class=\"one\" href=\"" && $wantOneURL =="0")
        {
            $rfile=substr($file,$i+20);
            for($m=0;$m<strlen($rfile);$m++)
            {
                if(substr($rfile,$m,3)=="\">I" && $wantOneURL == "0")
                {
                    $wantOneURL=substr($rfile,0,$m);
					list($goop,$prodID)=split("=",$wantOneURL);
                    $m = strlen($rfile);
                }
            }
        }

		if(substr($file,$i,12)=="\"PriceSpan\">" && $price == "")
		{
			$rfile=substr($file,$i+12);
			for($m=0;$m<strlen($rfile);$m++)
			{
				if(substr($rfile,$m,7)=="</span>" && $price == "")
				{
					$price = substr($rfile,0,$m);
					$m = strlen($rfile);
				}
			}
		}
	}

	if($value > 83) {
	print "Saving woot.jpg";
	$picURL = str_replace('www.woot.com', '$woot_ip', $picURL);

	// Fetch the picture
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $picURL);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);
	$file = curl_exec($ch);
	curl_close($ch);
	$filename = 'woot.jpg';
	if (!$handle = fopen($filename, 'w')) {
	    echo "Cannot open file ($filename)";
	}
	fwrite($handle, $file);

	fclose($handle);
	} else {
		print "Not saving woot.jpg this time.";
	}

	if($value==0) {
		$message1="Item sold out or woot lights went out";
		$refresh = 10;
	}
	else
	{	
		$numerical = substr($value, 0, -1);
		$message1 = "$value left.";
		if($refresh != 3) {
			if($numerical < 10) {
				$refresh = 15;
			} else if($numerical > 50) {
				$refresh = 60;
			} else {
				$refresh = 30;
			}
		}
	}
}

$con = mysql_pconnect($db_hostname, $db_user, $db_password);
$db = mysql_select_db($db_database, $con);
// Compute the rate
if($prodID) {
	$historicalInfo = array();
	$rateQS = "SELECT DISTINCT(message),lastupdate FROM `wootcheck` WHERE id=$prodID GROUP BY message ORDER BY lastupdate DESC LIMIT 0,3";
	$rateQ = mysql_query($rateQS) or print mysql_error();
	if(mysql_num_rows($rateQ) < 3) {
		$timeLeft = "0";
	} else {
		for($i = 0; $i < 3; $i++) {
			$lin = mysql_fetch_array($rateQ);
			//$historicalInfo[$i] = $lin;
			list($number,$rest) = split("%", $lin['message']);
			$historicalInfo[$i]['left'] = $number;
			$historicalInfo[$i]['timestamp'] = strtotime($lin['lastupdate']);
		}
		$deltaTime = $historicalInfo[0]['timestamp'] - $historicalInfo[2]['timestamp'];
		$deltaPercent = $historicalInfo[0]['left'] - $historicalInfo[2]['left'];
		$rate = $deltaPercent / $deltaTime;
		$percentToGo = $historicalInfo[0]['left'];
		$timeLeft = abs($percentToGo / $rate);
		$timeLeft = floor($timeLeft);
		print "$timeLeft seconds left.";
	}
} else {
	$timeLeft = 0;
}
$product = addslashes($product);
$s = mysql_query("INSERT INTO `wootcheck` (id, message, product, lastupdate, wantone, price, timeleft) VALUES ('$prodID', '$message1', '$product', NOW(), '$wantOneURL', '$price', '$timeLeft')") or print mysql_error();

$filename = 'wootcheck_ajax.html';
$somecontent = $message1 . "||" . stripslashes($product) . "||" . date("D M j G:i:s T Y"). "||" . $wantOneURL . "||" . $price . "||" . $timeLeft;

  if (!$handle = fopen($filename, "w")) {
         echo "Cannot open file ($filename)";
         exit;
   }

 if (fwrite($handle, $somecontent) === FALSE) {
       echo "Cannot write to file ($filename)";
       exit;
   }

   
   //echo 
   
   fclose($handle);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title><?php print $message1; ?></title>
<META HTTP-EQUIV="refresh" CONTENT="<? print $refresh;?>" />
<style type="text/css" media="screen">
<!--
* {margin:0;padding:0;}
body {
	background: #FFFFFF;
	font: 16px "Lucida Grande","Trebuchet MS",   Arial, Helvetica, sans-serif;
	padding:20px;
}

p {
	padding:5px;
	font-family:georgia, verdana, arial, sans-serif;
	line-height: 22px; 
	letter-spacing:1px;
}
p.sub {
	font-size:14px;
	line-height: 20px;
	margin-top:10px;
	border-top:1px solid #e9e9e9;
	width:90%;
}


a {color: #CC0000;}
a:hover {color:#0066CC; text-decoration:none;}

h1,h2{padding-bottom:2px;margin-bottom:5px;}
h1{border-bottom:2px solid #FF9933;width:90%;color:#336666;}
h2{color:#CC0000;}
-->
</style>
</head>
<body>
<h1>Woot-off Checker</h1>
<h2><?php print $message1; ?></h2>
<h3><?print "Success, wrote ($somecontent) to file ($filename)"; ?></h3>
<!--<script src="http://www.woot.com/lilwoot.ashx"></script>-->
<p>Product is <? echo $product; ?></p>
<p>This page may take a while to load depending on woot, refreshes every <? print $refresh; ?> seconds.</p>
<p>This page updates the local server copy of the cache.</p>
<p></p>
<p class="sub"><a href="http://www.woot.com/" target="_blank">http://www.woot.com/</a> -
  Modified code from <a href="http://black2d.com/" target="_blank">http://black2d.com/</a>. Modified by <a href="http://www.outsideofdreams.com/" target="_blank">Ekimus</a>. <a href="woot_checker_source.txt" target="_blank">Download
    the source</a></p>
	</body>
	</html>
