<?php

require_once('nokogiri.php');

$hostmysql = '127.0.0.1';	// database address
$database = 'asteriskcdrdb';		// database name
$usermysql = 'user';		// database user
$passmysql = 'password';		// password database user
$steptime = 30;				// time in days for the card
$stepnotify = 1;			// time in hours for operator notification
$filecard = '/var/lib/asterisk/bin/sms/card.txt'; // path to the file with business cards
$filenotify = '/var/lib/asterisk/bin/sms/operator.txt'; // path to the file with messages notifications to the operator
$url = 'https://trigger.macrodroid.com/xxxxxxxxx-xxxxxxx-xxxxxx/smsgate'; //sms gateway address
$log = '/var/log/sms';		// logfile


// retrieving data from the database
$curdate = time() + 120;				//get the current time
$stepdate = $curdate - $steptime*60*60*24;	//calculate the time shift
$fcurdate = date("Y-m-d H:i:s", $curdate);	//formatting the current time
$fstepdate = date("Y-m-d H:i:s", $stepdate);	//formatting time with a shift
$mysqli = mysqlconnect($hostmysql, $usermysql, $passmysql, $database); // connect to the base
$query = "SELECT dstchannel,disposition FROM cdr WHERE calldate >= '" . $fstepdate .  "' AND calldate <= '" . $fcurdate . "' AND src = " . $argv[1] . " AND dst = " . $argv[2] . ";"; // form a request
echo $query . "\n";
$results = mysqlquery($mysqli, $query);                 // execute the request
while($row = $results->fetch_array())   {               // analyzing the answer
	$dialstatus = $row["disposition"];              // remember the last dialstatus
	$dstchannel = $row["dstchannel"];		// where did the call go
}
if(!$dstchannel){
	$query = "SELECT dstchannel,disposition FROM cdr WHERE calldate >= '" . $fstepdate .  "' AND calldate <= '" . $fcurdate . "' AND cnam = " . $argv[1] . " AND dst = " . $argv[2] . ";"; 
	echo $query . "\n";
	$results = mysqlquery($mysqli, $query);
	while($row = $results->fetch_array())   {
		$dialstatus = $row["disposition"];
		$dstchannel = $row["dstchannel"];
	}
}
mysqlclose($mysqli);					// close the connection
preg_match('~\/(.*?)@~', $dstchannel, $dstchannel);	// select the call direction number
echo $dialstatus . "\t" . $dstchannel[1] . "\n";

//first chain - sending an SMS business card to the caller
if(preg_match('/^79[0-9]{9}/',$argv[1]))	{	//check that the number is mobile
	$row = 1;
	$srcnum = 0;
	if (($handle = fopen($log, "r")) !== FALSE) { 	// get a pointer to a file with a log
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {	// read the log file line by line
//			echo $data[2] . "-" . $argv[1] . "\t" . $data [1] . "-" . $stepdate . "\t" . $data[4] . "\n";
			if (($data[2] == $argv[1]) &&			//if the number is in the log
			($data[1] >= $stepdate) &&			// and the SMS was sent later with a split time
			($data[4] == "card") &&				// a business card was sent
			($data[5] == "")) { 	// and the dispatch was successful
				echo "SMS has been sent\n";
				$srcnum=0;
				break;					// then we finish reading the log
			} else {					// if the number is not found in the log
//				echo "SMS was not sent";
				$srcnum = $argv[1];			// then we remember it
			}	
			$row++;
		}
	}
	if($srcnum) {							// if you need to send an SMS
		$lines = file($filecard);		//read the file with the messages "Thank you for the call"
		foreach ($lines as $line_num => $line) {//we read line by line
			$num = substr($line,0,strpos($line,';'));	//select an internal number
			$str = substr($line,strpos($line,';')+1);	//highlight message text
			$ustr = urlencode($str);			// convert to code
			$ustr = str_replace('%5Cn','%0D%0A',$ustr);	// fix line breaks
			if($num == $argv[2]) {		//if extension number found
				$surl = $url . "?smsto=%2B" . $srcnum . "&smsbody=" . $ustr . "&smstype=sms";
				echo $surl . "\n";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $surl);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$result=0;
				$result = curl_exec($ch);
//				var_dump($result);
				if($result) {
					$html = new nokogiri($result);
					$answer = $html->get('td[colspan="3"]')->toArray();
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";card;" . trim($answer[0]["#text"][0]) . ";\n";
					file_put_contents($log, $logmessage, FILE_APPEND);
				} else {
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";card;SMS gate not answer;\n";
					file_put_contents($log, $logmessage, FILE_APPEND);    
				}
				break;
			}
		}
	}
}
else	{
	echo "not mobile";
}

