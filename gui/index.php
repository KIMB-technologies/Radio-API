<?php
define('HAMA-Radio', 'Radio');
error_reporting(0);

require_once( __DIR__ . '/../data/Config.php' );
Config::checkAccess();
?>
<!DOCTYPE HTML>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="none">
		<style>
		body{
			font-family:Ubuntu,sans-serif;
			font-size:100%;
			background-color:#fff;
		}
		h1{
			color:black;
			border-bottom: 1px solid #5d7;
			text-align:center;
			font-size:2em;
			font-type:bold;
		}
		h2{
			color:black;
			border-bottom: 1px solid #5d7;
			text-align:left;
			font-size:1.5em;
			font-type:bold;
		}
		input[type=text]{
			width:95%;
			max-width:500px;
		}
		div#apiviewer a{
			text-decoration: none;
		}
		</style>
		<title>Radio API &ndash; Backend</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<script src="/gui/load/jquery.min.js"></script>
		<script src="/gui/load/viewer.js"></script>
	</head>
	<body>
		<h1>
			Radio API &ndash; Backend
		</h1>

		<h2>Podcasts &amp; Radiosender</h2>
		
		<?php
			require_once(  __DIR__ . '/core/inner.php');

			echo '<script> var serverurl = "http'. ( empty($_SERVER['HTTPS']) ? '' : 's' ) .':'. substr(Config::DOMAIN, strpos(Config::DOMAIN, '//')) .'"; </script>';
		?>

		<h2>Vorschau</h2>

		<div id="apiviewer">
		</div>
		<div id="audiodiv">
		</div>

		<h1></h1>

		<center>
			<small>
				<a href="https://github.com/KIMB-technologies/Radio-API" target="_blank">Radio API by KIMB-trechnologies</a>
				&ndash;
				<a href="https://www.gnu.org/licenses/gpl-3.0.txt" target="_blank">GPLv3</a>
			</small>
		</center>
	</body>
</html>
