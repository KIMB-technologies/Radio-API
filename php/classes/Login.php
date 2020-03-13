<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2020 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMA-Radio') or die('Invalid Endpoint');

class Login {

	public function __construct(){
		Config::checkAccess();
		session_name('RADIO-API');
		session_start();

		if( $this->isLoggedIn() ){
			$_SESSION['login_time'] = time();
		}
		else{
			$_SESSION['login'] = false;
		}
	}

	public function isLoggedIn() : bool {
		return isset($_SESSION['login']) && isset( $_SESSION['db_all'] ) &&
			$_SESSION['login'] && $_SESSION['login_time'] + 600 > time();
	}

	public function getId() : int {
		return $_SESSION['db_all']['id'];
	}

	public function getAll() : array {
		return $_SESSION['db_all'];
	}

	public function loginByCode(string $code) : void {
		try{
			$id = new Id( $code, Id::CODE );
			$_SESSION['login'] = true;
			$_SESSION['login_time'] = time();
			$_SESSION['db_all'] = array(
				'mac' => $id->getMac(),
				'id' => $id->getId(),
				'code' => $id->getCode(),
			);
		}
		catch(Exception $e){
			$_SESSION['login'] = false;
		}
	}

	public function logout() : void {
		$_SESSION['login'] = false;
	}
}
?>