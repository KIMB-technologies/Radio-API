<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2026 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMARadio') or die('Invalid Endpoint');

/**
 * Radio-API JSON Style Output Class
 */
mb_substitute_character("none");
class OutputJSON extends Output {

	protected function detectLanguage() : int {
		if( !empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ){
			$langs = array_map( function ($i){
				return strtolower(substr(trim($i),0,2));
			}, explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE']));

			return in_array('de', $langs) ? 1 : 0;
		}
		else{
			return 0; // default to english
		}
	}

	public function addStation(
		int|string $id, string $name, string $url,
		OutputPlayStatus $status = OutputPlayStatus::Full,
		string $desc = '', string $logo = '', int|string $sortKey = ""
	) : void {
		$info = array(
			'id' => [
				'frontiersmart',
				'radio',
				'?sSearchtype=3&Search=' . $id
			],
			'title' => $this->cleanText($name, true),
			'url' => $this->cleanUrl(Config::RADIO_DOMAIN . '?sSearchtype=3&Search=' . $id),
			'description' => $this->cleanText($desc),
			'images' => array(array(
				'url' => $this->cleanUrl($this->logo->logoUrl($logo)),
				'size' => [150, 150],
				'type' => 'cover'
			)),
			'streams' => array(array(
				'url' => $this->cleanUrl(Config::RADIO_DOMAIN . '?sSearchtype=3&Search=' . $id . '&play'),
				'codec' => array(
					'name' => "MP3",
					'bitrate' => 64,
				),
				'reliability' => 1
			))
		);
		
		if($status == OutputPlayStatus::Info){
			unset($info['description']);
			unset($info['streams']);

			$this->items[] = array(
				'type' => 'directory',
				'id' => null,
				'key' => ['content', 'entries'],
				'value' => $info
			);
		}
		else{
			unset($info['id']);

			if($status == OutputPlayStatus::Play){
				$this->items[] = array(
					'type' => 'redirect',
					'id' => $id,
					'key' => [],
					'value' => array(
						'url' => $this->cleanUrl($url, false),
						'codec' => array(
							'name' => "MP3",
							'bitrate' => 64,
						),
						'content' => $info
					)
				);
			}
			else{
				$this->items[] = array(
					'type' => 'radio',
					'id' => $id,
					'key' => [],
					'value' => $info
				);
			}
		}

		$this->itemsSortKeys[] = 'ra==' . ($sortKey === "" ? $name : $sortKey);
	}

	public function addPodcast(int $podcastid, string $name, string $url, int|string $sortKey = "") : void {
		$this->items[] = array(
			'type' => 'directory',
			'id' => null,
			'key' => ['content', 'entries'],
			'value' => array(
				'id' => [
					'frontiersmart',
					'directory',
					$podcastid
				],
				'title' => $this->cleanText($name, true),
				'url' => $this->cleanUrl($url)
			)
		);

		$this->itemsSortKeys[] = 'pod==' . ($sortKey === "" ? $name : $sortKey);
	}

	public function addEpisode(
		int $podcastid, int|null $episodeid, string $podcastname, string $episodename,
		string $url, string $desc = '', string $logo = '', bool $top = false,
		OutputPlayStatus $status = OutputPlayStatus::Full
	) : void {
		$fullid = $podcastid . (!is_null($episodeid) ? 'X' . $episodeid : '');

		$info = array(
			'id' => [
				'frontiersmart',
				'episode',
				'?sSearchtype=5&Search=' . $fullid
			],
			'title' => $this->cleanText($episodename, true),
			'url' => $this->cleanUrl(Config::RADIO_DOMAIN . '?sSearchtype=5&Search=' . $fullid),
			'description' => $this->cleanText($desc),
			'images' => array(array(
				'url' => $this->cleanUrl($this->logo->logoUrl($logo)),
				'size' => [150, 150],
				'type' => 'cover'
			)),
			'streams' => array(array(
				'url' => $this->cleanUrl(Config::RADIO_DOMAIN . '?sSearchtype=5&Search=' . $fullid . '&play'),
				'codec' => array(
					'name' => "MP3",
					'bitrate' => 64,
				),
				'reliability' => 1
			))
		);

		if($status == OutputPlayStatus::Info){
			$this->items[] = array(
				'type' => 'feed',
				'id' => $podcastid,
				'key' => ['content', 'entries'],
				'value' => $info
			);
		}
		else{
			unset($info['id']);

			if($status == OutputPlayStatus::Play){
				$this->items[] = array(
					'type' => 'redirect',
					'id' => $fullid,
					'key' => [],
					'value' => array(
						'url' => $this->cleanUrl($url, false),
						'codec' => array(
							'name' => "MP3",
							'bitrate' => 64,
						),
						'content' => $info
					)
				);
			}
			else{
				$this->items[] = array(
					'type' => 'episode',
					'id' => $fullid,
					'key' => [],
					'value' => $info
				);
			}
		}
		
		$this->itemsSortKeys[] = ($top ? 'epA' : 'epZ' ) . '==' . $podcastid . '==' . $episodeid;
	}

	public function addDir(string $name, string $url, bool $isLast = false, int|string $sortKey = "") : void {
		$this->items[] = array(
			'type' => 'directory',
			'id' => null,
			'key' => ['content', 'entries'],
			'value' => array(
				'id' => [
					'frontiersmart',
					'directory',
					$this->clearId($name)
				],
				'title' => $this->cleanText($name, true),
				'url' => $this->cleanUrl($url)
			)
		);
		$this->itemsSortKeys[] = ($isLast ? 'z' : '') . 'dir==' . ($sortKey === "" ? $name : $sortKey);
	}

	public function index() : void {
		$this->currentUrl(Config::RADIO_DOMAIN, 'Index');

		$this->items[] = array(
			'type' => 'index',
			'id' => null,
			'key' => ['content', 'entries'],
			'value' => array(
				'id' => [
					'frontiersmart',
					'service',
					'radio'
				],
				'title' => 'Radio',
				'url' => $this->cleanUrl(Config::RADIO_DOMAIN . 'index')
			)
		);
		$this->itemsSortKeys[] = 'dir==Radio';

		$this->items[] = array(
			'type' => 'index',
			'id' => null,
			'key' => ['content', 'entries'],
			'value' => array(
				'id' => [
					'frontiersmart',
					'service',
					'feed'
				],
				'title' => 'Podcast',
				'url' => $this->cleanUrl(Config::RADIO_DOMAIN . 'index')
			)
		);
		$this->itemsSortKeys[] = 'dir==Podcast';
	}

	protected function getItemName(array $item) : string {
		return empty($item['value']['title']) ? '' : $item['value']['title'];
	}

	protected function clearId(string $id) : string {
		return preg_replace('/[^a-z0-9]/', '', strtolower($id));
	}

	protected function formatItems(array $items) : string {
		$json = array(
			'id' => array(), // prepare empty value
			'title' => self::cleanText($this->selfTitle, true),
			'url' => self::cleanUrl($this->selfUrl),
		);

		$type = 'directory';
		$id = null;
		foreach($items as $item){

			// add the values, either to root or to the right place in json
			if(count($item['key']) == 0){
				$json = array_merge($json, $item['value']);
			}
			else{
				// move to right place/ key in json 
				$ji = &$json;
				foreach($item['key'] as $k){
					if(!isset($ji[$k])){
						$ji[$k] = array();
					}
					$ji = &$ji[$k];
				}
				// and add item
				$ji[] = $item['value'];
			}

			// detect type and id for later
			$type = $item['type'];
			$id = $item['id'];
		}

		// add the id chain
		$json['id'] = $type == 'index' ?
				['airable', 'directory', 'index' ]
			:
				['frontiersmart', $type, is_null($id) ? $this->clearId($this->selfTitle) : $id];
		
		return json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	}

	protected function contentType() : string {
		return 'application/json';
	}
}

?>