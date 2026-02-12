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
defined('HAMARadio') or die('Invalid Endpoint');

/**
 * Authentication of Radios via Mac Parameter
 */
class Auth {

	/**
	 * Tries to auth radio request based on Mac parameter and returns a RadioID Object
	 * @param $redirectGui redirect to Gui Login, if not successful
	 */
	public static function authFromMac(bool $redirectGui = false) : Id {
		try{
			if( empty($_GET['mac']) ){
				throw new Exception();
			}
			$radioid = new Id($_GET['mac']);
		}
		catch(Exception $e){
			if($redirectGui && !isset($_GET['yesStay'])){ // redirect user to gui
				header('Location:'. Config::DOMAIN .'gui/view.php?redirFromIndex');
				http_response_code(303);
				die();
			}
			Output::sendAnswer('<Error>No MAC!</Error>');
			die(); //will never be reached
		}
		return $radioid;
	}

	
	
}
?>