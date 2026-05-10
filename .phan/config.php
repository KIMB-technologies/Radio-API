<?php
/*
	# Run via PHP Phar
	#	download it
	curl -L https://github.com/phan/phan/releases/latest/download/phan.phar -o phan.phar   

	# run it
	php phan.phar --allow-polyfill-parser -o report.txt 

*/
return [
	'target_php_version' => '8.5',
	'file_list' => [ 
		'./utils/cron.php',
		'./utils/startup.php',
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