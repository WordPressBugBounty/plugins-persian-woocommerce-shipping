<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'PWS_Map_Neshan' ) ) {
    return;
} // Stop if the class already exists

final class PWS_Map_Neshan extends PWS_Map_Service {

    /**
     * General
     * @string
     */
    private string $service_key;

    public function __construct() {
        parent::__construct();
        $this->set_service_key( PWS()->get_option( 'map.neshan_service_key', '' ) );
        $this->set_api_key( PWS()->get_option( 'map.neshan_api_key', '' ) );

        $this->set_map_params( 'api_key', base64_encode( $this->get_api_key() ) );
        $this->set_map_params( 'service_key', base64_encode( $this->get_service_key() ) );
    }

    public function get_service_key() {
        return $this->service_key;
    }

    public function set_service_key( $service_key ) {
        $this->service_key = $service_key;
    }

    public function init_hooks() {
        parent::init_hooks();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1000 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts( $hook_suffix = '' ) {
	    // Get the current screen, this method is only available in admin area
	    $screen = is_admin() ? get_current_screen() : null;

	    // Check if it's the WooCommerce Orders admin page
	    // The is_admin() condition is already on $screen variable, so I won't repeat it here
	    $is_wc_orders_admin_page = !empty($screen) && isset($screen->id) && $screen->id === 'shop_order';

	    // Check if it's the Checkout page
	    $is_checkout_page = ! is_admin() && function_exists( 'is_checkout' ) && is_checkout();
	    // Check if it's my account page
	    $is_my_account_page = is_account_page();

		// Check if it's in admin settings
	    // Return early if user is not on either of these pages
	    if ( ! ( $this->is_admin_tools_page() || $is_wc_orders_admin_page || $is_checkout_page || $is_my_account_page ) ) {
		    return;
	    }

	    parent::enqueue_scripts( $hook_suffix );

	    wp_enqueue_script(
            'pws-map-neshan-mapboxgl',
            PWS_URL . 'assets/maps/neshan/mapboxgl/mapboxgl.js',
            [],
            PWS_VERSION
        );

        wp_enqueue_style(
            'pws-map-neshan-mapboxgl',
            PWS_URL . 'assets/maps/neshan/mapboxgl/mapboxgl.css',
            [],
            PWS_VERSION
        );

        wp_enqueue_script(
            'pws-map-neshan',
            PWS_URL . 'assets/maps/neshan/neshan.js',
            [ 'pws-map-general', 'pws-map-neshan-mapboxgl', 'jquery' ],
            PWS_VERSION
        );

        wp_localize_script( 'pws-map-neshan', 'pws_map_params', $this->get_map_params() );
    }

    public function shortcode_callback( $atts ) {
        $center_lat        = '51.33774439566025';
        $center_long       = '35.6997006457524';
        $user_has_location = false;

        if ( is_user_logged_in() ) {
            $map_location = get_user_meta( get_current_user_id(), 'pws_map_location', true );

            if ( ! empty( $map_location ) ) {
                $center_lat        = $map_location[0];
                $center_long       = $map_location[1];
                $user_has_location = true;
            }
        }

        $atts = shortcode_atts(
            [
                'min-width'         => '400px',
                'min-height'        => '400px',
                'marker-color'      => '#FF8330',
                'center-lat'        => $center_lat,
                'center-long'       => $center_long,
                'poi'               => 'true',
                'traffic'           => 'false',
                'zoom'              => '12',
                'type'              => 'vector',
                'user-has-location' => $user_has_location
            ],
            $atts,
            'pws_map'
        );

        $min_width    = $atts['min-width'];
        $min_height   = $atts['min-height'];
        $marker_color = $atts['marker-color'];
        $center_lat   = $atts['center-lat'];
        $center_long  = $atts['center-long'];
        $poi          = $atts['poi'];
        $traffic      = $atts['traffic'];
        $zoom         = $atts['zoom'];
        $type         = PW()->get_options( 'map.neshan_type', $atts['type'] );
        $generated_id = rand( 0, 300 );

        return <<<MAP_TEMPLATE
                             <div class="pws-map__container pws-map__neshan"
                             id="pws-map-neshan-container-$generated_id" 
                             data-min-width="$min_width" 
                             data-min-height="$min_height"  
                             data-marker-color="$marker_color"
                             data-center-lat="$center_lat"
                             data-center-long="$center_long"
                             data-poi="$poi"
                             data-traffic="$traffic"
                             data-zoom="$zoom"
                             data-type="$type"
                             data-user-has-location="$user_has_location"
                             ></div>
         MAP_TEMPLATE;
    }
}
