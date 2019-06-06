<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Config{

	/**
	 * The real domain which should be used.
	 */
	const DOMAIN = 'http://radio.example.com/';

	/**
	 * Nextcloud Domain
	 */
	const NEXTCLOUD = 'http://radio.example.com/cloudproxy/';

	/**
	 * Checks if access allowed (for this request)
	 * 	Has to end the script, if not allowed!
	 */
	public static function checkAccess(){
		if( false /* DO SOME CHECK TO CHECK ACCESS!! */ ){
			http_response_code(403);
			die('Not Allowed!');
		}
	}

	/**
	 * Returns List of personal MyStreams
	 * 	[
	 * 		"key" : {
	 * 			"name" : "<NAME>"
	 * 		}, ...
	 * 	]
	 */
	public static function getMyStreamsList() : array {
		/*
			ADD SOME OWN STREAMS
		*/
		return array(
			"key" => array(
				"name" => "My Own Stream"
			)
		);
	}

	/**
	 * Gets the URL for one of MyStreams based on the key.
	 */
	public static function myStreamsListGetURL( string $key ) : string {
		/*
			TRANSLATE A KEY OF OWN STREAM TO URL
		*/
		return 'http://stream.example.com/?key=' . $key;
	}
}

?>
