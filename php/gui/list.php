<?php
define('HAMA-Radio', 'Radio');
//	Load System
require_once(__DIR__ . '/html.php');

$inner = null;

// Define Header and Content callbacks.
function do_header(){
	global $inner;
	$inner = new Inner();
	$inner->checkPost();
}
function do_content(){
		echo '<h2>Podcasts &amp; Radiosender</h2>' . PHP_EOL;

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

			$inner->addForm();
				echo $inner->getHTML();

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

				echo '<script> var serverurl = "http'. ( empty($_SERVER['HTTPS']) ? '' : 's' ) .':'. substr(Config::DOMAIN, strpos(Config::DOMAIN, '//')) .'"; </script>';

}
?>
