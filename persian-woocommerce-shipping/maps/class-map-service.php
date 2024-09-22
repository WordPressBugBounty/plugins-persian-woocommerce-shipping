<?php
/**
 * Map implementation
 * The map configurator class
 * @since 4.0.4
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

class PWS_Map_Service {

    /**
     * General
     * @string
     */
    protected string $provider;

    /**
     * General
     * @string
     */
    protected string $api_key;

    /**
     * General
     * @string
     */
    protected string $checkout_enable;

    /**
     * Specific values based on each api will be gathered in this property
     * @array
     */
    protected array $map_params;

    /**
     * Used to attach map placement in specific hook
     * General
     * @string
     */
    protected string $checkout_placement;

    /**
     * Force user to select location or not
     * @string
     */
    protected string $required_location;

    public function __construct() {

        // Set general options as class properties
        $this->checkout_enable    = PWS()->get_option( 'map.checkout_enable', true );
        $this->checkout_placement = PWS()->get_option( 'map.checkout_placement', 'after_form' );
        $this->required_location  = PWS()->get_option( 'map.required_location', true );

        $this->set_map_params( 'is_admin', is_admin() );
        $this->set_map_params( 'checkout_placement', $this->checkout_placement );
        $this->set_map_params( 'pws_url', PWS_URL );

        // Action and Filter WordPress Integration
        $this->init_hooks();
    }

    public function init_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        // Enable shortcode as [pws_map]
        add_action( 'init', [ $this, 'add_map_shortcode' ], 100 );
        // Add hidden inputs to the checkout form
        add_filter( 'woocommerce_checkout_fields', [ $this, 'add_map_location_field_to_checkout_form' ], 100 );
        add_filter( 'woocommerce_checkout_get_value', [ $this, 'disable_map_location_field_get_value' ], 101, 2 );

        // Save the location order meta
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_map_location_meta' ], 100 );

        // Validate the location if its required
        if ( $this->required_location ) {
            add_action( 'woocommerce_checkout_process', [ $this, 'validate_map_location_field' ] );
        }

        if ( $this->checkout_enable ) {

            switch ( $this->checkout_placement ) {
                case 'before_form':
                    $hook_names = [
                        'woocommerce_before_checkout_billing_form',
                        'woocommerce_before_checkout_shipping_form'
                    ];
                    break;
                case 'after_form':
                    $hook_names = [
                        'woocommerce_after_checkout_billing_form',
                        'woocommerce_after_checkout_shipping_form'
                    ];
                    break;
                default:
                    $hook_names = [
                        'woocommerce_after_checkout_billing_form',
                        'woocommerce_after_checkout_shipping_form'
                    ];
            }

            foreach ( $hook_names as $hook_name ) {
                add_action( $hook_name, [ $this, 'do_map_shortcode' ], 1000 );
            }

        }
    }

    /**
     * General styles and scripts
     * @return void
     */
    public function enqueue_scripts( $hook_suffix = '' ) {
        wp_enqueue_script(
            'pws-map-general',
            PWS_URL . 'assets/maps/map.js',
            [ 'jquery' ],
            PWS_VERSION
        );
    }

    /**
     * The map shortcode pure html
     * @return string
     */
    public function shortcode_callback( $atts ) {
        return "<div class='pws-map__container'></div>";
    }

    /**
     * Create shortcode from the shortcode() template
     * @return void
     */
    public function add_map_shortcode() {
        add_shortcode( 'pws_map', [ $this, 'shortcode_callback' ] );
    }

    /**
     * Method to run map shortcode
     *
     * @return void
     */
    public function do_map_shortcode() {
        echo do_shortcode( '[pws_map]' );
    }

    /**
     * Add hidden input to store user location selection latitude and longitude.
     *
     * @return array
     */
    public function add_map_location_field_to_checkout_form( $fields ) {
        $fields['order']['pws_map_location'] = [
            'type'       => 'hidden',
            'class'      => [ 'hidden' ],
            'label'      => '',
            'novalidate' => true,
        ];

        return $fields;
    }

    /**
     * Convert array to string manually to prevent error in woocommerce field processing
     *
     * @param mixed $value
     * @param string input
     *
     * @return string
     */
    public function disable_map_location_field_get_value( $value, $input ) {

        if ( $input !== 'pws_map_location' ) {
            return $value;
        }

        return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    /**
     * If the required location is enabled, the pws_map_location would have value
     *
     * @return void
     */
    public function validate_map_location_field() {

        if ( empty( $_POST ) || empty( $_POST['pws_map_location'] ) ) {
            wc_add_notice( __( 'لطفا موقعیت خود را روی نقشه انتخاب کنید.' ), 'error' );

            return;
        }

        /**
         * We are handling lat , long as [0,1] in pws_map_location from javascript,
         * it always should be presented as 0: latitude , 1: longitude.
         */
        $map_location        = json_decode( $_POST['pws_map_location'], true );
        $map_location_exists = ! empty( $map_location[0] ) && ! empty( $map_location[1] );

        if ( $map_location_exists && ! $this->is_iran_location( $map_location[0], $map_location[1] ) ) {
            wc_add_notice( __( 'لطفا مکان خود را در ایران انتخاب کنید.' ), 'error' );

            return;
        }

    }

    /**
     * Method to check given coordinates lies in iran.
     *
     * @param float $latitude
     * @param float $longitude
     *
     * @return bool
     */
    public function is_iran_location( float $latitude, float $longitude ): bool {
        $iran_boundary = [
            'min_latitude'  => 25.078237,
            'max_latitude'  => 39.777672,
            'min_longitude' => 44.032688,
            'max_longitude' => 63.322166
        ];
        /* Check if coordinates not in the area! */
        $invalid_latitude  = $latitude < $iran_boundary['min_latitude'] || $latitude > $iran_boundary['max_latitude'];
        $invalid_longitude = $longitude < $iran_boundary['min_longitude'] || $longitude > $iran_boundary['max_longitude'];

        if ( $invalid_longitude || $invalid_latitude ) {
            return false;
        }

        return true;
    }

    /**
     * Save the order map location
     * @HPOS_COMPATIBLE
     *
     * @param $order WC_Order
     */
    public function save_map_location_meta( $order ) {
        $map_location_json  = $_POST['pws_map_location'] ?? '';
        $map_location_array = json_decode( $map_location_json, true );
        if ( empty( $map_location_array ) ) {
            return;
        }

        $order->update_meta_data( 'pws_map_location', $map_location_array );

        // Also update this meta for user
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'pws_map_location', $map_location_array );
        }
    }

    public function get_provider() {
        return $this->provider;
    }

    public function set_provider( $provider ) {
        $this->provider = $provider;
    }

    public function get_api_key() {
        return $this->api_key;
    }

    public function set_api_key( $key ) {
        $this->api_key = $key;
    }

    public function get_map_params() {
        return $this->map_params;
    }

    public function set_map_params( $key, $value ) {
        $this->map_params[ $key ] = $value;
    }

	public function is_admin_tools_page() {
		return is_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'pws-tools';
	}

}
