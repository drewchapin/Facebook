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
	protected $cookiejar;
	
	/**
	 * Facebook User ID. This is populated when you login().
	 */
	protected $fbid;
	
	/**
	 * Initialize the class.
	 * @param $cookiejar  Location of cookies file. Use a different cookiejar for each 
	 *                    account / instance. 
	 */
	function __construct( string $cookiejar = null )
	{
		$this->cookiejar = $cookiejar;
	}
	
	/**
	 * Fetch the contents of a page.
	 * @param $url     URL to fetch. 
	 * @param $post    Perform HTTP POST instead of default HTTP GET
	 * @param $fields  Post fields for HTTP POST. This is ignored when $post is false. 
	 */
	private function cURL( string $url, bool $post = false, array $fields = null )
	{
		if( $fields != null )
		{
			$tmp = "";
			foreach( $fields as $name => $value )
			{
				$tmp .= strlen($tmp) > 0 ? "&" : "";
				$tmp .= $name . "=" . urlencode($value);
			}
			$fields = $tmp;
		}
		$ch = curl_init($url);
		if( $post == true )
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			if( is_string($fields) )
				curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
		}
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		if( !isset($this->cookiejar) )
		{
			curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookiejar);
			curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookiejar);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT,self::USER_AGENT );
		#curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		#curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		$response = curl_exec($ch); 
		if( !$response || curl_errno($ch) )
		{
			$error = curl_error($ch);
			curl_close($ch);
			throw new Exception(curl_error($ch));
		}
		$result = array();
		$result["http_code"] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$json = json_decode(substr($response,9),true);
		if( $json != null )
		{
			return $json;
		}
		$http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_code;
	}
	
	/**
	 * Authenticate with Facebook
	 */
	public function login( $username, $password, $remember = false )
	{
		$postdata = array(
			"email"      => $username,
			"pass"       => $password,
			"persistent" => $remember ? "1" : "0"
		);
		$dom = new DOMDocument;
		$dom->loadHTML($this->cURL("https://www.facebook.com"));
		$form = $dom->getElementById("login_form");
		if( $form == null )
			return true;
		$action = $form->getAttribute("action");
		$inputs = $form->getElementsByTagName("input");
		for( $i = 0; $i < $inputs->length; $i++ )
		{
			$type = $inputs[$i]->getAttribute("type");
			if( $type == "hidden" )
			{
				$name = $inputs[$i]->getAttribute("name");
				$value = $inputs[$i]->getAttribute("value");
				$postdata[$name] = $value;
			}
		}
		$this->cURL($action,$postdata,true);
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
