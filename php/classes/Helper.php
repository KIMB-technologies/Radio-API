<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2023 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * Helpful functions
 */
class Helper {
	
	/**
	 * Follow all 30x heades and return final url dest.
	 * @param $link the url to follow its redirects
	 */
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

	public static function checkFilename( $n ) : bool {
		return is_string($n) && preg_match( '/^[A-Za-z0-9]+$/', $n ) === 1;
	}

	public static function checkValue($val, string $preg) : bool {
		return is_string($val) && preg_match( $preg, $val ) === 1;
	}

	public static function randomCode( int $len ) : string {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY';
		$r = '';
		$charAnz = strlen( $chars );
		for($i = 0; $i < $len; $i++){
			$r .= $chars[random_int(0, $charAnz-1)];
		}
		return $r;
	}
}
?>