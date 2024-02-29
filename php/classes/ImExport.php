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
 * Implements the Im- & Export feature
 */
class ImExport {

	private string $msg = "";

	public function __construct(){
	}

	public function getMsg() : string {
		return $this->msg;
	}

	private function addToExport(string $dir, array &$export, bool $rm = false) : void {
		foreach(scandir($dir) as $f){
			if($this->exportFile($f)){
				$export[$f] = json_decode(file_get_contents($dir . '/' . $f), true);

				if($rm){
					unlink($dir . '/' . $f);
				}
			}
		}
	}

	private function exportFile(string $name, ?string $checkKind = null) : bool {
		$kind = null;
		switch ($name){
			case "table.json":
				$kind = "table";
				break;
			case "env.json":
				$kind = "env";
				break;
			case "radiobrowser.json":
			case "unread.json":
				$kind = "cache";
				break;
			default:
				if(preg_match('/^(?:radio|podcast)s_[0-9]{1,4}\.json$/', $name) === 1){
					$kind =  "list";
				}
				break;
		}

		return !is_null($kind) && (is_null($checkKind) || $checkKind === $kind);
	}

	private function getTmpDir() : string {
		$tmpDir = sys_get_temp_dir() . '/RadioAPI-' . Helper::randomCode(10);
		mkdir($tmpDir);
		return $tmpDir;
	}

	public function export(bool $yield = true) {
		// dump from cache/ redis
		//	create tmp dir
		$tmpDir = $this->getTmpDir();
		//	dump
		UnRead::dumpToDisk($tmpDir);
		RadioBrowser::dumpToDisk($tmpDir);

		// create the export array
		$export = array(
			"__version__" => Config::VERSION,
			"__date__" => date('d.m.Y H:i:s')
		);
		$this->addToExport(__DIR__ . '/../data', $export);
		$this->addToExport($tmpDir, $export, true);

		// remove tmp dir
		rmdir($tmpDir);

		if($yield){
			// encode as json
			$json = json_encode($export, JSON_PRETTY_PRINT);

			// yield header
			header('Content-Type: application/json;charset=UTF-8');
			header('Content-Disposition: attachment; filename=Radio-API_export_'.date('Y-m-d_H-i-s').'.json' );
			header('Content-Length: ' . strlen( $json ));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Pragma: no-cache');
			// yield the data
			echo $json;
		}
		else{
			return $json;
		}
	}

	private function validateExport(array $export) : bool {
		$ok = true;
		foreach($export as $f => $content){
			if($this->exportFile($f, "list")){
				$ok &= $this->validateList($content);
				if(!$ok){
					$this->msg = (Template::getLanguage() == 'de' ? "Fehlerhafte Liste" : "Error in list") . ": " . $f;
					break;
				}
			}
			else if($this->exportFile($f, "cache")){
				$ok &= $this->validateCache($content, $f);
				if(!$ok){
					$this->msg = (Template::getLanguage() == 'de' ? "Fehlerhafter Cache" : "Error in cache") . ": " . $f;
					break;
				}
			}
			else if($this->exportFile($f, "table")){
				$ok &= $this->validateTable($content);
				if(!$ok){
					$this->msg = Template::getLanguage() == 'de' ? "Fehlerhafte Tabelle" : "Error in table";
					break;
				}
			}
			else if($f == "__version__" || $f == "__date__"){
				// ignore
			}
			else if(!$this->exportFile($f, "env")){ // env is ignored, other ones not allowed!
				$ok = false;
				$this->msg = Template::getLanguage() == 'de' ? "Unbekannte Daten in Export" : "Unknown data in export!";
				break;
			}
		}

		return $ok;
	}

	private function validateList(array $content) : bool {
		$ok = true;
		$cnt = 0;
		foreach($content as $key => $val){
			$ok &= $key === $cnt && is_array($val);

			foreach($val as $k => $v){
				switch ($k){
					case "name":
					case "desc":
						$ok &= Inner::filterName($v) === $v;
						break;
					case "category":
						$ok &= ($v === "" || Inner::filterCategory($v) === $v);
						break;
					case "logo":
					case "url":
						$ok &= Inner::filterURL($v) === $v;
						break;
					case "type":
						$ok &= in_array($v, ["rss", "nc", "radio"]);
						break;
					case "finalurl":
					case "proxy":
						$ok &= is_bool($v);
						break;
					default:
						$ok = false;
						break;
				}
			}
			$ok &= array_key_exists("name", $val) && array_key_exists("url", $val) && array_key_exists("type", $val);

			$cnt += 1;
		}
		return $ok;
	}

