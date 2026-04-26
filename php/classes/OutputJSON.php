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
		$light = false, string $desc = '', string $logo = '', int|string $sortKey = ""
	) : void {
		// TODO
		$this->items[] = array();

		$id;
		$this->cleanText($name, true);

		if( !$light ){
			$this->cleanUrl($url);
			$this->cleanText($desc);
			$this->cleanUrl($this->logo->logoUrl($logo));
		}

		$this->itemsSortKeys[] = 'ra==' . ($sortKey === "" ? $name : $sortKey);
	}

	public function addPodcast(int $podcastid, string $name, string $url, int|string $sortKey = "") : void {
		// TODO
		$this->items[] = array();

		$podcastid;
		$this->cleanText($name, true);
		$this->cleanUrl($url);
		$this->cleanUrl($url);

		$this->itemsSortKeys[] = 'pod==' . ($sortKey === "" ? $name : $sortKey);
	}

	public function addEpisode(
		int $podcastid, int|null $episodeid, string $podcastname, string $episodename,
		string $url, string $desc = '', string $logo = '', bool $top = false
	) : void {
		// TODO
		$this->items[] = array();

		$podcastid . (!is_null($episodeid) ? 'X' . $episodeid : '');
		$this->cleanText($podcastname, true);
		$this->cleanUrl($this->logo->logoUrl($logo));
		$this->cleanText($episodename, true);
		$this->cleanUrl($url);
		$this->cleanText($desc);

		$this->itemsSortKeys[] = ($top ? 'epA' : 'epZ' ) . '==' . $podcastid . '==' . $episodeid;
	}

	public function addDir(string $name, string $url, bool $isLast = false, int|string $sortKey = "") : void {
		// TODO
		$this->items[] = array();

		$this->cleanText($name, true);
		$this->cleanUrl($url);
		
		$this->itemsSortKeys[] = ($isLast ? 'z' : '') . 'dir==' . ($sortKey === "" ? $name : $sortKey);;
	}

	protected function getItemName(array $item) : string {
		// TODO
		return '';
	}

	protected function formatItems(array $items) : string {
		// TODO
		return '
{
  "id": [
    "airable",
    "directory",
    "index"
  ],
  "title": "Index",
  "url": "'.Config::RADIO_DOMAIN.'",
  "content": {
    "entries": [
      {
        "id": [
          "frontiersmart",
          "service",
          "radio"
        ],
        "title": "Internet Radio",
        "url": "'.$this->cleanUrl(Config::RADIO_DOMAIN.'?huhu=radio') . '"
      },
      {
        "id": [
          "frontiersmart",
          "service",
          "feed"
        ],
        "title": "Podcasts",
        "url": "'.$this->cleanUrl(Config::RADIO_DOMAIN.'?huhu=podc') . '"
      }
    ]
  }
}
		';
	}

	protected function contentType() : string {
		return 'application/json';
	}
}

?>
