<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

spl_autoload_register(function ($class) {
	if( is_string($class) && preg_match( '/^[A-Za-z0-9]+$/', $class ) === 1 ){
		$classfile = __DIR__ . '/' . $class . '.php';
		if( is_file($classfile) ){
			require_once( $classfile );
		}
	}
});

?>