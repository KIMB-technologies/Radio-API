<?php
define('HAMA-Radio', 'Radio');
error_reporting(E_ALL);

require_once( '/php-code/classes/autoload.php' );

/**
 * Startup file for docker container, load allowed domains into
 * Redis Cache.
 */
Config::setRedisServer();
$redis = new RedisCache( 'allowed_domains' );

$allowedDomains = !empty($_ENV['CONF_ALLOWED_DOMAIN']) ? $_ENV['CONF_ALLOWED_DOMAIN'] : null;

if( $allowedDomains == 'all' ){
	$redis->set( 'type', 'all' );	
}
else if( !is_null( allowedDomains ) ){
	$redis->set( 'type', 'list' );
	$allowed = array_map( function ($domain){
		return trim($domain);
	}, explode(',', $allowedDomains ) );
	$redis->arraySet('domains', $allowed);
}
else{
	$redis->set( 'type', 'error' );	
}

$redis->output();
?>