	private function validateCache(array $content, string $name) : bool {
		$ok = true;
		foreach($content as $key => $value){
			$ok &= Id::isIdInteger($key) && is_array($value);

			if($name == "unread.json"){
				$ok &= array_reduce(
					$value,
					fn($c, $i) => $c && Inner::filterURL($i) === $i,
					true
				);
			}
			else { // radiobrowser.json
				foreach($value as $k => $v){
					$ok &= is_string($k) && RadioBrowser::uuidFromStationID(RadioBrowser::stationIDfromUUID($k)) === $k;

					$ok &= array_key_exists("name", $v) && array_key_exists("url", $v) && array_key_exists("time", $v);
					$ok &= is_string($v["name"]) && Inner::filterURL($v["url"]) === $v["url"] && is_integer($v["time"]);
				}
			}
		}
		return $ok;
	}

	private function validateTable(array $content) : bool {
		$ok = array_key_exists("macs", $content) && array_key_exists("ids", $content) && array_key_exists("codes", $content);
		if($ok){
			$macs = array_filter(
				$content["macs"],
				fn($v, $k) => Helper::checkValue( $k, Id::MAC_PREG ) && is_integer($v) && Id::isIdInteger($v),
				ARRAY_FILTER_USE_BOTH
			);
			$ok &= count($macs) === count($content["macs"]);

			$codes = array_filter(
				$content["codes"],
				fn($v, $k) => Helper::checkValue( $k, Id::CODE_PREG ) && is_integer($v) && Id::isIdInteger($v),
				ARRAY_FILTER_USE_BOTH
			);
			$ok &= count($codes) === count($content["codes"]);

			$ids = array_filter(
				$content["ids"],
				fn($v, $k) => Id::isIdInteger($k) && is_array($v) && count($v) === 2 &&
					Helper::checkValue( $v[0], Id::MAC_PREG ) && Helper::checkValue( $v[1], Id::CODE_PREG ),
				ARRAY_FILTER_USE_BOTH
			);
			$ok &= count($ids) === count($content["ids"]);
		}
		return $ok;
	}

	public function import(string $exportfile, string $kind, ?string $codeExport, ?string $codeSystem) : bool {
		$export = json_decode(file_get_contents($exportfile), true);
		if(!is_array($export)){
			$this->msg = Template::getLanguage() == 'de' ? "Kann Export-Datei nicht lesen!" : "Unable to open Export file!";
			return false;
		}
		if(!in_array($kind, ["append", "single", "replace"])){
			$this->msg = Template::getLanguage() == 'de' ? "Art des Imports unbekannt!" : "Kind of report unknown!";
			return false;
		}
		if($kind === "single"){
			if ( is_null($codeExport) || is_null($codeSystem) ){
				$this->msg = Template::getLanguage() == 'de' ? "Einzelner Import benötigt zwei GUI-Codes!" : "Single import requires two GUI-Codes.";
				return false;
			}
			else if ( !Helper::checkValue( $codeExport, Id::CODE_PREG ) || !Helper::checkValue( $codeSystem, Id::CODE_PREG ) ){
				$this->msg = Template::getLanguage() == 'de' ? "Ein GUI-Code ist ungültig." : "A GUI-Code is invalid.";
				return false;
			}
		}

		if(
			!array_key_exists("unread.json", $export) ||
			!array_key_exists("radiobrowser.json", $export) ||
			!array_key_exists("table.json", $export)
		){
			$this->msg = (
					Template::getLanguage() == 'de' ?
					"Daten im Export fehlen, mindestens notwendig" : "Data in export missing, at least required"
				) . ": unread.json, radiobrowser.json, table.json";
			return false;
		}
		
		if(!$this->validateExport($export)){
			return false;
		}

		switch($kind){
			case "replace":
				return $this->runReplace($export);
			case "append":
				return $this->runAppend($export);
			case "single":
				return $this->runSingle($export, $codeExport, $codeSystem);
		}
	}

	private function runReplace(array $export, bool $cleanUp = true) : bool {
		$ok = true;

		$dataDir = realpath(__DIR__ . '/../data');
		$tmpDir = $this->getTmpDir();

		if($cleanUp){
			// clean up data dir
			foreach(scandir($dataDir) as $f){
				if($this->exportFile($f, "table") || $this->exportFile($f, "list") ){
					$ok &= unlink($dataDir . '/' . $f);
				}
			}
			if(!$ok){
				$this->msg .= "<br>" . (Template::getLanguage() == 'de' ? "Vorbereiten schlug fehl!" : "Error during preparation!");
			}
		}

		// write files 
		foreach($export as $f => $data ){
			if($this->exportFile($f, "table") || $this->exportFile($f, "list") ){
				$file =  $dataDir . '/' . $f;
			}
			else if ($this->exportFile($f, "cache") ){
				$file =  $tmpDir . '/' . $f;
			}
			else{
				$file = null;
			}

			if(!is_null($file)){
				if(!file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX)){
					$this->msg .= "<br>" . (Template::getLanguage() == 'de' ? "Konnte Datei nicht schreiben" : "Error writing") . ": " . $f;
					$ok &= false;
				}				
			}
		}

