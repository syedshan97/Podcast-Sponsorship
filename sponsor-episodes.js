/**
 * Dynamic fields & UI for Sponsor Episodes plugin.
 * - Renders date + category per episode
 * - Hides/shows total price
 * - Enforces TOS checkbox before enabling Buy Now
 */
jQuery(function($) {
	
	$('form.cart')
  .attr('novalidate', 'novalidate')
  .find('select[required], input[required]')
    .removeAttr('required');
	
  var s          = SEP_Settings,
      $num       = $('#sep_num_episodes'),
      $cont      = $('#sep-repeat-container'),
      $price     = $('#sep-price'),
      $tos       = $('#sep-tos'),
      categories = [];

  // Fetch categories
  $.post(s.ajax_url, { action:'sep_get_categories' }, function(data){
    categories = data;
  });

  // Form submit: TOS + field validation
  $('form.cart').on('submit', function(e) {
    // clear existing errors
    $('.sep-tos-error, .sep-field-error').remove();

    var hasError = false;
	
	  //Numer of Episodes
	var val = $num.val();
$num.closest('p').find('.sep-field-error').remove();
if (!val) {
  $('<div class="sep-field-error" style="color:red; margin-top:4px;">Please select the number of episodes.</div>')
    .appendTo( $num.closest('p') );
  hasError = true;
}  

    // TOS
    if (! $tos.is(':checked')) {
      $('<div class="sep-tos-error">Please agree to the Terms of Service before proceeding.</div>')
        .appendTo( $tos.closest('p') );
      hasError = true;
    }

    // Per-episode validation
    $cont.find('fieldset.sep-block').each(function(idx) {
      var $b  = $(this),
          j   = idx + 1,
          $lvl= $b.find('#sep_level_' + j),
          $dt = $b.find('#sep_date_'  + j),
          $cat= $b.find('#sep_cat_'   + j);

      // Level
      if (!$lvl.val()) {
        $('<div class="sep-field-error" style="color:red; margin-top:4px;">Please select a Level.</div>')
          .appendTo( $lvl.closest('p') );
        hasError = true;
      }
      // Date
      if (!$dt.val()) {
        $('<div class="sep-field-error" style="color:red; margin-top:4px;">Please pick a Date.</div>')
          .appendTo( $dt.closest('p') );
        hasError = true;
      }
      // Category
      if (!$cat.val()) {
        $('<div class="sep-field-error" style="color:red; margin-top:4px;">Please select a Category.</div>')
          .appendTo( $cat.closest('p') );
        hasError = true;
      }
    });

    if (hasError) {
      e.preventDefault();
      // scroll to first error
      $('html,body').animate({
        scrollTop: $('.sep-tos-error, .sep-field-error').first().offset().top - 50
      }, 200);
    }
  });

  // Title-case helper
  function toTitleCase(str) {
    return str.toLowerCase().split(' ').map(function(w){
      return w.charAt(0).toUpperCase() + w.slice(1);
    }).join(' ');
  }

  // Recalc total price
  function recalc() {
    var total = 0;
    $cont.find('fieldset.sep-block').each(function(idx){
      var $b  = $(this),
          j   = idx + 1,
          lvl = $b.find('#sep_level_' + j).val();

      if (lvl === 'Ad')      total += 1000;
      else if (lvl === 'Custom') total += 1500;

      if ($b.find('#sep_email_' + j).is(':checked')) {
        var opt = $b.find('#sep_email_option_' + j).val();
        if (opt === '10k')      total += 1000;
        else if (opt === '20k') total += 1500;
        else if (opt === '30k') total += 2000;
      }
      if ($b.find('#sep_linkedin_' + j).is(':checked')) {
        total += 2000;
      }
      if ($b.find('#sep_slides_' + j).is(':checked')) {
        total += 500;
      }
      if ($b.find('#sep_multimedia_' + j).is(':checked')) {
        total += 700;
      }
    });
    if (total > 0) $price.text('Total (all episodes): $' + total.toLocaleString()).show();
    else           $price.hide();
  }

  // On episode count change
  $num.on('change', function(){
    var n = parseInt(this.value,10);
    $cont.empty(); $price.hide();
    if (!n||n<1) return;

    for (var i = 1; i <= n; i++) {
      (function(i){
        var dateId      = 'sep_date_' + i,
            catId       = 'sep_cat_' + i,
            lvlId       = 'sep_level_' + i,
            emailChkId  = 'sep_email_' + i,
            emailOptId  = 'sep_email_option_' + i,
            linkedinId  = 'sep_linkedin_' + i,
            slidesId    = 'sep_slides_' + i,
            multimediaId= 'sep_multimedia_' + i;

        //var icon = '<span class="info-icon" title="Tooltip text here"><i class="fas fa-info-circle"></i></span>';
            var icon = '<span class="info-icon" ' +
                           'data-tooltip="Please select the date that you would like to request your sponsored podcast to be published on.">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';

            var icons  = '<span class="info-icon" ' +
                           'data-tooltip="Please select the topic category that you would like your sponsored podcast to be about.">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';
			 var iconss = '<span class="info-icon" ' +
                           'data-tooltip="Please select the level Fully Custom Podcast Episode or Sponsor an Ad for Podcast Episode.">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';
			var iconsss = '<span class="info-icon" ' +
                           'data-tooltip="Check if you want Add-on for Email Marketing Promotion">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';
			var iconssss = '<span class="info-icon" ' +
                           'data-tooltip="Check if you want to have LinkedIn Ads Promotion">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';
			var iconsssss = '<span class="info-icon" ' +
                           'data-tooltip="Select the Audience Reach you want for your Sponsored Podcast">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';
        var iconslides = '<span class="info-icon" ' +
                           'data-tooltip="Check if you want Add-on for Podcast with Slides ($500)">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';
        var iconmulti = '<span class="info-icon" ' +
                           'data-tooltip="Check if you want Add-on for Podcast with Slides & Video ($700)">' +
                           '<i class="fas fa-info-circle"></i>' +
                           '</span>';

        var $box = $(
          '<fieldset class="sep-block">'+
            '<legend>Episode '+i+'</legend>'+

            '<p><label for="'+lvlId+'">Sponsorship Level * '+iconss+'</label>' +
              '<select id="'+lvlId+'" name="sep_levels[]" required>' +
                '<option value="" disabled selected>Select Level</option>' +
                '<option value="Ad">Sponsor an Ad for Podcast Episode ($1,000)</option>' +
                '<option value="Custom">Fully Podcast Episode ($1,500)</option>' +
              '</select>' +
            '</p>'+

            '<p><label for="'+dateId+'">Date *'+icon+'</label>' +
              '<input type="text" id="'+dateId+'" name="sep_dates[]" ' +
                     'class="sep-episode-date" ' +
                     'placeholder="Select Date for Episode '+i+'" required />' +
            '</p>'+

            '<p><label for="'+catId+'">Category * '+icons+'</label>' +
              '<select id="'+catId+'" name="sep_categories[]" required>' +
                '<option value="" disabled selected>Select Category for Episode '+i+'</option>' +
              '</select>' +
            '</p>'+

            '<p><label>' +
              '<input type="checkbox" id="'+emailChkId+'" name="sep_email_promo['+(i-1)+']" /> Add-on: Email Promotion' +
            '</label>'+iconsss+'</p>'+

            '<p class="sep-email-options" style="display:none;margin-left:20px;">' +
              '<label for="'+emailOptId+'">Email Reach:'+iconsssss+'</label>' +
              '<select id="'+emailOptId+'" name="sep_email_options[]">' +
                '<option value="" disabled selected>Select Email Reach</option>' +
                '<option value="10k">10,000 Emails ($1,000)</option>' +
                '<option value="20k">20,000 Emails ($1,500)</option>' +
                '<option value="30k">30,000 Emails ($2,000)</option>' +
              '</select>' +
            '</p>'+

            '<p><label for="'+linkedinId+'">' +
              '<input type="checkbox" id="'+linkedinId+'" name="sep_linkedin_promo['+(i-1)+']" />Add-on: LinkedIn Ads Promotion ($2,000)' +
            '</label>'+iconssss+'</p>'+

            '<p><label for="'+slidesId+'">' +
              '<input type="checkbox" id="'+slidesId+'" name="sep_slides_promo['+(i-1)+']" />Add-on: Podcast with Slides ($500)' +
            '</label>'+iconslides+'</p>'+

            '<p><label for="'+multimediaId+'">' +
              '<input type="checkbox" id="'+multimediaId+'" name="sep_multimedia_promo['+(i-1)+']" />Add-on: Podcast with Slides & Video ($700)' +
            '</label>'+iconmulti+'</p>'+

          '</fieldset>'
        );
        $cont.append($box);

        // populate categories
        var $sel = $box.find('#'+catId);
        categories.forEach(function(t){
          $sel.append('<option value="'+t.term_id+'">'+toTitleCase(t.name)+'</option>');
        });

        // toggle email options
        $box.find('#'+emailChkId).on('change', function(){
          $(this).closest('p').next('p.sep-email-options').toggle(this.checked);
          recalc();
        });

        // bind recalc on selects & checkboxes
        $box.find('#'+lvlId+', #'+emailOptId+', #'+linkedinId+', #'+slidesId+', #'+multimediaId).on('change', recalc);

        // init datepicker
        flatpickr('#'+dateId, { minDate: new Date().fp_incr(s.minDateOffset) });
      })(i);
    }

    // do initial recalc
    recalc();
  });
});
