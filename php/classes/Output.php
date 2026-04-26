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
 * Radio-API XML Style Output Class
 */
mb_substitute_character("none");
abstract class Output {

	protected
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
	public function __construct(){
		$this->language = $this->detectLanguage();
		$this->logo = new RadioLogo();
	}

	abstract protected function detectLanguage() : int;

	/**
	 * Add a station
	 */
	abstract public function addStation(
		int|string $id, string $name, string $url,
		$light = false, string $desc = '', string $logo = '', int|string $sortKey = ""
	) : void;

	/**
	 * Add a podcast
	 */
	abstract public function addPodcast(
		int $podcastid, string $name, string $url,
		int|string $sortKey = ""
	) : void;

	/**
	 * Add a podcast episode
	 */
	abstract public function addEpisode(
		int $podcastid, int|null $episodeid, string $podcastname, string $episodename, string $url,
		string $desc = '', string $logo = '', bool $top = false
	) : void;

	/**
	 * Add a folder
	 */
	abstract public function addDir(
		string $name, string $url,
		bool $isLast = false, int|string $sortKey = ""
	) : void;

	/**
	 * Set or override a Previous (<- Back URL)
	 */
	public function prevUrl(string $url) : void {
		$this->prevurl = $this->cleanUrl($url);
	}

	protected function cleanText( string $s, bool $translate = false ): string {
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

	protected function cleanUrl( string $s ): string {
		$url = mb_convert_encoding(str_replace( str_split('<>'), '', $s ), 'UTF-8', 'UTF-8');
		return 'http' . ( empty($_SERVER['HTTPS']) ? ':' : 's:' ) . substr( $url, strpos( $url, '//') );
	}

	abstract protected function getItemName(array $item) : string;

	protected function applyFavorites() : void {
		$favorites = array_map('trim', explode(',', Config::FAVORITE_ITEMS));
		foreach($this->items as $id => $item ){
			list($type, $name) = explode( '==', $this->itemsSortKeys[$id] );

			if(in_array($name, $favorites) || in_array($this->getItemName($item), $favorites)){
				$this->itemsSortKeys[$id] = 'A'. $type . '==' . $name;
			}
		}
	}

	abstract protected function formatItems(array $items) : string;

	/**
	 * Creates the xml/ json response 
	 * and sends it!
	 */
	public function __destruct(){
		$this->applyFavorites();
		array_multisort($this->itemsSortKeys, SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $this->items);
		if( count( $this->items ) > self::MAX_ITEMS ){
			$this->items = array_slice($this->items, 0, self::MAX_ITEMS);
		}

		$this->sendAnswer($this->formatItems($this->items), $this->contentType());
	}

	protected function contentType() : string {
		return 'text/plain;charset=UTF-8';
	}

	/**
	 * Sends the given string to the radio, settings all headers and ends script!
	 */
	public static function sendAnswer(string $out, string $contentType = 'text/plain;charset=UTF-8') : void {
		// header setup
		header('Content-Type: ' . $contentType);
		header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Content-Length: ' . strlen( $out ));
	  
		die( $out );
	}
}

?>
