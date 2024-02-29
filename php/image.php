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
Config::checkAccess();

// check if image hash given
if( !empty($_GET['hash']) && is_string($_GET['hash']) && preg_match('/^[a-f0-9]{40}$/', $_GET['hash']) === 1 ){
	$namehash = $_GET['hash'];
	$file = RadioLogo::BASE_DIR . '/' . $namehash . '.image';
}

// if no image file or file not exists
if( empty($file) || !is_file( $file ) ){
	// Redirect to default image
	header('Location: http' .
		( empty($_SERVER['HTTPS']) ? ':' : 's:' ) .
		substr( Config::RADIO_DOMAIN, strpos( Config::RADIO_DOMAIN, '//') )
		. 'media/default.png'
	);
	http_response_code(303);
}
else {
	// send file

	// Metadaten fuer Header
	$filesize = filesize( $file );
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mimetype = finfo_file($finfo, $file);
	finfo_close($finfo);

	//Header
	header( 'Content-type: '.$mimetype.'; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="'.$namehash.'.image"' );
	header( 'Content-Length: '.$filesize);

	readfile( $file );
}
?>