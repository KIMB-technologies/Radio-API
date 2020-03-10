#!/usr/local/bin/php
<?php
define('HAMA-Radio', 'Radio');
error_reporting(E_ALL);

require_once( '/php-code/classes/autoload.php' );

/**
 * Dumps known episodes to disk (json-file).
 * 
 * 	docker exec --user www-data radio_api php /cron.php 
 */
Config::setRedisServer();

if( trim(shell_exec("whoami")) != "www-data" ){
	die("Run as user www-data!" . PHP_EOL);
}
if( UnRead::dumpToDisk() ){
	echo "Dumped (Un-)Read at " . date("d.m.Y H:i:s") . PHP_EOL;
}
else{
	echo "Error while dumping (Un-)Read at " . date("d.m.Y H:i:s") . PHP_EOL;
}
?>