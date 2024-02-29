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
define('HAMA-Radio', 'Radio');
error_reporting(E_ALL);

require_once( '/php-code/classes/autoload.php' );

/**
 * Startup file for docker container
 */

/**
 *  First load allowed domains into Redis Cache.
 */
Config::parseAllowedDomain(true);

/**
 * Reset the 'table.json' cache
 */
(new Cache('table.json'))->removeGroup();

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
