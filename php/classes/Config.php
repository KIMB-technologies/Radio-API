<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2020 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * Docker ENV setup
 */
define( 'ENV_DOMAIN', $_ENV['CONF_DOMAIN'] . (substr( $_ENV['CONF_DOMAIN'], -1) !== '/' ? '/' : '') );
define( 'ENV_CACHE_EXPIRE', intval($_ENV['CONF_CACHE_EXPIRE']));
define( 'ENV_OWN_STREAM', $_ENV['CONF_OWN_STREAM'] == 'true');
define( 'ENV_PROXY_OWN_STREAM', $_ENV['CONF_PROXY_OWN_STREAM'] == 'true');
define( 'ENV_SHUFFLE_MUSIC', $_ENV['CONF_SHUFFLE_MUSIC'] == 'true');

// IP on reverse proxy setup
if( !empty($_SERVER['HTTP_X_REAL_IP']) ){
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}
// HTTPS or HTTP on reverse proxy setup
if( !empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ){
	$_SERVER['HTTPS'] = 'on';
}

class Config {

	/**
	 * The real domain which should be used.
	 */
	const DOMAIN = ENV_DOMAIN;

	/**
	 * Seconds for cache lifetime
	 */
	const CACHE_EXPIRE = ENV_CACHE_EXPIRE;

	/**
	 * Own Stream used?
	 */
	const OWN_STREAM = ENV_OWN_STREAM;

	/**
	 * Own Stream Proxy
	 */
	const PROXY_OWN_STREAM = ENV_PROXY_OWN_STREAM;

	/**
	 * Random shuffle music station streams from nc
	 */
	CONST SHUFFLE_MUSIC = ENV_SHUFFLE_MUSIC;

	/**
	 * Store redis cache for ALLOWED_DOMAINS, OWN_STREAM
	 */
	private static $redisAccessDomains = null, $redisOwnStream = null;

	/**
	 * Checks if access allowed (for this request)
	 * 	Has to end the script, if not allowed!
	 * @param $mac give the users mac (we will test his last domain first, to speed up things)
	 */
	public static function checkAccess( ?string $mac = null ) : void {
		if( is_null( self::$redisAccessDomains ) ){ // load redis, if not loaded
			self::setRedisServer();
			self::$redisAccessDomains = new RedisCache( 'allowed_domains' );
		}
		if( self::$redisAccessDomains->get('type' ) == 'all' ){ // allow all
			return;
		}
		else if( self::$redisAccessDomains->get('type' ) == 'list' ){ // check list
			$ip = $_SERVER["REMOTE_ADDR"]; // get client ip

			// iterate over all allowed domains, and check all ips (which are not timed out)
			$checklater = array();
			foreach( self::$redisAccessDomains->arrayGet('domains') as $domain ){
				if( self::$redisAccessDomains->keyExists( 'ip_for_domain.' . $domain ) ){
					if( self::$redisAccessDomains->get( 'ip_for_domain.' . $domain ) == $ip ){
						if( !is_null( $mac ) ){ // save this domain for this user
							self::$redisAccessDomains->set( 'domain_for_user.' . $mac, $domain );
						}
						return; // access granted
					}
				}
				$checklater[] = $domain;
			}

			// the last domain for this user should be check first
			if( !is_null( $mac ) && count( $checklater ) > 1 && self::$redisAccessDomains->keyExists( 'domain_for_user.' . $mac ) ){
				if( $pos = array_search( self::$redisAccessDomains->get( 'domain_for_user.' . $mac ), $checklater ) !== false ){
					$tmp = $checklater[$pos]; // swap positions, so last domain ist first
					$checklater[$pos] = $checklater[0];
					$checklater[0] = $tmp;
				}
			}

			foreach( $checklater as $domain ){
				$thisip = gethostbyname( $domain );
				self::$redisAccessDomains->set( 'ip_for_domain.' . $domain, $thisip, self::CACHE_EXPIRE );
				if( $thisip == $ip ){ // ip ok?
				    return;
				}
			}
			die('Not Allowed!');
		}
		else{
			die('Invalid Access Domains!');
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
			if( is_null( self::$redisOwnStream ) ){ // load redis, if not loaded
				self::$redisOwnStream = new RedisCache( 'own_stream' );
			}
			if( self::$redisOwnStream->keyExists( 'list' ) ){
				return self::$redisOwnStream->arrayGet( 'list' );
			}
			else if( !empty($_ENV['CONF_OWN_STREAM_JSON']) ){
				$data = file_get_contents( $_ENV['CONF_OWN_STREAM_JSON'] );
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
							self::$redisOwnStream->arraySet( 'list', $list, self::CACHE_EXPIRE );
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
			return (!empty($_ENV['CONF_OWN_STREAM_URL']) ? $_ENV['CONF_OWN_STREAM_URL'] : 'CONF_OWN_STREAM_URL' ) . $key;
		}
		else{
			return "";
		}
	}

	/**
	 * Sets the redis server copnnection details using the env vars. 
	 * Should be always called before creating a RedisCache.
	 */
	public static function setRedisServer() : void {
		if( isset( $_ENV['CONF_REDIS_HOST'], $_ENV['CONF_REDIS_PORT'], $_ENV['CONF_REDIS_PASS'] ) ){
			RedisCache::setRedisServer($_ENV['CONF_REDIS_HOST'], $_ENV['CONF_REDIS_PORT'], $_ENV['CONF_REDIS_PASS']);
		}
		else if( isset( $_ENV['CONF_REDIS_HOST'], $_ENV['CONF_REDIS_PORT'] ) ){
			RedisCache::setRedisServer($_ENV['CONF_REDIS_HOST'], $_ENV['CONF_REDIS_PORT']);
		}
		else if( isset( $_ENV['CONF_REDIS_HOST'] ) ){
			RedisCache::setRedisServer($_ENV['CONF_REDIS_HOST']);
		}
	}
}

?>
