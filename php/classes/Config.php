<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * Docker ENV setup
 */
define( 'ENV_NEXTCLOUD', $_ENV['CONF_NEXTCLOUD'] );
define( 'ENV_DOMAIN', $_ENV['CONF_DOMAIN'] );
define( 'ENV_CACHE_EXPIRE', intval($_ENV['CONF_CACHE_EXPIRE']));
define( 'ENV_OWN_STREAM', $_ENV['CONF_OWN_STREAM'] == 'true');
define( 'ENV_ALLOWED_DOMAIN', $_ENV['CONF_ALLOWED_DOMAIN']);

// IP on reverse proxy setup
if( !empty($_SERVER['HTTP_X_REAL_IP']) ){
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}
// HTTPS or HTTP on reverse proxy setup
if( !empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ){
	$_SERVER['HTTPS'] = 'on';
}

class Config{

	/**
	 * The real domain which should be used.
	 */
	const DOMAIN = ENV_DOMAIN;

	/**
	 * Nextcloud Domain
	 */
	const NEXTCLOUD = ENV_NEXTCLOUD;

	/**
	 * Seconds for cache lifetime
	 */
	const CACHE_EXPIRE = ENV_CACHE_EXPIRE;

	/**
	 * Own Stream used?
	 */
	const OWN_STREAM = ENV_OWN_STREAM;

	/**
	 * Allowed Domain
	 */
	const ALLOWED_DOMAIN = ENV_ALLOWED_DOMAIN;

	/**
	 * Checks if access allowed (for this request)
	 * 	Has to end the script, if not allowed!
	 */
	public static function checkAccess(){
		if( self::ALLOWED_DOMAIN != 'all' ){
			if( is_file( __DIR__ . '/ip_ok.txt' ) && filemtime( __DIR__ . '/ip_ok.txt' ) + self::CACHE_EXPIRE > time() ){
				$ip_ok = file_get_contents( __DIR__ . '/ip_ok.txt' );
			}
			else {
				$ip_ok = gethostbyname( self::ALLOWED_DOMAIN );
				if( filter_var( $ip_ok, FILTER_VALIDATE_IP ) !== false ){
					file_put_contents( __DIR__ . '/ip_ok.txt', $ip_ok );
				}
				else{
					$ip_ok = '';
				}
			}
			if( empty($ip_ok) || $_SERVER["REMOTE_ADDR"] != $ip_ok ){
				die('Not Allowed!');
			}
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
		if( self::OWN_STREAM ){
			if( is_file( __DIR__ . '/streamlist.json' )
				&& filemtime( __DIR__ . '/streamlist.json' ) + self::CACHE_EXPIRE > time() ){
				$list = json_decode(file_get_contents( __DIR__ . '/streamlist.json'), true );
			}
			else if( !empty($_ENV['CONF_own_stream_json']) ){
				$data = file_get_contents( $_ENV['CONF_own_stream_json'] );
				if( !empty($data) ){
					$list = json_decode( $data, true );
					if( !empty( $list )){
						$ok = true;
						foreach( $list as $key => $val ){
							if( !preg_match('/^[A-Za-z0-9]+$/', $key) || !isset( $val['name'] ) || !is_string($val['name']) ){
								$ok = false;
							} 
						}
						if( $ok ){
							file_put_contents(  __DIR__ . '/streamlist.json', json_encode( $list, JSON_PRETTY_PRINT ) );
						}
						else{
							$list = array("NoKey" => array( "name" => "JSON invalid array form" ));
						}
					}
					else{
						$list = array("NoKey" => array( "name" => "JSON PARSE Error" ));
					}
				}
				else{
					$list = array("NoKey" => array( "name" => "Empty Response of JSON URL" ));
				}
				
			}
			else{
				$list = array("NoKey" => array( "name" => "No JSON URL" ));
			}
			return $list;
		}
		else{
			return array();
		}
	}

	/**
	 * Gets the URL for one of MyStreams based on the key.
	 */
	public static function myStreamsListGetURL( string $key ) : string {
		if( self::OWN_STREAM ){
			return (!empty($_ENV['CONF_own_stream_url']) ? $_ENV['CONF_own_stream_url'] : 'CONF_own_stream_url' ) . $key;
		}
		else{
			return "";
		}
	}
}

?>
