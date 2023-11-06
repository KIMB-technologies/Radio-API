$(function (){
	if(radiomac === null){ // => using the viewer as a standalone app
		if( localStorage.hasOwnProperty("last_radio_mac") ){
			radiomac = localStorage.getItem("last_radio_mac");
		}
		else{
			$("div#apiviewer").html("<b>Unable to detect the last used radio in GUI!</b><br>Please log into GUI!");
			$("div#apiviewer").addClass("achtung");
		}
	}
	else{ // => using the list below gui 
		localStorage.setItem("last_radio_mac", radiomac);
	}

	if(radiomac !== null){
		loadPage( serverurl + '?', 'div#apiviewer' );
	}
});

var reloadPageValues = {};
function reloadPage(){
	loadPage(reloadPageValues.url, reloadPageValues.elem, reloadPageValues.play);
}

function loadPage( url, elem, play ){
	play = play || false;
	reloadPageValues = {url: url, elem:elem, play:play};

	var html = "<ul>";
	$.get( url + '&mac=' + radiomac , (data) => { 
		var xml = $( $.parseXML( data.replace(/&/g, '&amp;') ) );

		xml.find('Item').each( (k,v) => {
			html += '<li>' + printItem(v, play) + "</li>";
		});

		html += "</ul>";
		$(elem).html( html );

		addOpenTypeListener(elem);
	});
}

function printItem( item, play ){
	var type = $(item).find('ItemType').text();
	var playh = '';
	var markAsKnow = '';

	if( type == "Station" ){
		if(play){
			playh += $(item).find('StationName').text();
			playFile( $(item).find('StationUrl').text() );
		}
		else{
			var name = $(item).find('StationName').text();
			var url = $(item).find('StationId').text();
		}
	}
	else if( type == "Dir" ){
		var name = $(item).find('Title').text() + ' &rarr;';
		var url = $(item).find('UrlDir').text();
	}
	else if( type == "Previous" ){
		var name = '&larr; ..';
		var url = $(item).find('UrlPrevious').text();
	}
	else if( type == "ShowOnDemand" ){
		var name = $(item).find('ShowOnDemandName').text() + ' &rarr;';
		var url = $(item).find('ShowOnDemandURL').text();
	}
	else if( type == "ShowEpisode" ){
		if( play ){
			playh += $(item).find('ShowEpisodeName').text();
			playFile( $(item).find('ShowEpisodeURL').text() );
		}
		else{
			var name = $(item).find('ShowEpisodeName').text();
			var url = $(item).find('ShowEpisodeID').text();

			markAsKnow = ' &ndash; ' + (name.substr(0,1) == '*' ?
				'<span class="mark-known" title="Als angehört markieren/ Mark as seen">&check;</span>' :
				'<span class="mark-known" title="Als ungehört markieren/ Mark as unseen">&cross;</span>');
		}
	}

	return playh == '' ? '<a href="'+ url +'" class="openType" opentype="'+ type +'">'+ name +'</a>' + markAsKnow : playh;
}

function addOpenTypeListener(elem){
	$("a.openType").click( function (e) {
		e.preventDefault();
		var url = $(this).attr('href');
		var type = $(this).attr('opentype');

		if( type == "Dir" || type == "Previous" || type == "ShowOnDemand" ){
			loadPage( url, elem );
		}
		else if( type == "Station" ){
			loadPage( serverurl + '?sSearchtype=3&Search=' + url, elem, true );
		}
		else if( type == "ShowEpisode" ){
			loadPage( serverurl + '?sSearchtype=5&Search=' + url, elem, true );
		}
	});
	$("span.mark-known").click( function (){
		var url = $(this).parent().children('a').attr('href');
		$(this).html('&orarr;');
		$.get(serverurl + "?mac=" + radiomac + "&toggleUnRead=" + url, (d) => {
			if( d.indexOf('<Title>TOGGLE-UN-READ-ok</Title>') !== -1 ){
				reloadPage();
			}
			else {
				$(this).html('ERROR');
			}
		});
	});
}

function playFile( url ) {
	var html ='<audio controls="controls" autoplay="autoplay">'
		+ '<source src="'+ url +'" type="audio/mp3">'
		+ '</audio>';
	$("div#audiodiv").html(html);
}
