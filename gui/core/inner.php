<?php
defined('HAMA-Radio') or die('Invalid Endpoint');

function filterURL(string $url) : string {
	$url = filter_var( $url, FILTER_VALIDATE_URL) ? substr( $url , 0, 1000) : 'invalid';
	return empty($url) ? '' : $url;
}
function filterName(string $name): string{
	$name = str_replace( ['ä','ü','ß','ö','Ä','Ü','Ö'], ['ae','ue','ss','oe','Ae','Ue','Oe'], $name);
	$name = substr( preg_replace( '/[^0-9A-Za-z \.\-\_\,\&\;\/\(\)]/', '',  $name ), 0, 200 );
	return empty($name) ? 'empty' : $name;
}

if(isset( $_GET['radios'] ) && isset( $_POST['name'] )){
	echo '<span style="color:green;">Radiosender geändert!</span>';

	$radios = array();
	foreach( $_POST['name'] as $id => $name ){
		if( !empty($name) ){
			$radios[] = array(
				'name' => filterName( $name ),
				'url' => filterURL( $_POST['url'][$id] ),
				'logo' => filterURL( $_POST['logo'][$id] ),
				'desc' => filterName( $_POST['desc'][$id] )
			);
		}
	}
	file_put_contents( __DIR__ . '/../../data/radios.json', json_encode($radios, JSON_PRETTY_PRINT));
}
else if(isset( $_GET['podcasts'] ) && isset( $_POST['name'] ) ){
	echo '<span style="color:green;">Podcasts geändert!</span>';

	$podcasts = array();
	foreach( $_POST['name'] as $id => $name ){
		if( !empty($name) ){
			$podcasts[] = array(
				'name' => filterName( $name ),
				'url' => filterURL( $_POST['url'][$id] )
			);
		}
	}
	file_put_contents( __DIR__ . '/../../data/podcasts.json', json_encode($podcasts, JSON_PRETTY_PRINT));
}

//load in unloaded
if( !isset($radios) ){
	$radios = json_decode( file_get_contents( __DIR__ . '/../../data/radios.json' ), true);
}
if(!isset( $podcasts )){
	$podcasts = json_decode( file_get_contents( __DIR__ . '/../../data/podcasts.json' ), true);
}
?>

<h3>Radiosender</h3>
<form action="?radios" method="post">
<table>
	<tr><th>ID</th><td></td><td style="width: 500px; max-width:60%; "></td></tr>
<?php
	foreach($radios as $key => $radio ){
		$id = ($key+1000);
		echo '<tr><th>'. $id .'</th><td></td><td></td></tr>';
		echo '<tr><td></td><td>Name</td><td><input delid="d'.$id.'" type="text" value="'.$radio['name'].'" name="name[]"/></td></tr>';
		echo '<tr><td></td><td>URL</td><td><input delid="d'.$id.'" type="text" value="'.$radio['url'].'" name="url[]"/></td></tr>';
		echo '<tr><td></td><td>Logo</td><td><input delid="d'.$id.'" type="text" value="'.$radio['logo'].'" name="logo[]"/></td></tr>';
		echo '<tr><td></td><td>Beschreibung</td><td><input delid="d'.$id.'" type="text" value="'.$radio['desc'].'" name="desc[]"/></td><td><button class="del" delid="d'.$id.'" type="button" title="Löschen.">&cross;</button></td></tr>';
	}
?>
	<tr><th>New</th><td></td><td></td></tr>
	<tr><td></td><td>Name</td><td><input type="text" placeholder="Name" name="name[]"/></td></tr>
	<tr><td></td><td>URL</td><td><input type="text" placeholder="URL (MP3, ...)" name="url[]"/></td></tr>
	<tr><td></td><td>Logo</td><td><input type="text" placeholder="Logo (PNG, ...)" name="logo[]"/></td></tr>
	<tr><td></td><td>Beschreibung</td><td><input type="text" placeholder="Beschreibung" name="desc[]"/></td></tr>
</table>
<input type="submit" value="Sichern">
</form>

<h3>Podcasts</h3>
<form action="?podcasts" method="post">
<table>
	<tr><th>ID</th><td></td><td style="width: 500px; max-width:60%; "></td></tr>
<?php
	foreach($podcasts as $key => $pod ){
		$id = ($key+3000);
		echo '<tr><th>'. $id .'</th><td></td><td></td></tr>';
		echo '<tr><td></td><td>Name</td><td><input type="text" delid="d'.$id.'" value="'.$pod['name'].'" name="name[]"/></td></tr>';
		echo '<tr><td></td><td>URL</td><td><input type="text" delid="d'.$id.'" value="'.$pod['url'].'" name="url[]"/></td><td><button class="del" delid="d'.$id.'" type="button" title="Löschen.">&cross;</button></td></tr>';
	}
?>
	<tr><th>New</th><td></td><td></td></tr>
	<tr><td></td><td>Name</td><td><input type="text" placeholder="Name" name="name[]"/></td></tr>
	<tr><td></td><td>URL</td><td><input type="text" placeholder="URL (RSS Atom, Nextcloud Share [no SSL, no password] ...)" name="url[]"/></td></tr>
</table>
<input type="submit" value="Sichern">
</form>

<script>
$(function (){
	$("button.del").click( function (){
		var id = $(this).attr("delid");
		$("input[delid="+ id +"]").val('');
	});
});
</script>