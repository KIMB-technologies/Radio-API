<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Id {

	// construct types
	const ID = 1,
		MAC = 2,
		CODE = 3;

	// types form
	const ID_PREG = '/^[0-9]{1,4}$/',
		MAC_PREG = '/^[0-9a-f]{28,40}$/',
		CODE_PREG = '/^Z[0-9A-Za-z]{4}$/';

	private static function checkValue($val, $preg){
		return is_string($val) && preg_match( $preg, $val ) === 1;
	}
	public static function randomCode( $len ){
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY';
		$r = '';
		$charAnz = strlen( $chars );
		for($i = 1; $i < $len; $i++){
			$r .= $chars{random_int(0, $charAnz-1)};
		}
		return 'Z' . $r;
	}

	private $id, $data;
		//id => podcasts files
		//mac => radio id
		//code => gui access
		//data => [mac, code]

	public function __construct($val, $type = self::MAC){
		// import table
		if( is_file( __DIR__ . '/../data/table.json' ) ){
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);
		} else {
			$table = array(
				'macs' => array(), // mac => id
				'ids' => array(), // id => [ mac, code ]
				'codes' => array() // code => id
			);
		}

		// get id from given data
		if( $type === self::CODE && self::checkValue( $val, self::CODE_PREG ) ){
			if( isset($table['codes'][$val] ) ){
				$this->id = $table['codes'][$val]; // get ID
			}
			else{
				throw new Exception('Unknown Code, use Radio Mac to create!');
			}
		}
		else if( $type === self::MAC && self::checkValue( $val, self::MAC_PREG ) ){
			//check if new mac
			if( isset($table['macs'][$val] ) ){
				$this->id = $table['macs'][$val]; // get ID
			}
			else{
				// new id
				$this->id = count( $table['ids'] ) + 1;
				// new code
				do{
					$code = randomCode( 5 );
				} while( isset( $table['codes'][$code] ) );
				$table['ids'][$this->id] = array(
					// mac, code
					$val, $code
				);
				$table['macs'][$val] = $this->id;
				$table['codes'][$code] = $this->id;
				// save new table
				file_put_contents( __DIR__ . '/../data/table.json', json_encode($table, JSON_PRETTY_PRINT));
			}
		}
		else if( $type === self::ID && self::checkValue( $val, self::ID_PREG ) ){
			$this->id = $val;
		}
		else{
			throw new Exception('Invalid MAC Format');
		}

		//load this data by id
		if( isset( $table['ids'][$this->id] )){
			$this->data = $table['ids'][$this->id];
		}
		else{
			throw new Exception('Unknown ID, use Radio Mac to create!');
		}
	}


	public function getId(){
		return $this->id;
	}

	public function getMac(){
		return $this->data[0];
	}

	public function getCode(){
		return $this->data[1];
	}
}

?>