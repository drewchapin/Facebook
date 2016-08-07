<?php
/**
 * Makeshift API for working with Facebook without using the Graph API. 
 *
 * @author     Drew Chapin <druciferre@gmail.com>
 * @date       August 5th, 2016
 * @copyright  GNU General Public License (GPLv3)
 */
class Facebook
{

	/**
	 * User agent string to send when making requests with cURL
	 */
	const USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0";
	
	/**
	 * Location of cookies file. 
	 */
	private $cookiejar;
	
	/**
	 * Facebook User ID. This is populated when you login().
	 */
	private $fbid;
	
	/**
	 * Initialize the class.
	 * @param $cookiejar  Location of cookies file. Use a different cookiejar for each 
	 *                    account / instance. 
	 */
	function __construct( $cookiejar = null )
	{
		$this->cookiejar = $cookiejar;
	}
	
	/**
	 * Fetch the contents of a page.
	 * @param $url     URL to fetch. 
	 * @param $post    Perform HTTP POST instead of default HTTP GET
	 * @param $fields  Post fields for HTTP POST. This is ignored when $post is false. 
	 */
	private function cURL( $url, $post = false, $fields = null )
	{
		#if( is_array($fields) )
		#{
		#	$tmp = "";
		#	foreach( $fields as $name => $value )
		#	{
		#		$tmp .= strlen($tmp) > 0 ? "&" : "";
		#		$tmp .= $name . "=" . urlencode($value);
		#	}
		#	$fields = $tmp;
		#}
		$ch = curl_init($url);
		if( $post == true )
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			if( is_string($fields) )
				curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
		}
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		if( isset($this->cookiejar) )
		{
			curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookiejar);
			curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookiejar);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT,self::USER_AGENT );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		$body = curl_exec($ch); 
		if( !$body || curl_errno($ch) )
		{
			$error = curl_error($ch);
			curl_close($ch);
			throw new Exception($error);
		}
		$result = array();
		$result["http_code"] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$json = json_decode(substr($body,9),true);	
		$dom = new DOMDocument;
		if( $json != null )
		{
			$result["type"] = "json";
			$result["json"] = $json;
		}
		else if( $dom->loadHTML($body) )
		{
			$result["type"] = "dom";
			$result["dom"] = $dom;
		}	
		else
		{
			$result["type"] = "text";
			$result["text"] = $body;
		}
		curl_close($ch);
		return $result;
	}

	/**
	 * Get form info from HTML response
	 */
	private function getForm( $dom, $form_id )
	{
		#$dom = new DOMDocument;
		#$dom->loadHTML($html);
		$dom_form = $dom->getElementById($form_id);
		if( $dom_form == null )
			return null;
		$form_data = array();
		$form_data["action"] = $dom_form->getAttribute("action");
		$form_data["method"] = $dom_form->getAttribute("post");
		$form_data["inputs"] = array();
		$inputs = $dom_form->getElementsByTagName("input");
		for( $i = 0; $i < $inputs->length; $i++ )
		#foreach( $dom_form->getElementsByTagName("input") as $inputs )
		{
			$name = $inputs->item($i)->getAttribute("name");
			if( isset($name) && !empty($name) )
			{
				#$input = array();
				#$input["name"] = $name;
				#$input["type"] = $inputs->item($i)->getAttribute("type");
				#$input["value"] = $inputs->item($i)->getAttribute("value");
				#$form_data["inputs"][$input["name"]] = $input;
				$form_data["inputs"][$name] = $inputs->item($i)->getAttribute("value");
			}
		}
		return $form_data;
	}
	
	/**
	 * Determine if returned page is logged in.
	 */
	private function loggedin( $dom )
	{
		#$dom = new DOMDocument;
		#$dom->loadHTML($html);
		return $dom->getElementById("logoutMenu") != null;
	}

	/**
	 * Authenticate with Facebook
	 */
	public function login( $username, $password, $remember = false, $code = null )
	{
		$response = $this->cURL("https://www.facebook.com");
		if( isset($response["dom"]) )
		{
			$dom = $response["dom"];
			$dom->saveHTMLFile("login1.html");
			if( !$this->loggedIn($dom) )
			{
				$form = $this->getForm($dom,"login_form");
				$form["inputs"]["email"] = $username;
				$form["inputs"]["pass"] = $password;
				$form["inputs"]["persistent"] = $remember ? "1" : "0";
				$response = $this->cURL($form["action"],true,$form["inputs"]);
				if( isset($response["dom"]) )
				{
					$dom = $response["dom"];
					$dom->saveHTMLFile("login2.html");
					if( $form = $this->getForm($dom,"login_form") )
					{
						throw new Exception("Invalid username or password");
					}
					else if( $form = $this->getForm($dom,"u_0_1") )
					{
						// need code
						var_dump($form);
					}
				}
				else
					throw new Exception("Invalid secondary response from server.");
			}
			else
				return true;
		}
		else
			throw new Exception("Invalid response from server");
		#file_put_contents("login1.html",$response["body"]);
		#$resp = $this->cURL($action,true,$postdata);
		#echo "Resp: " . $resp["http_code"] . PHP_EOL;
		#file_put_contents("login2.html",$resp["body"]);
	}
	
	/**
	 * Get Facebook search history
	 */
	 public function getSearchHistory()
	 {
		$response = $this->cURL("https://www.facebook.com/ajax/browse/null_state.php?__a=1");
	 	if( isset($response["payload"]) )
	 		return $response;
	 	return null;
	 }
};
?>
