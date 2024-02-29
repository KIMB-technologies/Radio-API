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

class RadioLogo {

	const BASE_DIR = __DIR__ . '/../media';
	const BASE_URL = Config::RADIO_DOMAIN . 'media/';

	private $useImageCache;

	public function __construct(){

		// use image cache via config

		$useImageCache = is_writable(self::BASE_DIR);

		
	}
	

	public function logoUrl(string $logo) : string {
		return empty($logo) || substr($logo, 0, 4) != 'http' ? self::BASE_URL . 'default.png' : $logo;
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