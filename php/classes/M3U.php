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
		$this->data = new Data($this->radioid->getId());;
	}

	public function musicStream( $id ) : void {
		// id ok?
		if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
			// get station
			$stat = $this->data->getById($id);
			if( !empty($stat) ){ // is a station
				$urls = array();
				if( $stat['type'] == 'nc' ){ // nextcloud stattion?
					$urllist = PodcastLoader::getMusicById( $id, $this->data );

					if( $stat['proxy'] ){ // proxy links
						foreach( $urllist as $k => $m ){
							$urls[] = Config::DOMAIN . 'stream.php?id=' . $id . '&track=' . $k . '&mac=' . $this->radioid->getMac();
						}
					}
					else{ // echo links (no proxy)
						$urls = $urllist;
					}
				}
				else{ // normal station? (just echo streaming-link)
					$urls[] = $stat['url'];
				}
				$this->outputM3U($urls);
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