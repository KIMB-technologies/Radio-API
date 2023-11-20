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

// Load Templates
$mainTemplate = new Template('main');
$viewTemplate = new Template('view');

$mainTemplate->setContent('TITLE', Template::getLanguage() == 'de' ? 'Vorschau' : 'Preview');
$mainTemplate->setContent('MOREHEADER', '<script src="viewer.js?v=4"></script>');

// Redirect from /index.php to viewer?
if( isset( $_GET['redirFromIndex'] ) ){
	$viewTemplate->setContent('NOTESTYLE', '');
}

// add viewer to main and output
$mainTemplate->includeTemplate($viewTemplate);
echo $mainTemplate->output();
?>