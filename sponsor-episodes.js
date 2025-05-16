/**
 * Dynamic fields & UI for Sponsor Episodes plugin.
 * - Renders date + category per episode
 * - Hides/shows total price
 * - Enforces TOS checkbox before enabling Buy Now
 */
jQuery(function($) {
    var s         = SEP_Settings,
        $num      = $('#sep_num_episodes'),
        $cont     = $('#sep-repeat-container'),
        $price    = $('#sep-price'),
        $tos      = $('#sep-tos'),
        $button   = $('button.single_add_to_cart_button'),
        categories = [];

    // Fetch categories once
    $.post(s.ajax_url, { action: 'sep_get_categories' }, function(data) {
        categories = data;
    });

    // Disable Buy Now until TOS checked
  //  $button.prop('disabled', true);
//     $tos.on('change', function() {
//         $button.prop('disabled', !this.checked);
//     });

    // On number change
    $num.on('change', function() {
        var n = parseInt(this.value, 10);
        $cont.empty();
        $price.hide();

        if (!n || n < 1) {
            return;
        }

        for (var i = 1; i <= n; i++) {
            var dateId = 'sep_date_' + i,
                catId  = 'sep_cat_'  + i;

            // Icons
            var dateIcon = '<span class="info-icon" ' +
                           'data-tooltip="Please select the date that you would like  to request your sponsored podcast to be published on.">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';

            var catIcon  = '<span class="info-icon" ' +
                           'data-tooltip="Please select the topic category that you would like your sponsored podcast to be about.">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';

            // Build block
            var blockHtml = 
                '<div class="sep-block">' +
                  '<p>' +
                    '<label for="' + dateId + '">' +
                      'Episode ' + i + ' Date:' +
                      dateIcon +
                    '</label>' +
                    '<input type="text" id="' + dateId + '" name="sep_dates[]" ' +
                      'class="sep-episode-date" ' +
                      'placeholder="Select Requested Publishing for Episode ' + i + '" required />' +
                  '</p>' +
                  '<p>' +
                    '<label for="' + catId + '">' +
                      'Episode ' + i + ' Category:' +
                      catIcon +
                    '</label>' +
                    '<select id="' + catId + '" name="sep_categories[]" required>' +
                      '<option value="">Select Requested Topic Category for Episode ' + i + '</option>' +
                    '</select>' +
                  '</p>' +
                '</div>';

            $cont.append(blockHtml);

            // Populate category select
            var $sel = $('#' + catId);
            $.each(categories, function(_, term) {
                $sel.append('<option value="' + term.term_id + '">' + term.name + '</option>');
            });
        }

        // Initialize Flatpickr
        flatpickr('.sep-episode-date', {
            minDate: new Date().fp_incr(s.minDateOffset)
        });

        // Show total price
        var total = s.episodeRate * n;
        $price.text('Total: $' + total.toLocaleString()).show();
    });
	//TOS Checkbox validation
	$('form.cart').on('submit', function(e) {
        $('.sep-tos-error').remove();
        if (!$('#sep-tos').is(':checked')) {
            e.preventDefault();
            var err = $(
              '<div class="sep-tos-error" ' +
                   'style="color:red; margin-top:5px; font-size:0.9em;">' +
                'Please agree to the Terms and Conditions before proceeding.' +
              '</div>'
            );
            $('#sep-tos').closest('p').append(err);
        }
    });
});


