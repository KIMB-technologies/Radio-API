<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2026 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMARadio') or die('Invalid Endpoint');

enum ClientType {
    case RadioJSON;
    case RadioXML;
    case Other;
}

/**
 * Authentication of Radios via Mac Parameter
 */
class Auth {

	/**
	 * Tries to get the RadioID from the Authorization Header of new Radios
	 * @return string|null the RadioID string or null if not found
	 */
	public static function getRadioID() : ?string {
		if(!empty($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])){
			$id_hash = base64_decode($_SERVER['HTTP_AUTHORIZATION'], true);
			if($id_hash !== false && strpos($id_hash, ':') !== false){
				$rid = explode(':', $id_hash)[0];
				if(Helper::checkValue($rid, Id::RID_PREG )){
					return $rid;
				}
			}
		}
		return null;
	}

	/**
	 * Tries to get the Mac from the GET Parameter of old Radios
	 * @return string|null the Mac string or null if not found
	 */
	public static function getMac() : ?string {
		if( !empty($_GET['mac']) && Helper::checkValue( $_GET['mac'], Id::MAC_PREG ) ) {
			return $_GET['mac'];
		}
		else{
			return null;
		}
	}

	/**
	 * Tries to get the Mac or RadioID, depending on which is given
	 * @return string|null the Mac or RadioID string or null if not found
	 */
	public static function getMacRID() : ?string {
		$mac = self::getMac();
		if(!is_null($mac)){
			return $mac;
		}

		$rid = self::getRadioID();
		if(!is_null($rid)){
			return $rid;
		}
		
		return null;
	}

	/**
	 * Identify the type of client, old radio, new radio, other (human etc.)
	 * @return ClientType
	 */
	public static function identifyClient() : ClientType  {
		if(!empty($_GET['mac']) && Helper::checkValue($_GET['mac'], Id::MAC_PREG )){
			return ClientType::RadioXML;
		}
		else if(!empty($_SERVER['HTTP_AUTHORIZATION'])){
			$rid = self::getRadioID(); // try to get rid from header, if possible
			if(!is_null($rid)){
				return ClientType::RadioJSON;
			}
		}
		return ClientType::Other;
	}


	/**
	 * Tries to auth radio request based on Mac or RadioID parameter and returns a RadioID Object
	 * @param $redirectGui redirect to Gui Login, if not successful
	 */
	public static function authFromAny(bool $redirectGui = false) : Id {
		$clientType = Auth::identifyClient();
		$failed = false;

		if($clientType === ClientType::RadioXML){
			$mac = self::getMac();
			if(is_null($mac)){
				$failed = true;
				$msg = '<Error>No MAC!</Error>';
			}
			else{
				$radioid = new Id($mac);
			}
		}
		else if($clientType === ClientType::RadioJSON){
			$rid = self::getRadioID();
			
			if(is_null($rid)){
				$failed = true;
				$msg = '{ "error": "No Authorization Header!" }';
			}
			else{
				// TODO!!!
				$radioid = null;
			}
		}

		if($failed){
			if($redirectGui && !isset($_GET['yesStay'])){ // redirect user to gui
				header('Location:'. Config::DOMAIN .'gui/view.php?redirFromIndex');
				http_response_code(303);
				die();
			}
			Output::sendAnswer($msg);
			die(); //will never be reached
		}

		return $radioid;
	}
	
}
?>