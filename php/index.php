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
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

/**
 * Loading
 */
require_once( __DIR__ . '/classes/autoload.php' );

/**
 * Radio Server Test Requests
 */
$uri = !empty( $_GET['uri'] ) && is_string($_GET['uri']) ? $_GET['uri'] : 'none';
if($uri === 'none' && isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])){
	$uri = trim(substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')));
}

// Login (Radio tries a login before accessing the api)
if( preg_match('/^\/setupapp\/[A-Za-z0-9\-\_]+\/asp\/BrowseXML\/loginXML.asp/', $uri) === 1 && !isset( $_GET['mac'] )) {
	Output::sendAnswer('<EncryptedToken>3a3f5ac48a1dab4e</EncryptedToken>');
	die(); //will never be reached
}

/**
 * Check if IP valid
 */
Config::checkAccess( !empty($_GET['mac']) && Helper::checkValue( $_GET['mac'], Id::MAC_PREG ) ? $_GET['mac'] : null );

/**
 * Auth
 */
$radioid = Auth::authFromMac(true);

/**
 * Handle
 */
$router = new Router($radioid);
$router->handleGet($uri);
?>