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
defined('HAMARadio') or die('Invalid Endpoint');

class RadioLogo {

	const BASE_DIR = __DIR__ . '/../media';
	const BASE_URL = Config::RADIO_DOMAIN . 'media/';

	private bool $useImageCache;

	public function __construct(){
		// check if image cache active and if cache folder writable
		$this->useImageCache = Config::USE_LOGO_CACHE && is_writable(self::BASE_DIR);
	}

	public function clearCache() : bool {
		if($this->useImageCache){
			$ok = true;
			foreach(scandir(self::BASE_DIR) as $d){
				if(preg_match('/^[a-f0-9]{40}\.(image|error)$/', $d) === 1){
					$ok = $ok && unlink(self::BASE_DIR . '/' . $d);
				}
			}
			return $ok;
		}
		return false;
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

		$tmpName = tempnam(sys_get_temp_dir(), 'conv');
		if($mimetype == 'image/svg+xml'){
			if( self::svg2png($filename, $tmpName) ){
				return rename($tmpName, $filename);
			}
			else {
				unlink($filename);
				return false;
			}
		}
		else {

			// resize file 
			//	will only return true if resize done (false on error or if already small enough)
			if(self::resize($filename, $mimetype, $tmpName)){
				rename($tmpName, $filename);
			}

			return true;
		}
	}

	private static function svg2png(string $inputSVG, string $outputPNG) : bool {
		$command = array(
			'rsvg-convert',
			'--width', '256',
			'--height', '256',
			'--keep-aspect-ratio',
			'--background-color', 'white',
			'--format', 'png',
			'-o', '"'.$outputPNG.'"',
			'"'.$inputSVG.'"'
		);
		exec(implode(' ', $command), result_code:$rs);
		return $rs === 0;
	}

	private static function imageDimensions(string $file) : array {
		$finfo = finfo_open(FILEINFO_CONTINUE);
		$info = finfo_file($finfo, $file);
		finfo_close($finfo);
		
		// file info (including dimensions as ", 000 x 000,")
		if(preg_match('/,(\d+)x(\d+),/', str_replace(' ', '', $info), $matches) === 1){
			$width = intval($matches[1]);
			$height = intval($matches[2]);

			return [$width, $height];
		}
		else{
			return [0, 0];
		}
	}

	private static function resize(string $inputFile, string $inputMime, string $outputPNG) : bool {
		// determine image dimensions
		list($width, $height) = self::imageDimensions($inputFile);

		// error
		if($width == 0 || $height == 0){
			// no resize 
			return false;
		}

		// do not resize if smaller than 256 px
		if( $width <= 256 && $height <= 256 ){
			// resize not necessary
			return false;
		}
	
		// create an svg with image
		$svgFile = "<svg xmlns='http://www.w3.org/2000/svg'
				width='".$width."' height='".$height."' version='1.1'>
			<image href='data:".$inputMime.";base64,".base64_encode(file_get_contents($inputFile))."'
				width='".$width."' height='".$height."' />
		</svg>";

		// write to tmp
		$inputSVG = tempnam(sys_get_temp_dir(), 'svg');
		file_put_contents($inputSVG, $svgFile);

		// create small png
		return self::svg2png($inputSVG, $outputPNG);
	}

}

?>