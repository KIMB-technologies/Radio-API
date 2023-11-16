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
else if( !is_null( $allowedDomains ) ){
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

/**
 * load un/read episodes into Redis Cache
 */
echo "Load (Un-)Read: " . PHP_EOL;
foreach(UnRead::loadFromDisk() as $id => $d ){
	echo "\t" . $id . PHP_EOL;
	foreach( $d as $r ){
		echo "\t\t" . $r . PHP_EOL;
	}
}

/**
 * load last stations into Redis Cache
 */
echo "Load RadioBrowser: " . PHP_EOL;
foreach(RadioBrowser::loadFromDisk() as $id => $d ){
	echo "\t" . $id . " has " . count($d) . " last stations." .PHP_EOL;
}
?>
