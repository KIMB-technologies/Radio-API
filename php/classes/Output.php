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
 * Radio-API XML Style Output Class
 */
mb_substitute_character("none");
class Output {

	private static function cleanText( string $s ): string {
		return mb_substr( mb_convert_encoding(str_replace( str_split('"&<>/'), '', $s ), 'UTF-8', 'UTF-8'), 0, 100 );
	}
	private static function cleanUrl( string $s ): string {
		$url = mb_convert_encoding(str_replace( str_split('<>'), '', $s ), 'UTF-8', 'UTF-8');
		return 'http' . ( empty($_SERVER['HTTPS']) ? ':' : 's:' ) . substr( $url, strpos( $url, '//') );
	}

	private
		$items = array(),
		$itemsSortKeys = array(),
		$prevurl = '';

	const MAX_ITEMS = 200; // to many items will cause the radio to crash (one could add paging, but until then, we remove too much items)

	/**
	 * Create Outputter
	 */
	public function __construct(){
	}

	/**
	 * Add a station
	 */
	public function addStation( int $id, string $name, string $url,
						$light = false, string $desc = '', string $logo = '') : void {
		$a = array(
			'ItemType' => 'Station',
			'StationId' => $id,
			'StationName' => self::cleanText($name),
		);
		if( $light == false ){
			$logo = empty($logo) || substr($logo, 0, 4) != 'http' ? Config::DOMAIN . 'media/default.png' : $logo;
			$b = array(
				'StationUrl' => self::cleanUrl($url),
				'StationDesc' => self::cleanText($desc),
				'Logo' => self::cleanUrl($logo),
				'StationFormat' => 'Radio',
				'StationLocation' => 'Earth',
				'StationBandWidth' => 32,
				'StationMime' => 'MP3',
				'Relia' => 5
			);
		}
		else{
			$b = array();
		}
		$this->items[] = array_merge( $a, $b );
		$this->itemsSortKeys[] = 'ra==' . $name;
	}

	/**
	 * Add a podcast
	 */
	public function addPodcast( int $podcastid, string $name, string $url ) : void {
		$this->items[] = array(
			'ItemType' => 'ShowOnDemand',
			'ShowOnDemandID' => $podcastid,
			'ShowOnDemandName' => self::cleanText($name),
			'ShowOnDemandURL' => self::cleanUrl( $url ),
			'ShowOnDemandURLBackUp' => self::cleanUrl( $url ),
			'BookmarkShow' => ''
		);
		$this->itemsSortKeys[] = 'pod==' . $name;
	}

	/**
	 * Add a podcast episode
	 */
	public function addEpisode( int $podcastid, int $episodeid, string $podcastname, string $episodename,
						string $url, string $desc = '', string $logo = '', bool $top = false ) : void {
		$logo = empty($logo) || substr($logo, 0, 4) != 'http' ? Config::DOMAIN . 'media/default.png' : $logo;
		$this->items[] = array(
			'ItemType' => 'ShowEpisode',
			'ShowEpisodeID' =>  $podcastid . 'X' . $episodeid,	
			'ShowName' => self::cleanText($podcastname),
			'Logo' => self::cleanUrl($logo),
			'ShowEpisodeName' => self::cleanText($episodename),
			'ShowEpisodeURL' => self::cleanUrl($url),
			'BookmarkShow' => '',
			'ShowDesc' => self::cleanText( $desc ),
			'ShowFormat' => 'Podcast',
			'Lang' => 'KIMBisch',
			'Country' => 'KIMB',
			'ShowMime' => 'MP3'
		);
		$this->itemsSortKeys[] = ($top ? 'epA' : 'epZ' ) . '==' . $podcastid . '==' . $episodeid;
	}

	/**
	 * Add a folder
	 */
	public function addDir(string $name, string $url, bool $isLast = false) : void {
		$this->items[] = array(
			'ItemType' => 'Dir',
			'Title' => self::cleanText($name),
			'UrlDir' => self::cleanUrl($url),
			'UrlDirBackUp' => self::cleanUrl($url)
		);
		$this->itemsSortKeys[] = ($isLast ? 'z' : '') . 'dir==' . $name;
	}

	/**
	 * Set or override a Previous (<- Back URL)
	 */
	public function prevUrl(string $url) : void {
		$this->prevurl = self::cleanUrl($url);
	}

	/**
	 * Creates the xml response 
	 * and sends it!
	 */
	public function __destruct(){
		array_multisort($this->itemsSortKeys, SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $this->items);
		if( count( $this->items ) > self::MAX_ITEMS ){
			$this->items = array_slice($this->items, 0, self::MAX_ITEMS);
		}
		//output
		$lines = array(
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
			'<ListOfItems>',
			'  <ItemCount>' . ( count( $this->items ) ) .'</ItemCount>'
		);

		// add <- back url
		if(!empty( $this->prevurl )){
			array_unshift( $this->items, 
				array(
					'ItemType' => 'Previous',
					'UrlPrevious' => $this->prevurl,
					'UrlPreviousBackUp' => $this->prevurl
				)
			);
		}

		foreach( $this->items as $item ){
			$lines[] = '  <Item>';
			foreach( $item as $key => $value ){
				$lines[] = '    <' . $key . '>' . $value . '</' . $key . '>';
			}
			$lines[] = '  </Item>';
		}
		  $lines[] = '</ListOfItems>';
	
		//data setup
		$out = implode(PHP_EOL, $lines);
	  
		self::sendAnswer($out);
	}

	/**
	 * Sends the given string to the radio, settings all headers and ends script!
	 */
	public static function sendAnswer(string $out){
		// header setup
		header('Content-Type: text/plain;charset=UTF-8');
		header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Content-Length: ' . strlen( $out ));
	  
		die( $out );
	}
}

?>
