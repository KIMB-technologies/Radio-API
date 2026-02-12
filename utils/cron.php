#!/usr/local/bin/php
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
define('HAMARadio', 'Radio');
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

if( RadioBrowser::dumpToDisk() ){
	echo "Dumped RadioBrowser at " . date("d.m.Y H:i:s") . PHP_EOL;
}
else{
	echo "Error while dumping RadioBrowser at " . date("d.m.Y H:i:s") . PHP_EOL;
}
?>