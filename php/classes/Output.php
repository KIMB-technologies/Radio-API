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
defined('HAMARadio') or die('Invalid Endpoint');

/**
 * Radio-API XML Style Output Class
 */
mb_substitute_character("none");
class Output {

	private
		$items = array(),
		$itemsSortKeys = array(),
		$prevurl = '',
		$language,
		$logo;
	
	const MAX_ITEMS = 200; // to many items will cause the radio to crash (one could add paging, but until then, we remove too much items)

	const ALL_LANGUAGES = array(
		'eng',
		'ger'
	);
	const TRANSLATIONS = array(
		'Podcast' => ['Podcast', 'Podcast'],
		'Radio' => ['Radio stations', 'Radiosender'],
		'Radio-Browser' => ['Radio-Browser', 'Radio-Browser'],
		'Stream' => ['Stream', 'Stream'],
		'GUI-Code' => ['GUI-Code', 'GUI-Code'], 
		//
		'Countries' => ['Countries', 'Länder'],
		'All States' => ['All States', 'Alle Bundesländer'],
		'Languages' => ['Languages', 'Sprachen'],
		'My Last' => ['My Last', 'Meine zuletzt gehörten'],
		'Tags' => ['Tags', 'Kategorien'],
		'Top Click' => ['Top Click', 'Am meisten gehört'],
		'Top Vote' => ['Top Vote', 'Am höchsten bewertet'],
		'Next Page' => ['Next Page', 'Nächste Seite'],
		'You do not have last stations.' => ['You do not have last stations.', 'Sie haben keine zuletzt gehörten Sender!']
	);

	/**
	 * Create Outputter
	 */
	public function __construct(string $lang = 'eng'){
		$this->language = array_search($lang, self::ALL_LANGUAGES) ?? 0;
		$this->logo = new RadioLogo();
	}

	/**
	 * Add a station
	 */
	public function addStation( int|string $id, string $name, string $url,
						$light = false, string $desc = '', string $logo = '', int|string $sortKey = "") : void {
		$a = array(
			'ItemType' => 'Station',
			'StationId' => $id,
			'StationName' => $this->cleanText($name, true),
		);
		if( !$light ){
			$b = array(
				'StationUrl' => $this->cleanUrl($url),
				'StationDesc' => $this->cleanText($desc),
				'Logo' => $this->cleanUrl($this->logo->logoUrl($logo)),
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
		$this->itemsSortKeys[] = 'ra==' . ($sortKey === "" ? $name : $sortKey);
	}

	/**
	 * Add a podcast
	 */
	public function addPodcast( int $podcastid, string $name, string $url, int|string $sortKey = "" ) : void {
		$this->items[] = array(
			'ItemType' => 'ShowOnDemand',
			'ShowOnDemandID' => $podcastid,
			'ShowOnDemandName' => $this->cleanText($name, true),
			'ShowOnDemandURL' => $this->cleanUrl($url),
			'ShowOnDemandURLBackUp' => $this->cleanUrl($url),
			'BookmarkShow' => ''
		);
		$this->itemsSortKeys[] = 'pod==' . ($sortKey === "" ? $name : $sortKey);
	}

	/**
	 * Add a podcast episode
	 */
	public function addEpisode( int $podcastid, int|null $episodeid, string $podcastname, string $episodename,
						string $url, string $desc = '', string $logo = '', bool $top = false ) : void {
		$this->items[] = array(
			'ItemType' => 'ShowEpisode',
			'ShowEpisodeID' =>  $podcastid . (!is_null($episodeid) ? 'X' . $episodeid : ''),	
			'ShowName' => $this->cleanText($podcastname, true),
			'Logo' => $this->cleanUrl($this->logo->logoUrl($logo)),
			'ShowEpisodeName' => $this->cleanText($episodename, true),
			'ShowEpisodeURL' => $this->cleanUrl($url),
			'BookmarkShow' => '',
			'ShowDesc' => $this->cleanText($desc),
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
	public function addDir(string $name, string $url, bool $isLast = false, int|string $sortKey = "") : void {
		$this->items[] = array(
			'ItemType' => 'Dir',
			'Title' => $this->cleanText($name, true),
			'UrlDir' => $this->cleanUrl($url),
			'UrlDirBackUp' => $this->cleanUrl($url)
		);
		$this->itemsSortKeys[] = ($isLast ? 'z' : '') . 'dir==' . ($sortKey === "" ? $name : $sortKey);;
	}

	/**
	 * Set or override a Previous (<- Back URL)
	 */
	public function prevUrl(string $url) : void {
		$this->prevurl = $this->cleanUrl($url);
	}

	private function cleanText( string $s, bool $translate = false ): string {
		if($translate){
			$pos = strpos($s, ':');
			$suffix = '';
			if($pos !== false){
				$suffix = substr($s, $pos);
				$s = substr($s, 0, $pos);
			}

			if(array_key_exists($s, self::TRANSLATIONS)){
				$s = self::TRANSLATIONS[$s][$this->language];
			}

			$s .= $suffix;
		}
		return mb_substr( mb_convert_encoding(str_replace( str_split('"&<>/'), '', $s ), 'UTF-8', 'UTF-8'), 0, 100 );
	}

	private function cleanUrl( string $s ): string {
		$url = mb_convert_encoding(str_replace( str_split('<>'), '', $s ), 'UTF-8', 'UTF-8');
		return 'http' . ( empty($_SERVER['HTTPS']) ? ':' : 's:' ) . substr( $url, strpos( $url, '//') );
	}

	private function applyFavorites() : void {
		$favorites = array_map( 'trim', explode(',', Config::FAVORITE_ITEMS));
		foreach($this->items as $id => $item ){
			list($type, $name) = explode( '==', $this->itemsSortKeys[$id] );

			if(
				in_array($name, $favorites) ||
					(isset($item['Title']) && in_array($item['Title'], $favorites) ) ||
					(isset($item['StationName']) && in_array($item['StationName'], $favorites) ) ||
					(isset($item['ShowOnDemandName']) && in_array($item['ShowOnDemandName'], $favorites) ) ||
					(isset($item['ShowEpisodeName']) && in_array($item['ShowEpisodeName'], $favorites) )
			){
				$this->itemsSortKeys[$id] = 'A'. $type . '==' . $name;
			}
		}
	}

	/**
	 * Creates the xml response 
	 * and sends it!
	 */
	public function __destruct(){
		$this->applyFavorites();
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
