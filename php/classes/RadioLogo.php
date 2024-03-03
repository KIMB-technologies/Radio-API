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

class RadioLogo {

	const BASE_DIR = __DIR__ . '/../media';
	const BASE_URL = Config::RADIO_DOMAIN . 'media/';

	private bool $useImageCache;

	public function __construct(){
		// check if image cache active and if cache folder writable
		$this->useImageCache = Config::USE_LOGO_CACHE && is_writable(self::BASE_DIR);
	}
	
	public function logoUrl(string $logo) : string {
		// empty or no url
		if(empty($logo) || substr($logo, 0, 4) != 'http'){
			return self::BASE_URL . 'default.png';
		}
		
		// do not use cache
		if(!$this->useImageCache){
			return $logo;
		}

		// create hash, cache file access
		$namehash = sha1($logo);
		$cacheurl = Config::RADIO_DOMAIN . 'image.php?hash=' . $namehash;

		// return file from cache if available
		if(is_file(self::BASE_DIR . '/' . $namehash . '.image')){
			return $cacheurl;
		}
		// if download error, return url to logo
		else if(is_file(self::BASE_DIR . '/' . $namehash . '.error')){
			return $logo;
		}
		else {
			// try download
			if($this->fetchLogo($logo)){
				return $cacheurl;
			}
			else {
				// create error file
				file_put_contents(self::BASE_DIR . '/' . $namehash . '.error', '');
				return $logo;
			}
		}
	}

	private function fetchLogo(string $logo) : bool {
		//download
		$image = file_get_contents($logo);
		if($image === false){
			return false;
		}

		// file names
		$namehash = sha1($logo);
		$filename = self::BASE_DIR . '/' . $namehash . '.image';
		// store
		file_put_contents($filename, $image);

		// mime
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimetype = finfo_file($finfo, $filename);
		finfo_close($finfo);

		// its not a image file
		if( substr($mimetype, 0, strlen('image/')) !== 'image/'){
			unlink($filename);
			return false;
		}

		if($mimetype == 'image/svg+xml'){
			if(
				self::svg2png($filename, $filename . '.cv')
				&&
				@rename($filename . '.cv', $filename)
			){
				return true;
			}
			else {
				unlink($filename);
				return false;
			}
		}
		else {
			return true;
		}
	}

	private static function svg2png(string $inputSVG, string $outputPNG) : bool {
		$command = array(
			'rsvg-convert',
			'--width', '256',
			'--height', '256',
			'--keep-aspect-ratio',
			'--format', 'png',
			'-o', '"'.$outputPNG.'"',
			'"'.$inputSVG.'"'
		);
		return exec(implode(' ', $command)) !== false;
	}

}

?>