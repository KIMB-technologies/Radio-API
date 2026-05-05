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

	private ?string $clientMac = null;
	private ?string $clientRid = null;
	private ClientType $clientType;

	private ?Id $radioId = null;

	function __construct() {
		// init client info
		$this->clientMac = $this->getMac();
		$this->clientRid = $this->getRadioID();
		$this->clientType = $this->identifyClient();

	}

	/**
	 * Tries to get the Mac from the GET Parameter of old Radios
	 * @return string|null the Mac string or null if not found
	 */
	private function getMac() : ?string {
		if(!empty($_GET['mac']) && Helper::checkValue($_GET['mac'], Id::MAC_PREG)) {
			return $_GET['mac'];
		}
		else{
			return null;
		}
	}

	/**
	 * Tries to get the RadioID from the Authorization Header of new Radios
	 * @return string|null the RadioID string or null if not found
	 */
	private function getRadioID() : ?string {
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
	 * Identify the type of client, old radio, new radio, other (human etc.)
	 * @return ClientType
	 */
	private function identifyClient() : ClientType  {
		if(!is_null($this->clientMac)){
			return ClientType::RadioXML;
		}
		else if(!is_null($this->clientRid)){
			return ClientType::RadioJSON;
		}
		else{
			return ClientType::Other;
		}
	}

	
	/**
	 * Tries to get the Mac or RadioID, depending on which is given
	 * @return string|null the Mac or RadioID string or null if not found
	 */
	public function getClientID() : ?string {
		if($this->clientType === ClientType::RadioXML){
			return $this->clientMac;
		}
		elseif($this->clientType === ClientType::RadioJSON){
			return $this->clientRid;
		}
		else{
			return null;
		}
	}

	/**
	 * Get the type of client, old radio, new radio, other (web interface, human, ... etc.)
	 */
	public function getClientType() : ClientType {
		return $this->clientType;
	}

	/**
	 * Tries to auth radio request based on Mac or RadioID parameter and returns a RadioID Object
	 * @param $redirectGui redirect to Gui Login, if not successful
	 */
	public function auth(bool $redirectGui = false) : Id {
		// return id object if already created
		if(!is_null($this->radioId)){
			return $this->radioId;
		}

		// use the auth data to init object
		if($this->clientType === ClientType::RadioXML){
			$this->radioId = new Id($this->clientMac, Id::MAC);
		}
		else if($this->clientType === ClientType::RadioJSON){
			$this->radioId = new Id($this->clientRid, Id::RID);
		}
		else{
			// no auth data, so fail			
			if($redirectGui && !isset($_GET['yesStay'])){ // redirect user to gui
				header('Location:'. Config::DOMAIN .'gui/view.php?redirFromIndex');
				http_response_code(303);
				die();
			}

			Output::sendAnswer('Unauthorized, no valid Mac or RadioID given!');
			die(); //will never be reached
		}

		return $this->radioId;
	}
	
}
?>