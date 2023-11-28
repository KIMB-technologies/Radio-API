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
 * Implements the Im- & Export feature
 */
class ImExport {

	private string $msg = "";

	public function __construct(){
	}

	private function addToExport(string $dir, array &$export, bool $rm = false) : void {
		foreach(scandir($dir) as $f){
			if(str_ends_with($f, '.json') && !str_ends_with($f, '.error.json')){
				$export[$f] = json_decode(file_get_contents($dir . '/' . $f), true);

				if($rm){
					unlink($dir . '/' . $f);
				}
			}
		}
	}

	public function export(bool $yield = true) {
		// dump from cache/ redis
		//	create tmp dir
		$tmpDir = sys_get_temp_dir() . '/RadioAPI-' . Helper::randomCode(10);
		mkdir($tmpDir);
		//	dump
		UnRead::dumpToDisk($tmpDir);
		RadioBrowser::dumpToDisk($tmpDir);

		// create the export array
		$export = array();
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

	public function getMsg() : string {
		return $this->msg;
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
		if($kind === "single" && ( is_null($codeExport) || is_null($codeSystem) )){
			$this->msg = Template::getLanguage() == 'de' ? "Einzelner Import benötigt zwei GUI-Codes!" : "Single import requires two GUI-Codes.";
			return false;
		}

		// TODO

		$this->msg = Template::getLanguage() == 'de' ? "Dummy" : "Dummy";
		return true;
	}
	
}
?>