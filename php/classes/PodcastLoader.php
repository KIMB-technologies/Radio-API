<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class PodcastLoader {

	private static $memstore = array();

	private static function loadFromNextcloud( string $url ) : array{
		$server = Config::NEXTCLOUD;
		$share = substr( $url, strlen(Config::NEXTCLOUD)+2 );
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

				$poddata['episodes'][$eid] = array(
					'title' => $fina,
					'desc'  => '',
					'url' => $server . 's/'. $share .'/download?path=%2F&files=' . urlencode( $fina )
				);
				$eid++;
			}
		}

		return $poddata;
	}

	private static function loadFromFeed( string $url ) : array{
		$rss = file_get_contents( $url );
		$data = json_decode(json_encode( simplexml_load_string( $rss, 'SimpleXMLElement', LIBXML_NOCDATA ) ), true );
				
		$poddata = array(
			'title' => isset($data['channel']['title']) ? $data['channel']['title'] : '',
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

	public static function getPodcastByUrl( string $url ){
		$cachefile = __DIR__ . '/../data/cache/' . sha1( $url ) . '.json';
		if( isset( self::$memstore[sha1($url)] ) ){
			return self::$memstore[sha1($url)];
		}
		else if( is_file( $cachefile ) && filemtime($cachefile) >= time() - Config::CACHE_EXPIRE ){
			return json_decode( file_get_contents( $cachefile ), true);	
		}

		$poddata = substr( $url, 0, strlen(Config::NEXTCLOUD) ) == Config::NEXTCLOUD ? self::loadFromNextcloud( $url ) : self::loadFromFeed( $url );

		self::$memstore[sha1($url)] = $poddata;
		file_put_contents( $cachefile, json_encode( $poddata ) );
		return $poddata;
	}

	public static function getEpisodeData( int $id, int $eid, Data $data ) : array{
		$pod = $data->getById( $id );
		if( $pod['cid'] !== 3 ){
			return array();
		}
		$poddata = self::getPodcastByUrl( $pod['url'] );

		if( isset( $poddata['episodes'][$eid] ) ){
			//ok
			return array(
				'episode' => $poddata['episodes'][$eid],
				'title' => $poddata['title'],
				'logo' =>  $poddata['logo'],
				'finalurl' => !empty($pod['finalurl'])
			);
		} 
		else{
			return array();
		}
	}

	public static function getPodcastDataById( int $id, Data $data ) : array {
		$pod = $data->getById( $id );
		if( $pod['cid'] !== 3 ){
			return array();
		}
		return self::getPodcastByUrl( $pod['url'] );
	}
}		
?>