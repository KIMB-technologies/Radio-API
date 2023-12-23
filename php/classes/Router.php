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
		$this->out = new Output(isset($_GET['dlang']) && is_string($_GET['dlang']) ? $_GET['dlang'] : 'eng');
		$this->data = new Data($this->radioid->getId());
		$this->unread = new UnRead($this->radioid->getId());
		$this->radio_browser = new RadioBrowser($this->radioid);
	}

	/**
	 * Handle the get requests
	 */
	public function handleGet(string $uri) : void {
		// only one station or episode (play this)
		if( isset( $_GET['sSearchtype'] ) && ($_GET['sSearchtype'] == 3 || $_GET['sSearchtype'] == 5 ) && !empty($_GET['Search'])){
			// is an ID from Radio-Browser??
			if( RadioBrowser::matchStationID($_GET['Search']) ){ 
				$this->radio_browser->handleStationPlay($this->out, $_GET['Search']);
			}
			// podcast episode ID? "3000-3999"+"X"+"[0-9]+" 
			else if( preg_match('/^(3\d\d\d)X(\d+)$/', $_GET['Search'], $parts ) === 1 ){ 
				$this->listPodcastEpisode(intval($parts[1]), intval($parts[2]));
			}
			// radio or "own stream" ID (Range 1000 - 2999)
			else if( preg_match('/^(1|2)\d\d\d$/', $_GET['Search'], $parts ) === 1){ 
				$this->listPlayItem(intval($_GET['Search']), $parts[1]);
			}
			else {
				$this->out->addDir( 'No item found for this ID!', Config::RADIO_DOMAIN . '?go=initial');
			}
		}
		// radio browser browsing
		else if( $uri == '/radio-browser' && !empty( $_GET['by'] ) && !empty( $_GET["term"] ) ){
			$offset = isset($_GET["offset"]) && is_numeric($_GET["offset"]) ? intval($_GET["offset"]) : 0;
			$this->radio_browser->handleBrowse($this->out, $_GET['by'], $_GET["term"], $offset);
		}
		// (Un)Read for podcast episodes (used in GUI)
		else if( !empty($_GET['toggleUnRead']) && is_string($_GET['toggleUnRead']) ){
			$this->out->addDir('TOGGLE-UN-READ-' . $this->unread->toggleById($_GET['toggleUnRead'], $this->data), '');
		}
		// list of stations or podcasts depending on category
		else if( $uri == '/cat' && !empty( $_GET['cid'] ) ){ 
			$this->out->prevUrl(Config::RADIO_DOMAIN . '?go=initial');

			$cid = $_GET['cid'];
			if( is_numeric($cid) && array_key_exists($cid, $this->data->getCategories()) ){
				if( $cid == 3 && isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']) === 1 ){
					$this->listPodcast(intval($_GET['id']));
				}
				else{
					$this->listDirectory(intval($cid));
				}
			}
			else {
				$this->out->addDir( 'No item found for this cID!', Config::RADIO_DOMAIN . '?go=initial');
			}
		}
		// list of categories (startpage)
		else{ 
			// add local categories
			foreach( $this->data->getCategories() as $cid => $name ){
				$this->out->addDir( $name, Config::RADIO_DOMAIN . 'cat?cid=' . $cid );
			}
			// add RadioBrowser
			$this->out->addDir( 'Radio-Browser', Config::RADIO_DOMAIN . 'radio-browser?by=none&term=none' );

			// add code (for gui)
			$this->out->addDir( 'GUI-Code: ' . $this->radioid->getCode(), Config::RADIO_DOMAIN . '?go=initial', true );

			// Log unknown request
			if(
				preg_match('/^\/setupapp\/[A-Za-z0-9\-\_]+\/asp\/BrowseXML\/loginXML.asp/i', $uri) === 0 &&
				(!isset( $_GET['go'] ) || $_GET['go'] != "initial")
			){
				file_put_contents(
					Config::LOG_DIR . '/requests.log',
					date('d.m.Y H:i:s') . " : " . json_encode( $_GET ) . PHP_EOL,
					FILE_APPEND
				);
			}
		}
	}

	private function listPodcastEpisode(int $id, int $eid, int $cid = 3) : void {
		$this->out->prevUrl(Config::RADIO_DOMAIN . 'cat?cid='.$cid.'&id='.$id);
		
		$ed = PodcastLoader::getEpisodeData( $id, $eid, $this->data );
		if( $ed != array() ){
			$this->unread->openItem($id, $ed['episode']['url']);

			$this->out->addEpisode(
				$id, $eid,
				$ed['title'], $ed['episode']['title'],
				$this->data->getPodcastURL($id, $eid, $this->radioid->getMac()),
				$ed['episode']['desc'],
				$ed['logo']
			);
		}
	}

	private function listPlayItem(int $id, int $cid = 1) : void {
		$this->out->prevUrl(Config::RADIO_DOMAIN . 'cat?cid=' . $cid );
		
		$sta = $this->data->getById( $id );
		if( $sta !== array() ){
			// radio station or stream with "live" == true
			if( !isset($sta["live"]) || $sta["live"] ){
				$this->out->addStation(
					$id,
					$sta['name'],
					$this->data->getStationURL($id, $this->radioid->getMac()),
					false,
					$sta['desc'] ?? '',
					$sta['logo'] ?? ''
				);
			}
			else { // "live" == false
				$this->out->addEpisode(
					$id, null,
					$sta['name'], $sta['name'],
					$this->data->getStationURL($id, $this->radioid->getMac()),
					$sta['desc']  ?? '',
					$sta['logo'] ?? ''
				);
			}
		}
	}

	private function listPodcast(int $id) : void {
		$this->out->prevUrl(Config::RADIO_DOMAIN . 'cat?cid=3');
		$this->unread->searchItem($id);

		$pd = PodcastLoader::getPodcastDataById( $id, $this->data );
		
		foreach( $pd['episodes'] as $eid => $e ){
			$this->out->addEpisode(
				$id, $eid,
				$pd['title'],
				$this->unread->knownItemMark($id, $e['url']) . $e['title'],
				$this->data->getPodcastURL($id, $eid, $this->radioid->getMac(), sloppy: true),
				$e['desc'], $pd['logo'],
				!$this->unread->knownItem($id, $e['url'])
			);
		}
		
	}
		
	private function listDirectory(int $cid) : void {
		foreach( $this->data->getListOfItems( $cid ) as $id => $item ){
			if($item['cid'] == 1 || ($item['cid'] == 2 && $item['live'])){ // radio station, or live "own stream"
				$this->out->addStation(
					$id,
					$item['name'],
					$this->data->getStationURL($id, $this->radioid->getMac()),
					true
				);
			}
			else if($item['cid'] == 3){ // podcast
				$this->out->addPodcast(
					$id,
					$item['name'],
					Config::RADIO_DOMAIN . 'cat?cid=' . $cid . '&id=' . $id
				);
			}
			else if ($item['cid'] == 2 && !$item['live']) { // file based "own stream"
				$this->out->addEpisode(
					$id, null,
					$item['name'], $item['name'],
					$this->data->getStationURL($id, $this->radioid->getMac())
				);
			}
		
		}
	}
}
?>
