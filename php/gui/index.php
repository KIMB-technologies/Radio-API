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
define('HAMA-Radio', 'Radio');
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

// Load System
require_once(__DIR__ . '/../classes/autoload.php');
// Load Login
$login = new Login();
// Parse Language and change
Template::detectLanguage();

// Load Main Template
$mainTemplate = new Template('main');

// Login Form?
if( isset($_GET['login']) || isset( $_GET['err'] )){
	if( !empty($_POST['code']) && is_string($_POST['code']) ){
		$login->loginByCode($_POST['code']);
	}
	else{
		$login->logout();
	}
}
if( $login->isLoggedIn() ){
	// RadioBrowser Search?
	if(!empty($_GET["search"]) || isset($_GET["last"])){
		$rb = new RadioBrowser($login->getId());
		header('Content-Type: application/json;charset=UTF-8');
		die(json_encode(
			isset($_GET["last"]) ? $response = $rb->lastStations() : $rb->searchStation($_GET["search"]), 
			JSON_PRETTY_PRINT
		));
	}
	else {
		$mainTemplate->setContent('TITLE', Template::getLanguage() == 'de' ? 'Eigene Listen' : 'User defined Lists');
		$mainTemplate->setContent('MOREHEADER',
			'<script src="viewer.js?v=4"></script>' .
			'<script src="radio-browser.js?v=2"></script>' . 
			'<style>.only-docker-mode { display: '. ( DOCKER_MODE ? 'table-row' : 'none' )
				.'; } .not-docker-mode { display: '. ( DOCKER_MODE ? 'none' : 'block' ) .'; }</style>'
		);
	
		$listTemplate = new Template('list');
		$listTemplate->setContent('RADIO_MAC', $login->getAll()['mac']);
		$listTemplate->setContent('LOGIN_CODE', $login->getAll()['code']);
	
		$mainTemplate->includeTemplate( $listTemplate );
	
		$inner = new Inner($login->getId(), $listTemplate);
		$inner->checkPost();
	
		$inner->radioForm();
		$inner->podcastForm();
	
		$inner->outputMessages();
	}
}
else{
	$mainTemplate->setContent('TITLE', 'Login');
	$loginTemplate = new Template('login');
	$mainTemplate->includeTemplate( $loginTemplate );

	// Login Error
	if( !empty($_POST['code']) ){
		$msg = Template::getLanguage() == 'de' ? 'Login fehlgeschlagen' : 'Login not successful!';
		$loginTemplate->setContent('ADD_HTML', '<div class="achtung">'.$msg.'</div>');
	}
	// Error Page
	if( isset( $_GET['err'] ) && ( $_GET['err'] == '404' || $_GET['err'] == '403' ) ){
		$msg = Template::getLanguage() == 'de' ? 'Fehler' : 'Error';
		$loginTemplate->setContent('ADD_HTML', '<div class="achtung">'.$msg.' '. $_GET['err'] .'</div>');
	}
}
echo $mainTemplate->output();
?>
