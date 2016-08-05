#!/usr/bin/env php
<?php
$fb = new Facebook("cookies.txt");
$fb->login(USERNAME,PASSWORD);
/*$response = $fb->getSearchHistory();
if( isset($response["payload"]) )
{
	foreach( $response["payload"]["queries"] as $query )
	{
		echo $query["parse"]["display"][0] . PHP_EOL;
	}
}*/

?>
