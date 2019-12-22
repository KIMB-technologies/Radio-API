<?php
define('HAMA-Radio', 'Radio');
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

/**
 * Loading
 */
require_once( __DIR__ . '/classes/autoload.php' );
Config::checkAccess( !empty($_GET['mac']) && Helper::checkValue( $_GET['mac'], Id::MAC_PREG ) ? $_GET['mac'] : null );

/**
 * Radio ID/ Mac Login
 */
try{
	if( empty($_GET['mac']) ){
		throw new Exception();
	}
	$radioid = new Id($_GET['mac']);
}
catch(Exception $e){
	Output::sendAnswer('<Error>No MAC!</Error>');
	die(); //will never be reached
}
$data = new Data($radioid->getId());

/**
 * Answer M3U Request
 */
if( !empty( $_GET['id'] ) ){
	$id = $_GET['id'];

	// id ok?
	if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
		// get stattion
		$stat = $data->getById($id);
		if( !empty($stat)){ // is a station
			header('Content-Type: audio/x-mpegurl; charset=utf-8');

			$musik = PodcastLoader::getPodcastByUrl( $stat['url'], true )['episodes'];
			foreach( $musik as $m ){
				echo $m['url'] . PHP_EOL;
			}
			die();
		}
	}
}
Output::sendAnswer('<Error>Invalid Parameter</Error>');
?>