<?php
define('HAMA-Radio', 'Radio');

// Load System
require_once(__DIR__ . '/../classes/autoload.php');
// Load Main Template
$mainTemplate = new Template('main');

if( false ){
	$mainTemplate->setContent('TITLE', 'Liste');
	$mainTemplate->setContent('MOREHEADER', '<script src="viewer.js"></script>');
	$listTemplate = new Template('list');
	$mainTemplate->includeTemplate( $listTemplate );

	$listTemplate->setContent('SERVER_URL', 'http'. ( empty($_SERVER['HTTPS']) ? '' : 's' ) .':'. substr(Config::DOMAIN, strpos(Config::DOMAIN, '//')));
	$listTemplate->setContent('DOMAIN', Config::DOMAIN);
	$listTemplate->setContent('NEXTCLOUD', Config::NEXTCLOUD);

	$inner = new Inner();
	$inner->checkPost();

	$listTemplate->setContent('PODCAST_FORM', $inner->podcastForm());
	$listTemplate->setContent('RADIO_FORM', $inner->radioForm());
	$listTemplate->setContent('ADD_HTML', $inner->getAdditionalHTML());
}
else{
	$listTemplate->setContent('TITLE', 'Login');
	$loginTemplate = new Template('login');
	$mainTemplate->includeTemplate( $loginTemplate );

	

}
?>