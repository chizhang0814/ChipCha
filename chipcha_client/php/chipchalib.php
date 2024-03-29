<?php
/*
 * This is a PHP library that handles calling CHIPCHA.
 *    - Documentation and latest version
 *          http://chipcha.com/plugins/php/
 *    - Get a CHIPCHA API Key
 *          https://www.chipcha.com/chipcha/admin/create
 *    - Discussion group
 *          http://chipcha.com/bbs
 *
 * Copyright (c) 2012 CHIPCHA -- http://chipcha.com
 * AUTHORS:
 *   Chi Zhang
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * The CHIPCHA server URL's
 */
define("CHIPCHA_API_SERVER", "http://www.chipcha.com/chipcha/api");
define("CHIPCHA_API_SECURE_SERVER", "https://www.chipcha.com/chipcha/api");
define("CHIPCHA_VERIFY_SERVER", "www.chipcha.com");

/**
 * Encodes the given data into a query string format
 * @param $data - array of string elements to be encoded
 * @return string - encoded request
 */
function _chipcha_qsencode ($data) {
        $req = "";
        foreach ( $data as $key => $value )
                $req .= $key . '=' . urlencode( stripslashes($value) ) . '&';

        // Cut the last '&'
        $req=substr($req,0,strlen($req)-1);
        return $req;
}



/**
 * Submits an HTTP POST to a CHIPCHA server
 * @param string $host
 * @param string $path
 * @param array $data
 * @param int port
 * @return array response
 */
function _chipcha_http_post($host, $path, $data, $port = 80) {

        $req = _chipcha_qsencode ($data);

        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($req) . "\r\n";
        $http_request .= "User-Agent: CHIPCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        $response = '';
        if( false == ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
                die ('Could not open socket');
        }

        fwrite($fs, $http_request);

        while ( !feof($fs) )
                $response .= fgets($fs, 1160); // One TCP-IP packet
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);

        return $response;
}



/**
 * Gets the challenge HTML (javascript and non-javascript version).
 * This is called from the browser, and the resulting CHIPCHA HTML widget
 * is embedded within the HTML form it was called from.
 * @param string $pubkey A public key for CHIPCHA
 * @param string $error The error given by CHIPCHA (optional, default is null)
 * @param boolean $use_ssl Should the request be made over ssl? (optional, default is false)

 * @return string - The HTML to be embedded in the user's form.
 */
function chipcha_get_html ($pubkey, $error = null, $use_ssl = false)
{
	if ($pubkey == null || $pubkey == '') {
		die ("To use CHIPCHA you must get an API key from <a href='https://www.chipcha.com/chipcha/admin/create'>https://www.chipcha.com/chipcha/admin/create</a>");
	}
	
	if ($use_ssl) {
                $server = CHIPCHA_API_SECURE_SERVER;
        } else {
                $server = CHIPCHA_API_SERVER;
        }

        $errorpart = "";
        if ($error) {
           $errorpart = "&amp;error=" . $error;
        }
        return '<script type="text/javascript" src="'. $server . '/challenge?k=' . $pubkey . $errorpart . '"></script>

	<noscript>
  		<iframe src="'. $server . '/noscript?k=' . $pubkey . $errorpart . '" height="300" width="500" frameborder="0"></iframe><br/>
  		<textarea name="chipcha_challenge_field" rows="3" cols="40"></textarea>
  		<input type="hidden" name="chipcha_response_field" value="manual_challenge"/>
	</noscript>';
}




/**
 * A chipchaResponse is returned from chipcha_check_answer()
 */
class chipchaResponse {
        var $is_valid;
        var $error;
}


/**
  * Calls an HTTP POST function to verify if the user's guess was correct
  * @param string $privkey
  * @param string $remoteip
  * @param string $challenge
  * @param string $response
  * @param array $extra_params an array of extra variables to post to the server
  * @return chipchaResponse
  */