//the second chain - sending a notification to the operator
if ($dialstatus != "ANSWERED"){
	$stepdate = $curdate - $stepnotify*60*60;	//calculate time shift
	$row = 1;
	$srcnum = 0;
	if (($handle = fopen($log, "r")) !== FALSE) { 	// get a pointer to a file with a log
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {	// read the log file line by line
//			echo $data[2] . "-" . $argv[1] . "\t" . $data [1] . "-" . $stepdate . "\t" . $data[4] . "\n";
			if (($data[2] == $argv[1]) &&			//if the number is in the log
			($data[1] >= $stepdate) &&			// and the SMS was sent later with a shift
			($data[4] == "notify") &&				// a business card was sent
			($data[5] == "")) { 	// and the dispatch was successful
				echo "SMS has been sent\n";
				$srcnum=0;
				break;					// then the log reading ends
			} else {					// if the number is not found in the log
//				echo "SMS not sent";
				$srcnum = $argv[1];			// then we remember it
			}	
			$row++;
		}
	}
	if($srcnum) {							// if you need to send an SMS
		$lines = file($filenotify);		//read the file with messages to the operator
		foreach ($lines as $line_num => $line) {//we read line by line
			$num = substr($line,0,strpos($line,';'));	//select an internal number
			$str = substr($line,strpos($line,';')+1);	//highlight message text
			$ustr = urlencode($str);			// convert to code
			$ustr = str_replace('%5Cn','%0D%0A',$ustr);	// fix line breaks
			if($num == $argv[2]) {		//if extension number found
				$surl = $url . "?smsto=%2B" . $dstchannel[1] . "&smsbody=" . $ustr . "%20%2B" . $argv[1] . "&smstype=sms";
				echo $surl . "\n";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $surl);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$result=0;
				$result = curl_exec($ch);
//				var_dump($result);
				if($result) {
					$html = new nokogiri($result);
					$answer = $html->get('td[colspan="3"]')->toArray();
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";notify;" . trim($answer[0]["#text"][0]) . ";\n";
					file_put_contents($log, $logmessage, FILE_APPEND);
				} else {
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";notify;SMS gate not answer;\n";
					file_put_contents($log, $logmessage, FILE_APPEND);    
				}
				break;
			}
		}
	}

}


function mysqlconnect($host, $user, $password, $database) {
	$mysqli = new mysqli($host, $user, $password, $database);
	if ($mysqli->connect_error) {
		die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	} else {
//		printf("Database %s connected\n", $database);
	}
	if (!$mysqli->set_charset("utf8")) {
//		printf("Error loading character set utf8: %s\n", $mysqli->error);
	} else {
//		printf("Current character set: %s\n", $mysqli->character_set_name());
	}
	return $mysqli;
}

function mysqlquery($mysqli, $query) {
	$results = $mysqli->query($query);
	if($results) {
//		print "The request was successful\n";
	} else {
//		print "Request failed\n";
	}
	return $results;
}

function mysqlclose($mysqli) {
	mysqli_close($mysqli);
//	print "Connection closed\n";
}



?>
