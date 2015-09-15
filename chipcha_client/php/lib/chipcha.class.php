<?php
/**************************************************************************
 **
 **	 # Chipcha Client Class
 **
 *		Version: 1.0
 *		Language: PHP
 *		CopyRight: Chi Zhang	
 *		Customer Support: +1 469 544 9233
 *		email: handsomechi@gmail.com
 *		release date: 2012-03-13
 *		find more: http://www.chipcha.com
 **
***************************************************************************/
function chipcha_check_answer ($chipcha_privatekey,$sid, $input)
{
	$config = array(
			"RequestURL"=>'http://www.chipcha.com:80/bxb/server/server2.php',
			"PrivateKey"=>"aaaaaaaaaaaaaaaaaa"
	);
	$client = new Chipcha($config['RequestURL'],$config['PrivateKey']);
	if(($ip = $_SERVER['REMOTE_ADDR'])== NULL){
		$ip = "127.0.0.1";
	}
	
	$new_input = str_replace(" ","",$input);
	//echo $new_input;
	//$encoding_list = array("GB2312", "GBK", "BIG5", "UTF-8");
	$encoding  =  mb_detect_encoding($new_input,array('ASCII','GB2312','GBK','BIG5','UTF-8'));
	$len = strlen($new_input);
	//echo $encoding."<br>".$len;
	//$utf_input = mb_convert_encoding($new_input, 'UTF-8', 'gb2312');
	if(($encoding != NULL )&&($encoding != 'UTF-8')){
		$utf_input = mb_convert_encoding($new_input, 'UTF-8', $encoding);
		$encoding  =  mb_detect_encoding($utf_input);
		$len = strlen($utf_input);
		//echo $encoding."<br>".$len;
	}
	else{
		$utf_input = $new_input;
	}
	//$utf_input =  $new_input;
	$response = $client->Verify($ip,$utf_input,$sid);
	//echo $ip."<br>";

	$chipcha_Response = new chipchaResponse();
	
	if ($response) {
		$chipcha_Response->is_valid = true;
	}
	else {
		$chipcha_Response->is_valid = false;
		$chipcha_Response->error = "not valid input";
	}
		
	return $chipcha_Response;
}

class chipchaResponse {
	var $is_valid = false;
	var $error;
}
class Chipcha{
	var $Address;
	var $Server;
	var $Port;
	var $PrivateKey;
	var $RequestURL;
	
	function Chipcha($RequestURL, $PrivateKey=""){
		if($PrivateKey == "" || $RequestURL == ""){
			return "no initial";
		}
		$this->GetServerInfoByURL($RequestURL);
		$this->PrivateKey = $PrivateKey;
		$this->RequestURL = $this->GetVerifyShortURL($RequestURL);
		$this->Connect();
	}
	
	function Connect(){
		return $this->Server = fsockopen($this->Address, $this->Port, $ErrorNo, $ErrorString, 5);
	}
	
	function Disconnect(){
		fclose($this->Server);
	}
	
	public function Verify($IP, $AuthCode, $Sid=""){
		if($IP == ""){
			return "no ip";
		}
		if($AuthCode == ""){
			return "no input";
		}
	
		
		$Content = "&ip=".$IP."&c=".$AuthCode."&k=".$this->PrivateKey."&sid=".$Sid;
	
		$Out = "POST ".$this->RequestURL." HTTP/1.1\r\nHost: ".$this->Address."\r\nUser-Agent: Hinside\r\nReferer: http://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]."\r\nContent-Type: application/x-www-form-urlencoded;\r\nContent-Length: ".strlen($Content)."\r\nConnection: close\r\n\r\n".$Content;
		fputs($this->Server, $Out);
		$ReturnHeaders = "";
		while($str = trim(fgets($this->Server, 1024))){
			$ReturnHeaders .= $str."\r\n";
		}
		$ReturnBody = "";
		while(!feof($this->Server)){
			$ReturnBody .= fgets($this->Server, 1024);
		}
		$this->Disconnect();
		return $ReturnBody;
	}
	
	public function GetServerInfoByURL($URLString=""){
		if($URLString == ""){
			return -1;
		}
		
		$URLArr = parse_url($URLString);
		if(isset($URLArr['host'])){
			$this->Address = $URLArr['host'];
		}
		else{
			$this->Address = "127.0.0.1";
		}
		if(isset($URLArr['port'])){
			$this->Port = $URLArr['port'];
		}
		else{
			$this->Port = 80;
		}
	}
	
	public function GetVerifyShortURL($URLString=""){
		if($URLString == ""){
			return -1;
		}
		
		$URLArr = parse_url($URLString);
		$ShortURL = "";
		if(isset($URLArr['path'])){
			$ShortURL .= $URLArr['path'];
		}
		
		if(isset($URLArr['query'])){
			$ShortURL .= "?".$URLArr['query'];
		}
		if($ShortURL == ""){
			$ShortURL = "/";
		}		
		return $ShortURL;
	}
}
?>