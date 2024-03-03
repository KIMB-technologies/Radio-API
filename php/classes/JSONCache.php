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
 * A class to cache values using json files.
 */
class JSONCache implements CacheInterface {

	const BASE_DIR = Config::CACHE_DIR;

	private $file, $data, $cleanupRan = false;

	public function __construct($group){
		if(!is_dir(self::BASE_DIR)){
			mkdir(self::BASE_DIR);
		}
		if(!is_writable(self::BASE_DIR)){
			throw new Exception('Unable to write to cache directory "'. self::BASE_DIR .'"!');
		}

		$this->file = self::BASE_DIR . '/' . sha1($group) . '.json';
		$data = null;
		if(is_file($this->file)){
			$data = json_decode(file_get_contents($this->file), true);
			if(is_null($data)){ 
				rename($this->file, $this->file . '.error');
			}
		}

		if(is_null($data)){
			$this->data = array();
			$this->writeFile();
		}
		else{
			$this->data = $data;
		}

		if(!is_writable($this->file)){
			throw new Exception('Unable to write to cache file "'. $this->file .'"!');
		}
	}

	private function writeFile() : bool {
		if( !$this->cleanupRan ){
			$oldKeys = array_diff(
				array_keys($this->data),
				$this->getAllKeysOfGroup()
			);
			foreach($oldKeys as $oK){
				unset($this->data[$oK]);
			}
			$this->cleanupRan = true;
		}
		return file_put_contents(
			$this->file,
			json_encode($this->data, JSON_PRETTY_PRINT),
			LOCK_EX
		);
	}

	public function getAllKeysOfGroup() : array {
		return array_values(array_filter(
			array_keys($this->data),
			fn($k) => $this->keyExists($k)
		));
	}

	public function removeGroup() : bool {
		$this->data = array();
		return $this->writeFile();
	}

	public function set( string $key, array|string $value, int $ttl = 0 ): bool {
		$this->data[$key] = array(
			$value,
			$ttl === 0 ? true : (time() + $ttl)
		);
		return $this->writeFile();
	}

	public function get( string $key ) : string {
		return $this->keyExists($key) ? $this->data[$key][0] : '';
	}

	public function keyExists(string $key) : bool {
		return isset($this->data[$key]) && ($this->data[$key][1] === true || time() <= $this->data[$key][1]);
	}

	public function remove(string $key) : bool {
		unset($this->data[$key]);
		return $this->writeFile();
	}

	// # # # # #
	// Key => Array (HashMap)
	// # # # # #

	public function arraySet( string $key, array $array, int $ttl = 0 ) : bool {
		$d = array();
		foreach( $array as $k => $v ){
			$d[strval($k)] = $v;
		}
		return $this->set($key, $d, $ttl);
	}

	public function arrayGet( string $key ) : array {
		return $this->keyExists($key) ? $this->data[$key][0] : array();
	}

	public function arrayKeyExists(string $key, string $arrayKey ) : bool {
		return $this->keyExists($key) && isset($this->data[$key][0][$arrayKey]);
	}

	public function arrayKeyGet(string $key, string $arrayKey ) {
		return $this->arrayGet($key)[$arrayKey];
	}

	public function arrayKeySet(string $key, ?string $arrayKey, $value, int $ttl = 0 ) : bool {
		$d = $this->arrayGet($key);
		if( $arrayKey === null ){
			$d[] = $value;
		}
		else{
			$d[$arrayKey] = $value;
		}
		return $this->set($key, $d, $ttl);
	}

	public function output(): void {
		echo '=================================' . PHP_EOL;
		foreach($this->getAllKeysOfGroup() as $key){
			echo $key . "\t\t : " . (is_array( $this->data[$key][0]) ? $this->arrayGet($key) : $this->get($key)) . PHP_EOL;
		}
		echo '=================================' . PHP_EOL . PHP_EOL;
	}
}

?>