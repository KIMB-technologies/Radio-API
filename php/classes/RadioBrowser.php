<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2024 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
defined('HAMA-Radio') or die('Invalid Endpoint');


/**
 * Main class to access https://www.radio-browser.info/ and make the stations from there 
 * available to the users of Radio-API.
 */
class RadioBrowser {

	private const STATION_ID_REGEX = '/^[0-9a-f]{32}$/i'; // the radio can only memorize IDs of 32 chars, thus remove the - and add them later again

	private const RADIO_BROWSER_API = "all.api.radio-browser.info";
	private const RADIO_BROWSER_LIMIT = 50;
	private const RADIO_BROWSER_LAST_MAX = 40;

	private Id $radioid;
	private Cache $redis;
	private array $api_servers;
	private string $api_server;
	private bool $initialized = false;

	public static function matchStationID(string $id) : bool {
		return preg_match(self::STATION_ID_REGEX, $id) === 1;
	}

	public static function stationIDfromUUID(string $uuid) : string {
		return str_replace('-', '', $uuid);
	}

	public static function uuidFromStationID(string $id) : string|false {
		if(!self::matchStationID($id)) {
			return false;
		}
		return substr($id, 0, 8) . '-' .  substr($id, 8, 4) . '-' .
			substr($id, 12, 4) . '-' . substr($id, 16, 4) . '-' . substr($id, 20, 12);
	}


	/**
	 * Create the Radio-Browser object to access https://api.radio-browser.info/
	 */
	public function __construct( Id|int $id ){
		if(is_integer($id)){
			$this->radioid = new Id( strval($id), Id::ID );
		}
		else {
			$this->radioid = $id;
		}
	}

	private function log(array $data) : void {
		file_put_contents(
			Config::LOG_DIR . '/radiobrowser.log',
			date('d.m.Y H:i:s') . " : " . json_encode( $data ) . PHP_EOL,
			FILE_APPEND
		);
	}

	private function before_request(?Output $out = null) : void {
		if(!$this->initialized){
			$this->redis = new Cache("radio-browser");

			// API server selection
			if($this->redis->keyExists('api_servers') ){
				$this->api_servers = $this->redis->arrayGet('api_servers');
				$this->api_server = $this->redis->get('api_server');
			}
			else {
				// get server ips
				$records = dns_get_record(self::RADIO_BROWSER_API, DNS_A);
				if($records === false){
					if(!is_null($out)){
						$out->addDir("Error connecting to Radio-Browser API!", Config::RADIO_DOMAIN . "?go=initial");
					}
					$this->log(["Error connecting to Radio-Browser API!", "dns request failed"]);
					return;
				}
		
				// only keep ips 
				$records = array_map(fn($v) => $v["ip"], $records);
				shuffle($records);

				// translate to hostnames
				$this->api_servers = [];
				foreach( $records as $record ){
					$this->api_servers[] = gethostbyaddr($record);
				}

				// select one API server
				$this->api_server = $this->api_servers[0];
				
				$this->redis->arraySet('api_servers', $this->api_servers, Config::CACHE_EXPIRE);
				$this->redis->set('api_server', $this->api_server);
			}

			// mark as initialized
			$this->initialized = true;
		}
	}

	private function run_request(string $path, array $params = array(), bool $cache = true) {
		if($cache){
			$cacheKey = 'api_cache.' . sha1($path . json_encode($params));
			if($this->redis->keyExists($cacheKey)){
				return json_decode($this->redis->get($cacheKey), true);
			}
		}

		// check all servers, if one has errors
		$errored_servers = array();
		while( true ){

			$context  = stream_context_create(
				array(
					'http' =>
						array(
							'method'  => 'POST',
							'header'  =>
								"Content-Type: application/x-www-form-urlencoded\r\n" . 
								"User-Agent: KIMB-technologies/Radio-API/".Config::VERSION."\r\n",
							'content' => http_build_query( $params ),
							'timeout' => 5
						)
				)
			);
			
			$response = file_get_contents(
				'https://' . $this->api_server . '/json/' . $path,
				false,
				$context
			);

			if($response !== false){
				$result = json_decode($response, true);
				if( !is_null($result) ){
					if($cache){
						$this->redis->set($cacheKey, $response, Config::CACHE_EXPIRE);
					}
					return $result;
				}
			}

			$this->log(["Error requesting from Radio-Browser API!", $this->api_server, '/json/'. $path, $params, $response ]);

			// error with current server occured
			$errored_servers[] = $this->api_server;
			$remaining_servers = array_values(array_diff($this->api_servers, $errored_servers));
			if( count($remaining_servers) > 0) {
				$this->api_server = $remaining_servers[0];
				$this->redis->set('api_server', $this->api_server);
			}
			else {
				return false;
			}
		}
	}

