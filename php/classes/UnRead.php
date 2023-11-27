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
 * Manages Un/Read Podcast Episodes
 */
class UnRead {

	private Cache $redis;
	private bool $dontRemove = false;

	/**
	 * Already listened to podcast (mark by *)
	 * @param $id the id of the user
	 */
	public function __construct(int $id){
		$this->redis = new Cache('unread_podcasts.' . $id );
	}

	/**
	 * Tell the system, that a user visits the episode list of this podcast
	 * @param id the podcast id
	 */
	public function searchItem(int $id, string $noturl = '') : void {
		if( $this->redis->keyExists( $id . '-started' ) ){
			$url = $this->redis->get(  $id . '-started' );
			if( empty($noturl) || $url !== $noturl ){
				$this->redis->remove( $url );
				$this->redis->remove( $id . '-started' );
				if( $this->redis->keyExists( 'started' ) ){
					$this->redis->remove( 'started' );	
				}
				$this->dontRemove = true;
			}
		}
	}

	public function knownItem(int $id, string $url) : bool {
		return $this->redis->keyExists( $url );
	}

	/**
	 * Get the status of one episode (as gui prefix string)
	 * @param id the podcast id
	 * @param url of episode
	 */
	public function knownItemMark(int $id, string $url) : string {
		return $this->knownItem($id, $url) ? '' : '*';
	}

	/**
	 * Tell the system, that a user started an episode (SearchType=5) 
	 * @param id the podcast id
	 * @param eid the episode id
	 */
	public function openItem(int $id, string $url){
		$this->searchItem($id, $url);
		if( !$this->redis->keyExists( $url ) ){
			$this->redis->set( $url, 'S' ); // Started
			$this->redis->set( $id . '-started' , $url, 120 );
			$this->redis->set( 'started' , $id , 115 );

			$this->dontRemove = true;
		}
	}

	public function __destruct(){
		if( !$this->dontRemove && $this->redis->keyExists( 'started' ) ){
			$id = intval($this->redis->get( 'started' ));
			$this->searchItem($id);
		}
	}

	/**
	 * Dump all known podcast episodes to disk (called by cron)
	 */
	public static function dumpToDisk(?string $exportDir = null) : bool {
		if( is_file( __DIR__ . '/../data/table.json' ) ){
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);

			$reads = array();
			foreach( $table['ids'] as $id => $data ){
				$redis = new Cache('unread_podcasts.' . $id );
				$reads[$id] = array();
				foreach($redis->getAllKeysOfGroup() as $key ){
					$reads[$id][] = $key;
				}
			}

			return file_put_contents(
				 	(is_null($exportDir) ? __DIR__ . '/../data' : $exportDir) . '/unread.json',
					json_encode( $reads, JSON_PRETTY_PRINT)
				) !== false;
		}
		return true;
	}

	/**
	 * Load dumped known episodes into Redis (done on container startup)
	 */
	public static function loadFromDisk(?string $exportDir = null) : array {
		$file = (is_null($exportDir) ? __DIR__ . '/../data' : $exportDir) . '/unread.json';
		if( is_file($file) ){
			$reads = json_decode(file_get_contents($file), true);
			foreach( $reads as $id => $read ){
				if( !empty($read) ){
					$redis = new Cache('unread_podcasts.' . $id );
					foreach( $read as $r ){
						// update "old" key! "3001-http://..." => "http://..."
						if( preg_match('/^\d+\-(.*)$/', $r, $matches) === 1 ){
							$r = $matches[1];
						}
						$redis->set( $r, 'S' ); // Started
					}
				}
			}
			return $reads;
		}
		return array();
	}

	public function toggleById(string $id, Data $data) : string {
		$this->dontRemove = true;

		if( preg_match('/^(\d+)X(\d+)$/', $id, $parts ) === 1 ){
			$ed = PodcastLoader::getEpisodeData( $parts[1], $parts[2], $data );
			if( $ed != array() ){
				$rkey = $ed['episode']['url'];
				if( $this->redis->keyExists( $rkey ) ){
					$this->redis->remove( $rkey );
				}
				else{
					$this->redis->set( $rkey , 'S' );
				}

				if( $this->redis->keyExists( $parts[1] . '-started' ) ){
					$this->redis->remove( $parts[1] . '-started' );
				}
				if( $this->redis->keyExists( 'started' ) ){
					$this->redis->remove( 'started' );	
				}
				return "ok";
			}
		}
		return "error";
	}
}
?>
