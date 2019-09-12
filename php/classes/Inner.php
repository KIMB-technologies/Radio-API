<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

class Inner {

	private $html = array();
	private $radios, $podcasts;

	public function __construct(){
		$this->radios = json_decode( file_get_contents( __DIR__ . '/../data/radios.json' ), true);
		$this->podcasts = json_decode( file_get_contents( __DIR__ . '/../data/podcasts.json' ), true);
	}

	public function checkPost() {
		if(isset( $_GET['radios'] ) && isset( $_POST['name'] )){
			$this->html[] = '<span style="color:green;">Radiosender geändert!</span>';
		
			$this->radios = array();
			foreach( $_POST['name'] as $id => $name ){
				if( !empty($name) ){
					$this->radios[] = array(
						'name' => self::filterName( $name ),
						'url' => self::filterURL( $_POST['url'][$id] ),
						'logo' => self::filterURL( $_POST['logo'][$id] ),
						'desc' => self::filterName( $_POST['desc'][$id] )
					);
				}
			}

			
		}
		else if(isset( $_GET['podcasts'] ) && isset( $_POST['name'] ) ){
			$this->html[] = '<span style="color:green;">Podcasts geändert!</span>';
		
			$this->podcasts = array();
			foreach( $_POST['name'] as $id => $name ){
				if( !empty($name) ){
					$this->podcasts[] = array(
						'name' => self::filterName( $name ),
						'url' => self::filterURL( $_POST['url'][$id] ),
						'finalurl' => isset($_POST['finalurl'][$id]) && $_POST['finalurl'][$id] == 'yes'
					);
				}
			}
			
		}
	}

	public function addForm(){
		$this->html[] = '<h3>Radiosender</h3>';
		$this->html[] = '<form action="?radios" method="post">';
		$this->html[] = '<table>';
		$this->html[] = $this->radioForm();
		$this->html[] = '</table>';
		$this->html[] = '<input type="submit" value="Sichern">';
		$this->html[] = '</form>';

		$this->html[] = '<h3>Podcasts</h3>';
		$this->html[] = '<form action="?podcasts" method="post">';
		$this->html[] = '<table>';
		$this->html[] = $this->podcastForm();
		$this->html[] = '</table>';
		$this->html[] = '<input type="submit" value="Sichern">';
		$this->html[] = '</form>';
	}

	private function radioForm(){
		$head .= '<tr><th>ID</th><td></td><td style="width: 500px; max-width:60%; "></td></tr>';
		$rows = array();
		$count = 0;
		foreach($this->radios as $key => $radio ){
			$id = ($key+1000);

			$rows[] = array(  '<b>'. $id .'</b>', '', '' );
			$rows[] = array(  '', 'Name', '<input delid="d'.$id.'" type="text" value="'.$radio['name'].'" name="name['.$count.']"/>' );
			$rows[] = array(  '', 'URL', '<input delid="d'.$id.'" type="text" value="'.$radio['url'].'" name="url['.$count.']"/>' );
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
		return $head . PHP_EOL . implode( PHP_EOL , array_map( function ($c) {
			return '<tr><td>' . implode( '</td><td>', $c ) . '</td></tr>';
		} , $rows ) );
	}

	private function podcastForm(){
		$head = '<tr><th>ID</th><td></td><td style="width: 500px; max-width:60%;"></td></tr>';
		$rows = array();
		$count = 0;
		foreach($this->podcasts as $key => $pod ){
			$id = ($key+3000);

			$rows[] = array(  '<b>'. $id .'</b>', '', '' );
			$rows[] = array(  '', 'Name', '<input type="text" delid="d'.$id.'" value="'.$pod['name'].'" name="name['.$count.']"/>' );
			$rows[] = array(  '', 'URL', '<input type="text" delid="d'.$id.'" value="'.$pod['url'].'" name="url['.$count.']"/>' );
			$rows[] = array(  '', 'EndURL',
				'<input type="radio" delid="d'.$id.'" value="yes" name="finalurl['.$count.']" '. ( $pod['finalurl'] ? 'checked="checked"' : '' ) .' /> &check;' .
				'<input type="radio" delid="d'.$id.'" value="no" name="finalurl['.$count.']" '. ( !$pod['finalurl'] ? 'checked="checked"' : '' ) .' /> &cross;',
				'<button class="del" delid="d'.$id.'" type="button" title="Löschen.">&cross;</button>'
			);
			$count++;
		}
		$rows[] = array('<b>Neu</b>', '', '');
		$rows[] = array('', 'Name', '<input type="text" placeholder="Name" name="name['.$count.']"/>');
		$rows[] = array('', 'URL', '<input type="text" placeholder="URL" name="url['.$count.']"/>');
		$rows[] = array(  '', 'EndURL',
				'<input type="radio" delid="d'.$id.'" value="yes" name="finalurl['.$count.']" /> &check;' .
				'<input type="radio" delid="d'.$id.'" value="no" name="finalurl['.$count.']" checked="checked" /> &cross;'
		);
		return $head . PHP_EOL . implode( PHP_EOL , array_map( function ($c) {
			return '<tr><td>' . implode( '</td><td>', $c ) . '</td></tr>';
		} , $rows ) );
	}

	public function getHTML() : string {
		$this->html[] = '<script>
		$(function (){
			$("button.del").click( function (){
				var id = $(this).attr("delid");
				$("input[delid="+ id +"]").val("");
			});
		});
		</script>';

		return implode(PHP_EOL, $this->html);
	}

	public function __destruct(){
		file_put_contents( __DIR__ . '/../data/radios.json', json_encode($this->radios, JSON_PRETTY_PRINT));
		file_put_contents( __DIR__ . '/../data/podcasts.json', json_encode($this->podcasts, JSON_PRETTY_PRINT));
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
