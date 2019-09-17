<?php
define('HAMA-Radio', 'Radio');

// Load System
require_once(__DIR__ . '/../classes/autoload.php');
// Load Login
$login = new Login();
// Load Main Template
$mainTemplate = new Template('main');

// Login Form?
if( isset($_GET['login']) || isset( $_GET['err'] )){
	if( !empty($_POST['code']) && is_string($_POST['code']) ){
		$login->loginByCode($_POST['code']);
	}
	else{
		$login->logout($_POST['code']);
	}
}
if( $login->isLoggedIn() ){
	$mainTemplate->setContent('TITLE', 'Liste');
	$mainTemplate->setContent('MOREHEADER', '<script src="viewer.js"></script>');
	$listTemplate = new Template('list');
	$mainTemplate->includeTemplate( $listTemplate );

	$listTemplate->setContent('DOMAIN', Config::DOMAIN);
	$listTemplate->setContent('NEXTCLOUD', Config::NEXTCLOUD);

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
		$loginTemplate->setContent('ADD_HTML', '<div class="achtung">Login nicht möglich!</div>');
	}
	// Error Page
	if( isset( $_GET['err'] ) && ( $_GET['err'] == '404' || $_GET['err'] == '403' ) ){
		$loginTemplate->setContent('ADD_HTML', '<div class="achtung">Fehler</div>');
	}
}
echo $mainTemplate->output();
?>