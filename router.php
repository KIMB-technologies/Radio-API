<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2023 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */

/*
 * This file is for running the Radio-API in non-Docker mode using the PHP
 * built in webserver.
 * Start in root of Git repo with, e.g.,:
 * 	php -S localhost:8080 -t ./php/ ./router.php
 */
if (php_sapi_name() == 'cli-server') {
	if (
		realpath( $_SERVER['DOCUMENT_ROOT'] . '/' . $_SERVER['SCRIPT_NAME']) ||
		realpath( $_SERVER['DOCUMENT_ROOT'] . '/' . $_SERVER['SCRIPT_NAME'] . '/index.php')
	) {
		// serve existent files (and try folders with "index.php")
		return false;
	}
	else { 
		// else use the index.php as fallback!
		include(__DIR__ . '/php/index.php');
	}
}
else{
	die("Error, only for usage with built in webserver!");
}
?>