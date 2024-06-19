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
		// list of stations or podcasts (= the type) and possibly selecting a category
		else if( $uri == '/list' ){ 
			$this->out->prevUrl(Config::RADIO_DOMAIN . '?go=initial');

			if( !empty($_GET['tid']) && is_numeric($_GET['tid']) && array_key_exists($_GET['tid'], $this->data->getTypes()) ){
				$tid = intval($_GET['tid']);
			}
			else if( empty($_GET['tid']) && !empty($_GET['cat']) ) {
				$tid = null; 
			}
			else{
				$this->out->addDir( 'No item found for this tID or Category!', Config::RADIO_DOMAIN . '?go=initial');
			}
			
			// list items of a podcast
			if( $tid == 3 && isset($_GET['id']) && preg_match('/^\d+$/', $_GET['id']) === 1 ){
				$this->listPodcast(intval($_GET['id']));
			}
			// list items of type (or all of category), possibly filter by category
			else{
				$this->listDirectory(
					$tid, 
					isset($_GET['cat']) && preg_match( '/^[0-9A-Za-z \-\,]{0,200}$/', $_GET['cat'] ) === 1 ? $_GET['cat'] : null
				);
			}
		}
		// list of types (startpage)
		else{ 
			// add local types
			foreach( $this->data->getTypes() as $tid => $name ){
				$this->out->addDir( $name, Config::RADIO_DOMAIN . 'list?tid=' . $tid );
			}
			// add RadioBrowser
			$this->out->addDir( 'Radio-Browser', Config::RADIO_DOMAIN . 'radio-browser?by=none&term=none' );

			// add code (for gui)
			$this->out->addDir( 'GUI-Code: ' . $this->radioid->getCode(), Config::RADIO_DOMAIN . '?go=initial', true );

			// add Favorites category, if exists
			$allCats = $this->data->getCategories();
			if( in_array( 'Favorites', $allCats ) || in_array( 'Favoriten', $allCats ) ) {
				$name = in_array( 'Favoriten', $allCats ) ? 'Favoriten' : 'Favorites';
				$this->out->addDir( $name, Config::RADIO_DOMAIN . 'list?cat=' . $name );
			}

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

	private function listPodcastEpisode(int $id, int $eid, int $tid = 3) : void {
		$this->out->prevUrl(Config::RADIO_DOMAIN . 'list?tid='.$tid.'&id='.$id);
		
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

	private function listPlayItem(int $id, int $tid = 1) : void {
		$sta = $this->data->getById( $id );

		$this->out->prevUrl(
			Config::RADIO_DOMAIN . 'list?tid=' . $tid .
			(empty($sta['category']) ? '' : '&cat=' . rawurlencode($sta['category']))
		);

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
		$this->unread->searchItem($id);

		$pod = $this->data->getById($id);
		$this->out->prevUrl(
			Config::RADIO_DOMAIN . 'list?tid=3' .
			(empty($pod['category']) ? '' : '&cat=' . rawurlencode($pod['category']))
		);

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
		
	private function listDirectory(?int $tid = null, ?string $cat = null) : void {
		// first add categories if in "root" folder of type
		if(is_null($cat) && !is_null($tid)){
			foreach( $this->data->getCategories( $tid ) as $category ){
				$this->out->addDir( $category, Config::RADIO_DOMAIN . 'list?tid=' . $tid . '&cat=' . rawurlencode($category) );
			}
		}
		else{
			$this->out->prevUrl(Config::RADIO_DOMAIN . (is_null($tid) ? '?go=initial' : 'list?tid=' . $tid));
		}

		// then add items
		foreach( $this->data->getListOfItems( $tid, $cat ) as $id => $item ){
			if($item['tid'] == 1 || ($item['tid'] == 2 && $item['live'])){ // radio station, or live "own stream"
				$this->out->addStation(
					$id,
					$item['name'],
					$this->data->getStationURL($id, $this->radioid->getMac()),
					true
				);
			}
			else if($item['tid'] == 3){ // podcast
				$this->out->addPodcast(
					$id,
					$item['name'],
					Config::RADIO_DOMAIN . 'list?tid=3&id=' . $id
				);
			}
			else if ($item['tid'] == 2 && !$item['live']) { // file based "own stream"
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
