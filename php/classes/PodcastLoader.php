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
 * Loads Podcasts from RSS or Nextcloud links, caches and parses them
 */
class PodcastLoader {

	/**
	 * Redis Cache of Podcasts
	 */
	private static $redis = null;

	/**
	 * Redis Setup
	 */
	private static function loadRedis(){
		if( is_null(self::$redis) ){
			self::$redis = new RedisCache('podcast_loader');
		}
	}

	/**
	 * Nextcloud share loader
	 */
	private static function loadFromNextcloud( string $url ) : array {
		$server = substr( $url, 0, strrpos( $url , '/s/' )+1 );
		$share = substr( $url, strlen($server)+2 );
		$share = preg_replace( '/[^0-9A-Za-z]/', '', $share );

		$cont = stream_context_create( array(
			"http" => array(
				'method' => "PROPFIND",
				"header" => "Authorization: Basic " . base64_encode($share . ':')
			)
		));
		$data = file_get_contents( $server . 'public.php/webdav/', false, $cont );
		$data = explode( '<d:href>', $data );
		
		$poddata = array(
			'title' => '',
			'logo' => '',
			'episodes' => array()
		);
		$eid = 1;

		foreach($data as $d){
			if( strpos( $d, 'audio' ) !== false ){
				$fina = substr( $d, 0, strpos( $d, '</d:href>') );
				$fina = substr( $fina, strrpos( $fina, '/' ) + 1 );
				$fina = urldecode( $fina );

				$poddata['episodes'][$eid] = array(
					'title' => $fina,
					'desc'  => '',
					'url' => $server . 's/'. $share .'/download?path=%2F&files=' . rawurlencode( $fina ) 
				);
				$eid++;
			}
		}

		return $poddata;
	}

	/**
	 * RSS/ Atom loader
	 */
	private static function loadFromFeed( string $url ) : array{
		$rss = file_get_contents( $url );
		$data = json_decode(json_encode( simplexml_load_string( $rss, 'SimpleXMLElement', LIBXML_NOCDATA ) ), true );
				
		$poddata = array(
			'title' => $data['channel']['title'] ?? '',
			'logo' => isset( $data['channel']['image'] ) ? $data['channel']['image']['url'] : '',
			'episodes' => array()
		);

		$eid = 1;

		if( isset( $data['channel']['item']['enclosure'] ) ){ // one item is like "item" : { ... }, but should be like "item" : [ { ... } ]
			$data['channel']['item'] = array( $data['channel']['item'] );
		}

		foreach( $data['channel']['item'] as $item ){
			if( !empty( $item['enclosure'] ) ){
			
				if( count( $item['enclosure'] ) > 1){
					$url = '';
					foreach( $item['enclosure'] as $en ){
						if( substr( $en['@attributes']['type'], 0, 5) == 'audio' ){
							$url = $en['@attributes']['url'];
							break;
						}
					}
					if( empty($url) ){
						$url = $item['enclosure'][0]['@attributes']['url'];
					}
				}
				else{
					$url = $item['enclosure']['@attributes']['url'];
				}

				$poddata['episodes'][$eid] = array(
					'title' => empty( $item['title'] ) ? 'Unnamed' :  $item['title'],
					'desc'  => empty( $item['description'] ) ? '' :  $item['description'] ,
					'url' => $url 
				);
				$eid++;
			}
		}
		return $poddata;
	}

	/**
	 * Podcast from url loader, using the cache
	 */
	public static function getPodcastByUrl( string $url, bool $nextcloud ) : array {
		self::loadRedis();
		if( self::$redis->keyExists( 'url.' . sha1($url) ) ){
			return self::$redis->arrayGet( 'url.' . sha1($url) );
		}

		$poddata = $nextcloud ? self::loadFromNextcloud( $url ) : self::loadFromFeed( $url );
		self::$redis->arraySet( 'url.' . sha1($url), $poddata, Config::CACHE_EXPIRE );
		return $poddata;
	}

	/**
	 * Get informations about one episode
	 */
	public static function getEpisodeData( int $id, int $eid, Data $data ) : array{
		$pod = $data->getById( $id );
		if( $pod['cid'] !== 3 ){
			return array();
		}
		$poddata = self::getPodcastByUrl( $pod['url'], !empty($pod['type']) && $pod['type'] == 'nc' );

		if( isset( $poddata['episodes'][$eid] ) ){
			//ok
			return array(
				'episode' => $poddata['episodes'][$eid],
				'title' => $poddata['title'],
				'logo' =>  $poddata['logo'],
				'finalurl' => !empty($pod['finalurl']),
				'proxy' => !empty($pod['proxy']),
				'type' => !empty($pod['type']) && $pod['type'] == 'nc' ? 'nc' : 'rss'
			);
		} 
		else{
			return array();
		}
	}

	/**
	 * Get the Podcast by its ID
	 */
	public static function getPodcastDataById( int $id, Data $data ) : array {
		$pod = $data->getById( $id );
		if( $pod['cid'] !== 3 ){
			return array();
		}
		return self::getPodcastByUrl( $pod['url'], !empty($pod['type']) && $pod['type'] == 'nc' );
	}

	/**
	 * Get nextcloud url list from nextcloud radio station ID
	 */
	public static function getMusicById( int $id, Data $data ) : array {
		$stat = $data->getById($id);
		if( !empty($stat) && $stat['type'] == 'nc' ){ // is a nextcloud station
			self::loadRedis();
			if( !self::$redis->keyExists( 'm3u.' .  $id ) ){
				$musik = self::getPodcastByUrl( $stat['url'], true )['episodes'];
				$urllist = array();
				foreach( $musik as $m ){
					$urllist[] = $m['url'];
				}
				self::$redis->arraySet( 'm3u.' . $id, $urllist, Config::CACHE_EXPIRE );
			}
			return self::$redis->arrayGet( 'm3u.' . $id );
		}
		else{
			return array();
		}
	}
}		
?>