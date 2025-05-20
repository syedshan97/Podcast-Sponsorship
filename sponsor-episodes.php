<?php
/**
 * Plugin Name:     Sponsor Episodes for WooCommerce
 * Description:     Dynamic per-episode sponsorship: date+category fields, $1,000/episode pricing, TOS checkbox, Buy Now → Checkout.
 * Version:         1.2
 * Author:          Shan
 * Author URI:      https://stonefly.com
 * Text Domain:     sponsor-episodes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEP_Plugin {

    /** @var int[] Product IDs where sponsorship applies */
    private $targets = [ 4226 ]; // ← Edit your product IDs heregit 

    /** @var SEP_Plugin */
    private static $instance = null;

    /** Singleton init */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init_hooks();
        }
        return self::$instance;
    }

    /** Hook registrations */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts',                           [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_sep_get_categories',                   [ $this, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_nopriv_sep_get_categories',            [ $this, 'ajax_get_categories' ] );

        add_action( 'woocommerce_before_add_to_cart_button',        [ $this, 'render_fields' ] );
      //  add_filter( 'woocommerce_add_to_cart_validation',           [ $this, 'validate' ], 10, 2 );

        add_filter( 'woocommerce_add_cart_item_data',               [ $this, 'add_cart_item_data' ], 10, 2 );
        add_filter( 'woocommerce_get_cart_item_from_session',       [ $this, 'load_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals',          [ $this, 'apply_price' ], 20 );

        add_filter( 'woocommerce_get_item_data',                    [ $this, 'display_cart_meta' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item',  [ $this, 'save_order_meta' ], 10, 4 );

        add_filter( 'woocommerce_product_single_add_to_cart_text',  [ $this, 'button_text' ] );
        add_filter( 'woocommerce_add_to_cart_redirect',             [ $this, 'redirect_to_checkout' ] );

        add_filter( 'woocommerce_thankyou_order_received_text',     [ $this, 'thankyou_message' ], 10, 2 );
    }

    /** Are we on one of our target product pages? */
    private function is_target() {
        return is_product() && in_array( get_the_ID(), $this->targets, true );
    }

    /** Enqueue Flatpickr + plugin JS/CSS tweaks */
    public function enqueue_assets() {
        if ( ! $this->is_target() ) return;

        wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true );
        wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' );

        wp_enqueue_script(
            'sep-js',
            plugin_dir_url( __FILE__ ) . 'sponsor-episodes.js',
            [ 'jquery', 'flatpickr' ],
            '1.1.2',
            true
        );

        // Inline CSS: hide default qty, style boxes, legends, icons, errors
        $css = "
          input.qty, .quantity { display:none!important; }
          .sep-block { padding:15px; background:#ffc4b61f; margin-bottom:20px; border:1px solid #ddd; }
          .sep-block legend { font-size:1.2em; font-weight:bold; padding:0 5px; }
          .sep-tos-error { color:red; margin-top:5px; font-size:0.9em; }
        ";
        wp_add_inline_style( 'flatpickr-css', $css );

        wp_localize_script( 'sep-js', 'SEP_Settings', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'minDateOffset' => 3,     // disable today + 1 & +2, first pickable = today+3
            'targets'       => $this->targets,
        ] );
    }

    /** AJAX endpoint: return all blog categories */
    public function ajax_get_categories() {
        $terms = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
        $out   = [];
        foreach ( $terms as $t ) {
            $out[] = [ 'term_id' => $t->term_id, 'name' => $t->name ];
        }
        wp_send_json( $out );
    }

    /** Render the episode-count dropdown, containers, price & TOS */
    public function render_fields() {
        if ( ! $this->is_target() ) return;
        ?>
        <div id="sep-wrapper">
			<style>
			.info-icon {
    position: relative;
    display: inline-block;
    cursor: pointer;
    color: #E33309; /* Customize to match your theme */
    margin-left: 5px;
}

.info-icon i {
    font-size: 16px;
}

/* Tooltip styling */
.info-icon::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 125%; /* Show above icon */
    left: 50%;
    transform: translateX(-50%);
    background-color: #333;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease-in-out;
    z-index: 999;
    white-space: normal;
    width: max-content;
    max-width: 240px; /* Responsive width */
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    text-align: left;
}

/* Show tooltip on hover */
.info-icon:hover::after {
    opacity: 1;
}

/* Responsive tweaks (optional but useful) */
@media (max-width: 480px) {
    .info-icon::after {
        font-size: 13px;
        max-width: 200px;
        padding: 6px 10px;
    }
}

			</style>
          <p>
            <label for="sep_num_episodes"><strong><?php esc_html_e( 'Number of Episodes to Sponsor *', 'sponsor-episodes' ); ?></strong><span class="info-icon" data-tooltip="Please Select the No of Episodes you want to Sponsor."><i class="fas fa-info-circle"></i></span></label>
            <select id="sep_num_episodes" name="sep_num_episodes" required>
              <option value=""><?php esc_html_e( 'Select…', 'sponsor-episodes' ); ?></option>
              <?php for ( $i = 1; $i <= 10; $i++ ) : 
                  $label = ( $i === 1 ) ? 'Episode' : 'Episodes';
              ?>
                <option value="<?php echo esc_attr( $i ); ?>">
                  <?php echo esc_html( sprintf( '%d %s', $i, $label ) ); ?>
                </option>
              <?php endfor; ?>
            </select>
          </p>
          <div id="sep-repeat-container"></div>
          <p id="sep-price" style="display:none; font-weight:bold;"></p>
          <p>
            <label>
              <input type="checkbox" id="sep-tos" name="sep_tos" value="1" />
              <?php
              echo sprintf(
                  /* translators: %s: Terms of Service link */
                  __( 'I agree to the %s', 'sponsor-episodes' ),
                  '<a href="#tos-popup" >' . esc_html__( 'Terms of Service', 'sponsor-episodes' ) . '</a>'
              );
              ?>
            </label>
          </p>
        </div>
        <?php
    }

    /** Server-side validation of all fields */
    public function validate( $passed, $product_id ) {
        if ( ! in_array( $product_id, $this->targets, true ) ) {
            return $passed;
        }
        $num      = absint( $_POST['sep_num_episodes'] ?? 0 );
        $dates    = $_POST['sep_dates']         ?? [];
        $cats     = $_POST['sep_categories']    ?? [];
        $levels   = $_POST['sep_levels']        ?? [];
        $emailChk = $_POST['sep_email_promo']   ?? [];
        $emailOpt = $_POST['sep_email_options'] ?? [];
        $linkedin = $_POST['sep_linkedin_promo']?? [];

        if ( $num < 1 ) {
            wc_add_notice( __( 'Please select number of episodes.', 'sponsor-episodes' ), 'error' );
            return false;
        }

        if ( count( $dates ) < $num || count( $cats ) < $num || count( $levels ) < $num ) {
            wc_add_notice( __( 'Please fill Level, Date & Category for each episode.', 'sponsor-episodes' ), 'error' );
            return false;
        }

        // Dates are restricted via Flatpickr minDate; omit server check or keep if desired

        if ( empty( $_POST['sep_tos'] ) ) {
            wc_add_notice( __( 'You must accept the Terms of Service.', 'sponsor-episodes' ), 'error' );
            return false;
        }

        return $passed;
    }

    /** Collect all episode data + compute total price */
    public function add_cart_item_data( $cart_item_data, $product_id ) {
        if ( ! in_array( $product_id, $this->targets, true ) ) {
            return $cart_item_data;
        }

        $num      = absint( $_POST['sep_num_episodes'] );
        $dates    = array_map( 'sanitize_text_field', $_POST['sep_dates']      ?? [] );
        $cats     = array_map( 'absint',             $_POST['sep_categories'] ?? [] );
        $levels   = array_map( 'sanitize_text_field', $_POST['sep_levels']    ?? [] );
        $emailChk = $_POST['sep_email_promo']   ?? [];
        $emailOpt = $_POST['sep_email_options'] ?? [];
        $linkedin = $_POST['sep_linkedin_promo']?? [];

        $total = 0;
        for ( $i = 0; $i < $num; $i++ ) {
            $lvl = $levels[ $i ];
            $total += ( 'Custom' === $lvl ? 1500 : 1000 );
            if ( isset( $emailChk[ $i ] ) ) {
                $opt = $emailOpt[ $i ];
                $total += ( '10k' === $opt ? 1000 : ( '20k' === $opt ? 1500 : 2000 ) );
            }
            if ( isset( $linkedin[ $i ] ) ) {
                $total += 2000;
            }
        }

        $cart_item_data['sep_num']          = $num;
        $cart_item_data['sep_dates']        = $dates;
        $cart_item_data['sep_cats']         = $cats;
        $cart_item_data['sep_levels']       = $levels;
        $cart_item_data['sep_email_chk']    = $emailChk;
        $cart_item_data['sep_email_opts']   = $emailOpt;
        $cart_item_data['sep_linkedin_chk'] = $linkedin;
        $cart_item_data['sep_price']        = $total;
        $cart_item_data['sep_key']          = wp_generate_uuid4();

        return $cart_item_data;
    }

    /** Restore data from session */
    public function load_cart_item_data( $item, $values ) {
        foreach ( [ 'sep_num','sep_dates','sep_cats','sep_levels','sep_email_chk','sep_email_opts','sep_linkedin_chk','sep_price','sep_key' ] as $key ) {
            if ( isset( $values[ $key ] ) ) {
                $item[ $key ] = $values[ $key ];
            }
        }
        return $item;
    }

    /** Override the line‑item price */
    public function apply_price( $cart ) {
        foreach ( $cart->get_cart() as $item ) {
            if ( isset( $item['sep_price'] ) ) {
                $item['data']->set_price( floatval( $item['sep_price'] ) );
            }
        }
    }

    /** Display meta in Cart & Checkout */
    public function display_cart_meta( $meta, $item ) {
        if ( isset( $item['sep_num'] ) ) {
            $meta[] = [ 'key' => __( 'Episodes', 'sponsor-episodes' ), 'value' => $item['sep_num'] ];
            for ( $i = 0; $i < $item['sep_num']; $i++ ) {
                $meta[] = [
                    'key'   => sprintf( __( 'Episode %d Level', 'sponsor-episodes' ), $i + 1 ),
                    'value' => esc_html( $item['sep_levels'][ $i ] ),
                ];
                $meta[] = [
                    'key'   => sprintf( __( 'Episode %d Date', 'sponsor-episodes' ), $i + 1 ),
                    'value' => esc_html( $item['sep_dates'][ $i ] ),
                ];
                $term = get_term( $item['sep_cats'][ $i ] );
                $name = $term ? ucwords( strtolower( $term->name ) ) : '';
                $meta[] = [
                    'key'   => sprintf( __( 'Episode %d Category', 'sponsor-episodes' ), $i + 1 ),
                    'value' => esc_html( $name ),
                ];
                if ( isset( $item['sep_email_chk'][ $i ] ) ) {
                    $opt = $item['sep_email_opts'][ $i ];
                    $meta[] = [
                        'key'   => sprintf( __( 'Episode %d Email Promotion', 'sponsor-episodes' ), $i + 1 ),
                        'value' => esc_html( ucfirst( $opt ) . ' Email Promo' ),
                    ];
                }
                if ( isset( $item['sep_linkedin_chk'][ $i ] ) ) {
                    $meta[] = [
                        'key'   => sprintf( __( 'Episode %d LinkedIn Ads', 'sponsor-episodes' ), $i + 1 ),
                        'value' => esc_html__( 'LinkedIn Ads Promotion', 'sponsor-episodes' ),
                    ];
                }
            }
        }
        return $meta;
    }

    /** Save meta to the order */
    public function save_order_meta( $line_item, $cart_key, $values ) {
        if ( isset( $values['sep_num'] ) ) {
            $line_item->add_meta_data( __( 'Episodes', 'sponsor-episodes' ), $values['sep_num'], true );
            for ( $i = 0; $i < $values['sep_num']; $i++ ) {
                $line_item->add_meta_data(
                    sprintf( __( 'Episode %d Level', 'sponsor-episodes' ), $i + 1 ),
                    $values['sep_levels'][ $i ],
                    true
                );
                $line_item->add_meta_data(
                    sprintf( __( 'Episode %d Date', 'sponsor-episodes' ), $i + 1 ),
                    sanitize_text_field( $values['sep_dates'][ $i ] ),
                    true
                );
                $term = get_term( $values['sep_cats'][ $i ] );
                $name = $term ? ucwords( strtolower( $term->name ) ) : '';
                $line_item->add_meta_data(
                    sprintf( __( 'Episode %d Category', 'sponsor-episodes' ), $i + 1 ),
                    $name,
                    true
                );
                if ( isset( $values['sep_email_chk'][ $i ] ) ) {
                    $opt = $values['sep_email_opts'][ $i ];
                    $line_item->add_meta_data(
                        sprintf( __( 'Episode %d Email Promotion', 'sponsor-episodes' ), $i + 1 ),
                        ucfirst( $opt ) . ' Email Promo',
                        true
                    );
                }
                if ( isset( $values['sep_linkedin_chk'][ $i ] ) ) {
                    $line_item->add_meta_data(
                        sprintf( __( 'Episode %d LinkedIn Ads', 'sponsor-episodes' ), $i + 1 ),
                        __( 'LinkedIn Ads Promotion', 'sponsor-episodes' ),
                        true
                    );
                }
            }
        }
    }

    /** Change the button text */
    public function button_text() {
        return $this->is_target() ? __( 'Buy Now', 'sponsor-episodes' ) : null;
    }

    /** Redirect immediately to Checkout */
    public function redirect_to_checkout( $url ) {
        if ( isset( $_REQUEST['add-to-cart'] ) && in_array( intval( $_REQUEST['add-to-cart'] ), $this->targets, true ) ) {
            return wc_get_checkout_url();
        }
        return $url;
    }

    /** Append thank-you note */
//     public function thankyou_message( $text, $order ) {
//         return $text . '<p><em>Heads up – You will see the charge from our parent group <strong>ParsInternationalComputerGroup</strong> on your bank statement.</em></p>';
//     }

	/**
 * Append a styled thank‑you block to the default text.
 */
public function thankyou_message( $text, $order ) {
    // Merchant name (uppercased)
    $merchant = 'PARSINTLCOMPUTER';
    // Order total formatted by WooCommerce
    $amount   = $order->get_formatted_order_total();

    // Inline CSS for this block
    $style = '
    <style>
      .sep-thankyou { text-align: center; margin: 2em 0; }
      .sep-thankyou .sep-check-icon {
        font-size: 3rem;
        color: #28a745;
        line-height: 1;
        margin-bottom: 0.5em;
      }
      .sep-thankyou h2 {
        margin: 0.2em 0;
        font-size: 1.6em;
      }
      .sep-thankyou .sep-subtext {
        color: #666;
        margin-bottom: 1em;
      }
      .sep-thankyou .sep-receipt {
        display: flex;
        padding: 1em 1.5em;
        background: #f5f5f5;
        border-top: 1px solid #ddd;
        position: relative;
        font-family: monospace;
        text-transform: uppercase;
        letter-spacing: 1px;
      }
      .sep-thankyou .sep-merchant {
        font-weight: bold;
      }
      .sep-thankyou .sep-amount {
        position: absolute;
        right: 1.5em;
        top: 50%;
        transform: translateY(-50%);
        font-weight: bold;
      }
	  .woocommerce-thankyou-order-received, h1.entry-title {
	  display:none;
	  }
    </style>';

    // Build the HTML
    $html  = $style;
    $html .= '<div class="sep-thankyou">';
    $html .= '  <div class="sep-check-icon"><i style="border: 2px solid; padding: 15px; border-radius: 50%;" class="fas fa-check"></i></div>';
    $html .= '  <h2>' . esc_html__( 'Thanks for your payment', 'sponsor-episodes' ) . '</h2>';
    $html .= '  <p class="sep-subtext">'
           . sprintf(
               /* translators: 1: merchant name */
               esc_html__( 'A payment to %1$s will appear on your statement.', 'sponsor-episodes' ),
               esc_html( $merchant )
             )
           . '</p>';
    $html .= '  <div class="sep-receipt">';
    $html .= '    <span class="sep-merchant">' . esc_html( $merchant ) . '</span>';
    $html .= '    <span class="sep-amount">'  . wp_kses_post( $amount )  . '</span>';
    $html .= '  </div>';
    $html .= '</div>';

    return $text . $html;
}

}

// Initialize the plugin
SEP_Plugin::instance();