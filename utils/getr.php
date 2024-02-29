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
header('Content-Type: text/plain;');

$redis = new Redis();
$redis->pconnect('redis');

$all = array();
$iterator = NULL;
do {
	$keys = $redis->scan($iterator);
	if ($keys !== FALSE) {
		$all = array_merge( $all, $keys);
	}
} while ($iterator > 0);
	
echo '=================================' . PHP_EOL;
echo 'Key' . "\t\t\t\t : " . 'Value' . PHP_EOL;
echo '---------------------------------' . PHP_EOL;
foreach( $all as $key ){
	if( $redis->type($key) !== Redis::REDIS_HASH ){
		$val = $redis->get($key);
	}
	else {
		$val = print_r( $redis->hGetAll($key), true);
	}
	echo $key . "\t\t\t\t : " . substr( $val, 0, 1024 ) . PHP_EOL;
}	
echo '=================================' . PHP_EOL . PHP_EOL;
?>