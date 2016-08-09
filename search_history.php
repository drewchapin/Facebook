#!/usr/bin/env php
<?php

require_once("config.php");
require_once("Facebook.class.php");

$fb = new Facebook("cookies.txt");

$response = $fb->getSearchHistory();
var_dump($response);
if( isset($response["payload"]) )
{
	foreach( $response["payload"]["queries"] as $query )
	{
		echo $query["parse"]["display"][0] . PHP_EOL;
	}
}

?>
