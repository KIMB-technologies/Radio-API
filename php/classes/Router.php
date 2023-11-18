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
 * Routing for Radio-API XML Index
 */
class Router {

	private Id $radioid;
	private Output $out;
	private Data $data;
	private Unread $unread;
	private RadioBrowser $radio_browser;

	/**
	 * Generate Objects
	 */
	public function __construct( Id $id ){
		$this->radioid = $id;
		$this->out = new Output();
		$this->data = new Data($this->radioid->getId());
		$this->unread = new UnRead($this->radioid->getId());
		$this->radio_browser = new RadioBrowser($this->radioid);
	}

	/**
	 * Handle the get requests
	 */
	public function handleGet(string $uri) : void {
		if( isset( $_GET['sSearchtype'] ) && $_GET['sSearchtype'] == 3 ){ // only one station (play this)
			if( !empty($_GET['Search']) && RadioBrowser::matchStationID($_GET['Search']) ){ // is an id from Radio-Browser??
				$this->radio_browser->handleStationPlay($this->out, $_GET['Search']);
			}
			else { // local ID
				$this->listStation();
			}
		}
		else if( isset( $_GET['sSearchtype'] ) && $_GET['sSearchtype'] == 5 ){ // only one episode (play this)
			$this->listEpisode();
		}
		else if( $uri == '/cat' && !empty( $_GET['cid'] )  ){ // list of stations by category
			$this->out->prevUrl(Config::DOMAIN . '?go=initial');

			$cid = $_GET['cid'];
			if( is_numeric( $cid ) && in_array( $cid, array_keys($this->data->getCategories()) ) ){
				if( $cid == 3 && isset( $_GET['id'] ) && preg_replace('/[^0-9]/','', $_GET['id']  ) === $_GET['id'] ){
					$this->listPodcast();
				}
				else{
					$this->listDirectories($cid);
				}
			}
		}
		else if( $uri == '/radio-browser' && !empty( $_GET['by'] ) && !empty( $_GET["term"] ) ){
			$offset = isset($_GET["offset"]) && is_numeric($_GET["offset"]) ? intval($_GET["offset"]) : 0;
			$this->radio_browser->handleBrowse($this->out, $_GET['by'], $_GET["term"], $offset);
		}
		else if( !empty($_GET['toggleUnRead']) && is_string($_GET['toggleUnRead']) ){
			$this->out->addDir('TOGGLE-UN-READ-' . $this->unread->toggleById($_GET['toggleUnRead'], $this->data), '');
		}
		else{ // list of categories (startpage)
			foreach( $this->data->getCategories() as $cid => $name ){
				$this->out->addDir( $name, Config::DOMAIN . 'cat?cid=' . $cid );
			}
			// add link to RadioBrowser
			$this->out->addDir( 'Radio-Browser', Config::DOMAIN . 'radio-browser?by=none&term=none' );

			// add code (for gui)
			$this->out->addDir( 'GUI-Code: ' . $this->radioid->getCode(), Config::DOMAIN . '?go=initial', true );

			// Log unknown
			if(
				preg_match('/^\/setupapp\/[A-Za-z0-9\-\_]+\/asp\/BrowseXML\/loginXML.asp/i', $uri) === 0 &&
				(!isset( $_GET['go'] ) || $_GET['go'] != "initial")
			){
				file_put_contents( __DIR__ . '/../data/log.txt', date('d.m.Y H:i:s') . " : " . json_encode( $_GET ) . PHP_EOL, FILE_APPEND );
			}
		}
	}

	private function listStation() : void {
		$this->out->prevUrl(Config::DOMAIN . '?go=initial');
		$id = $_GET['Search'];
		if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
			$sta = $this->data->getById( $id );
			if( $sta !== array() ){
				$this->out->addStation(
					$id,
					$sta['name'],
					$this->data->getStationURL($id, $this->radioid->getMac()),
					false,
					$sta['desc'] ?? '',
					$sta['logo'] ?? ''
				);
				$this->out->prevUrl(Config::DOMAIN . 'cat?cid=' . $sta['cid'] );
			}
		}
	}

	private function listEpisode() : void {
		$this->out->prevUrl(Config::DOMAIN . 'cat?cid=3');
		$id = $_GET['Search'];
		$parts = array();
		if( preg_match('/^(\d+)X(\d+)$/', $id, $parts ) === 1 ){
			$ed = PodcastLoader::getEpisodeData( $parts[1], $parts[2], $this->data );
			if( $ed != array() ){
				$this->unread->openItem($parts[1], $ed['episode']['url']);
				if($ed['proxy']){
					$url = Config::DOMAIN . 'stream.php?id=' . $parts[1] . '&eid=' . $parts[2] . '&mac=' . $this->radioid->getMac();
				}
				else if($ed['finalurl']){
					$url = Helper::getFinalUrl($ed['episode']['url']);
				}
				else{
					$url = $ed['episode']['url'];
				}
				$this->out->addEpisode(
					$parts[1],
					$parts[2],
					$ed['title'],
					$ed['episode']['title'],
					$url,
					$ed['episode']['desc'],
					$ed['logo']
				);
				$this->out->prevUrl(Config::DOMAIN . 'cat?cid=3&id=' . $parts[1]);
			}
		}
	}

	private function listPodcast(){
		$id = $_GET['id'];
		$pd = PodcastLoader::getPodcastDataById( $id, $this->data );
		$proxy = !empty($this->data->getById( $id )['proxy']);
		$this->unread->searchItem($id);
		foreach( $pd['episodes'] as $eid => $e ){
			$this->out->addEpisode(
				$id,
				$eid,
				$pd['title'],
				$this->unread->knownItemMark($id, $e['url']) . $e['title'],
				$proxy ? Config::DOMAIN . 'stream.php?id=' . $id . '&eid=' . $eid . '&mac=' . $this->radioid->getMac() : $e['url'],
				$e['desc'],
				$pd['logo'],
				!$this->unread->knownItem($id, $e['url'])
			);
		}
		$this->out->prevUrl(Config::DOMAIN . 'cat?cid=3');
	}
		
	private function listDirectories(int $cid){
		foreach( $this->data->getListOfItems( $cid ) as $id => $item ){
			if( $cid == 3){
				$this->out->addPodcast(
					$id,
					$item['name'],
					Config::DOMAIN . 'cat?cid=' . $cid . '&id=' . $id
				);
			}
			else{
				$this->out->addStation(
					$id,
					$item['name'],
					$this->data->getStationURL($id, $this->radioid->getMac()),
					true
				);
			}
		}
	}
}
?>
