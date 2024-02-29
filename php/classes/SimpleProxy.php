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
 * Simple PHP based proxy for web requests, here mostly media files.
 * 	This is used if Docker/ NGINX is not available.
 */
class SimpleProxy {
	
	public static $TIMEOUT = 2;
	
	private static function getAllRequestHeaders(){
		// preg_grep on keys
		$headers = [];
		foreach( $_SERVER as $key => $value ){
			if( in_array( $key, ['CONTENT_LENGTH', 'CONTENT_TYPE', 'REQUEST_METHOD']) ||
				null !== $key = preg_filter('/^HTTP_([A-Za-z_]+)$/', '$1', $key )  
			){
				//make CamelCase and replace _ by -, e.g. MY_HEADER to My-Header
				$key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
				$headers[$key] = $value . "";
			}
		}
		return array_diff( $headers, [' ', '']);
	}
	
	private static function createContextByHeaders( $headers, $host = null ){
		// Method
		$method = $headers['Request-Method'];
		if( !in_array( strtolower( $method ), ['head','get','post'] ) ){
			die('Unsupported HTTP Type');
		}
		unset($headers['Request-Method']);
		
		// Host
		if( $host !== null ){
			$headers['Host'] = $host;
		}
		// Other Header fields
		array_walk( $headers, function (&$value, $key) {
				$value = $key . ': ' . preg_replace( "/:|\n|\r/", '', $value );
		});
		
		//POST
		if( strtolower( $method ) == 'post' ){
			$postdata = http_build_query( $_POST );
		}
		else{
			$postdata = null;
		}
		
		// Create
		$opts = array(
			'http' => array(
				'method' => $method,
				'header' => implode( "\r\n", $headers ) . "\r\n",
				'timeout' => self::$TIMEOUT,
				'content' => $postdata
			)
		);
		$ctx = stream_context_create($opts);
		stream_context_set_params($ctx, array("notification" => "SimpleProxy::callback"));
		return $ctx;
	}
	
	private static function callback( $notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max ){
		if( $notification_code === STREAM_NOTIFY_FILE_SIZE_IS ){
			header( 'Content-Length: ' . $bytes_max );
		}
		if( $notification_code === STREAM_NOTIFY_MIME_TYPE_IS ){
			header( 'Content-Type: ' . $message );
		}
	}
	
	private static function sendHeader( $header ){
		$nohead = array( 'Content-Length:', 'Content-Type:');
		foreach( $header as $h ){
			$ok = true;
			foreach( $nohead as $pref ){
				if( substr( $h, 0, strlen( $pref ) ) == $pref ){
					$ok = false;
					break;
				}
			}
			if( $ok ){
				header( preg_replace( "/\n|\r/", '', $h ) );
			}
		}
	}
	
	public static function open( $url ){
		$host = preg_filter( '/^https?:\/\/([^\/\:]+).*$/', '$1', $url );
		$f = fopen(
				$url,
				'rb',
				false,
				self::createContextByHeaders(
					self::getAllRequestHeaders(),
					$host
				)
			);
		$header = $http_response_header;
		
		if( $f !== false ){
			self::sendHeader($header);
			while(!feof($f) && !connection_aborted() ){
				echo fread($f, 128);
				flush();
			};
			fpassthru($f);
			fclose($f);
		}
		else{
			die('Connection Error');
		}
	}
}

?>