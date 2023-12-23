$(()=> {
	$("select.cat_select").change( (e) =>{
		var option = $(e.target).val();
		var d_id = $(e.target).attr('delid');

		if(option == "*new"){
			$("input.new_cat[delid='"+d_id+"']").css("display", "block");
		}
		else {
			$("input.new_cat[delid='"+d_id+"']").css("display", "none");
			$("input.new_cat[delid='"+d_id+"']").val("");
		}
	});
});