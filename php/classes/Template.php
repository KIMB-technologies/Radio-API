<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

/**
 * System Template class
 * 	Each Template consists of an <name>.html and <name>.json
 * 	The JSON defines all Placeholders used in the Template and the default values.
 * 	Templates can be included in each other, while the content of the inner goes to:
 * 		%%INNERCONTAINER%%
 */
class Template{
	/*
		Using this Template system, you must not allow users to insert strings like (wile xxx is some alphanum.)
			<!--MULTIPLE-xxxx-BEGIN-->, <!--MULTIPLE-xxxx-END-->, %%xxxx%%
	*/
	
	/**
	 * Name, Placeholderdata and included Template
	 */
	private $filename = '';
	private $placeholder = array();
	private $multiples = array();
	private $multiples_data = array();
	private $inner = null;

	private static $lang = 'de';
	private static $allLangs = array(
		'de'
	);

	/**
	 * Change the language of the site
	 * see $allLangs for list
	 * @param lang the lang to use
	 */
	public static function setLanguage( string $lang ) : void {
		if( in_array( $lang, self::$allLangs ) ){
			self::$lang = $lang;
		}
	}

	/**
	 * Create an new Template
	 * @param name The name of the template
	 * 		./templates/<name>.json)
	 * 		./templates/<name>_<lang>.html
	 */
	public function __construct( $name ){
		if( Helper::checkFileName( $name ) ) {
			$this->filename = $name;
			if( !is_file( __DIR__ . '/templates/' . $this->filename .  '_' . self::$lang . '.html' ) ){
				throw new Exception('Kann Template nicht finden!');
			}
			try{
				$this->placeholder = json_decode( file_get_contents( __DIR__ . '/templates/' . $this->filename . '.json' ) , true);
				if( isset($this->placeholder['multiples']) ){
					$this->multiples = $this->placeholder['multiples'];
					unset($this->placeholder['multiples']);
				}
			} catch (Exception $e) {
				throw new Exception('Kann Template nicht erstellen!');
			}			
		}
		else{
			throw new Exception('Name des Templates fehlerhaft!');
		}
	}

	/**
	 * Sets the content for one type of multiple page elements
	 * @param $name the name of the multiple page element
	 * @param $content the content for each part as array
	 * 	array(
	 * 		array(
	 * 			"key" => "val",
	 * 			//...
	 * 		)
	 * 		//...
	 * 	)
	 */
	public function setMultipleContent($name, $content) : bool {
		if( isset( $this->multiples[$name] ) ){
			$mults = array();
			foreach( $content as $data){
				$mul = $this->multiples[$name];
				foreach( $data as $key => $val){
					$key = "%%".str_replace("%%", "", $key)."%%";
					if( isset( $mul[$key] ) ){
						$mul[$key] = $val;
					}
				}
				$mults[] = $mul;
			}
			if( !empty($mults) ){
				$this->multiples_data[$name] = $mults;
			}
			return true;
		}
		else{
			return false;
		}
	}

	/**
	 * Setting the content for one of the placeholders
	 * @param $key placeholder
	 * @param $value html value
	 */
	public function setContent(string $key, string $value) : bool {
		$key = "%%".str_replace("%%", "", $key)."%%";
		if( isset( $this->placeholder[$key] ) ){
			$this->placeholder[$key] = $value;
			return true;
		}
		else{
			return false;
		}
	}

	/**
	 * Includes a Tempalte in this. (Output of included on will be
	 * 	put in %%INNERCONTAINER%%)
	 * @param $template the template object to include
	 */
	public function includeTemplate( Template $template ) : bool {
		if( get_class( $template ) === 'Template' ){
			$this->inner = $template;
			return true;
		}
		return false;
	}

	/**
	 * Change the loaded Template file
	 * (only the html is changed, uses the first json)
	 */
	public function loadOtherTemplate(string $name) : void {
		if( Helper::checkFileName( $name ) ) {
			if( is_file( __DIR__ . '/templates/' . $name .  '_' . self::$lang . '.html' ) ){
				$this->filename = $name;
			}
			else {
				throw new Exception('Kann Template nicht finden!');
			}
		}
		else{
			throw new Exception('Name des Templates fehlerhaft!');
		}
	}

	/**
	 * Getting the output of this template (incl. included ones)
	 */
	public function getOutputString() : string {
		$htmldata = file_get_contents( __DIR__ . '/templates/' . $this->filename .  '_' . self::$lang . '.html' );

		foreach( $this->multiples as $name => $val ){
			$a = explode( '<!--MULTIPLE-'.$name.'-BEGIN-->', $htmldata );
			$b = explode( '<!--MULTIPLE-'.$name.'-END-->', $htmldata );

			if( !empty($this->multiples_data[$name]) ){
				$inner = substr( $a[1], 0, strpos($a[1], '<!--MULTIPLE-'.$name.'-END-->') );
				$middle = '';
				foreach( $this->multiples_data[$name] as $data){
					$middle .= str_replace(
							array_keys( $data ),
							array_values( $data ),
						$inner );
				}
			}
			else{
				$middle = '';
			}
			$htmldata = $a[0] . $middle . $b[1];
		}

		$this->placeholder['%%SERVERURL%%'] = 'http'. ( empty($_SERVER['HTTPS']) ? '' : 's' ) .':'. substr(Config::DOMAIN, strpos(Config::DOMAIN, '//'));
		

		if( $this->inner !== null ){
			$this->placeholder['%%INNERCONTAINER%%'] = $this->inner->getOutputString();
		}
		return str_replace(
				array_keys( $this->placeholder ),
				array_values( $this->placeholder ),
			$htmldata );
	}

	/**
	 * Output the page using this template.
	 * ends the script!
	 */
	public function output(){
		if( !headers_sent() ){
			header( 'Content-type:text/html; charset=utf-8' );
		}
		echo $this->getOutputString();
		die();
	}
}

?>