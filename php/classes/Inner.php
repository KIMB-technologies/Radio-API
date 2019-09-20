<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Inner {

	private $html = array();
	private $radios, $podcasts, $data;

	public function __construct(int $id){
		$this->data = new Data($id);
		$this->radios = $this->data->getRadioList();
		$this->podcasts = $this->data->getPodcastList();
	}

	public function checkPost() : void {
		if(isset( $_GET['radios'] ) && isset( $_POST['name'] )){
			$this->html[] = '<span style="color:green;">Radiosender geändert!</span>';
		
			$this->radios = array();
			foreach( $_POST['name'] as $id => $name ){
				if( !empty($name) ){
					$this->radios[] = array(
						'name' => self::filterName( $name ),
						'url' => self::filterURL( $_POST['url'][$id] ),
						'logo' => self::filterURL( $_POST['logo'][$id] ),
						'desc' => self::filterName( $_POST['desc'][$id] ),
						'proxy' => isset($_POST['proxy'][$id]) && $_POST['proxy'][$id] == 'yes',
					);
				}
			}
			$this->data->setRadioList($this->radios, false);
		}
		else if(isset( $_GET['podcasts'] ) && isset( $_POST['name'] ) ){
			$this->html[] = '<span style="color:green;">Podcasts geändert!</span>';
		
			$this->podcasts = array();
			foreach( $_POST['name'] as $id => $name ){
				if( !empty($name) ){
					$this->podcasts[] = array(
						'name' => self::filterName( $name ),
						'url' => self::filterURL( $_POST['url'][$id] ),
						'finalurl' => isset($_POST['finalurl'][$id]) && $_POST['finalurl'][$id] == 'yes',
						'proxy' => isset($_POST['proxy'][$id]) && $_POST['proxy'][$id] == 'yes',
						'type' => isset($_POST['type'][$id]) && $_POST['type'][$id] == 'nc' ? 'nc' : 'rss'
					);
				}
			}
			$this->data->setPodcastList($this->podcasts, false);
		}
	}

	public function radioForm() : string {
		$head = '<tr><th>ID</th><td></td><td style="width: 500px; max-width:60%; "></td></tr>';
		$rows = array();
		$count = 0;
		foreach($this->radios as $key => $radio ){
			$id = ($key+1000);

			$rows[] = array(  '<b>'. $id .'</b>', '', '' );
			$rows[] = array(  '', 'Name', '<input delid="d'.$id.'" type="text" value="'.$radio['name'].'" name="name['.$count.']"/>' );
			$rows[] = array(  '', 'URL', '<input delid="d'.$id.'" type="text" value="'.$radio['url'].'" name="url['.$count.']"/>' );
			$rows[] = array(  '', 'Proxy',
				'<input type="radio" value="yes" name="proxy['.$count.']" '. ( $radio['proxy'] ? 'checked="checked"' : '' ) .' /> &check;' .
				'<input type="radio" value="no" name="proxy['.$count.']" '. ( !$radio['proxy'] ? 'checked="checked"' : '' ) .' /> &cross;',
			);
			$rows[] = array(  '', 'Logo', '<input delid="d'.$id.'" type="text" value="'.$radio['logo'].'" name="logo['.$count.']"/>' );
			$rows[] = array(  '', 'Beschreibung',
				'<input delid="d'.$id.'" type="text" value="'.$radio['desc'].'" name="desc['.$count.']"/>' .
				'<button class="del" delid="d'.$id.'" type="button" title="Löschen.">&cross;</button>'
			);
			$count++;
		}
		$rows[] = array(  '<b>Neu</b>', '', '' );
		$rows[] = array(  '', 'Name', '<input type="text" placeholder="Name" name="name['.$count.']"/>' );
		$rows[] = array(  '', 'URL', '<input type="text" placeholder="URL (MP3, ...)" name="url['.$count.']"/>' );
		$rows[] = array(  '', 'Logo', '<input type="text" placeholder="Logo (PNG, ...)" name="logo['.$count.']"/>' );
		$rows[] = array(  '', 'Beschreibung', '<input type="text" placeholder="Beschreibung" name="desc['.$count.']"/>' );
		$rows[] = array('', '', '<input type="hidden" value="no" name="proxy['.$count.']" />');
		return $head . PHP_EOL . implode( PHP_EOL , array_map( function ($c) {
			return '<tr><td>' . implode( '</td><td>', $c ) . '</td></tr>';
		} , $rows ) );
	}

	public function podcastForm() : string {
		$head = '<tr><th>ID</th><td></td><td style="width: 500px; max-width:60%;"></td></tr>';
		$rows = array();
		$count = 0;
		foreach($this->podcasts as $key => $pod ){
			$id = ($key+3000);

			$rows[] = array(  '<b>'. $id .'</b>', '', '' );
			$rows[] = array(  '', 'Name', '<input type="text" delid="d'.$id.'" value="'.$pod['name'].'" name="name['.$count.']"/>' );
			$rows[] = array(  '', 'Typ',
				'<input type="radio" value="rss" value="'.$pod['type'].'" name="type['.$count.']" '.( $pod['type'] == 'rss' ? 'checked="checked"' : '' ).' /> RSS/ Atom' .
				'<input type="radio" value="nc" value="'.$pod['type'].'" name="type['.$count.']" '.( $pod['type'] == 'nc' ? 'checked="checked"' : '' ).' /> Nextcloud'
			);
			$rows[] = array(  '', 'URL', '<input type="text" delid="d'.$id.'" value="'.$pod['url'].'" name="url['.$count.']"/>' );
			$rows[] = array(  '', 'EndURL',
				'<input type="radio" value="yes" name="finalurl['.$count.']" '. ( $pod['finalurl'] ? 'checked="checked"' : '' ) .' /> &check;' .
				'<input type="radio" value="no" name="finalurl['.$count.']" '. ( !$pod['finalurl'] ? 'checked="checked"' : '' ) .' /> &cross;'
			);
			$rows[] = array(  '', 'Proxy',
				'<input type="radio" value="yes" name="proxy['.$count.']" '. ( $pod['proxy'] ? 'checked="checked"' : '' ) .' /> &check;' .
				'<input type="radio" value="no" name="proxy['.$count.']" '. ( !$pod['proxy'] ? 'checked="checked"' : '' ) .' /> &cross;',
				'<button class="del" delid="d'.$id.'" type="button" title="Löschen.">&cross;</button>'
			);
			$count++;
		}
		$rows[] = array('<b>Neu</b>', '', '');
		$rows[] = array('', 'Name', '<input type="text" placeholder="Name" name="name['.$count.']"/>');
		$rows[] = array(  '', 'Typ',
				'<input type="radio" value="rss" name="type['.$count.']" checked="checked"  /> RSS/ Atom' .
				'<input type="radio" value="nc" name="type['.$count.']" /> Nextcloud'
		);
		$rows[] = array('', 'URL', '<input type="text" placeholder="URL" name="url['.$count.']"/>');
		$rows[] = array(  '', 'EndURL',
				'<input type="radio" value="yes" name="finalurl['.$count.']" /> &check;' .
				'<input type="radio" value="no" name="finalurl['.$count.']" checked="checked" /> &cross;'
		);
		$rows[] = array('', '', '<input type="hidden" value="no" name="proxy['.$count.']" />');
		return $head . PHP_EOL . implode( PHP_EOL , array_map( function ($c) {
			return '<tr><td>' . implode( '</td><td>', $c ) . '</td></tr>';
		} , $rows ) );
	}

	public function getMessages() : string {
		return implode(PHP_EOL, $this->html);
	}

	// ==== //

	public static function filterURL(string $url) : string {
		$url = filter_var( $url, FILTER_VALIDATE_URL) ? substr( $url , 0, 1000) : 'invalid';
		return empty($url) ? '' : $url;
	}

	public static function filterName(string $name): string{
		$name = str_replace( ['ä','ü','ß','ö','Ä','Ü','Ö'], ['ae','ue','ss','oe','Ae','Ue','Oe'], $name);
		$name = substr( preg_replace( '/[^0-9A-Za-z \.\-\_\,\&\;\/\(\)]/', '',  $name ), 0, 200 );
		return empty($name) ? 'empty' : $name;
	}
}
?>
