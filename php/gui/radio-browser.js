$(function (){
	$("input#openLast").click(() => {
		radioBrowserDo(false);
	})
	$("input#openSearch").click(() => {
		radioBrowserDo(true);
	});

	$("input[name=radioBrowserType]").change( () => {
		radioBrowserDo( $("input[name=radioBrowserType][value=search]").prop('checked') )
	});

	$("input#runSearch").click( (e) => {
		e.preventDefault();
		runSearchRequest();
	});

	$("input#searchTerm").keypress( (e) => {
		if (e.keyCode === 13) {
			e.preventDefault();
			runSearchRequest();
		}
	});
});

function radioBrowserDo(isSearch){
	$("#radioBrowserButtons").css("display", "none");
	$("#radioBrowserView").css("display", "table-row");

	if(isSearch){
		$("input[name=radioBrowserType][value=search]").prop('checked', true);
		$("div#searchElements").css("display", "block");
		
		$("div#results").text("");
		runSearchRequest();
	}
	else{
		$("input[name=radioBrowserType][value=last]").prop('checked', true);
		$("div#searchElements").css("display", "none");

		runRequest("")
	}
}

function runSearchRequest(){
	var term = $("input#searchTerm").val().trim()
	if(term.length > 0){
		runRequest(term);
	}
}

var currently_shown_data_list = [];
function choseData(){
	var id = $(this).attr("did");
	var item = currently_shown_data_list[id];

	$("input[name='name["+radiocount+"]']").val(item.name);
	$("input[name='url["+radiocount+"]']").val(item.url);
	$("input[name='desc["+radiocount+"]']").val(item.hasOwnProperty('desc') ? item.desc : '');
	$("input[name='logo["+radiocount+"]']").val(item.hasOwnProperty('logo') ? item.logo : '');
}

function runRequest(term){
	var isSearch = term.length > 0

	$("div#results").html("Loading ...");
	$.get(
		serverurl + '/gui/?' + (
			isSearch ? 'search=' + encodeURIComponent(term) : 'last'
		),
		(response) => { 

			if( !isSearch && response.length > 0 ){
				var data = []
				Object.getOwnPropertyNames(response).forEach( (k) => {
					data.push(response[k])
				});
				data.sort((a, b) => b['time'] - a['time'])
			}
			else{
				var data = response;
			}

			var html = "<dl>";
			if(data.length == 0){
				html += "<dt>Nothing found!</td>"
			}
			data.forEach( (v, k) => {
				html += '<dt class="radioBrowserChoose" did="'+k+'"><input type="button" value="+"> '+ v.name +'</dt>';
				if(v.hasOwnProperty('desc')){
					html += '<dd>'+ v.desc +'</dd>';
				}
			})
			$("div#results").html(html + "</dl>");
			$("dt.radioBrowserChoose").click(choseData);
			currently_shown_data_list = data;
		}
	);
}