<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Data {

	private $radio, $stream, $podcasts, $table; 

	public function __construct(){
		$this->radio = json_decode(file_get_contents( __DIR__ . '/radios.json' ), true);
		$this->stream = $this->loadStreams();
		$this->podcasts = json_decode(file_get_contents( __DIR__ . '/podcasts.json' ), true);

		$this->constructTable();
	}

	private function loadStreams() : array{
		$stream = array();
		if( Config::OWN_STREAM ){
			$mydata = Config::getMyStreamsList();
			if( !empty($mydata) ) {
				$stream = array();
				foreach( $mydata as $key => $val){
					$stream[] = array(
						'name' => $key . ( empty( $val['name'] ) ? '' : ' - ' . $val['name'] ),
						'url' => Config::myStreamsListGetURL( $key )
					);
				}
			}
		}
		return $stream;
	}


	private function constructTable() : void{
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
	}

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
	 * Returns List of categorie, indexed by catID
	 */
	public function getCategories() : array {
		return $this->table['categories'];
	}

	/**
	 * Returns list of items in this cat, indexed by id!
	 */
	public function getListOfItems( int $cid ) : array {
		return array_filter( $this->table['items'], function($i) use (&$cid){
			return $cid == $i['cid'];
		});
	}

	/**
	 * Get data of one item by his id.
	 */
	public function getById( int $id ) : array {
		if( !isset( $this->table['items'][$id] ) ){
			return array();
		}
		return $this->table['items'][$id];
	}
}
?>
