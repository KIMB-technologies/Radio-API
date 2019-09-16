$(function (){
	if( typeof serverurl !== "undefined" ){
		loadPage( serverurl, 'div#apiviewer' );
	}
});

function loadPage( url, elem, play ){
	play = play || false;
	var html = "<ul>";
	$.get( url, (data) => { 
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
		}
	}

	return playh == '' ? '<a href="'+ url +'" class="openType" opentype="'+ type +'">'+ name +'</a>' : playh;
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
}

function playFile( url ) {
	var html ='<audio controls="controls" autoplay="autoplay">'
		+ '<source src="'+ url +'" type="audio/wav">'
		+ '</audio>';
	$("div#audiodiv").html(html);
}
