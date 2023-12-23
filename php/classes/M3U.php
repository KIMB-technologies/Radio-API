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
 * M3U Generator Class
 */
class M3U {

	private Id $radioid;
	private Data $data;

	public function __construct(Id $id) {
		$this->radioid = $id;
		$this->data = new Data($this->radioid->getId());
	}

	public function musicStream( $id ) : void {
		// id ok?
		if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
			// get station
			$stat = $this->data->getById($id);
			if( !empty($stat) ){ // is a station
				if( $stat['type'] == 'nc' ){ // nextcloud stattion?

					$urls = PodcastLoader::getMusicById( $id, $this->data );
					if( $stat['proxy'] ){
						// proxy links
						$m3uLinks = array();
						foreach( $urls as $k => $m ){
							$m3uLinks[] = Config::RADIO_DOMAIN . 'stream.php?id=' . $id . '&track=' . $k . '&mac=' . $this->radioid->getMac();
						}
					}
					else{ // echo links (no proxy)
						$m3uLinks = $urls;
					}

					if( Config::SHUFFLE_MUSIC ){ // different random order each 10 minutes
						srand(intdiv(time(), 600));
						shuffle($m3uLinks);
					}
				}
				else{ // normal station? (just echo streaming-link)
					$m3uLinks = array(
						$stat['url']
					);
				}

				$this->outputM3U($m3uLinks);
			}
		}
	}

	public function audiobookStream() : void {
		die('See Issue #7 on https://github.com/KIMB-technologies/Radio-API/');
	}

	private function outputM3U(array $urls) : void {
		header('Content-Type: audio/x-mpegurl; charset=utf-8');
		echo implode( PHP_EOL, $urls ) . PHP_EOL;
		die();
	}
}
?>