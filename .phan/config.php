<?php
/*
	Run check via Docker-Installes Phan:

	# function defined in shell
	phan() {
		docker run -v $PWD:/mnt/src --rm -u "1000:1000" phanphp/phan:latest $@; return $?;
	} 

	# start in document root of project
	$ phan -o report.txt 
*/
return [
	'target_php_version' => '8.0',
	'file_list' => [ 
		'./utils/cron.php',
		'./utils/startup.php',
		'./utils/getr.php'
	],
	'directory_list' => [
		'php'
	],
	'autoload_internal_extension_signatures' => [
		'redis' => './.phan/redis.phan_php',
		'curl' => './.phan/curl.phan_php'
	],
	'backward_compatibility_checks' => true,
	'plugins' => [
		'AlwaysReturnPlugin',
		'DollarDollarPlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
		'PregRegexCheckerPlugin',
		'PrintfCheckerPlugin',
		'SleepCheckerPlugin',
		'UnreachableCodePlugin',
		'UseReturnValuePlugin',
		'EmptyStatementListPlugin',
		'LoopVariableReusePlugin',
	]
];