function chipcha_check_answer ( $response, $extra_params = array())
{
		
        //discard spam submissions
        if ($response == null || strlen($response) == 0) {
                $chipcha_response = new chipchaResponse();
                $chipcha_response->is_valid = false;
                $chipcha_response->error = 'incorrect-captcha-sol';
                return $chipcha_response;
        }

        $response = _chipcha_http_post ("www.chipcha.com", "/checkcode",
                                          array ('response' => $response) + $extra_params
                                          );

        $answers = explode ("\n", $response [1]);
        $chipcha_response = new chipchaResponse();

        if (trim ($answers [0]) == 'true') {
                $chipcha_response->is_valid = true;
        }
        else {
                $chipcha_response->is_valid = false;
                $chipcha_response->error = $answers [1];
        }
        return $chipcha_response;

}

/**
 * gets a URL where the user can sign up for CHIPCHA. If your application
 * has a configuration page where you enter a key, you should provide a link
 * using this function.
 * @param string $domain The domain where the page is hosted
 * @param string $appname The name of your application
 */
function chipcha_get_signup_url ($domain = null, $appname = null) {
	return "https://www.chipcha.com/checkcode.php?"._chipcha_qsencode (array ('domains' => $domain, 'app' => $appname));
}

function _chipcha_aes_pad($val) {
	$block_size = 16;
	$numpad = $block_size - (strlen ($val) % $block_size);
	return str_pad($val, strlen ($val) + $numpad, chr($numpad));
}

/* Mailhide related code */

function _chipcha_aes_encrypt($val,$ky) {
	if (! function_exists ("mcrypt_encrypt")) {
		die ("To use CHIPCHA Mailhide, you need to have the mcrypt php module installed.");
	}
	$mode=MCRYPT_MODE_CBC;   
	$enc=MCRYPT_RIJNDAEL_128;
	$val=_chipcha_aes_pad($val);
	return mcrypt_encrypt($enc, $ky, $val, $mode, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
}


function _chipcha_mailhide_urlbase64 ($x) {
	return strtr(base64_encode ($x), '+/', '-_');
}

/* gets the CHIPCHA Mailhide url for a given email, public key and private key */
function chipcha_mailhide_url($pubkey, $privkey, $email) {
	if ($pubkey == '' || $pubkey == null || $privkey == "" || $privkey == null) {
		die ("To use CHIPCHA Mailhide, you have to sign up for a public and private key, " .
		     "you can do so at <a href='http://www.chipcha.com/chipcha/mailhide/apikey'>http://www.chipcha.com/chipcha/mailhide/apikey</a>");
	}
	

	$ky = pack('H*', $privkey);
	$cryptmail = _chipcha_aes_encrypt ($email, $ky);
	
	return "http://www.chipcha.com/chipcha/mailhide/d?k=" . $pubkey . "&c=" . _chipcha_mailhide_urlbase64 ($cryptmail);
}

/**
 * gets the parts of the email to expose to the user.
 * eg, given johndoe@example,com return ["john", "example.com"].
 * the email is then displayed as john...@example.com
 */
function _chipcha_mailhide_email_parts ($email) {
	$arr = preg_split("/@/", $email );

	if (strlen ($arr[0]) <= 4) {
		$arr[0] = substr ($arr[0], 0, 1);
	} else if (strlen ($arr[0]) <= 6) {
		$arr[0] = substr ($arr[0], 0, 3);
	} else {
		$arr[0] = substr ($arr[0], 0, 4);
	}
	return $arr;
}

/**
 * Gets html to display an email address given a public an private key.
 * to get a key, go to:
 *
 * http://www.chipcha.com/chipcha/mailhide/apikey
 */
function chipcha_mailhide_html($pubkey, $privkey, $email) {
	$emailparts = _chipcha_mailhide_email_parts ($email);
	$url = chipcha_mailhide_url ($pubkey, $privkey, $email);
	
	return htmlentities($emailparts[0]) . "<a href='" . htmlentities ($url) .
		"' onclick=\"window.open('" . htmlentities ($url) . "', '', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=500,height=300'); return false;\" title=\"Reveal this e-mail address\">...</a>@" . htmlentities ($emailparts [1]);

}


?>
