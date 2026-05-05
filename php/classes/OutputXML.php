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
 * Radio-API XML Style Output Class
 */
mb_substitute_character("none");
class OutputXML extends Output {

	protected function detectLanguage() : int {
		if( isset($_GET['dlang']) && is_string($_GET['dlang'])) {
			return array_search($_GET['dlang'], self::ALL_LANGUAGES) ?? 0;
				// 'eng', 'ger', ... (only these two are supported)
		}
		else{
			return 0; // default to english
		}		
	}

	/**
	 * Add a station
	 */
	public function addStation( int|string $id, string $name, string $url,
		OutputPlayStatus $status = OutputPlayStatus::Full,
		string $desc = '', string $logo = '', int|string $sortKey = ""
	) : void {
		$a = array(
			'ItemType' => 'Station',
			'StationId' => $id,
			'StationName' => $this->cleanText($name, true),
		);
		if( $status != OutputPlayStatus::Info ){
			$b = array(
				'StationUrl' => $this->cleanUrl($url),
				'StationDesc' => $this->cleanText($desc),
				'Logo' => $this->cleanUrl($this->logo->logoUrl($logo)),
				'StationFormat' => 'Radio',
				'StationLocation' => 'Earth',
				'StationBandWidth' => 32,
				'StationMime' => 'MP3',
				'Relia' => 5
			);
		}
		else{
			$b = array();
		}
		$this->items[] = array_merge( $a, $b );
		$this->itemsSortKeys[] = 'ra==' . ($sortKey === "" ? $name : $sortKey);
	}

	/**
	 * Add a podcast
	 */
	public function addPodcast(int $podcastid, string $name, string $url, int|string $sortKey = "" ) : void {
		$this->items[] = array(
			'ItemType' => 'ShowOnDemand',
			'ShowOnDemandID' => $podcastid,
			'ShowOnDemandName' => $this->cleanText($name, true),
			'ShowOnDemandURL' => $this->cleanUrl($url),
			'ShowOnDemandURLBackUp' => $this->cleanUrl($url),
			'BookmarkShow' => ''
		);
		$this->itemsSortKeys[] = 'pod==' . ($sortKey === "" ? $name : $sortKey);
	}

	/**
	 * Add a podcast episode
	 */
	public function addEpisode(
		int $podcastid, int|null $episodeid, string $podcastname, string $episodename,
		string $url, string $desc = '', string $logo = '', bool $top = false,
		OutputPlayStatus $status = OutputPlayStatus::Full
	) : void {
		$this->items[] = array(
			'ItemType' => 'ShowEpisode',
			'ShowEpisodeID' =>  $podcastid . (!is_null($episodeid) ? 'X' . $episodeid : ''),	
			'ShowName' => $this->cleanText($podcastname, true),
			'Logo' => $this->cleanUrl($this->logo->logoUrl($logo)),
			'ShowEpisodeName' => $this->cleanText($episodename, true),
			'ShowEpisodeURL' => $this->cleanUrl($url),
			'BookmarkShow' => '',
			'ShowDesc' => $this->cleanText($desc),
			'ShowFormat' => 'Podcast',
			'Lang' => 'KIMBisch',
			'Country' => 'KIMB',
			'ShowMime' => 'MP3'
		);
		$this->itemsSortKeys[] = ($top ? 'epA' : 'epZ' ) . '==' . $podcastid . '==' . $episodeid;
	}

	/**
	 * Add a folder
	 */
	public function addDir(string $name, string $url, bool $isLast = false, int|string $sortKey = "") : void {
		$this->items[] = array(
			'ItemType' => 'Dir',
			'Title' => $this->cleanText($name, true),
			'UrlDir' => $this->cleanUrl($url),
			'UrlDirBackUp' => $this->cleanUrl($url)
		);
		$this->itemsSortKeys[] = ($isLast ? 'z' : '') . 'dir==' . ($sortKey === "" ? $name : $sortKey);;
	}

	protected function getItemName(array $item) : string {
		foreach(['Title', 'StationName', 'ShowOnDemandName', 'ShowEpisodeName'] as $field){
			if(isset($item[$field])){
				return $item[$field];
			}
		}
		return '';
	}

	protected function formatItems(array $items) : string {
		//output
		$lines = array(
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
			'<ListOfItems>',
			'  <ItemCount>' . ( count( $this->items ) ) .'</ItemCount>'
		);

		// add <- back url
		if(!empty( $this->prevUrl )){
			array_unshift( $this->items, 
				array(
					'ItemType' => 'Previous',
					'UrlPrevious' => $this->cleanUrl($this->prevUrl),
					'UrlPreviousBackUp' => $this->cleanUrl($this->prevUrl)
				)
			);
		}

		foreach( $this->items as $item ){
			$lines[] = '  <Item>';
			foreach( $item as $key => $value ){
				$lines[] = '    <' . $key . '>' . $value . '</' . $key . '>';
			}
			$lines[] = '  </Item>';
		}
		$lines[] = '</ListOfItems>';
	
		//data setup
		return implode(PHP_EOL, $lines);
	}
}
?>