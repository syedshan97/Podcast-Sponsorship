/**
 * Dynamic fields & UI for Sponsor Episodes plugin.
 * - Renders date + category per episode
 * - Hides/shows total price
 * - Enforces TOS checkbox before enabling Buy Now
 */

jQuery( function($) {
    var settings = SEP_Settings,
        $num     = $('#sep_num_episodes'),
        $container = $('#sep-repeat-container'),
        $price      = $('#sep-price'),
        $tos        = $('#sep-tos'),
        $button     = $('button.single_add_to_cart_button');

    // Fetch categories once
    var categories = [];
    $.post(settings.ajax_url, { action: 'sep_get_categories' }, function(data) {
        categories = data;
    });

    // Disable Buy Now until TOS checked
    $button.prop('disabled', true);
    $tos.on('change', function(){
        $button.prop('disabled', ! this.checked);
    });

    // On episode count change
    $num.on('change', function(){
        var n = parseInt(this.value, 10);
        $container.empty();
        $price.hide();

        if (!n) {
            return;
        }

        for (var i = 1; i <= n; i++) {
            // Date field
            var dateId = 'sep_date_' + i,
                catId  = 'sep_cat_'  + i;

            $container.append(
                '<div class="sep-block">' +
                  '<p><label for="'+dateId+'">Episode '+i+' Date:</label>' +
                  '<input type="text" id="'+dateId+'" name="sep_dates[]" ' +
                    'class="sep-episode-date" placeholder="Select Date for Episode '+i+'" required /></p>' +
                  '<p><label for="'+catId+'">Episode '+i+' Category:</label>' +
                  '<select id="'+catId+'" name="sep_categories[]" required>' +
                    '<option value="">Select Category for Episode '+i+'</option>' +
                  '</select></p>' +
                '</div>'
            );

            // Populate categories
            var $sel = $('#' + catId);
            $.each(categories, function(_, term){
                $sel.append('<option value="'+term.term_id+'">'+term.name+'</option>');
            });
        }

        // Initialize Flatpickr
        flatpickr('.sep-episode-date', {
            minDate: new Date().fp_incr(settings.minDateOffset)
        });

        // Show total price
        var total = settings.episodeRate * n;
        $price.text('Total: $' + total.toLocaleString()).show();
    });
});
