<?php
define('HAMA-Radio', 'Radio');
error_reporting(0);

require_once( __DIR__ . '/../classes/Config.php' );
require_once(  __DIR__ . '/../classes/Inner.php');
require_once( __DIR__ . '/../classes/Id.php' );
Config::checkAccess();

/**
 * 
 * ADD ID CHECK
 * 
 */

$inner = new Inner();
$inner->checkPost();
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
		div.achtung{
			border: 2px solid red;
			padding: 5px;
			border-radius: 4px;
			background-color: orange;
		}
		div.note{
			border: 2px solid red;
			padding: 5px;
			border-radius: 4px;
			background-color: lightblue;
		}
		</style>
		<title>Radio API &ndash; Backend</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<script src="jquery.min.js"></script>
		<script src="viewer.js"></script>
	</head>
	<body>
		<h1>Radio API &ndash; Backend</h1>

			<h2>Podcasts &amp; Radiosender</h2>

			<div class="note">
				Der Name eines Radiosenders wird in der GUI des Radios angezeigt,
				als URL wird ein streamfähiger Link benötigt (MP3 etc.).
				Über Logo kann eine URL zu einem den Sender illustrieredenen 
				Bild angegeben werden. Wird kein Bild angegeben, so wird
				<a href="<?php echo Config::DOMAIN; ?>media/default.png" target="_blank">
				<code><?php echo Config::DOMAIN; ?>media/default.png</code></a>
				im Radio angezeigt. Beschreibung kann leer bleiben und ist 
				eine weitere Beschreibung des Sender.
			</div>
			<p></p>

			<?php
				$inner->addForm();
				echo $inner->getHTML();
			?>

				<p></p>
				<div class="note">
					Der Name eines Podcasts wird in der GUI des Radios angezeigt,
					als URL kann entweder ein RSS Atom Feed angegeben werden oder 
					der Link zu einer Nextcloud Freigabe. (Letzterer muss mit 
					<code><?php echo Config::NEXTCLOUD; ?></code> beginnen
					und die Form <code><?php echo Config::NEXTCLOUD; ?>s/sdHvTUVtVHb/</code>
					haben. Die Freigabe darf kein Passwort haben.)<br/>
					Einige Anbieter wie Soundcloud leiten in den Links zu den 
					Episoden auf SSL um, die eigentliche URL zur Audiodatei ist jedoch 
					auch ohne SSL erreichbar. Mit EndURL wird der direkte Link zur
					Audiodatei an das Radio geschickt.
				</div>

			<h2>Vorschau</h2>

				<div id="apiviewer">
				</div>
				<div id="audiodiv">
				</div>

				<div class="achtung">
					Achtung, die Vorschau dient in erster Linie dazu die Liste der Radios, Podcast, Episoden 
					und Streams anzuschauen. Das Abspielen von einigen Audioformaten klappt in 
					Browsern nicht, dafür aber auf dem Radio. Weiterhin kann das Radio kein SSL 
					ein Browser jedoch schon.
				</div>
<?php
				echo '<script> var serverurl = "http'. ( empty($_SERVER['HTTPS']) ? '' : 's' ) .':'. substr(Config::DOMAIN, strpos(Config::DOMAIN, '//')) .'"; </script>';
?>

		<h1></h1>

		<center>
			<small>
				<a href="https://github.com/KIMB-technologies/Radio-API" target="_blank">Radio API by KIMB-technologies</a>
				&ndash;
				<a href="https://www.gnu.org/licenses/gpl-3.0.txt" target="_blank">GPLv3</a>
			</small>
		</center>
	</body>
</html>
