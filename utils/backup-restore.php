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

// load Radio-API
$autoloadPath = null;
$autoloadCandidates = array(
	__DIR__ . '/../php/classes/autoload.php',
	__DIR__ . '/php/classes/autoload.php',
	__DIR__ . '/classes/autoload.php',
	'/php-code/classes/autoload.php'
);
foreach($autoloadCandidates as $aC ){
	if(is_file($aC)){
		require_once($aC);
		$autoloadPath = $aC;
		break;
	}
}
if( is_null($autoloadPath) ){
	die(
		"Error, unable to find the source/ directory of Radio-API!" . PHP_EOL . 
		"Checked folders:" . PHP_EOL . 
		" - " . implode(PHP_EOL . " - ", $autoloadCandidates) . PHP_EOL
	);
}
Config::setRedisServer();

echo "Welcome to Backup-Restore of Radio-API:" . PHP_EOL . PHP_EOL;
echo "Please choose a task:" . PHP_EOL;
echo " - e: Export items from cache " . PHP_EOL . " - i: Import item to cache" . PHP_EOL;
$task = readline("Type e/ i: ");
if($task !== "e" && $task !== "i") {
	die("Only Tasks 'e' and 'i' are known, but you did not choose one of them!");
}

echo PHP_EOL;
$dataDir = realpath(dirname($autoloadPath) . '/../data/');
echo "The files " . ($task == "e" ? "are exported to" : "are imported from") . " the folder: " . $dataDir . PHP_EOL;
$diffFolder = readline("Press Enter to confirm folder or type a different folder: " );
if(!empty($diffFolder)){
	$dataDir = $diffFolder;
}
if(!is_dir($dataDir)){
	die("The folder " . $dataDir . " does not exist!");
}

echo PHP_EOL;
if($task == "e"){
	echo "Export (Un)Read ... " . PHP_EOL;
	UnRead::dumpToDisk($dataDir);

	echo "Export RadioBrowser ... " . PHP_EOL;
	RadioBrowser::dumpToDisk($dataDir);

	echo "Please check " . $dataDir . " for two exported json files!" . PHP_EOL;
}
elseif($task == "i"){
	echo "Import (Un-)Read: " . PHP_EOL;
	foreach(UnRead::loadFromDisk($dataDir) as $id => $d ){
		echo "  " . $id . PHP_EOL;
		foreach( $d as $r ){
			echo "    " . $r . PHP_EOL;
		}
	}

	echo "Import RadioBrowser: " . PHP_EOL;
	foreach(RadioBrowser::loadFromDisk($dataDir) as $id => $d ){
		echo "  " . $id . " has " . count($d) . " last stations." .PHP_EOL;
	}
}

echo PHP_EOL . "Bye!" . PHP_EOL;
?>