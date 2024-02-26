<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2023 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * The caching interface used by Radio-API.
 */
interface CacheInterface {

	/**
	 * Generate a storage
	 * @param $group The storage group, a prefix for the key.
	 */
	public function __construct(string $group);

	/**
	 * Get an array of all the keys of this group.
	 * @return The array of keys
	 */
	public function getAllKeysOfGroup() : array;

	/**
	 * Remove the entire storage Group.
	 */
	public function removeGroup() : bool ;

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
	public function set( string $key, string $value, int $ttl = 0 ): bool;

	/**
	 * Does the key exists?
	 * @param $key The key.
	 * @return The value
	 */
	public function get( string $key ) : string;

	/**
	 * Does the key exists?
	 * @param $key The key.
	 * @return exists?
	 */
	public function keyExists(string $key) : bool;

	/**
	 * Removes a key.
	 * @return successful?
	 */
	public function remove(string $key) : bool;

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
	public function arraySet( string $key, array $array, int $ttl = 0 ) : bool;

	/**
	 * Gets an array from the cache.
	 * @param $key The key of the array
	 * @return the array
	 */
	public function arrayGet( string $key ) : array;

	/**
	 * Check an array for a key.
	 * @param $key The key of the array
	 * @param $arrayKey The key of the value in the array
	 * @return Does the key exist?
	 */
	public function arrayKeyExists(string $key, string $arrayKey ) : bool;

	/**
	 * Get value of a key of an array.
	 * @param $key The key of the array
	 * @param $arrayKey The key of the value in the array
	 * @return The value
	 */
	public function arrayKeyGet(string $key, string $arrayKey );

	/**
	 * Set the value of one key in an array.
	 * @param $key The key of the array
	 * @param $arrayKey The key of the value in the array (null to append)
	 * @param $value The value to store
	 * @param $ttl The time to live for the entire array (0 => always)
	 * @return successful stored?
	 */
	public function arrayKeySet(string $key, ?string $arrayKey, $value, int $ttl = 0 ) : bool;

	/**
	 * Print all keys and values of this Group.
	 */
	public function output(): void;
}

/**
 * A class to cache values using redis or in json file.
 * 	If DOCKER_MODE=true -> use Redis, else use JSON.
 */
class Cache implements CacheInterface {

	private $s;

	public function __construct(string $group){
		$this->s = DOCKER_MODE && !Config::USE_JSON_CACHE ? new RedisCache($group) : new JSONCache($group);
	}
	public function getAllKeysOfGroup() : array {
		return $this->s->getAllKeysOfGroup();
	}

	public function removeGroup() : bool {
		return $this->s->removeGroup();
	}

	public function set( string $key, string $value, int $ttl = 0 ): bool {
		return $this->s->set( $key, $value, $ttl );
	}

	public function get( string $key ) : string {
		return $this->s->get( $key );
	}

	public function keyExists(string $key) : bool {
		return $this->s->keyExists( $key );
	}

	public function remove(string $key) : bool {
		return $this->s->remove( $key );
	}

	public function arraySet( string $key, array $array, int $ttl = 0 ) : bool {
		return $this->s->arraySet( $key, $array, $ttl );
	}

	public function arrayGet( string $key ) : array {
		return $this->s->arrayGet( $key );
	}

	public function arrayKeyExists(string $key, string $arrayKey ) : bool {
		return $this->s->arrayKeyExists( $key, $arrayKey );
	}

	public function arrayKeyGet(string $key, string $arrayKey ) {
		return $this->s->arrayKeyGet( $key, $arrayKey );
	}

	public function arrayKeySet(string $key, ?string $arrayKey, $value, int $ttl = 0 ) : bool {
		return $this->s->arrayKeySet( $key, $arrayKey, $value, $ttl);
	}

	public function output(): void {
		$this->s->output();
	}
}

?>