	private function browseUrl(string $by = "none", string $term = "none", int $offset = 0) : string {
		return Config::RADIO_DOMAIN . "radio-browser?". 
			"by=" . rawurlencode($by) .
			"&term=" . rawurlencode($term) .
			( $offset > 0 ? "&offset=".$offset : '');
	}

	public function handleBrowse(Output $out, string $by, string $term, int $offset = 0) : void {
		$this->before_request($out);

		// store last request from user 
		$keyPrev = 'last_browse.'.$this->radioid->getId();
		$this->redis->set($keyPrev, $this->browseUrl($by, $term, $offset));		

		if($by == "none" && $term == "none"){
			$out->prevUrl(Config::RADIO_DOMAIN . "?go=initial");

			$out->addDir("Languages", $this->browseUrl("languages"));
			$out->addDir("Tags", $this->browseUrl("tags"));
			$out->addDir("Countries", $this->browseUrl("countries"));
			$out->addDir("Top Click", $this->browseUrl("topclick"));
			$out->addDir("Top Vote", $this->browseUrl("topvote"));
			$out->addDir("My Last", $this->browseUrl("last"));

			return;
		}
		else if ($term == "none"){
			$out->prevUrl($this->browseUrl());

			// build query parameters
			$params = array(
				"limit" => self::RADIO_BROWSER_LIMIT,
				"hidebroken" => "true",
				"offset" => $offset
			);
			switch ($by) {
				case "languages":
				case "tags":
					$params["order"] = "stationcount";
					$params["reverse"] = "true";
				case "countries":
					$path = $by;
					break;
				case "topclick":
				case "topvote":
					$path = "stations/".$by;
					break;
				case "last":
					$last = $this->lastStations();
					$now = time();
					foreach($last as $uuid => $item){
						$out->addStation(
							self::stationIDfromUUID($uuid), $item["name"], $item["url"], light: true, sortKey: $now-$item["time"]
						);
					}
					if(empty($last)){
						$out->addDir("You do not have last stations.", $this->browseUrl());
					}
					return; 
				default:
					$out->addDir("Invalid request!", Config::RADIO_DOMAIN . "?go=initial");
					return;
			}

			// run the query
			$list = $this->run_request($path, $params);
			if($list === false){
				$out->addDir("Error fetching data from Radio-Browser API!", Config::RADIO_DOMAIN . "?go=initial");
				return;
			}

			// build the result
			switch ($by) {
				case "languages":
				case "tags":
				case "countries":
					foreach($list as $i => $item){
						$out->addDir(
							( $by == "countries" ? $item["iso_3166_1"] . " - " : '') . $item["name"],
							$this->browseUrl($by, $item["name"]), sortKey: $i+1
						);
					}
					break;
				case "topclick":
				case "topvote":
					foreach($list as $i => $item){
						$out->addStation(
							self::stationIDfromUUID($item["stationuuid"]), $item["name"],
							$item["url"], light: true, sortKey: $i+1
						);
					}
					break;
			}
		}
		else if ( $by == "countries" ) { // $term != "all" => show states to select from !!
			$out->prevUrl($this->browseUrl($by));

			$out->addDir("All States", $this->browseUrl("country-all", $term), sortKey: 1 );

			$list = $this->run_request(
				"states/".rawurlencode($term)."/",
				array(
					"limit" => self::RADIO_BROWSER_LIMIT,
					"hidebroken" => "true",
					"offset" => $offset,
					"order" => "stationcount",
					"reverse" => "true"
				)
			);
			if($list === false){
				$out->addDir("Error fetching data from Radio-Browser API!", Config::RADIO_DOMAIN . "?go=initial");
				return;
			}

			foreach($list as $i => $item){
				$out->addDir( $item["name"], $this->browseUrl("states", $item["name"]), sortKey: $i+2 );
			}	
		}
		else if ( $by == "languages" || $by == "tags" || $by == "states" || $by == "country-all" ){
			$out->prevUrl($this->browseUrl($by));
			if($by == "country-all"){
				$out->prevUrl($this->browseUrl("countries", $term));
			}

			// build query parameters
			$params = array(
				"limit" => self::RADIO_BROWSER_LIMIT,
				"hidebroken" => "true",
				"offset" => $offset,
				"order" => "clickcount",
				"reverse" => "true"
			);
			switch ($by) {
				case "languages":
					$path = "stations/bylanguageexact";
					break;
				case "tags":
					$path = "stations/bytagexact";
					break;
				case "states":
					$path = "stations/bystateexact";
					break;
				case "country-all":
					$path = "stations/bycountryexact";
					break;
			}

			$list = $this->run_request($path . "/" . rawurlencode($term), $params );
			if($list === false){
				$out->addDir("Error fetching data from Radio-Browser API!", Config::RADIO_DOMAIN . "?go=initial");
				return;
			}

			foreach($list as $i => $item){
				$out->addStation(
					self::stationIDfromUUID($item["stationuuid"]), $item["name"],
					$item["url"], light: true, sortKey: $i+1
				);
			}

			// get country of state
			if( $by == "states" && count($list) > 0 ){
				$out->prevUrl($this->browseUrl( "countries" , $list[0]["country"]));
			}
		}
		else {
			$out->addDir("Invalid request!", Config::RADIO_DOMAIN . "?go=initial");
			return;
		}

		// pagination 
		if( $offset > 0 ){ // leads to "prevUrl" with smaller offset
			$out->prevUrl($this->browseUrl($by, $term, max(0, $offset-self::RADIO_BROWSER_LIMIT)));
		}
		$out->addDir("Next Page", $this->browseUrl($by, $term, $offset+self::RADIO_BROWSER_LIMIT), true);
	}

