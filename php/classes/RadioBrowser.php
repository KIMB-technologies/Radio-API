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
	private const RADIO_BROWSER_CACHE_TTL = 60*5;
	private const RADIO_BROWSER_LAST_MAX = 20;

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
				
				$this->redis->arraySet('api_servers', $this->api_servers, self::RADIO_BROWSER_CACHE_TTL);
				$this->redis->set('api_server', $this->api_server);
			}

			// mark as initialized
			$this->initialized = true;
		}
	}

	private function run_request(string $path, array $params = array()) {
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

	public function handleBrowse(Output $out, string $by, string $term, int $offset = 0) : void {
		$this->before_request($out);

		// store last request from user 
		$this->redis->set('last_browse.'.$this->radioid->getId(), "by=".$by."&term=".urlencode($term)."&offset=".$offset );		

		if($by == "none" && $term == "none"){
			$out->prevUrl(Config::DOMAIN . "?go=initial");

			$out->addDir("Country", Config::DOMAIN . 'radio-browser?by=country&term=none');
			$out->addDir("Language", Config::DOMAIN . 'radio-browser?by=language&term=none');
			$out->addDir("Tags", Config::DOMAIN . 'radio-browser?by=tags&term=none');
			$out->addDir("Top Click", Config::DOMAIN . 'radio-browser?by=topclick&term=none');
			$out->addDir("Top Votes", Config::DOMAIN . 'radio-browser?by=votes&term=none');
			$out->addDir("My Last", Config::DOMAIN . 'radio-browser?by=last&term=none');
		}
		else if ($term == "none"){
			$out->prevUrl(Config::DOMAIN . 'radio-browser?by=none&term=none');

			// build query parameters
			$params = array(
				"limit" => self::RADIO_BROWSER_LIMIT,
				"hidebroken" => "true",
				"offset" => $offset
			);
			switch ($by) {
				case "country":
					$path = "countries";
					break;
				case "language":
					$path = "languages";
					$params["order"] = "stationcount";
					$params["reverse"] = "true";
					break;
				case "tags":
					$path = "tags";
					$params["order"] = "stationcount";
					$params["reverse"] = "true";
					break;
				case "topclick":
					$path = "stations/topclick";
					break;
				case "votes":
					$path = "stations/topvote";
					break;
				case "last":
					if($this->redis->keyExists('last_stations.'.$this->radioid->getId())){
						$last = $this->redis->arrayGet('last_stations.'.$this->radioid->getId());
						// TODO: better storing for Redis (Redis stores Arrays Keys as strings!!)
						$last = array_reverse($last);
						foreach($last as $i => $item){
							$out->addStation(
								$item["uuid"], $item["name"], $item["url"],
								true, "", "", $i+1
							);
						}
					}
					else {
						$out->addDir("You do not have last stations.", Config::DOMAIN . 'radio-browser?by=none&term=none');
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
				case "country":
				case "language":
				case "tags":
					foreach($list as $i => $item){
						$out->addDir(
							$item["name"],
							Config::DOMAIN . "radio-browser?by=".$by."&term=".urlencode($item["name"]),
							false, $i+1
						);
					}
					break;
				case "topclick":
				case "votes":
					foreach($list as $i => $item){
						$out->addStation(
							$item["stationuuid"], $item["name"], $item["url"],
							true, "", "", $i+1
						);
					}
					break;
			}

			// pagination
			if($offset >= self::RADIO_BROWSER_LIMIT ){
				$out->addDir(
					"Previous Page",
					Config::DOMAIN . "radio-browser?by=".$by."&term=none&offset=" . strval($offset-self::RADIO_BROWSER_LIMIT), 
					false, 0
				);
			}
			$out->addDir(
				"Next Page",
				Config::DOMAIN . "radio-browser?by=".$by."&term=none&offset=" . strval($offset+self::RADIO_BROWSER_LIMIT), 
				true
			);
		}
		else if ( $by == "language" || $by == "tags" || $by == "state" || $by == "country-all" ){
			$out->prevUrl(Config::DOMAIN . 'radio-browser?by='.$by.'&term=none');

			// TODO
			// addStation ...

			// https://de1.api.radio-browser.info/xml/stations/bycountryexact/germany
			
			$out->addDir("TODO!!! language, tags, country all, state", Config::DOMAIN . "?go=initial");
		}
		else if ( $by == "country" ) { // $term != "all" => show states to select from !!
			$out->prevUrl(Config::DOMAIN . 'radio-browser?by='.$by.'&term=none');

			// TODO
			// addDir "All States" "?by=country-all&term=<country>"
			// addDir "<State>" "?by=state&term=<state>" 
			
			$out->addDir("TODO!!! country states list", Config::DOMAIN . "?go=initial");
		}
		else {
			$out->addDir("Invalid request!", Config::DOMAIN . "?go=initial");
		}
	}
	
	public function handleStationPlay(Output $out, string $uuid) : void {
		$this->before_request($out);

		if($this->redis->keyExists('last_browse.'.$this->radioid->getId())){
			$out->prevUrl(Config::DOMAIN . 'radio-browser?' . $this->redis->get('last_browse.'.$this->radioid->getId()));
		}
		else {
			$out->prevUrl(Config::DOMAIN . 'radio-browser?by=none&term=none');
		}

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
		$this->run_request("url/" . $uuid);

		// store/ update to user's "last stations"
		if($this->redis->keyExists('last_stations.'.$this->radioid->getId())){
			$last = $this->redis->arrayGet('last_stations.'.$this->radioid->getId());
		}
		else {
			$last = array();
		}

		// TODO: better storing for Redis (Redis stores Arrays Keys as strings!!)

		// remove the current stations first
		$last = array_values(array_filter($last, fn($l) => $l["uuid"] != $station["stationuuid"]));
		// add current stations as "latest"
		$last[] = array(
			"uuid" => $station["stationuuid"],
			"name" => $station["name"],
			"url" => $station["url"]
		);
		if (count($last) > self::RADIO_BROWSER_LAST_MAX){
			$last = array_slice($last, -self::RADIO_BROWSER_LAST_MAX);
		}

		$this->redis->arraySet('last_stations.'.$this->radioid->getId(), $last);
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