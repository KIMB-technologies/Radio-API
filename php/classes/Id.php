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
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * We use 3 ids per user.
 * 	- mac (radio auth parameter)
 * 	- id (internal numeric id for each user)
 * 	- code (gui login code)
 */
class Id {

	// construct types
	const ID = 1,
		MAC = 2,
		CODE = 3;

	// types form
	public const ID_PREG = '/^[0-9]{1,4}$/',
		MAC_PREG = '/^[0-9a-f]{28,40}$/',
		CODE_PREG = '/^Z[0-9A-Za-z]{4}$/';

	const CACHE_TTL = 60 * 60 * 6; // 6 hours

	private $id, $data;
		//id => podcasts files
		//mac => radio id
		//code => gui access
		//data => [mac, code]

	public static function isIdInteger(int $i) : bool {
		return $i > 0 && $i < 10_000;
	}

	public static function getTableData() : array {
		$table = null;
		if( is_file( __DIR__ . '/../data/table.json' ) ){ // load table form disk?
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);

			if(is_null($table)){ // on json error, move file and create new
				rename(__DIR__ . '/../data/table.json', __DIR__ . '/../data/table.error.json');
			}
		}
		if ( is_null($table) ) { // init empty table
			$table = array(
				'macs' => array(), // mac => id
				'ids' => array(), // id => [ mac, code ]
				'codes' => array() // code => id
			);
			// save init table
			file_put_contents( __DIR__ . '/../data/table.json', json_encode($table, JSON_PRETTY_PRINT), LOCK_EX);
		}

		return $table;
	}

	public function __construct($val, int $type = self::MAC){
		// load redis
		$redis = new Cache('table.json');
		// import table
		if( !$redis->keyExists('ids') ){
			$this->loadFileIntoRedis($redis);
		}

		// get id from given data
		if( $type === self::CODE && Helper::checkValue( $val, self::CODE_PREG ) ){
			if( $redis->arrayKeyExists('codes', $val ) ){
				$this->id = $redis->arrayKeyGet('codes', $val ); // get ID
			}
			else{
				throw new Exception('Unknown Code, use Radio Mac to create!');
			}
		}
		else if( $type === self::MAC && Helper::checkValue( $val, self::MAC_PREG ) ){
			//check if new mac
			if(  $redis->arrayKeyExists('macs', $val ) ){
				$this->id = $redis->arrayKeyGet('macs', $val ); // get ID
			}
			else{
				$this->id = $this->generateNewId($val, $redis); 
			}
		}
		else if( $type === self::ID && Helper::checkValue( $val, self::ID_PREG ) ){
			$this->id = $val;
		}
		else{
			throw new Exception('Invalid Format');
		}

		//load this data by id
		if( $redis->arrayKeyExists('ids', $this->id ) ){
			$this->data = $redis->arrayKeyGet('ids', $this->id );
		}
		else{
			throw new Exception('Unknown ID, use Radio Mac to create!');
		}
	}

	public function getId() : int {
		return $this->id;
	}

	public function getMac() : string {
		return $this->data[0];
	}

	public function getCode() : string {
		return $this->data[1];
	}

	private function loadFileIntoRedis(Cache $redis) : void {
		$table = self::getTableData();

		// set also in redis
		$redis->arraySet('macs', $table['macs'], self::CACHE_TTL);
		$redis->arraySet('ids', $table['ids'], self::CACHE_TTL);
		$redis->arraySet('codes', $table['codes'], self::CACHE_TTL);
	}

	private function generateNewId( string $val, Cache $redis ) : int { 
		//	Load file, as file it the primary storage
		$table = self::getTableData();

		// new id
		$id = count( $table['ids'] ) + 1;

		// new code
		do{
			$code =  'Z' . Helper::randomCode( 4 );
		} while( isset( $table['codes'][$code] ) );

		// alter table
		$table['ids'][$id] = array(
			// mac, code
			$val, $code
		);
		$table['macs'][$val] = $id;
		$table['codes'][$code] = $id;

		// save new table
		//	File
		file_put_contents( __DIR__ . '/../data/table.json', json_encode($table, JSON_PRETTY_PRINT), LOCK_EX);
		//	Redis
		$redis->arraySet('macs', $table['macs'], self::CACHE_TTL);
		$redis->arraySet('ids', $table['ids'], self::CACHE_TTL);
		$redis->arraySet('codes', $table['codes'], self::CACHE_TTL);

		return $id;
	}
}

?>