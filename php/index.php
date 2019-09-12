<?php
define('HAMA-Radio', 'Radio');
error_reporting(0);

/**
 * Loading
 */
require_once( __DIR__ . '/classes/Config.php' );
Config::checkAccess();
//error_reporting(E_ALL);
require_once( __DIR__ . '/classes/Helper.php' );
require_once( __DIR__ . '/classes/Output.php' );
require_once( __DIR__ . '/classes/PodcastLoader.php' );
require_once( __DIR__ . '/classes/Data.php' );


/**
 * Radio Requests
 */
$uri = !empty( $_GET['uri'] ) ? $_GET['uri'] : 'none';
// Login
if( $uri == '/setupapp/hama/asp/BrowseXML/loginXML.asp' && !isset( $_GET['mac'] ) ){
	Output::sendAnswer('<EncryptedToken>3a3f5ac48a1dab4e</EncryptedToken>');
	die(); //will never be reached
}

$out = new Output();
$data = new Data();

//Normal Pages
if( isset( $_GET['sSearchtype'] ) && $_GET['sSearchtype'] == 3 ){ // only one station (play this)
	$out->prevUrl(Config::DOMAIN . '?go=inital');
	$id = $_GET['Search'];
	if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
		$sta = $data->getById( $id );
		if( $sta !== array() ){
			$out->addStation(
				$id,
				$sta['name'],
				$sta['url'],
				false,
				isset($sta['desc']) ? $sta['desc'] : '',
				isset($sta['logo']) ? $sta['logo'] : ''
			);
			$out->prevUrl(Config::DOMAIN . 'cat?cid=' . $sta['cid'] );
		}
	}
}
else if( isset( $_GET['sSearchtype'] ) && $_GET['sSearchtype'] == 5 ){ // only one episode (play this)
	$out->prevUrl(Config::DOMAIN . 'cat?cid=3');
	$id = $_GET['Search'];
	$parts = array();
	if( preg_match('/^(\d+)X(\d+)$/', $id, $parts ) === 1 ){
		$ed = PodcastLoader::getEpisodeData( $parts[1], $parts[2], $data );
		if( $ed != array() ){
			$url = $ed['finalurl'] ? Helper::getFinalUrl($ed['episode']['url']) : $ed['episode']['url'];
			$out->addEpisode(
				$parts[1],
				$parts[2],
				$ed['title'],
				$ed['episode']['title'],
				$url,
				$ed['episode']['desc'],
				$ed['logo']
			);
			$out->prevUrl(Config::DOMAIN . 'cat?cid=3&id=' . $parts[1]);
		}
	}
}
else if( $uri == '/cat' && !empty( $_GET['cid'] )  ){ // list of stations by catergory
	$out->prevUrl(Config::DOMAIN . '?go=inital');

	$cid = $_GET['cid'];
	if( is_numeric( $cid ) && in_array( $cid, array_keys($data->getCategories()) ) ){
		if( $cid == 3 && isset( $_GET['id'] ) && preg_replace('/[^0-9]/','', $_GET['id']  ) === $_GET['id'] ){
			$id = $_GET['id'];
			$pd = PodcastLoader::getPodcastDataById( $id, $data );
			foreach( $pd['episodes'] as $eid => $e ){
				$out->addEpisode(
					$id,
					$eid,
					$pd['title'],
					$e['title'],
					$e['url'],
					$e['desc'],
					$pd['logo']
				);
			}
			$out->prevUrl(Config::DOMAIN . 'cat?cid=3');
		}
		else{
			foreach( $data->getListOfItems( $cid ) as $id => $item ){
				if( $cid == 3){
					$out->addPodcast(
						$id,
						$item['name'],
						Config::DOMAIN . 'cat?cid=' . $cid . '&id=' . $id
					);
				}
				else{
					$out->addStation(
						$id,
						$item['name'],
						$item['url'],
						true
					);
				}
			}
		}
	}
}
else{ // list of categories (startpage)
	foreach( $data->getCategories() as $cid => $name ){
		$out->addDir( $name, Config::DOMAIN . 'cat?cid=' . $cid );
	}

	// Log unknown
	if( $uri != '/setupapp/hama/asp/BrowseXML/loginXML.asp' ){
		file_put_contents( __DIR__ . '/data/log.txt', json_encode( $_GET ) . PHP_EOL, FILE_APPEND );
	}
}
?>
