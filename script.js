jQuery(document).ready(function($) {
    var applyMapContainerHeight = function() {
        if($("body").hasClass("product_page_pricing")){
            var height = document.body.clientHeight - 84; //jQuery("#wpbody").height();
            var width = document.body.clientWidth - ($("#wpcontent").outerWidth(true) - $("#wpcontent").width()) - 10; //jQuery("#wpbody").width();
            $("#sheetiframe").height(height);
            $("#sheetiframe").width(width);
            $("#wpbody").css({position: 'fixed'})
        }
    };

    $(document).ready(function() {
        applyMapContainerHeight();
    });

    $(window).resize(function() {
        applyMapContainerHeight();
    });

    // $("#wpcontent").on('resize', function() {
    //     setTimeout(applyMapContainerHeight(), 1000);
    // });

    $("#ppt_save_prices_button").on('click', function() {
        if (confirm('Are you suer you want to </br> SAVE </br> the changes to the database? This action cannot be reversed.')) {
            //alert('SAVING');
            $("#ppt_save_prices_button").prop('disabled', true);
            $("#ppt_load_prices_button").prop('disabled', true);
            $("#ppt_spinner").addClass("is-active");

            var data = {
                'action': 'ppt_save_prices'
            };
            jQuery.post(ajaxurl, data, function(response) {
                //alert('Got this from the server: ' + response);
                $("#ppt_save_prices_button").prop('disabled', false);
                $("#ppt_load_prices_button").prop('disabled', false);
                $("#ppt_spinner").removeClass("is-active");
            });
        }
    });

    $("#ppt_load_prices_button").on('click', function() {
        if (confirm('Are you suer you want to </br> LOAD </br> prices from the database? All changes in the "main" tab will be lost.')) {
            //alert('LOADING');
            $("#ppt_save_prices_button").prop('disabled', true);
            $("#ppt_load_prices_button").prop('disabled', true);
            $("#ppt_spinner").addClass("is-active");

            var data = {
                'action': 'ppt_load_prices'
            };
            jQuery.post(ajaxurl, data, function(response) {
                //alert('Got this from the server: ' + response);
                $("#ppt_save_prices_button").prop('disabled', false);
                $("#ppt_load_prices_button").prop('disabled', false);
                $("#ppt_spinner").removeClass("is-active");
            });
        }
    });

    $("#ppt_save_sheet_id").on('click', function() {
        $("#ppt_save_sheet_id").prop('disabled', true);
        $("#ppt_spinner_1").addClass("is-active");

        var data = {
            'action': 'ppt_save_sheet_id',
            'sheet_id': $('#ppt_new_sheet_id').prop('value')
        };
        jQuery.post(ajaxurl, data, function(response) {
            //alert('Got this from the server: ' + response);
            $("#ppt_save_sheet_id").prop('disabled', false);
            $("#ppt_spinner_1").removeClass("is-active");
            alert('Sheet ID updated');
        });
    });
});