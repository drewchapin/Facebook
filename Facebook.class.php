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
	 * Generate form data from array.
	 */
	private function arrayToFormData( $fields )
	{
		$tmp = "";
		foreach( $fields as $name => $value )
		{
			$tmp .= strlen($tmp) > 0 ? "&" : "";
			$tmp .= $name . "=" . urlencode($value);
		}
		return $tmp;
	}
	
	/**
	 * Fetch the contents of a page.
	 * @param $url     URL to fetch. 
	 * @param $fields  Form data for HTTP GET or HTTP POST.
	 * @param $post    Perform HTTP POST instead of default HTTP GET
	 */
	private function cURL( $url, $fields = null, $post = false )
	{
		// convert $fields into URL encoded form data if it is an array.
		if( is_array($fields) )
		{
			$tmp = "";
			foreach( $fields as $name => $value )
			{
				$tmp .= strlen($tmp) > 0 ? "&" : "";
				$tmp .= $name . "=" . urlencode($value);
			}
			$fields = $tmp;
		}
		// if URL is relative, prepend www.facebook.com
		if( substr($url,0,4) != "http" )
			$url = "https://www.facebook.com/" . $url;
		// add URL encoded fields to URL if they exist
		if( $post != true && !is_null($fields) )
		{
			$url .= (strpos($url,"?") != false ? "&" : "?") . $fields;
		}
		$ch = curl_init($url);
		if( $post == true )
		{
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$fields);
		}
		curl_setopt($ch,CURLOPT_HEADER,false);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
		if( isset($this->cookiejar) )
		{
			curl_setopt($ch,CURLOPT_COOKIEJAR,$this->cookiejar);
			curl_setopt($ch,CURLOPT_COOKIEFILE,$this->cookiejar);
		}
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_USERAGENT,self::USER_AGENT);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
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
		$dom_form = $dom->getElementById($form_id);
		if( $dom_form == null )
			return null;
		$form_data = array();
		$form_data["action"] = $dom_form->getAttribute("action");
		$form_data["method"] = $dom_form->getAttribute("method");
		$form_data["inputs"] = array();
		$inputs = $dom_form->getElementsByTagName("input");
		for( $i = 0; $i < $inputs->length; $i++ )
		{
			$name = $inputs->item($i)->getAttribute("name");
			if( isset($name) && !empty($name) )
			{
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
			if( !$this->loggedIn($dom) )
			{
				// login form
				$form = $this->getForm($dom,"login_form");
				$form["inputs"]["email"] = $username;
				$form["inputs"]["pass"] = $password;
				$form["inputs"]["persistent"] = $remember ? "1" : "0";
				$response = $this->cURL($form["action"],$form["inputs"],true);
				if( isset($response["dom"]) )
				{
					$dom = $response["dom"];
					if( $form = $this->getForm($dom,"login_form") )
					{
						throw new Exception("Invalid username or password");
					}
					else if( $form = $this->getForm($dom,"u_0_1") )
					{
						// approval code form
						$form["inputs"]["approvals_code"] = $code;
						$response = $this->cURL($form["action"],$form["inputs"],true);
						if( isset($response["dom"]) )
						{
							$dom = $response["dom"];
							if( !$this->loggedIn($dom) )
							{
								throw new Exception("Invalid login code.");
							}
							return true;
						}
						else
							throw new Exception("Invalid response from server verifying login code.");
					}
				}
				else
					throw new Exception("Invalid login response from server.");
			}
			else
				return true;
		}
		else
			throw new Exception("Invalid response from server");
	}
	
	/**
	 * Get Facebook search history
	 */
	 public function getSearchHistory()
	 {
		$response = $this->cURL("https://www.facebook.com/ajax/browse/null_state.php?__a=1");
		if( isset($response["json"]) )
		{
	 		return $response["json"];
	 	}
		else if( isset($response["dom"]) && !$this->loggedIn($response["dom"]) )
		{
			throw new Exception("Not logged in");		
		}
		else
			throw new Exception("Invalid response");
	 }
};
?>
