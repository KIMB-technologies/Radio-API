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
 * Main Data Storage, Data means Podcasts, Episodes and Stations
 */
class Data {

	private $id, $redis, $own_streams, $preloaded = false;
	private $radio, $stream, $podcasts, $table; 

	/**
	 * Generate Data (the main Radiolist)
	 * @param $id the id of the user
	 * @param $preload Load all JSON from disk, else redis is used if available
	 */
	public function __construct(int $id, bool $preload = false ){
		$this->id = $id;
		$this->redis = new Cache('radios_podcasts.' . $this->id );
		$this->own_streams = new OwnStreams();
		
		if( !$this->redis->keyExists( 'types' ) || $preload ){
			$this->preloadAll();
			if( !$this->redis->keyExists( 'types' ) ){
				$this->constructTable();
			}
		}
	}

	/**
	 * Load files from disk
	 */
	private function preloadAll() : void {
		$radio = null;
		if(is_file( __DIR__ . '/../data/radios_'. $this->id .'.json' )){
			$radio = json_decode( file_get_contents( __DIR__ . '/../data/radios_'. $this->id .'.json' ), true);
			if(is_null($radio)){ // on json error, move file and create new
				rename(__DIR__ . '/../data/radios_'. $this->id .'.json', __DIR__ . '/../data/radios_'. $this->id .'.error.json');
			}
		}
		$this->radio = is_null($radio) ? array() : $radio;

		$podcasts = null;
		if(is_file( __DIR__ . '/../data/podcasts_'. $this->id .'.json' )){
			$podcasts = json_decode( file_get_contents( __DIR__ . '/../data/podcasts_'. $this->id .'.json'  ), true);
			if(is_null($podcasts)){ 
				rename(__DIR__ . '/../data/podcasts_'. $this->id .'.json', __DIR__ . '/../data/podcasts_'. $this->id .'.error.json');
			}
		}
		$this->podcasts = is_null($podcasts) ?  array() : $podcasts;

		$this->preloaded = true;
	}

	/**
	 * Generate the main Table of Stations
	 */
	private function constructTable() : void {
		if(!$this->preloaded){ // load data, in not already done
			$this->preloadAll();
		}

		// generate Table
		$this->table = array();
		$this->table['types'] = array(
			1 => 'Radio',
			3 => 'Podcast'
		);
		$this->table['items'] = array();

		// add radio, podcasts
		$this->addTypeToTable( 1, $this->radio );
		$this->addTypeToTable( 3, $this->podcasts );

		// add "own streams"
		if( Config::STREAM_JSON ){
			$this->table['types'][2] = 'Stream';
			$this->addTypeToTable( 2, $this->own_streams->getStreams() );
		}

		// save in redis
		//	if using own stream, we need to give a ttl, else the system won't reload the list of own streams
		$this->redis->arraySet( 'types', $this->table['types'], Config::STREAM_JSON ? Config::CACHE_EXPIRE : 0 );
		$this->redis->arraySet( 'items', $this->table['items'], Config::STREAM_JSON ? Config::CACHE_EXPIRE : 0 );
	}

	/**
	 * Add a type to table
	 * 	Helper for constructTable
	 */
	private function addTypeToTable( int $tid, array $data ) : void{
		foreach( $data as $id => $d ){
			$idd = $id + 1000 * $tid;
			$this->table['items'][$idd] = $d;
			$this->table['items'][$idd]['tid'] = $tid;
			if( $id >= 999 ){ // only 999 per type!!
				break;
			}
		}
	}

	/**
	 * Returns List of type, indexed by typeID
	 */
	public function getTypes() : array {
		return $this->redis->arrayGet('types');
	}

	/**
	 * Returns List of categories for a typeID
	 */
	public function getCategories(?int $tid = null) : array {
		$cats = array_filter(
			$this->redis->arrayGet('items'),
			fn($i) => (is_null($tid) || $tid == $i['tid']) && !empty($i["category"])
		);
		return array_unique(array_map(fn($i) => $i["category"], $cats));
	}

	/**
	 * Returns list of items in this type, indexed by id!
	 * 	Filter for a category or set "null" to return all categories
	 */
	public function getListOfItems(?int $tid = null, ?string $cat = null ) : array {
		return array_filter(
			$this->redis->arrayGet('items'),
			fn($i) => (is_null($tid) || $tid == $i['tid']) && ( (is_null($cat) && empty($i['category'])) || $cat == $i['category'])
		);
	}

	/**
	 * Get data of one item by its id.
	 */
	public function getById( int $id ) : array {
		if( !$this->redis->arrayKeyExists('items', $id) ){
			return array();
		}
		return $this->redis->arrayKeyGet('items', $id);
	}

	/**
	 * Generate Link for station
	 * @param $id radio station id
	 * @param $mac users radio mac
	 */
	public function getStationURL( int $id, string $mac ) : string {
		$station = $this->getById($id);
		if(empty($station)){
			return "";
		}
		if(!empty($station['type']) && $station['type'] == 'nc' ){
			return Config::RADIO_DOMAIN . 'm3u.php?id=' . $id . '&mac=' . $mac;
		}
		else if(!empty($station['proxy'])){
			return Config::RADIO_DOMAIN . 'stream.php?id=' . $id . '&mac=' . $mac;
		}
		else{
			return $station['url'];
		}
	}

	/**
	 * Generate Link for podcast
	 * @param $id podcast id
	 * @param $eid episode id
	 * @param $mac users radio mac
	 * @param $sloppy (default=false) do not perform endURL lookup
	 */
	public function getPodcastURL( int $id, int $eid, string $mac, bool $sloppy = false ) : string {
		$ed = PodcastLoader::getEpisodeData( $id, $eid, $this );

		if(empty($ed)){
			return "";
		}

		if($ed['proxy']){
			return Config::RADIO_DOMAIN . 'stream.php?id=' . $id . '&eid=' . $eid . '&mac=' . $mac;
		}
		else if($ed['finalurl'] && !$sloppy){
			return Helper::getFinalUrl($ed['episode']['url']);
		}
		else{
			return $ed['episode']['url'];
		}
	}

	/**
	 * Backend Raw Access
	 */
	public function getRadioList() : array {
		if(!$this->preloaded){
			$this->preloadAll();
		}
		return $this->radio;
	}
	public function getPodcastList() : array {
		if(!$this->preloaded){
			$this->preloadAll();
		}
		return $this->podcasts;
	}
	public function setRadioList(array $radios) : void {
		$this->radio = $radios;
		file_put_contents( __DIR__ . '/../data/radios_'. $this->id .'.json', json_encode($this->radio, JSON_PRETTY_PRINT), LOCK_EX);
		$this->constructTable(); // update redis
	}
	public function setPodcastList(array $pods) : void {
		$this->podcasts = $pods;
		file_put_contents( __DIR__ . '/../data/podcasts_'. $this->id .'.json', json_encode($this->podcasts, JSON_PRETTY_PRINT), LOCK_EX);
		$this->constructTable(); // update redis
	}
}
?>
