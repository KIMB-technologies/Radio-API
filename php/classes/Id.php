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

/**
 * We use 4 ids per user.
 * 	- mac (radio auth parameter, XML API)
 * 	- rid (radio id for new radios, JSON API)
 * 	- id (internal numeric id for each user)
 * 	- code (gui login code)
 * The class Id is responsible for managing these ids and their relations. Each radio will have mac
 * and rid, for older radios a rid is generated on first connect, for newer a mac.
 */
class Id {

	// construct types
	const ID = 1,
		MAC = 2,
		CODE = 3,
		RID = 4;

	// types form
	public const ID_PREG = '/^[0-9]{1,4}$/',
		MAC_PREG = '/^[0-9a-f]{28,40}$/', // mac of old radios (XML) 
		RID_PREG = '/^[0-9A-Z]{10,20}$/', // radio id of new radios (JSON)
		CODE_PREG = '/^Z[0-9A-Za-z]{4}$/';

	const CACHE_TTL = 60 * 60 * 6; // 6 hours

	private $id, $data;
		//id => podcasts files
		//mac => radio id
		//code => gui access
		//data => [mac, code, rid]

	public static function isIdInteger(int $i) : bool {
		return $i > 0 && $i < 10_000;
	}

	public static function getTableData() : array {
		$table = null;
		$changed = false;

		if( is_file( __DIR__ . '/../data/table.json' ) ){ // load table from disk?
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);

			if(is_null($table)){ // on json error, move file (as backup) and init new table
				rename(__DIR__ . '/../data/table.json', __DIR__ . '/../data/table.error.json');
				$changed = true;
			}
		}

		 // init empty table
		if(is_null($table)) {
			$table = array();
			$changed = true;
		}

		// assure the required keys 
		foreach( ['macs', 'rids', 'ids', 'codes'] as $key ){
			// 'macs': mac => id
			// 'rids': rid => id
			// 'ids': id => [ mac, code, rid ]
			// 'codes': code => id

			if( !isset($table[$key]) || !is_array($table[$key]) ){
				$table[$key] = array();
				$changed = true;
			}
		}

		if($changed){
			// save changed table
			self::updateTableData($table);
		}

