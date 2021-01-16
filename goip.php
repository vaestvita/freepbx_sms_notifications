<?php

require_once('nokogiri.php');

$hostmysql = '127.0.0.1';	// адрес базы
$database = 'asteriskcdrdb';		// имя базы
$usermysql = 'cdruser';		// пользователь базы
$passmysql = 'cdruser';		// пароль
$steptime = 30;				// время в днях для карточки
$stepnotify = 1;			// время в часах для уведомления оператора
$filecard = '/var/lib/asterisk/bin/sms/card.txt'; // путь до фала с сообщениями визитками
$filenotify = '/var/lib/asterisk/bin/sms/operator.txt'; // путь до фала с сообщениями уведомлениями оператору
$url = 'http://127.0.0.1/goip/en/dosend.php?USERNAME=USER&PASSWORD=PASS&smsprovider=1&method=2'; //адрес смс гейта
$log = '/var/log/asterisk/sms';		// лог файл


// получение данных из базы
$curdate = time() + 120;				//получаем текущее время
$stepdate = $curdate - $steptime*60*60*24;	//вычисляем сдвиг по времени
$fcurdate = date("Y-m-d H:i:s", $curdate);	//форматируем текущее время
$fstepdate = date("Y-m-d H:i:s", $stepdate);	//форматируем время со сдвигом
$mysqli = mysqlconnect($hostmysql, $usermysql, $passmysql, $database); // подключаемся к базе
$query = "SELECT dstchannel,disposition FROM cdr WHERE calldate >= '" . $fstepdate .  "' AND calldate <= '" . $fcurdate . "' AND src = " . $argv[1] . " AND dst = " . $argv[2] . ";"; // формируем запрос
echo $query . "\n";
$results = mysqlquery($mysqli, $query);                 // выполняем запрос
while($row = $results->fetch_array())   {               // анализируем ответ
	$dialstatus = $row["disposition"];              // запоминаем последний dialstatus
	$dstchannel = $row["dstchannel"];		// куда ушел вызов
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
mysqlclose($mysqli);					// закрываем соединение
preg_match('~\/(.*?)@~', $dstchannel, $dstchannel);	// выделяем номер направления вызова
echo $dialstatus . "\t" . $dstchannel[1] . "\n";

//первая цепочка - отправка смс-визитки позвонившему
if(preg_match('/^79[0-9]{9}/',$argv[1]))	{	//проверяем что номер мобильный
	$row = 1;
	$srcnum = 0;
	if (($handle = fopen($log, "r")) !== FALSE) { 	// получаем указатель на файл с логом
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {	// построчто читаем фал лога
//			echo $data[2] . "-" . $argv[1] . "\t" . $data [1] . "-" . $stepdate . "\t" . $data[4] . "\n";
			if (($data[2] == $argv[1]) &&			//если номер есть в логе
			($data[1] >= $stepdate) &&			// и смс была отправлена позже времени со сдивигом
			($data[4] == "card") &&				// была отправлена визитка
			($data[5] == "")) { 	// и отправка была успешной
				echo "СМС была отправлена\n";
				$srcnum=0;
				break;					// то заканичваем чтение лога
			} else {					// если номер не встретился в логе
//				echo "СМС не отправлялась";
				$srcnum = $argv[1];			// то запоминаем его
			}	
			$row++;
		}
	}
	if($srcnum) {							// если смс надо отправить
		$lines = file($filecard);		//читаем файл с сообщеиями "Спасибо за звонок"
		foreach ($lines as $line_num => $line) {//читаем построчно
			$num = substr($line,0,strpos($line,';'));	//выделем внутренний номер
			$str = substr($line,strpos($line,';')+1);	//выделяем тескт сообщения
			$ustr = urlencode($str);			// перобразуем с код
			$ustr = str_replace('%5Cn','%0D%0A',$ustr);	// исправляем переводы строки
			if($num == $argv[2]) {		//если внутренни номер найден
				$surl = $url . "&smsnum=%2B" . $srcnum . "&Memo=" . $ustr;
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

//вторая цепочка - отправка уведомления оператору
if ($dialstatus != "ANSWERED"){
	$stepdate = $curdate - $stepnotify*60*60;	//вычисляем сдвиг по времени
	$row = 1;
	$srcnum = 0;
	if (($handle = fopen($log, "r")) !== FALSE) { 	// получаем указатель на файл с логом
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {	// построчто читаем фал лога
//			echo $data[2] . "-" . $argv[1] . "\t" . $data [1] . "-" . $stepdate . "\t" . $data[4] . "\n";
			if (($data[2] == $argv[1]) &&			//если номер есть в логе
			($data[1] >= $stepdate) &&			// и смс была отправлена позже времени со сдивигом
			($data[4] == "notify") &&				// была отправлена визитка
			($data[5] == "")) { 	// и отправка была успешной
				echo "СМС была отправлена\n";
				$srcnum=0;
				break;					// то заканичваем чтение лога
			} else {					// если номер не встретился в логе
//				echo "СМС не отправлялась";
				$srcnum = $argv[1];			// то запоминаем его
			}	
			$row++;
		}
	}
	if($srcnum) {							// если смс надо отправить
		$lines = file($filenotify);		//читаем файл с сообщеиями оператору
		foreach ($lines as $line_num => $line) {//читаем построчно
			$num = substr($line,0,strpos($line,';'));	//выделем внутренний номер
			$str = substr($line,strpos($line,';')+1);	//выделяем тескт сообщения
			$ustr = urlencode($str);			// перобразуем с код
			$ustr = str_replace('%5Cn','%0D%0A',$ustr);	// исправляем переводы строки
			if($num == $argv[2]) {		//если внутренни номер найден
				$surl = $url . "&smsnum=%2B" . $dstchannel[1] . "&Memo=" . $ustr . "%20%2B" . $argv[1];
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
//		printf("Соединение с базой %s установлено\n", $database);
	}
	if (!$mysqli->set_charset("utf8")) {
//		printf("Ошибка при загрузке набора символов utf8: %s\n", $mysqli->error);
	} else {
//		printf("Текущий набор символов: %s\n", $mysqli->character_set_name());
	}
	return $mysqli;
}

function mysqlquery($mysqli, $query) {
	$results = $mysqli->query($query);
	if($results) {
//		print "Выполнение запроса прошло успешно\n";
	} else {
//		print "Запрос не выполнен\n";
	}
	return $results;
}

function mysqlclose($mysqli) {
	mysqli_close($mysqli);
//	print "Соединение закрыто\n";
}



?>

