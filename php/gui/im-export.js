$(() => {

	var err_format = {
		'color' : 'red',
		'font-weight' : 'bold'
	};
	var def_format = {
		'color' : 'black',
		'font-weight' : 'normal'
	};

	$("input[name=kind]").change( () => {
		var kind = $("input[name=kind]:checked").val();
		if (kind == "replace" ){
			$("tr#replace-confirm-row").css('display', 'table-row');
			$(".kind-single-only").css('display', 'none');

			$("input[name=replace]").prop('checked', false);
		}
		else if( kind == "single" ){
			$(".kind-single-only").css('display', 'table-row');
			$("tr#replace-confirm-row").css('display', 'none');
		}
		else{
			$("tr#replace-confirm-row").css('display', 'none');
			$(".kind-single-only").css('display', 'none');
		}
	});

	$("form#import").submit( () => {
		var kind = $("input[name=kind]:checked").val();

		// checks
		var okF = $("input[name=export]").val().length > 0;
		$("tr#file-row").css(okF ? def_format : err_format);

		var okT = $("tr#import-token-row input[name=token]").val().length >= 15;
		$("tr#import-token-row").css(okT ? def_format : err_format);

		var okB = kind !== "single" || $("input[name=code-backup]").val().match(/^Z[0-9A-Za-z]{4}$/) !== null;
		$("tr#single-backup-row").css(okB ? def_format : err_format);

		var okS = kind !== "single" || $("input[name=code-system]").val().match(/^Z[0-9A-Za-z]{4}$/) !== null;
		$("tr#single-system-row").css(okS ? def_format : err_format);
	
		var okC = kind !== "replace" || $("input[name=replace]").prop('checked');
		$("tr#replace-confirm-row").css(okC ? def_format : err_format);

		return okF && okT && okB && okS && okC;
	});

	$("form#export").submit( () => {
		var okT = $("tr#export-token-row input[name=token]").val().length >= 15;
		$("tr#export-token-row").css(okT ? def_format : err_format);

		return okT;
	});

});