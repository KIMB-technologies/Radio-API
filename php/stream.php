<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2020 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
define('HAMA-Radio', 'Radio');
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

/**
 * Loading
 */
require_once( __DIR__ . '/classes/autoload.php' );
Config::checkAccess( !empty($_GET['mac']) && Helper::checkValue( $_GET['mac'], Id::MAC_PREG ) ? $_GET['mac'] : null );

/**
 * Auth
 */
$radioid = Auth::authFromMac();

$data = new Data($radioid->getId());

/**
 * Answer Proxy Request
 */
if( !empty( $_GET['id'] ) ){
	$id = $_GET['id'];

	// id ok?
	if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
		if( !isset($_GET['eid']) && !isset( $_GET['track'] ) ){ // station
			// get url
			$station = $data->getById( $id ); 
			$url = !empty($station) ? $station['url'] : '';
		}
		else if(isset( $_GET['eid'] ) && is_numeric( $_GET['eid'] ) && preg_replace('/[^0-9]/','', $_GET['eid'] ) === $_GET['eid'] ){ // podcast episode
			$ed = PodcastLoader::getEpisodeData( $id, $_GET['eid'], $data );
			$url = !empty($ed) ? $ed['episode']['url'] : '';
		}
		else if(is_numeric( $_GET['track'] ) && preg_replace('/[^0-9]/','', $_GET['track'] ) === $_GET['track'] ){
			$track = $_GET['track'];

			$urllist = PodcastLoader::getMusicById( $id, $data );
			if( Config::SHUFFLE_MUSIC ){ // generate random index for each track id (cache via redis)
				$redis = new RedisCache('shuffle');
				if( $redis->keyExists( $id . $track ) ){
					$index = $redis->get( $id . $track );
				}
				else{
					$index = random_int( 0, count( $urllist ) - 1 );
					$redis->set( $id . $track, $index, Config::CACHE_EXPIRE );
				}
			}
			else{
				$index = $track;
			}
			$url = empty( $urllist[$index] ) ? '' : $urllist[$index];
		}
		else{
			$url = '';
		}
		// station known?
		if( !empty($url) && filter_var( $url, FILTER_VALIDATE_URL) !== false ){ 
			$url = filter_var($url, FILTER_SANITIZE_URL); //clean url

			// the proxy does not support redirects!, so do them before
			$url = Helper::getFinalUrl($url);
			
			// get hostname and url parts before and after
			$matches = array();
			$matchok = preg_match( '/^(https?:\/\/)([^\/]+\.?[a-zA-Z]+)((?::[0-9]+)?(?:\/.*)?)$/', $url, $matches ); // get host
			$host = $matches[2];

			// host ok?
			if( $matchok === 1 ){
				// check ip address
				$ip = gethostbyname( $host ); 
				// create url using ip
				$url = $matches[1] . $ip . $matches[3];
				// allow only external ips
				if( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE) !== false ){
					//let nginx do the rest
					header("X-Accel-Redirect: /proxy/". $host ."/?" . $url );
					die();
				}
			}
		}
		else{
			Output::sendAnswer('<Error>Invalid ID</Error>');
		}
	}
}
Output::sendAnswer('<Error>No ID given!</Error>');
die(); //will never be reached
?>