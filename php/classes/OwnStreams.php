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
 * Manager class for Own Streams
 */
class OwnStreams {

	const IS_ACTIVE = Config::STREAM_JSON !== false;

	private RedisCache $redis;

	public function __construct(){
		if( self::IS_ACTIVE ){
			$this->redis = new RedisCache( 'own_stream' );
		}

		$this->redis->removeGroup(); // remove before version commit !!
	}

	public function getStreams() : array {
		if( !self::IS_ACTIVE ){
			return array();
		}

		if( $this->redis->keyExists( 'list' ) ){
			return $this->redis->arrayGet( 'list' );
		}
		
		$data = file_get_contents( Config::STREAM_JSON );
		$streams = array();

		if( !empty($data) ){
			$list = json_decode( $data, true );
			if( !empty( $list ) ){

				foreach( $list as $item ){

					$urlOK = isset( $item['url'] ) && filter_var($item['url'], FILTER_VALIDATE_URL) !== false;
					$streams[] = array(
						"name" => (!$urlOK ? "(url invalid!)" : '')
							. (isset( $item['name'] ) && is_string($item['name']) ? $item["name"]  : 'Invalid Name'),
						"url" =>  $urlOK ? $item['url'] : '',
						"live" => isset($item['live']) && is_bool($item['live']) ? $item['live'] : true,
						"proxy" => isset($item['proxy']) && is_bool($item['proxy']) ? $item['proxy'] : false
					);

				}
				$this->redis->arraySet( 'list', $list, Config::CACHE_EXPIRE );
			}
		}

		if(empty($streams)){
			$streams[] = array(	
				"name" => 'No Stream Items found or error on JSON parse.',
				"url" => "",
				"live" => true,
				"proxy" => false
			);
		}
		
		return $streams;
	}
}

?>