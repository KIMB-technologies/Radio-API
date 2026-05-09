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
		loadPage( serverurl + '?go=initial', 'div#apiviewer' );
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
	$.get( url + '&mac=' + radiomac + '&dlang=' + dlang_val , (data) => { 
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
	let type = $(item).find('ItemType').text();
	let playh = '';
	let markAsKnow = '';
	let url = "";
	let name = "";

	if( type == "Station" ){
		if(play){
			playh += $(item).find('StationName').text();
			playFile( $(item).find('StationUrl').text(), $(item).find('Logo').text() );
		}
		else{
			name = $(item).find('StationName').text();
			url = $(item).find('StationId').text();
		}
	}
	else if( type == "Dir" ){
		name = $(item).find('Title').text() + ' &rarr;';
		url = $(item).find('UrlDir').text();
	}
	else if( type == "Previous" ){
		name = '&larr; ..';
		url = $(item).find('UrlPrevious').text();
	}
	else if( type == "ShowOnDemand" ){
		name = $(item).find('ShowOnDemandName').text() + ' &rarr;';
		url = $(item).find('ShowOnDemandURL').text();
	}
	else if( type == "ShowEpisode" ){
		if( play ){
			playh += $(item).find('ShowEpisodeName').text();
			playFile( $(item).find('ShowEpisodeURL').text(),  $(item).find('Logo').text() );
		}
		else{
			name = $(item).find('ShowEpisodeName').text();
			url = $(item).find('ShowEpisodeID').text();

			if( url.match(/^3\d\d\dX\d+$/) ){ // only podcast episodes support UnRead
				markAsKnow = ' &ndash; ' + (name.substr(0,1) == '*' ?
					'<span class="mark-known" title="Als angehört markieren/ Mark as seen">&check;</span>' :
					'<span class="mark-known" title="Als ungehört markieren/ Mark as unseen">&cross;</span>');
			}
		}
	}

	
	if( typeof url === 'string' && url.startsWith(radiourl) ){
		url = serverurl + url.slice(radiourl.length)
	}
	else{
		
	}

	return playh == '' ?
		'<a href="'+ url +'" class="openType" opentype="'+ type +'">'+ name +'</a>' + markAsKnow : playh;
}

function addOpenTypeListener(elem){
	$("a.openType").on( "click", function (e) {
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
	$("span.mark-known").on( "click", function (){
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

function playFile( url, logo ) {
	if( url.startsWith(radiourl)){
		url = serverurl + url.slice(radiourl.length)
	}
	if( logo.startsWith(radiourl)){
		logo = serverurl + logo.slice(radiourl.length)
	}

	var html ='<audio controls="controls" autoplay="autoplay">'
		+ '<source src="'+ url +'" type="audio/mp3">'
		+ '</audio>'
		+ '<img src="'+logo+'" width="48" height="48" style="margin-left:20px; border: solid 1px grey;">';
	$("div#audiodiv").html(html);
}
