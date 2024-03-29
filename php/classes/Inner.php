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
 * Inner GUI Code/ Handling of Post and Output of tables
 */
class Inner {

	private $html = array();
	private $radios, $podcasts, $data, $template;

	public function __construct(int $id, Template $template){
		$this->data = new Data($id);
		$this->radios = $this->data->getRadioList();
		$this->podcasts = $this->data->getPodcastList();
		$this->template = $template;
	}

	public function clearCache() : void {
		if(Config::USE_LOGO_CACHE){
			$this->template->setContent('CLEAR_CACHE', '');

			if(isset($_GET['clear-logo-cache'])){
				$this->html[] = '<span style="color:' . (
						(new RadioLogo())->clearCache() ?  'green;">Cleared logo cache!' : 'red;">Error clearing logo cache!'
					) .'</span>';
			}
		}
	}

	public function checkPost() : void {
		if(isset( $_GET['radios'] ) && isset( $_POST['name'] )){
			$this->html[] = '<span style="color:green;">Changed Radio stations!</span>';
		
			$this->radios = array();
			foreach( $_POST['name'] as $id => $name ){
				if( !empty($name) ){
					$this->radios[] = array(
						'name' => self::filterName( $name ),
						'url' => self::filterURL( $_POST['url'][$id] ),
						'logo' => self::filterURL( $_POST['logo'][$id] ),
						'desc' => self::filterName( $_POST['desc'][$id] ),
						'proxy' => isset($_POST['proxy'][$id]) && $_POST['proxy'][$id] == 'yes',
						'type' => !empty($_POST['type'][$id]) && $_POST['type'][$id] == 'nc' ? 'nc' : 'radio',
						'category' => $this->getCategory($id)
					);
				}
			}
			$this->data->setRadioList($this->radios);
		}
		else if(isset( $_GET['podcasts'] ) && isset( $_POST['name'] ) ){
			$this->html[] = '<span style="color:green;">Changed podcasts!</span>';
		
			$this->podcasts = array();
			foreach( $_POST['name'] as $id => $name ){
				if( !empty($name) ){
					$this->podcasts[] = array(
						'name' => self::filterName( $name ),
						'url' => self::filterURL( $_POST['url'][$id] ),
						'finalurl' => isset($_POST['finalurl'][$id]) && $_POST['finalurl'][$id] == 'yes',
						'proxy' => isset($_POST['proxy'][$id]) && $_POST['proxy'][$id] == 'yes',
						'type' => isset($_POST['type'][$id]) && $_POST['type'][$id] == 'nc' ? 'nc' : 'rss',
						'category' => $this->getCategory($id)
					);
				}
			}
			$this->data->setPodcastList($this->podcasts);
		}
	}

	public function radioForm() : void {
		$categories = array_filter(array_unique(array_column($this->radios, 'category')));
		$radios = array();
		$count = 0;
		foreach($this->radios as $key => $radio ){
			$id = ($key+1000);

			$radios[] = array(
				"ID" => $id,
				"COUNT" => $count,
				"NAME" => htmlspecialchars($radio['name'], encoding: 'UTF-8'),
				"URL" => $radio['url'],
				"PROXY_YES" => $radio['proxy'] ? 'checked="checked"' : '',
				"PROXY_NO" => !$radio['proxy'] ? 'checked="checked"' : '',
				"TYPE_RADIO" => $radio['type'] != 'nc' ? 'checked="checked"' : '',
				"TYPE_NC" => $radio['type'] == 'nc' ? 'checked="checked"' : '',
				"LOGO" => $radio['logo'],
				"DESC" => htmlspecialchars($radio['desc'], encoding: 'UTF-8'),
				"CAT_OPTIONS" => array_reduce(
					$categories,
					fn($c, $i) => $c.'<option value="'.$i.'" '.($i === $radio['category'] ? 'selected' : '').'>'.$i.'</option>',
					''
				)
			);
			
			$count++;
		}
		$this->template->setMultipleContent('RadioStations', $radios);
		$this->template->setContent('RADIO_COUNT', $count);
		$this->template->setContent('RADIO_OPTIONS', array_reduce($categories, fn($c, $i) => $c.'<option value="'.$i.'">'.$i.'</option>', '' ));
	}

	public function podcastForm() : void {
		$categories = array_filter(array_unique(array_column($this->podcasts, 'category')));
		$podcasts = array();
		$count = 0;
		foreach($this->podcasts as $key => $pod ){
			$id = ($key+3000);

			$podcasts[] = array(
				"ID" => $id,
				"COUNT" => $count,
				"NAME" => htmlspecialchars($pod['name'], encoding: 'UTF-8'),
				"URL" => $pod['url'],
				"TYPE_RSS" => $pod['type'] == 'rss' ? 'checked="checked"' : '',
				"TYPE_NC" => $pod['type'] == 'nc' ? 'checked="checked"' : '',
				"ENDURL_YES" => $pod['finalurl'] ? 'checked="checked"' : '',
				"ENDURL_NO" => !$pod['finalurl'] ? 'checked="checked"' : '',
				"PROXY_YES" => $pod['proxy'] ? 'checked="checked"' : '',
				"PROXY_NO" => !$pod['proxy'] ? 'checked="checked"' : '',
				"CAT_OPTIONS" => array_reduce(
						$categories,
						fn($c, $i) => $c.'<option value="'.$i.'" '.($i === $pod['category'] ? 'selected' : '').'>'.$i.'</option>',
						''
					)
			);
			
			$count++;
		}
		$this->template->setMultipleContent('Podcasts', $podcasts);
		$this->template->setContent('PODCAST_COUNT', $count);
		$this->template->setContent('PODCAST_OPTIONS', array_reduce($categories, fn($c, $i) => $c.'<option value="'.$i.'">'.$i.'</option>', '' ));
	}

	public function outputMessages() : void {
		$this->template->setContent('ADD_HTML', implode(PHP_EOL, $this->html));
	}

	// ==== //

	public static function filterURL(string $url) : string {
		$url = filter_var( $url, FILTER_VALIDATE_URL) ? substr( $url , 0, 1000) : 'invalid';
		return empty($url) ? '' : $url;
	}

	public static function filterName(string $name): string{
		// $name = str_replace( ['ä','ü','ß','ö','Ä','Ü','Ö'], ['ae','ue','ss','oe','Ae','Ue','Oe'], $name);
		$name = substr( preg_replace(
			'/[^ -\x{2122}]/u',
			// pattern inspired from
			//	https://stackoverflow.com/a/43106144 by mickmackusa
			//	CC BY-SA, https://creativecommons.org/licenses/by-sa/3.0/
			'', $name
		), 0, 200 );
		
		return empty($name) ? 'empty' : $name;
	}

	public static function filterCategory(string $cat) : string{
		$cat = self::filterName($cat);
		$cat = preg_replace( '/[^0-9A-Za-z \-\,]/', '',  $cat );
		return empty($cat) ? 'empty' : $cat;
	}

	private function getCategory(int $post_id): string {
		if($_POST['cat'][$post_id] === '*root') {
			return "";
		}
		else {
			return self::filterCategory(
				$_POST['cat'][$post_id] === '*new' ?
					$_POST['new_cat'][$post_id] : $_POST['cat'][$post_id]
			);
		}
	}
}
?>
