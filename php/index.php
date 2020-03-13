<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2020 KIMB-technologies 
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
 * Radio Server Test Requests
 */
$uri = !empty( $_GET['uri'] ) && is_string($_GET['uri']) ? $_GET['uri'] : 'none';
// Login (Radio tries a login before accessing the api)
if( $uri == '/setupapp/hama/asp/BrowseXML/loginXML.asp' && !isset( $_GET['mac'] ) ){
	Output::sendAnswer('<EncryptedToken>3a3f5ac48a1dab4e</EncryptedToken>');
	die(); //will never be reached
}

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