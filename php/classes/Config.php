<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2024 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * Docker or Non-Docker ENV setup
 */
define( 'DOCKER_MODE', !empty($_ENV['DOCKER_MODE']) && $_ENV['DOCKER_MODE'] == 'true');

// use the env.json
if( DOCKER_MODE ) {
	$ENV = $_ENV;
}
else{
	$ENV = json_decode(file_get_contents(__DIR__ . '/../data/env.json'), true);
	if(is_null($ENV)){
		die('Error: The non-Docker mode requires a valid env.json file in ./data/!');
	}
}

// load ENV values
define( 'ENV_DOMAIN',
	$ENV['CONF_DOMAIN'] . (substr( $ENV['CONF_DOMAIN'], -1) !== '/' ? '/' : '')
);
define( 'ENV_RADIO_DOMAIN',
	empty($ENV['CONF_RADIO_DOMAIN']) ? ENV_DOMAIN : ($ENV['CONF_RADIO_DOMAIN'] . (substr( $ENV['CONF_RADIO_DOMAIN'], -1) !== '/' ? '/' : ''))
);
define( 'ENV_ALLOWED_DOMAIN',
	!empty($ENV['CONF_ALLOWED_DOMAIN']) ?
		strval($ENV['CONF_ALLOWED_DOMAIN']) : null
);
define( 'ENV_CACHE_EXPIRE',
		intval($ENV['CONF_CACHE_EXPIRE'])
);
define( 'ENV_STREAM_JSON',
	!empty($ENV['CONF_STREAM_JSON']) && $ENV['CONF_STREAM_JSON'] != 'false' ?
		strval($ENV['CONF_STREAM_JSON']) : false
);
define( 'ENV_SHUFFLE_MUSIC',
		$ENV['CONF_SHUFFLE_MUSIC'] == 'true'
);
define( 'ENV_LOG_DIR',
	!empty($ENV['CONF_LOG_DIR']) && realpath($ENV['CONF_LOG_DIR']) ?
		realpath($ENV['CONF_LOG_DIR']) : __DIR__ . '/../data'
);
define(
	'ENV_CACHE_DIR',
	!empty($ENV['CONF_CACHE_DIR']) && realpath(substr($ENV['CONF_LOG_DIR'], 0, strrpos($ENV['CONF_LOG_DIR'], '/', -2))) ?
		$ENV['CONF_CACHE_DIR'] : __DIR__ . '/../data/cache'
);
define(
	'ENV_IM_EXPORT_TOKEN',
	!empty($ENV['CONF_IM_EXPORT_TOKEN']) && Helper::checkFilename($ENV['CONF_IM_EXPORT_TOKEN']) && strlen($ENV['CONF_IM_EXPORT_TOKEN']) > 15 ?
		$ENV['CONF_IM_EXPORT_TOKEN'] : false
);
define(
	'ENV_USE_JSON_CACHE',
		!empty($_ENV['CONF_USE_JSON_CACHE']) && $_ENV['CONF_USE_JSON_CACHE'] == 'true'
);
define(
	'ENV_USE_LOGO_CACHE',
		!empty($_ENV['CONF_USE_LOGO_CACHE']) && $_ENV['CONF_USE_LOGO_CACHE'] == 'true'
);

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
	 * The system's version.
	 */
	const VERSION = 'v2.8.4';

	/**
	 * The real domain which should be used.
	 * 	Used for GUI
	 */
	const DOMAIN = ENV_DOMAIN;

	/**
	 * The real domain which should be used.
	 * 	Used for access of the radio.
	 */
	const RADIO_DOMAIN = ENV_RADIO_DOMAIN;

	/**
	 * Seconds for cache lifetime
	 */
	const CACHE_EXPIRE = ENV_CACHE_EXPIRE;

	/**
	 * Own Stream used?
	 */
	const STREAM_JSON = ENV_STREAM_JSON;

	/**
	 * Random shuffle music station streams from nc
	 */
	const SHUFFLE_MUSIC = ENV_SHUFFLE_MUSIC;

	/**
	 * The directory where the logfiles are stored.
	 */
	const LOG_DIR = ENV_LOG_DIR;

	/**
	 * The directory used by the json cache (replacement for Redis in non-Docker mode)
	 */
	const CACHE_DIR = ENV_CACHE_DIR;

	/**
	 * Im- & Export via web GUI (at ./gui/im-export.php)
	 */
	const IM_EXPORT_TOKEN = ENV_IM_EXPORT_TOKEN;

	/**
	 * Always use json cache, even in Docker-Mode
	 */
	const USE_JSON_CACHE = ENV_USE_JSON_CACHE;

	/**
	 * Cache logos and make them accessible without ssl.
	 */
	const USE_LOGO_CACHE = ENV_USE_LOGO_CACHE;

	/**
	 * Store redis cache for ALLOWED_DOMAINS
	 */
	private static $redisAccessDomains = null;

	/**
	 * Store latest version and update available status
	 */
	private static $redisUpdateStatus = null;

	/**
	 * Checks if access allowed (for this request)
	 * 	Has to end the script, if not allowed!
	 * @param $mac give the users mac (we will test his last domain first, to speed up things)
	 */
	public static function checkAccess( ?string $mac = null ) : void {
		if( is_null( self::$redisAccessDomains ) ){ // already loaded?
			if(DOCKER_MODE){ // use the preloaded values from ./startup.php
				self::setRedisServer();
				self::$redisAccessDomains = new Cache( 'allowed_domains' );
			}
			else { // load the values from ENV
				self::parseAllowedDomain();
			}
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
				$pos = array_search( self::$redisAccessDomains->get( 'domain_for_user.' . $mac ), $checklater );
				if( $pos !== false ){
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
	 * Sets the redis server connection details using the env vars. 
	 * Should be always called before creating a RedisCache.
	 */
	public static function setRedisServer() : void {
		// Redis only in Docker mode and if not USE_JSON_CACHE
		if(DOCKER_MODE && !self::USE_JSON_CACHE){ 
			// configure redis
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

	public static function updateAvailable() : bool {
		if( is_null( self::$redisUpdateStatus ) ){ // load redis, if not loaded
			self::setRedisServer();
			self::$redisUpdateStatus = new Cache( 'update_status' );
		}

		// remove values of "old" update indicator
		if(self::$redisUpdateStatus->keyExists('update_available')){
			self::$redisUpdateStatus->remove('update_available');
			self::$redisUpdateStatus->remove('latest_version');
		}

		// check for new information from GitHub API
		if(!self::$redisUpdateStatus->keyExists('latest_version')){
			$infos = json_decode(file_get_contents(
					'https://api.github.com/repos/KIMB-technologies/Radio-API/releases/latest', 
					false,
					stream_context_create(array('http' =>array(
						'method'  => 'GET',
						'header'  => "Content-Type: application/json\r\n". "User-Agent: KIMB-technologies/Radio-API\r\n",
						'timeout' => 4
					)))
				), true);

			if(is_null($infos)){
				// error checking latest version
				return false;
			}
			else{
				self::$redisUpdateStatus->set(
					'latest_version',
					$infos["tag_name"],
					60*60*24*3 // check every 3 days
				);
				self::$redisUpdateStatus->set(
					'last_check',
					date('d.m.Y H:i:s')
				);
			}
		}
		
		return version_compare(self::VERSION, self::$redisUpdateStatus->get('latest_version'), '<');
	}

	public static function parseAllowedDomain(bool $output = false) : void {
		self::setRedisServer();
		self::$redisAccessDomains = new Cache( 'allowed_domains' );

		if( ENV_ALLOWED_DOMAIN == 'all' ){
			self::$redisAccessDomains->set( 'type', 'all' );	
		}
		else if( !is_null( ENV_ALLOWED_DOMAIN ) ){
			self::$redisAccessDomains->set( 'type', 'list' );
			$allowed = array_map( function ($domain){
				return trim($domain);
			}, explode(',', ENV_ALLOWED_DOMAIN ) );
			self::$redisAccessDomains->arraySet('domains', $allowed);
		}
		else{
			self::$redisAccessDomains->set( 'type', 'error' );	
		}

		if($output){
			self::$redisAccessDomains->output();
		}
	}
}

?>
