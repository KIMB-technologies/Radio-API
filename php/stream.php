<?php
define('HAMA-Radio', 'Radio');
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

/**
 * Loading
 */
require_once( __DIR__ . '/classes/autoload.php' );
Config::checkAccess();

/**
 * Radio ID/ Mac Login
 */
try{
	if( empty($_GET['mac']) ){
		throw new Exception();
	}
	$radioid = new Id($_GET['mac']);
}
catch(Exception $e){
	Output::sendAnswer('<Error>No MAC!</Error>');
	die(); //will never be reached
}
$data = new Data($radioid->getId());

/**
 * Answer Proxy Request
 */
if( !empty( $_GET['id'] ) ){
	$id = $_GET['id'];

	// id ok?
	if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
		if( !isset($_GET['eid']) ){ // station
			// get url
			$station = $data->getById( $id ); 
			$url = !empty($station) ? $station['url'] : '';
		}
		else if(is_numeric( $_GET['eid'] ) && preg_replace('/[^0-9]/','', $_GET['eid'] ) === $_GET['eid'] ){ // podcast episode
			$ed = PodcastLoader::getEpisodeData( $id, $_GET['eid'], $data );
			$url = !empty($ed) ? $ed['episode']['url'] : '';
		}
		else{
			$url = '';
		}
		// station known?
		if( !empty($url) ){ 
			$url = filter_var( $url, FILTER_SANITIZE_URL, FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_SCHEME_REQUIRED); //clean url

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