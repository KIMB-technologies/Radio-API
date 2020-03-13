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

	private $id, $data;
		//id => podcasts files
		//mac => radio id
		//code => gui access
		//data => [mac, code]

	public function __construct($val, int $type = self::MAC){
		// load redis
		$redis = new RedisCache('table.json');
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

	private function loadFileIntoRedis(RedisCache $redis) : void {
		if( is_file( __DIR__ . '/../data/table.json' ) ){ // load table form disk?
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);
		}
		else { // init empty table
			$table = array(
				'macs' => array(), // mac => id
				'ids' => array(), // id => [ mac, code ]
				'codes' => array() // code => id
			);
			// save init table
			file_put_contents( __DIR__ . '/../data/table.json', json_encode($table, JSON_PRETTY_PRINT));
		}
		// set also in redis
		$redis->arraySet('macs', $table['macs']);
		$redis->arraySet('ids', $table['ids']);
		$redis->arraySet('codes', $table['codes']);
	}

	private function generateNewId( string $val, RedisCache $redis ) : int { 
		//	Load file, as file it the primary storage
		$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);

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
		file_put_contents( __DIR__ . '/../data/table.json', json_encode($table, JSON_PRETTY_PRINT));
		//	Redis
		$redis->arraySet('macs', $table['macs']);
		$redis->arraySet('ids', $table['ids']);
		$redis->arraySet('codes', $table['codes']);

		return $id;
	}
}

?>