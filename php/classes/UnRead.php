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
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * Manages Un/Read Podcast Episodes
 */
class UnRead {

	private RedisCache $redis;
	private bool $dontRemove = false;

	/**
	 * Already listened to podcast (mark by *)
	 * @param $id the id of the user
	 */
	public function __construct(int $id){
		$this->redis = new RedisCache('unread_podcasts.' . $id );
	}

	/**
	 * Tell the system, that a user visits the episode list of this podcast
	 * @param id the podcast id
	 */
	public function searchItem(int $id, string $noturl = '') : void {
		if( $this->redis->keyExists( $id . '-started' ) ){
			$url = $this->redis->get(  $id . '-started' );
			if( empty($noturl) || $url !== $noturl ){
				$this->redis->remove( $id . '-' . $url );
				$this->redis->remove( $id . '-started' );
				if( $this->redis->keyExists( 'started' ) ){
					$this->redis->remove( 'started' );	
				}
				$this->dontRemove = true;
			}
		}
	}

	/**
	 * Get the status of one episode (as gui prefix string)
	 * @param id the podcast id
	 * @param url of episode
	 */
	public function knownItem(int $id, string $url) : string {
		return $this->redis->keyExists( $id . '-' . $url ) ? '' : '*';
	}

	/**
	 * Tell the system, that a user started an episode (SearchType=5) 
	 * @param id the podcast id
	 * @param eid the episode id
	 */
	public function openItem(int $id, string $url){
		$this->searchItem($id, $url);
		if( !$this->redis->keyExists( $id . '-' . $url ) ){
			$this->redis->set( $id . '-' . $url, 'S' ); // Started
			$this->redis->set( $id . '-started' , $url, 120 );
			$this->redis->set( 'started' , $id , 115 );

			$this->dontRemove = true;
		}
	}

	public function __destruct(){
		if( !$this->dontRemove && $this->redis->keyExists( 'started' ) ){
			$id = intval($this->redis->get(  'started' ));
			$this->searchItem($id);
		}
	}

	/**
	 * Dump all known podcast episodes to disk (called by cron)
	 */
	public static function dumpToDisk() : bool {
		if( is_file( __DIR__ . '/../data/table.json' ) ){
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);

			$reads = array();
			foreach( $table['ids'] as $id => $data ){
				$redis = new RedisCache('unread_podcasts.' . $id );
				$reads[$id] = array();
				foreach($redis->getAllKeysOfGroup() as $key ){
					if( preg_match('/^.*:(\d+\-[^s].*)$/', $key, $matches) === 1){
						$reads[$id][] = $matches[1];
					}
				}
			}

			return file_put_contents(__DIR__ . '/../data/unread.json', json_encode( $reads, JSON_PRETTY_PRINT)) !== false;
		}
		return true;
	}

	/**
	 * Load dumped known episodes into Redis (done on container startup)
	 */
	public static function loadFromDisk() : array {
		if( is_file(__DIR__ . '/../data/unread.json') ){
			$reads = json_decode(file_get_contents(__DIR__ . '/../data/unread.json'), true);
			foreach( $reads as $id => $read ){
				if( !empty($read) ){
					$redis = new RedisCache('unread_podcasts.' . $id );
					foreach( $read as $r ){
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
				$rkey = $parts[1] . '-' . $ed['episode']['url'];
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
