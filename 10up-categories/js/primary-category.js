(function ($) {

	cat = {

		cat : this,
		checkbox_list : $("div[id$='-all']").children(".categorychecklist"),
		selectbox : '#tenup_primary_category',
		vars : primary_category_vars,

		init : function() {

			// bind events when page loads
			cat.engage_actions();

			// rebind events and update selectbox when a new category is added via ajax
			$(document).ajaxSuccess(function(e, request, settings) {

		        if( settings.data ) {

			        var params = cat.get_url_vars(settings.data);
			        console.log(params);
			        if(params.action == 'add-' + cat.vars.taxonomy ) {
						cat.engage_actions();
						var cat_list = cat.get_selected_cat_list();
						cat.update_cat_list(cat_list);
						
			        }
				}
		      
		    });

		},

		// bind events for categories checkboxes
		engage_actions : function() {

			cat.checkbox_list.find('input[type=checkbox]').unbind("click");
			cat.checkbox_list.find('input[type=checkbox]').on("click", function() {

				var cat_list = cat.get_selected_cat_list();
				cat.update_cat_list(cat_list);

			});

		},

		// parse and returns a list with all checked categories
		get_selected_cat_list : function() {

			var cat_list = [],
				key		 = 0;

			cat.checkbox_list.find('input:checked').map(function() {

				cat_list[key] = {};
				cat_list[key]['val'] = $(this).val();
				cat_list[key]['name'] = $(this).parent().text();

				key++;

			});

			return cat_list;
			
		}, 

		// syncs the select options list with checked categories 
		update_cat_list : function( cat_list ) {
			
			// get current select value
			var selected = $(cat.selectbox).val();
			
			$(cat.selectbox).find("option").remove();
			for( i = 0 ; i < cat_list.length ; i++ ){
				$(cat.selectbox).append($('<option>', {
				    value: cat_list[i]['val'],
				    text: cat_list[i]['name']
				}));

			}
			
			// set the same value as before if that option still exists
			if( selected && $(cat.selectbox).find("option[value=" + selected + "]").length > 0 ) {
				$(cat.selectbox).val(selected);

			} else {
				$(cat.selectbox).val( $(cat.selectbox).find("option:first-child").val() );

			}

			// check if at least two categories are selected. If not disable the selectbox and the post meta will be deleted on post update
			if( cat_list.length < 2 ) {
				cat.disable_cat_list();

			} else {
				cat.enable_cat_list();
			}

		},

		// disables the selectbox and show a help message
		disable_cat_list : function() {
			$(cat.selectbox).prop('disabled', true).parent().addClass("hidden").next("em").removeClass("hidden");
		},

		// removes help message and enables the selectbox again
		enable_cat_list : function() {
			$(cat.selectbox).removeAttr("disabled").parent().removeClass("hidden").next("em").addClass("hidden");

		},

		// used to pars url vars
		get_url_vars : function( url ) {

			var vars = {};
			var parts = url.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
				vars[key] = value;
			});

			return vars;

		}
	}

	cat.init();


})(jQuery);