<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * A class to cache values using redis.
 * Supporting single values and HashMap based arrays.
 * Also supporting TimeToLive for all values.
 */
class RedisCache {

	/**
	 * Redis Settings
	 */
	private static $host = '127.0.0.1',
		$port = 6379,
		$auth = null;

	/**
	 * Set the details of the redis server.
	 * @param $host the host of the server (default 127.0.0.1)
	 * @param $port the port to use (default 6379)
	 * @param $auth the password to use (default no auth)
	 */
	public static function setRedisServer($host, $port = 6379, $auth = null){
		self::$host = $host;
		self::$port = $port;
		self::$auth = $auth;
	}

	private $redis, $prefix;

	/**
	 * Generate a storage
	 * @param $group The storage group, a prefix for the key.
	 */
	public function __construct($group){
		$this->redis = new Redis();
		$this->redis->pconnect(self::$host, self::$port);
		if( !empty(self::$auth) ){
			$this->redis->auth(self::$auth);
		}

		if( !$this->redis->ping() ){
			throw new Exception('Unable to connect to Redis Server!');
		}

		$this->prefix = base64_encode(hash('sha512', strtolower($group), true)) . ':';
	}

	/**
	 * Generate the key for some storage.
	 * @param $key the key
	 * @return the full key
	 */
	private function generateKey( string $key ) : string {
		return $this->prefix . str_replace( ':', '', $key );
	}

	/**
	 * Get an array of all the keys of this group.
	 * @return The array of keys
	 */
	public function getAllKeysOfGroup() : array {
		$all = array();
		$lenpref = strlen($this->prefix);
		$iterator = NULL;
		do {
			$keys = $this->redis->scan($iterator);
			if ($keys !== FALSE) {
				$all = array_merge( $all, array_filter( $keys, function( $k ) use ($lenpref){
					return substr($k, 0, $lenpref) == $this->prefix;
				}));
			}
		} while ($iterator > 0);
		return $all;
	}

	/**
	 * Remove the entire storage Group.
	 */
	public function removeGroup() : bool {
		$dels = $this->getAllKeysOfGroup();
		return $this->redis->unlink($dels) == count($dels);
	}

	// # # # # #
	// Key => Value 
	// # # # # #

	/**
	 * Does the key exist?
	 * @param $key The key.
	 * @param $value The value to store.
	 * @param The time to live for the value.
	 * @return The value
	 */
	public function set( string $key, string $value, int $ttl = 0 ): bool {
		$r = $this->redis->set( $this->generateKey($key), $value );
		if( $ttl !== 0){
			$this->redis->expire($this->generateKey($key), $ttl);	
		}
		return $r;
	}

	/**
	 * Does the key exists?
	 * @param $key The key.
	 * @return The value
	 */
	public function get( string $key ) : string {
		return $this->redis->get($this->generateKey($key));
	}

	/**
	 * Does the key exists?
	 * @param $key The key.
	 * @return exists?
	 */
	public function keyExists(string $key) : bool {
		return $this->redis->exists($this->generateKey($key));
	}

	/**
	 * Removes a key.
	 * @return successful?
	 */
	public function remove(string $key) : bool {
		return $this->redis->del($this->generateKey($key)) == 1;
	}

	// # # # # #
	// Key => Array (HashMap)
	// # # # # #

	/**
	 * Sets an array into the cache.
	 * 	We do a json_encode on deep arrays!
	 * @param $key The key of the array
	 * @param $array The array 
	 * @param $ttl The time to live for the array (0 => always)
	 * @return successful stored?
	 */
	public function arraySet( string $key, array $array, int $ttl = 0 ) : bool {
		$this->remove( $key );
		$d = array();
		foreach( $array as $k => $v ){
			$d[strval($k)] = json_encode( $v );
		}
		$r = $this->redis->hMSet( $this->generateKey($key), $d);
		if( $ttl !== 0){
			$this->redis->expire( $this->generateKey($key), $ttl);	
		}
		return $r;
	}

	/**
	 * Gets an array from the cache.
	 * @param $key The key of the array
	 * @return the array
	 */
	public function arrayGet( string $key ) : array {
		return array_map( function ($v){
				return json_decode($v, true);
			}, $this->redis->hGetAll($this->generateKey($key))
		);
	}

	/**
	 * Check an array for a key.
	 * @param $key The key of the array
	 * @param $arrayKey The key of the value in the array
	 * @return Does the key exist?
	 */
	public function arrayKeyExists(string $key, string $arrayKey ) : bool {
		return $this->redis->hExists($this->generateKey($key), strval($arrayKey));
	}

	/**
	 * Get value of a key of an array.
	 * @param $key The key of the array
	 * @param $arrayKey The key of the value in the array
	 * @return The value
	 */
	public function arrayKeyGet(string $key, string $arrayKey ) {
		return json_decode( $this->redis->hGet($this->generateKey($key), strval($arrayKey)), true);
	}

	/**
	 * Set the value of one key in an array.
	 * @param $key The key of the array
	 * @param $arrayKey The key of the value in the array (null to append)
	 * @param $value The value to store
	 * @param $ttl The time to live for the entire array (0 => always)
	 * @return successful stored?
	 */
	public function arrayKeySet(string $key, ?string $arrayKey, $value, int $ttl = 0 ) : bool {
		if( $arrayKey === null ){
			$arrayKey = $this->redis->hLen($this->generateKey($key)) + 1;
		}
		$r = $this->redis->hSet( $this->generateKey($key), strval($arrayKey), json_encode($value));
		if( $ttl !== 0){
			$this->redis->expire($this->generateKey($key), $ttl);	
		}
		return $r;
	}

	/**
	 * Print all keys and values of this Group.
	 */
	public function output(): void {
		echo '=================================' . PHP_EOL;
		echo 'Key' . "\t\t : " . 'Value' . PHP_EOL;
		echo '---------------------------------' . PHP_EOL;
		$lenpref = strlen($this->prefix);
		foreach( $this->getAllKeysOfGroup() as $fullkey ){
			$key = substr($fullkey, $lenpref);
			if( $this->redis->type($fullkey) !== Redis::REDIS_HASH ){
				$val = $this->get($key);
			}
			else {
				$val = json_encode($this->arrayGet($key));
			}
			echo $key . "\t\t : " . $val . PHP_EOL;
		}
		echo '=================================' . PHP_EOL . PHP_EOL;
	}
}

?>