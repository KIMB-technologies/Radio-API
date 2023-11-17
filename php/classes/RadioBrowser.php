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
 * Main class to access https://www.radio-browser.info/ and make the stations from there 
 * available to the users of Radio-API.
 */
class RadioBrowser {

	private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

	private const RADIO_BROWSER_API = "all.api.radio-browser.info";
	private const RADIO_BROWSER_LIMIT = 100;
	private const RADIO_BROWSER_LAST_MAX = 40;

	private Id $radioid;
	private RedisCache $redis;
	private array $api_servers;
	private string $api_server;
	private bool $initialized = false;

	public static function matchStationID(string $id) : bool {
		return preg_match(self::UUID_REGEX, $id) === 1;
	}

	/**
	 * Create the Radio-Browser object to access https://api.radio-browser.info/
	 */
	public function __construct( Id $id ){
		$this->radioid = $id;
	}

	private function before_request(?Output $out = null) : void {
		if(!$this->initialized){
			$this->redis = new RedisCache("radio-browser");

			// API server selection
			if($this->redis->keyExists('api_servers') ){
				$this->api_servers = $this->redis->arrayGet('api_servers');
				$this->api_server = $this->redis->get('api_server');
			}
			else {
				// get server ips
				$records = dns_get_record(self::RADIO_BROWSER_API, DNS_A);
				if($records === false){
					$out->addDir("Error connecting to Radio-Browser API!", Config::DOMAIN . "?go=initial");
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
								"User-Agent: KIMB-technologies/Radio-API\r\n",
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
		return Config::DOMAIN . "radio-browser?". 
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
			$out->prevUrl(Config::DOMAIN . "?go=initial");

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
					$keyLast = 'last_stations.'.$this->radioid->getId();
					if($this->redis->keyExists($keyLast)){
						$last = $this->redis->arrayGet($keyLast);
						$now = time();
						foreach($last as $uuid => $item){
							$out->addStation(
								$uuid, $item["name"], $item["url"], light: true, sortKey: $now-$item["time"]
							);
						}
					}
					else {
						$out->addDir("You do not have last stations.", $this->browseUrl());
					}
					return; 
				default:
					$out->addDir("Invalid request!", Config::DOMAIN . "?go=initial");
					return;
			}

			// run the query
			$list = $this->run_request($path, $params);
			if($list === false){
				$out->addDir("Error fetching data from Radio-Browser API!", Config::DOMAIN . "?go=initial");
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
						$out->addStation( $item["stationuuid"], $item["name"], $item["url"], light: true, sortKey: $i+1);
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
				$out->addDir("Error fetching data from Radio-Browser API!", Config::DOMAIN . "?go=initial");
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
				$out->addDir("Error fetching data from Radio-Browser API!", Config::DOMAIN . "?go=initial");
				return;
			}

			foreach($list as $i => $item){
				$out->addStation( $item["stationuuid"], $item["name"], $item["url"], light: true, sortKey: $i+1);
			}

			// get country of state
			if( $by == "states" && count($list) > 0 ){
				$out->prevUrl($this->browseUrl( "countries" , $list[0]["country"]));
			}
		}
		else {
			$out->addDir("Invalid request!", Config::DOMAIN . "?go=initial");
			return;
		}

		// pagination
		if( $offset > 0 ){
			$out->addDir("Previous Page", $this->browseUrl($by, $term, max(0, $offset-self::RADIO_BROWSER_LIMIT)), sortKey: 0);
		}
		$out->addDir("Next Page", $this->browseUrl($by, $term, $offset+self::RADIO_BROWSER_LIMIT), true);
	}
	
	public function handleStationPlay(Output $out, string $uuid) : void {
		$this->before_request($out);

		$keyPrev = 'last_browse.'.$this->radioid->getId();
		$out->prevUrl($this->redis->keyExists($keyPrev) ? $this->redis->get($keyPrev) : $this->browseUrl());

		if(!self::matchStationID($uuid)){
			$out->addDir("Invalid Station ID!", Config::DOMAIN . "?go=initial");
		}

		// fetch station data
		$stations = $this->run_request("stations/byuuid", array("uuids" => $uuid));
		if($stations === false){
			$out->addDir("Error fetching data from Radio-Browser API!", Config::DOMAIN . "?go=initial");
			return;
		}
		$station = $stations[0];

		// show to radio
		$out->addStation(
			$station["stationuuid"],
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
		$keyLast = 'last_stations.'.$this->radioid->getId();
		$last = $this->redis->keyExists($keyLast) ? $this->redis->arrayGet($keyLast) : array();

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

		$this->redis->arraySet($keyLast, $last);
	}


	/**
	 * Dump all last stations to disk (called by cron)
	 */
	public static function dumpToDisk() : bool {
		if( is_file( __DIR__ . '/../data/table.json' ) ){
			$table = json_decode(file_get_contents( __DIR__ . '/../data/table.json' ), true);
			$redis = new RedisCache("radio-browser");

			$lasts = array();
			foreach( $table['ids'] as $id => $data ){
				$lasts[$id] = $redis->arrayGet('last_stations.'.$id);
			}

			return file_put_contents(__DIR__ . '/../data/radiobrowser.json', json_encode( $lasts, JSON_PRETTY_PRINT)) !== false;
		}
		return true;
	}

	/**
	 * Load dumped last stations into Redis (done on container startup)
	 */
	public static function loadFromDisk() : array {
		if( is_file(__DIR__ . '/../data/radiobrowser.json') ){
			$lasts = json_decode(file_get_contents(__DIR__ . '/../data/radiobrowser.json'), true);
			$redis = new RedisCache("radio-browser");

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