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

// Load System
require_once(__DIR__ . '/../classes/autoload.php');
// Parse Language and change
Template::detectLanguage();

// Load Templates
$mainTemplate = new Template('main');
$imExTemplate = new Template('imexport');

$mainTemplate->setContent('TITLE', 'Import & Export');
$mainTemplate->setContent('MOREHEADER', '<script src="im-export.js?'.Config::VERSION.'"></script>');
if(Config::updateAvailable()){
	$mainTemplate->setContent('UPDATEINFO', '');
}

// Im- & Export feature activated?
if( Config::IM_EXPORT_TOKEN ){
	if(!empty($_REQUEST['token']) && $_REQUEST['token'] === Config::IM_EXPORT_TOKEN){
		$imExTemplate->setContent('MESSAGEENABLE', '');	

		// run the im- or export
		$ie = new ImExport();

		if($_REQUEST['task'] === 'import'){
			$ok = true;

			if(empty($_FILES["export"]["tmp_name"])){
				$ok = false;
				$msg = Template::getLanguage() == 'de' ? 'Keine Export-Datei!' : 'No export file!';
			}

			if(
				!empty($_POST["kind"]) && $_POST["kind"] === "replace" &&
				( empty($_POST["replace"]) || $_POST["replace"] !== "yes" )
			){
				$ok = false;
				$msg = Template::getLanguage() == 'de' ? 'Bestätigung alles zu überschreiben fehlt!' : 'Confirmation to overwrite all is missing!';
			}

			if($ok){
				$ok = $ie->import(
					$_FILES["export"]["tmp_name"],
					!empty($_POST["kind"]) && is_string($_POST["kind"]) ? $_POST["kind"] : "error",
					!empty($_POST["code-backup"]) && is_string($_POST["code-backup"]) ? $_POST["code-backup"] : null,
					!empty($_POST["code-system"]) && is_string($_POST["code-system"]) ? $_POST["code-system"] : null
				);
				$msg = $ie->getMsg();
			}
			
			$msg = '<h4>' . ($ok ? 
						(Template::getLanguage() == 'de' ? 'Erfolg' : 'Success' )
					:
						(Template::getLanguage() == 'de' ? 'Fehler' : 'Error' )
				) . '</h4>' .  $msg;
			
		}
		else if($_REQUEST['task'] === 'export'){
			$ie->export();
			die();
		}
		else{
			$msg = Template::getLanguage() == 'de' ? 'Kein oder unbekannter Task!' : 'No or unknown task!';
		}
		$imExTemplate->setContent('MESSAGE', $msg);
	}
	
	$imExTemplate->setContent('FEATUREENABLED', '');
}
else{
	$imExTemplate->setContent('FEATUREDISABLED', '');
	$imExTemplate->setContent('EXAMPLETOKEN', Helper::randomCode(20));
}

// add im-export to main and output
$mainTemplate->includeTemplate($imExTemplate);
echo $mainTemplate->output();
?>