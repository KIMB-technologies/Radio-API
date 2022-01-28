<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2022 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
define('HAMA-Radio', 'Radio');
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

/**
 * Loading
 */
require_once( __DIR__ . '/classes/autoload.php' );
Config::checkAccess( !empty($_GET['mac']) && Helper::checkValue( $_GET['mac'], Id::MAC_PREG ) ? $_GET['mac'] : null );

/**
 * Auth
 */
$radioid = Auth::authFromMac();

/**
 * Answer M3U Request
 */
if( !empty( $_GET['id'] ) ){
	$id = $_GET['id'];

	$m3u = new M3U($radioid);
	$m3u->musicStream($id);
}
else{
	Output::sendAnswer('<Error>Invalid Parameter</Error>');
}
?>