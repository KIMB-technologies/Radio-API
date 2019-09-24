<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Data {

	private $id, $redis, $preloaded = false;
	private $radio, $stream, $podcasts, $table; 

	/**
	 * Generate Data (the main Radiolist)
	 * @param $id the id of the user
	 * @param $preload Load all JSON from disk, else redis is used if available
	 */
	public function __construct(int $id, bool $preload = false ){
		$this->id = $id;
		$this->redis = new RedisCache('radios_podcasts.' . $this->id );

		if( !$this->redis->keyExists( 'categories' ) || $preload ){
			$this->preloadAll();
			if( !$this->redis->keyExists( 'categories' ) ){
				$this->constructTable();
			}
		}
	}

	/**
	 * Load files from disk
	 */
	private function preloadAll() : void {
		$this->radio = is_file( __DIR__ . '/../data/radios_'. $this->id .'.json' ) ?
			json_decode( file_get_contents( __DIR__ . '/../data/radios_'. $this->id .'.json' ), true) : array();
		$this->podcasts = is_file( __DIR__ . '/../data/podcasts_'. $this->id .'.json'  ) ?
			json_decode( file_get_contents( __DIR__ . '/../data/podcasts_'. $this->id .'.json'  ), true) : array();

		$this->stream = $this->loadStreams();

		$this->preloaded = true;
	}

	/**
	 * Load own streams into table
	 * 	Helper for preloadAll
	 */
	private function loadStreams() : array{
		$stream = array();
		if( Config::OWN_STREAM ){
			$mydata = Config::getMyStreamsList();
			if( !empty($mydata) ) {
				$stream = array();
				foreach( $mydata as $key => $val){
					$stream[] = array(
						'name' => $key . ( empty( $val['name'] ) ? '' : ' - ' . $val['name'] ),
						'url' => Config::myStreamsListGetURL( $key ),
						'proxy' => Config::PROXY_OWN_STREAM
					);
				}
			}
		}
		return $stream;
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
		$this->table['categories'] = array(
			1 => 'Radio',
			3 => 'Podcast'
		);

		$this->table['items'] = array();
		$this->addCategoryToTable( 1, $this->radio );
		if( Config::OWN_STREAM ){
			$this->table['categories'][2] = 'Stream';
			$this->addCategoryToTable( 2, $this->stream );
		}
		$this->addCategoryToTable( 3, $this->podcasts );

		// save in redis
		$this->redis->arraySet( 'categories', $this->table['categories'] );
		$this->redis->arraySet( 'items', $this->table['items'] );
	}

	/**
	 * Add a category to table
	 * 	Helper for constructTable
	 */
	private function addCategoryToTable( int $cid, array $data ) : void{
		foreach( $data as $id => $d ){
			$idd = $id + 1000 * $cid;
			$this->table['items'][$idd] = $d;
			$this->table['items'][$idd]['cid'] = $cid;
			if( $id >= 999 ){ // only 999 per cat!!
				break;
			}
		}
	}

	/**
	 * Returns List of category, indexed by catID
	 */
	public function getCategories() : array {
		return $this->redis->arrayGet('categories');
	}

	/**
	 * Returns list of items in this cat, indexed by id!
	 */
	public function getListOfItems( int $cid ) : array {
		return array_filter( $this->redis->arrayGet('items'), function($i) use (&$cid){
			return $cid == $i['cid'];
		});
	}

	/**
	 * Get data of one item by his id.
	 */
	public function getById( int $id ) : array {
		if( !$this->redis->arrayKeyExists('items', $id) ){
			return array();
		}
		return $this->redis->arrayKeyGet('items', $id);
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
		file_put_contents( __DIR__ . '/../data/radios_'. $this->id .'.json', json_encode($this->radio, JSON_PRETTY_PRINT));
		$this->constructTable(); // update redis
	}
	public function setPodcastList(array $pods) : void {
		$this->podcasts = $pods;
		file_put_contents( __DIR__ . '/../data/podcasts_'. $this->id .'.json', json_encode($this->podcasts, JSON_PRETTY_PRINT));
		$this->constructTable(); // update redis
	}
}
?>
