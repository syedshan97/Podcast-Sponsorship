<?php
/**
 * Plugin Name:     Sponsor Episodes for WooCommerce
 * Description:     Dynamic per-episode sponsorship: date+category fields, $1,000/episode pricing, TOS checkbox, Buy Now → Checkout.
 * Version:         1.1
 * Author:          Shan
 * Author URI:      https://stonefly.com
 * Text Domain:     sponsor-episodes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Plugin {

    /** @var int[] Product IDs where sponsorship applies */
    private $targets = [ 4226 ]; // ← Edit these IDs

    /** @const int Per-episode rate in USD */
    const RATE = 1000;

    /** Singleton instance */
    private static $instance = null;

    private function __construct() {
        add_action( 'wp_enqueue_scripts',                 [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_sep_get_categories',         [ $this, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_nopriv_sep_get_categories',  [ $this, 'ajax_get_categories' ] );

        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_fields' ] );
        add_filter( 'woocommerce_add_to_cart_validation',    [ $this, 'validate' ], 10, 2 );

        add_filter( 'woocommerce_add_cart_item_data',        [ $this, 'add_cart_item_data' ], 10, 2 );
        add_filter( 'woocommerce_get_cart_item_from_session',[ $this, 'load_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals',    [ $this, 'apply_price' ], 20 );

        add_filter( 'woocommerce_get_item_data',             [ $this, 'display_cart_meta' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_meta' ], 10, 4 );

        add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'button_text' ] );
        add_filter( 'woocommerce_add_to_cart_redirect',            [ $this, 'redirect_to_checkout' ] );
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function is_target() {
        return is_product() && in_array( get_the_ID(), $this->targets, true );
    }

    public function enqueue_assets() {
        if ( ! $this->is_target() ) {
            return;
        }

        // Flatpickr
        wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true );
        wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' );

        // Our JS
        wp_enqueue_script(
            'sep-js',
            plugin_dir_url( __FILE__ ) . 'sponsor-episodes.js',
            [ 'jquery', 'flatpickr' ],
            '1.0.1',
            true
        );

        // Hide default quantity input and localize settings
        wp_add_inline_style( 'flatpickr-css', "
            input.qty, .quantity { display: none !important; }
        " );

        wp_localize_script( 'sep-js', 'SEP_Settings', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'minDateOffset' => 2,
            'episodeRate'   => self::RATE,
            'targets'       => $this->targets,
        ] );
    }

    public function ajax_get_categories() {
        $terms = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
        $out   = [];
        foreach ( $terms as $t ) {
            $out[] = [ 'term_id' => $t->term_id, 'name' => $t->name ];
        }
        wp_send_json( $out );
    }

    public function render_fields() {
        if ( ! $this->is_target() ) {
            return;
        }
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
                <label for="sep_num_episodes"><strong><?php esc_html_e( 'Number of Episodes to Sponsor:', 'sponsor-episodes' ); ?></strong></label>
                <select id="sep_num_episodes" name="sep_num_episodes" required>
                    <option value=""><?php esc_html_e( 'Select…', 'sponsor-episodes' ); ?></option>
                    <?php for ( $i = 1; $i <= 10; $i++ ): ?>
    <option value="<?php echo $i; ?>">
        <?php 
            printf(
                esc_html__( '%d %s', 'sponsor-episodes' ),
                $i,
                ( $i === 1 ) ? esc_html__( 'Episode', 'sponsor-episodes' ) : esc_html__( 'Episodes', 'sponsor-episodes' )
            );
        ?>
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
        __( 'I agree to the %s', 'sponsor-episodes' ),
        '<a href="#tos-popup">' . esc_html__( 'Terms of Service', 'sponsor-episodes' ) . '</a>'
    );
    ?>                </label>
            </p>
        </div>
        <?php
    }

    public function validate( $passed, $product_id ) {
        if ( ! in_array( $product_id, $this->targets, true ) ) {
            return $passed;
        }
        if ( empty( $_POST['sep_num_episodes'] ) ) {
            wc_add_notice( __( 'Please select number of episodes.', 'sponsor-episodes' ), 'error' );
            return false;
        }
        $num  = absint( $_POST['sep_num_episodes'] );
        $dates = $_POST['sep_dates']      ?? [];
        $cats  = $_POST['sep_categories'] ?? [];

        if ( count( $dates ) < $num || count( $cats ) < $num ) {
            wc_add_notice( __( 'Please fill date & category for every episode.', 'sponsor-episodes' ), 'error' );
            return false;
        }
        foreach ( $dates as $d ) {
            if ( strtotime( sanitize_text_field( $d ) ) < strtotime( '+2 days' ) ) {
                wc_add_notice( __( 'Each date must be at least 48 hours in the future.', 'sponsor-episodes' ), 'error' );
                return false;
            }
        }
        if ( empty( $_POST['sep_tos'] ) ) {
            wc_add_notice( __( 'You must accept the Terms of Service.', 'sponsor-episodes' ), 'error' );
            return false;
        }
        return $passed;
    }

    public function add_cart_item_data( $cart_item_data, $product_id ) {
        if ( ! in_array( $product_id, $this->targets, true ) ) {
            return $cart_item_data;
        }
        $n    = absint( $_POST['sep_num_episodes'] );
        $dates = array_map( 'sanitize_text_field', $_POST['sep_dates']      ?? [] );
        $cats  = array_map( 'absint',             $_POST['sep_categories'] ?? [] );

        $cart_item_data['sep_num']    = $n;
        $cart_item_data['sep_dates']  = $dates;
        $cart_item_data['sep_cats']   = $cats;
        $cart_item_data['sep_price']  = self::RATE * $n;
        $cart_item_data['sep_key']    = wp_generate_uuid4();

        return $cart_item_data;
    }

    public function load_cart_item_data( $item, $values ) {
        foreach ( [ 'sep_num','sep_dates','sep_cats','sep_price','sep_key' ] as $key ) {
            if ( isset( $values[ $key ] ) ) {
                $item[ $key ] = $values[ $key ];
            }
        }
        return $item;
    }

    public function apply_price( $cart ) {
        foreach ( $cart->get_cart() as $item ) {
            if ( isset( $item['sep_price'] ) ) {
                $item['data']->set_price( floatval( $item['sep_price'] ) );
            }
        }
    }

    public function display_cart_meta( $meta, $item ) {
        if ( isset( $item['sep_num'] ) ) {
            $meta[] = [ 'key'=> __( 'Episodes', 'sponsor-episodes' ), 'value'=> $item['sep_num'] ];
            foreach ( $item['sep_dates'] as $i => $d ) {
                $meta[] = [ 'key'=> sprintf( __( 'Episode %d Date', 'sponsor-episodes' ), $i+1 ), 'value'=> esc_html($d) ];
                $term   = get_term( $item['sep_cats'][ $i ] );
                $meta[] = [ 'key'=> sprintf( __( 'Episode %d Category', 'sponsor-episodes' ), $i+1 ), 'value'=> $term? $term->name : '' ];
            }
        }
        return $meta;
    }

    public function save_order_meta( $line_item, $cart_key, $values ) {
        if ( isset( $values['sep_num'] ) ) {
            $line_item->add_meta_data( __( 'Episodes', 'sponsor-episodes' ), $values['sep_num'], true );
            foreach ( $values['sep_dates'] as $i => $d ) {
                $line_item->add_meta_data(
                    sprintf( __( 'Episode %d Date', 'sponsor-episodes' ), $i+1 ),
                    $d,
                    true
                );
                $term = get_term( $values['sep_cats'][ $i ] );
                $line_item->add_meta_data(
                    sprintf( __( 'Episode %d Category', 'sponsor-episodes' ), $i+1 ),
                    $term? $term->name : '',
                    true
                );
            }
        }
    }

    public function button_text() {
        return $this->is_target() ? __( 'Buy Now', 'sponsor-episodes' ) : null;
    }

    public function redirect_to_checkout( $url ) {
        if ( isset( $_REQUEST['add-to-cart'] ) && in_array( intval( $_REQUEST['add-to-cart'] ), $this->targets, true ) ) {
            return wc_get_checkout_url();
        }
        return $url;
    }
}

// Initialize
SEP_Plugin::instance();