	public function lastStations() : array {
		$this->before_request();

		$keyLast = 'last_stations.'.$this->radioid->getId();
		return $this->redis->keyExists($keyLast) ? $this->redis->arrayGet($keyLast) : array();
	}

	public function searchStation(string $search) : array {
		$this->before_request();

		$stations = $this->run_request(
			"stations/search",
			array(
				"name" => trim($search),
				"order" => "clickcount",
				"limit" => self::RADIO_BROWSER_LIMIT
			)
		);
		if($stations === false){
			return array();
		}

		return array_map(
			fn($s) => array(
				"name" => $s["name"],
				"url" => $s["url"],
				"logo" => $s["favicon"],
				"desc" => $s["language"] . ', ' . $s["country"] . ', ' . $s["state"] . ', ' . $s["tags"]
			),
			$stations
		);
	}
	
	public function handleStationPlay(Output $out, string $id) : void {
		$this->before_request($out);

		$keyPrev = 'last_browse.'.$this->radioid->getId();
		$out->prevUrl($this->redis->keyExists($keyPrev) ? $this->redis->get($keyPrev) : $this->browseUrl());

		$uuid = self::uuidFromStationID($id);
		if($uuid === false){
			$out->addDir("Unable to find station by ID!", Config::RADIO_DOMAIN . "?go=initial");
			return;
		}

		// fetch station data
		$stations = $this->run_request("stations/byuuid", array("uuids" => $uuid));
		if($stations === false){
			$out->addDir("Error fetching data from Radio-Browser API!", Config::RADIO_DOMAIN . "?go=initial");
			return;
		}
		$station = $stations[0];

		// show to radio
		$out->addStation(
			self::stationIDfromUUID($station["stationuuid"]),
			$station["name"],
			$station["url"],
			false,
			$station["tags"] . " - " . $station["country"],
			$station["favicon"]
		);

		// send the click to server to maintain "station clicks"
		// 	https://at1.api.radio-browser.info/#Count_station_click
		$this->run_request("url/" . $uuid, cache: false);

		// get user's "last stations"
		$last = $this->lastStations();

		// add station and add current time
		if(!array_key_exists($station["stationuuid"], $last)){
			$last[$station["stationuuid"]] = array(
				"name" => $station["name"],
				"url" => $station["url"],
			);
		}
		$last[$station["stationuuid"]]["time"] = time();

		if (count($last) > self::RADIO_BROWSER_LAST_MAX){
			uasort($last, fn($x, $y) => $y["time"] <=> $x["time"] );
			$last = array_slice($last, 0, self::RADIO_BROWSER_LAST_MAX);
		}

		$this->redis->arraySet('last_stations.'.$this->radioid->getId(), $last);
	}


	/**
	 * Dump all last stations to disk (called by cron)
	 */
	public static function dumpToDisk(?string $exportDir = null) : bool {
		if( is_file( __DIR__ . '/../data/table.json' ) ){
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);
			$redis = new Cache("radio-browser");

			$lasts = array();
			foreach( $table['ids'] as $id => $data ){
				$lasts[$id] = $redis->arrayGet('last_stations.'.$id);
			}

			return file_put_contents(
					(is_null($exportDir) ? __DIR__ . '/../data' : $exportDir) . '/radiobrowser.json',
					json_encode( $lasts, JSON_PRETTY_PRINT)
				) !== false;
		}
		return true;
	}

	/**
	 * Load dumped last stations into Redis (done on container startup)
	 */
	public static function loadFromDisk(?string $exportDir = null) : array {
		$file = (is_null($exportDir) ? __DIR__ . '/../data' : $exportDir) . '/radiobrowser.json';
		if( is_file($file) ){
			$lasts = json_decode(file_get_contents($file), true);
			$redis = new Cache("radio-browser");

			foreach( $lasts as $id => $last ){
				if( !empty($last) ){
					$redis->arraySet('last_stations.'.$id, $last);
				}
			}
			return $lasts;
		}
		return array();
	}
}

?>