		// import cache
		UnRead::loadFromDisk($tmpDir);
		RadioBrowser::loadFromDisk($tmpDir);

		// tidy up tmpDir
		$cok = true;
		foreach(scandir($tmpDir) as $f){
			if(is_file($tmpDir . '/' . $f)){
				$cok &= unlink($tmpDir . '/' . $f);
			}
		}
		$cok &= rmdir($tmpDir);
		if(!$cok){
			$this->msg .= "<br>" . (Template::getLanguage() == 'de' ? "Aufräumen schlug fehlt!" : "Error during clean up!");
			$ok &= false;
		}

		// invalidate caches
		foreach(Id::getTableData()["codes"] as $id){
			(new Cache('radios_podcasts.' . $id ))->removeGroup();
		}
		(new Cache('table.json'))->removeGroup();

		return $ok;		
	}

	private function runAppend(array $export) : bool {
		$mainTable = Id::getTableData();
		$idShift = count( $mainTable['ids'] );

		// change the data to import in a way such that the items are appended
		foreach($export as $f => $value){
			if($this->exportFile($f, "list")){ // increment ids of all list files 
				$newF = preg_replace_callback(
					'/^((?:radio|podcast)s_)([0-9]{1,4})(\.json)$/',
					fn($m) => $m[1] . intval($m[2])+$idShift . $m[3],
					$f
				);

				$export[$newF] = $value;
				unset($export[$f]);
			}
			else if($this->exportFile($f, "cache")){ // increment the keys of the cache files 
				$export[$f] = array();
				foreach($value as $k => $v){
					if(!empty($v)){
						$export[$f][$k+$idShift] = $v;
					}
				}
			}
			else if($this->exportFile($f, "table")){ // create a new "merged" table.
				$export[$f] = $mainTable; // set the main table

				// append import to the current main table
				foreach($value["ids"] as $k => $v){
					// tamper values to import
					$id = $k + $idShift;
					$mac = $v[0];
					$code = $v[1];

					if(isset($export[$f]["macs"][$mac])){
						$this->msg .= "<br>" . (Template::getLanguage() == 'de' ? "Radio $code war bereits im System vorhanden, Listen wurden überschrieben!" : "Radio $code was already known to system, list have been overwritten!");
					}

					// prevent collisions among GUI-Codes
					while( isset( $export[$f]['codes'][$code] ) ){
						$code =  'Z' . Helper::randomCode( 4 );
					}

					// add to table
					$export[$f]["macs"][$mac] = $id;
					$export[$f]["ids"][$id] = array($mac, $code);
					$export[$f]["codes"][$code] = $id;
				}
			}
		}

		// run a replace with changed data (and no clean up of data dir!)
		return $this->runReplace($export, false);
		
	}

	private function runSingle(array $export, string $codeExport, string $codeSystem) : bool {
		$mainTable = Id::getTableData();

		if( !isset($export["table.json"]["codes"][$codeExport]) || !isset($mainTable["codes"][$codeSystem]) ){
			$this->msg = Template::getLanguage() == 'de' ? "Bitte prüfen Sie beide Codes!" : "Please make sure both Codes are available!";
			return false;
		}

		$idExport = $export["table.json"]["codes"][$codeExport];
		$idSystem = $mainTable["codes"][$codeSystem];

		$newExport = array();
		foreach($export as $f => $value){
			if($this->exportFile($f, "list")){ 
				preg_match('/^((?:radio|podcast)s_)([0-9]{1,4})(\.json)$/', $f, $matches);
				$id = intval($matches[2]);
				
				if($id == $idExport){ // if this is the id to import, make sure to write this file
					$newExport[$matches[1] . $idSystem . $matches[3]] = $value;
				}
			}
			else if($this->exportFile($f, "cache")){ 
				if(!empty($value[$idExport])){
					$newExport[$f] = array(
						$idSystem => $value[$idExport]
					);
				}
			}
			// the table stays unchanged
		}

		// run a replace with changed data (and no clean up of data dir!)
		return $this->runReplace($newExport, false);
	}
	
}
?>