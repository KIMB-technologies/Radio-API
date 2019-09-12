<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Helper {
	
	public static function getFinalUrl( string $link ) : string {
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $link);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
		if( curl_exec($ch) ){
			$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		} else{
			$url = $link;
		}
		
		curl_close($ch);
		
		return $url;
	}

}


?>