		return $table;
	}

	private static function updateTableData(array $table,
		array $keys = ['macs', 'ids', 'codes', 'rids'], ?Cache $redis = null
	){
		// save  table file
		file_put_contents( __DIR__ . '/../data/table.json', json_encode($table, JSON_PRETTY_PRINT), LOCK_EX);

		// update redis cache
		foreach(['macs', 'ids', 'codes', 'rids'] as $key){
			if(in_array($key, $keys)){ // update each cache if requested

				if(is_null($redis)){ // load redis if not loaded
					$redis = new Cache('table.json');
				}

				$redis->arraySet($key, $table[$key], self::CACHE_TTL);
			}
		}
	}

	// merge the radio $id with the radio using $code, s.t., radio $id uses the lists of $code
	public static function mergeRadios(int $id, string $code) : bool {
		// merge changes the id in the table.json lookups for 'macs' and 'rids'

		// get table from file, as file is the primary storage
		$table = self::getTableData();

		if(isset($table['ids'][$id]) && isset($table['codes'][$code]) ){
			// id of the radio with $code
			$code_id = $table['codes'][$code];

			// radio infos (mac and rid) to merge to $code
			$data = $table['ids'][$id];

			// tamper
			$table['macs'][$data[0]] = $code_id;
			$table['rids'][$data[2]] = $code_id;
			//	and save
			self::updateTableData($table, ['macs', 'rids']);

			return true;
		}
		else{
			return false;
		}

	}

	// remove any merge for the radio with $id
	public static function unmerge(int $id) : bool {
		// merge tampers the lookup of 'macs' and 'rids' in table.json, restore from 'ids'

		// get table from file, as file is the primary storage
		$table = self::getTableData();

		if(isset($table['ids'][$id])){
			$data = $table['ids'][$id];

			// restore
			$table['macs'][$data[0]] = $id;
			$table['rids'][$data[2]] = $id;
			//	and save
			self::updateTableData($table, ['macs', 'rids']);

			return true;
		}
		else{
			return false;
		}
	}

	public function __construct(string $val, int $type = self::MAC){
		// load redis
		$redis = new Cache('table.json');

		// import table
		if( !$redis->keyExists('ids') ){
			$this->loadFileIntoRedis($redis);
		}

		// get the radioId, if not exist create new one (only by mac or rid, code only for existing radios)
		if(
			($type === self::CODE && Helper::checkValue($val, self::CODE_PREG)) ||
			($type === self::MAC && Helper::checkValue($val, self::MAC_PREG)) ||
			($type === self::RID && Helper::checkValue($val, self::RID_PREG))
		){
			$key = match($type){
				self::CODE => 'codes',
				self::MAC => 'macs',
				self::RID => 'rids',
			};
			if($redis->arrayKeyExists($key, $val)){
				$this->id = $redis->arrayKeyGet($key, $val); // get ID
			}
			else{
				if($type === self::CODE){
					throw new Exception('Unknown Code, use Radio with Mac/ Rid to create!');
				}
				else{
					$this->id = $this->generateNewId($val, $type, $redis,); 
				}	
			}
		}
		else if($type === self::ID && Helper::checkValue($val, self::ID_PREG)){
			$this->id = $val;
		}
		else{
			throw new Exception('Invalid Format');
		}

		//load this data by id
		if( $redis->arrayKeyExists('ids', strval($this->id) ) ){
			// make sure to have a rid and a mac for this radio
			$this->data = $this->assureMacRid(
				// get the data (mac, code, rid) for this id
				$redis->arrayKeyGet('ids', strval($this->id) ),
				$redis
			);
		}
		else{
			throw new Exception('Unknown ID, check input values or use Radio Mac/ Rid to create !');
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

	public function getRid() : string {
		return $this->data[2];
	}

	private function loadFileIntoRedis(Cache $redis) : void {
		$table = self::getTableData();

		// set also in redis
		$redis->arraySet('macs', $table['macs'], self::CACHE_TTL);
		$redis->arraySet('rids', $table['rids'], self::CACHE_TTL);
		$redis->arraySet('ids', $table['ids'], self::CACHE_TTL);
		$redis->arraySet('codes', $table['codes'], self::CACHE_TTL);
	}

	private function assureMacRid(array $data, Cache $redis) {
		if(count($data) < 3){
			// does not have a rid, generate one (mac is old format and always exists)
			do{
				$rid = Helper::randomCode(20, Helper::BASE36);
			} while( $redis->arrayKeyExists('rids', $rid) );

			// add to data for local use
			$data[] = $rid;

			// load file, as file is the primary storage
			$table = self::getTableData();

			// alter table
			$table['ids'][$this->id][] = $rid; 
			$table['rids'][$rid] = $this->id; 
			
			// save new table
			self::updateTableData($table, ['rids', 'ids'], $redis);
		}
		return $data;
	}

	private function generateNewId(string $val, int $type, Cache $redis) : int {
		// Load file, as file is the primary storage
		$table = self::getTableData();
		
		// make sure to have a rid and a mac for new radio, depending on the type of input
		if($type === self::MAC){
			$mac = $val;
			do{
				$rid = Helper::randomCode(20, Helper::BASE36);
			} while( isset( $table['rids'][$rid] ) );
		}
		else if($type === self::RID){
			$rid = $val;
			do{
				$mac = Helper::randomCode(40, Helper::HEX);
			} while( isset( $table['macs'][$mac] ) );
		}
		else{
			throw new Exception('Can only generate new ID from Mac or Rid!');
		}

		// new id
		$id = count( $table['ids'] ) + 1;

		// new code
		do{
			$code =  'Z' . Helper::randomCode( 4 );
		} while( isset( $table['codes'][$code] ) );

		// alter table
		$table['ids'][$id] = array(
			// mac, code, rid
			$mac, $code, $rid
		);
		$table['macs'][$mac] = $id;
		$table['rids'][$rid] = $id;
		$table['codes'][$code] = $id;

		// save new table and update all redis values
		self::updateTableData($table, redis:$redis);

		return $id;
	}
}

?>