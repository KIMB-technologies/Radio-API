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
	$mainTemplate->setContent('TITLE', 'List');
	$mainTemplate->setContent('MOREHEADER', '<script src="viewer.js?v=2"></script>');
	$listTemplate = new Template('list');
	$mainTemplate->includeTemplate( $listTemplate );

	$listTemplate->setContent('DOMAIN', Config::DOMAIN);

	$inner = new Inner($login->getId());
	$inner->checkPost();

	$listTemplate->setContent('PODCAST_FORM', $inner->podcastForm());
	$listTemplate->setContent('RADIO_FORM', $inner->radioForm());
	$listTemplate->setContent('ADD_HTML', $inner->getMessages());
	$listTemplate->setContent('RADIO_MAC', $login->getAll()['mac']);
	$listTemplate->setContent('LOGIN_CODE', $login->getAll()['code']);
}
else{
	$mainTemplate->setContent('TITLE', 'Login');
	$loginTemplate = new Template('login');
	$mainTemplate->includeTemplate( $loginTemplate );

	// Login Error
	if( !empty($_POST['code']) ){
		$loginTemplate->setContent('ADD_HTML', '<div class="achtung">Login not successful!</div>');
	}
	// Error Page
	if( isset( $_GET['err'] ) && ( $_GET['err'] == '404' || $_GET['err'] == '403' ) ){
		$loginTemplate->setContent('ADD_HTML', '<div class="achtung">Error '. $_GET['err'] .'</div>');
	}
}
echo $mainTemplate->output();
?>
