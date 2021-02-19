#!/usr/bin/php -q

<?php

require_once('nokogiri.php');
require('/var/lib/asterisk/agi-bin/phpagi.php'); 
$agi = new AGI();

$dstchannel = $agi->request['agi_arg_3']; //${CDR(dstchannel)} - operator number from the module follow me where the call was made 
$dialstatus = $agi->request['agi_arg_2']; //${DIALSTATUS}  - result of the last Asterisk cmd Dial attempt
$src = $agi->request['agi_callerid']; //${CALLERID(num)} - Caller ID Number only
$ext = $agi->request['agi_arg_1']; //${CONNECTEDLINE(num)} - Gets Connected Line data on the channel

$steptime = 30;				// frequency of SMS business cards in days
$stepnotify = 10;			// time in minutes to notify the operator of a missed call
$filecard = '/var/lib/asterisk/agi-bin/sms/card.txt'; // path to the file with SMS business cards 
$filenotify = '/var/lib/asterisk/agi-bin/sms/notify.txt'; // path to the file with messages notifications to the operator
$fileanswered = '/var/lib/asterisk/agi-bin/sms/answered.txt'; // path to file with sms received call
$url = 'https://myserver/goip/en/dosend.php?USERNAME=smsuser&PASSWORD=password&smsprovider=1&method=2'; //sms gate url
$log = '/var/log/asterisk/sms';		// log

$curdate = time() + 120;				//current time 
$stepdate = $curdate - $steptime*60*60*24;	//calculate time shift
$fcurdate = date("Y-m-d H:i:s", $curdate);	//formatting the current time
$fstepdate = date("Y-m-d H:i:s", $stepdate);	//formatting time with shift

preg_match('~\/(.*?)@~', $dstchannel, $dstchannel);	// select the operator's mobile number
echo $dialstatus . "\t" . $dstchannel[1] . "\n";

//first scenario - send an SMS business card to the client's mobile number after the call
if(preg_match('/^79[0-9]{9}/',$src))	{	//check that the number is mobile
	$row = 1;
	$srcnum = 0;
	if (($handle = fopen($log, "r")) !== FALSE) { 	// get a pointer to a file with a log
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {	// read the log file line by line
			if (($data[2] == $src) &&			//if the number is in the log
			($data[1] >= $stepdate) &&			// and the SMS was sent later with a shift
			($data[4] == "card") &&				// a business card was sent
			($data[5] == "")) { 	// and the dispatch was successful
				echo "SMS has been sent\n";
				$srcnum=0;
				break;					// then the log reading ends
			} else {					// if the number is not found in the log
//				echo "SMS not sent";
				$srcnum = $src;			// then remember it
			}	
			$row++;
		}
	}
	if($srcnum) {							// if SMS need to send
		$lines = file($filecard);		//read a file with sms business cards
		foreach ($lines as $line_num => $line) {//read line by line
			$num = substr($line,0,strpos($line,';'));	//select an internal number
			$str = substr($line,strpos($line,';')+1);	//highlight message text
			$ustr = urlencode($str);			//convert to code
			$ustr = str_replace('%5Cn','%0D%0A',$ustr);	// fix line breaks
			if($num == $ext) {		//if extension number found
				$surl = $url . "&smsnum=%2B" . $srcnum . "&Memo=" . $ustr;
				echo $surl . "\n";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $surl);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
//				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$result=0;
				$result = curl_exec($ch);
//				var_dump($result);
				if($result) {
					$html = new nokogiri($result);
					$answer = $html->get('td[colspan="3"]')->toArray();
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";card;" . trim($answer[0]["#text"][0]) . ";\n";
					file_put_contents($log, $logmessage, FILE_APPEND);
				} else {
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";card;;\n";
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

//second scenario - sending SMS notification to the operator
if ($dialstatus != "ANSWER"){
	$stepdate = $curdate - $stepnotify*60;	//calculate time shift
	$row = 1;
	$srcnum = 0;
	if (($handle = fopen($log, "r")) !== FALSE) { 	// get a pointer to a file with a log
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {	// read the log file line by line
			if (($data[2] == $src) &&			//if the number is in the log
			($data[1] >= $stepdate) &&			// and the SMS was sent later with a shift
			($data[4] == "notify") &&				// notification was sent
			($data[5] == "")) { 	// and the dispatch was successful
				echo "SMS has been sent\n";
				$srcnum=0;
				break;					// then the log reading ends
			} else {					// if the number is not found in the log
//				echo "SMS not sent";
				$srcnum = $src;			// then remember it
			}	
			$row++;
		}
	}
	if($srcnum) {							// if SMS need to send
		$lines = file($filenotify);		//read a file with notifications
		foreach ($lines as $line_num => $line) {//read line by line
			$num = substr($line,0,strpos($line,';'));	//select an internal number
			$str = substr($line,strpos($line,';')+1);	//highlight message text
			$ustr = urlencode($str);			// convert to code
			$ustr = str_replace('%5Cn','%0D%0A',$ustr);	// fix line breaks
			if($num == $ext) {		//if the number is in the log
				$surl = $url . "&smsnum=%2B" . $dstchannel[1] . "&Memo=" . $ustr . "%20%2B" . $src;
				echo $surl . "\n";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $surl);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
//				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$result=0;
				$result = curl_exec($ch);
//				var_dump($result);
				if($result) {
					$html = new nokogiri($result);
					$answer = $html->get('td[colspan="3"]')->toArray();
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";notify;" . trim($answer[0]["#text"][0]) . ";\n";
					file_put_contents($log, $logmessage, FILE_APPEND);
				} else {
					$logmessage = $fcurdate . ";" . $curdate . ";" . $srcnum . ";" . $num . ";notify;;\n";
					file_put_contents($log, $logmessage, FILE_APPEND);    
				}
				break;
			}
		}
	}

}

//third scenario - sending the caller's number to the operator
if ($dialstatus == "ANSWER"){
	$lines = file($fileanswered);
	foreach ($lines as $line_num => $line) {
		$num = substr($line,0,strpos($line,';'));
		$str = substr($line,strpos($line,';')+1);
		$ustr = urlencode($str);
		$ustr = str_replace('%5Cn','%0D%0A',$ustr);
		if($num == $ext) {
			$surl = $url . "&smsnum=%2B" . $dstchannel[1] . "&Memo=" . $ustr . "%20%2B" . $src;
			echo $surl . "\n";
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $surl);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
//			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$result=0;
			$result = curl_exec($ch);
			break;
		}
	}
}

?>