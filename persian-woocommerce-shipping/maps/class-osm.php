<?php
/**
 * OpenStreet map module
 * @since 4.0.4
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'PWS_Map_OSM' ) ) {
    return;
} // Stop if the class already exists

final class PWS_Map_OSM extends PWS_Map_Service {

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

	    wp_enqueue_style(
            'pws-map-osm-open-layers',
            PWS_URL . 'assets/maps/osm/open-layers/ol.css',
            [],
            PWS_VERSION
        );

        wp_enqueue_script(
            'pws-map-osm-open-layers',
            PWS_URL . 'assets/maps/osm/open-layers/ol.js',
            [ 'jquery' ],
            PWS_VERSION
        );

        wp_enqueue_script(
            'pws-map-osm-main',
            PWS_URL . 'assets/maps/osm/osm.main.js',
            [ 'jquery', 'pws-map-osm-open-layers' ],
            PWS_VERSION
        );

        wp_enqueue_script(
            'pws-map-osm',
            PWS_URL . 'assets/maps/osm/osm.js',
            [ 'pws-map-general', 'pws-map-osm-main' ],
            PWS_VERSION
        );

        wp_localize_script( 'pws-map-osm', 'pws_map_params', $this->get_map_params() );
    }

    public function shortcode_callback( $atts ) {
        $center_lat        = '51.33774439566025';
        $center_long       = '35.6997006457524';
        $user_has_location = false;
        $map_location      = [];

        if ( is_user_logged_in() ) {
            $map_location = get_user_meta( get_current_user_id(), 'pws_map_location', true );
        }

        if ( ! empty( $map_location ) ) {
            $center_lat        = $map_location[0];
            $center_long       = $map_location[1];
            $user_has_location = true;
        }

        $atts = shortcode_atts(
            [
                'min-width'         => '400px',
                'min-height'        => '400px',
                'marker-color'      => '#FF8330',
                'center-lat'        => $center_lat,
                'center-long'       => $center_long,
                'zoom'              => '6',
                'type'              => 'vector',
                'user-has-location' => $user_has_location,
                'marker-url'        => PWS_URL . 'assets/images/map-marker.png'
            ],
            $atts,
            'pws_map'
        );

        $min_width    = $atts['min-width'];
        $min_height   = $atts['min-height'];
        $marker_color = $atts['marker-color'];
        $center_lat   = $atts['center-lat'];
        $center_long  = $atts['center-long'];
        $zoom         = $atts['zoom'];
        $marker_url   = $atts['marker-url'];
        $generated_id = rand( 0, 300 );

        $gps_control = '';
        if ( ! is_admin() ) {
            $gps_control = '<input id="pws-map__osm__track" type="checkbox"/><label for="pws-map__osm__track"></label>';
        }

        return <<<MAP_TEMPLATE
                             <div class="pws-map__container"
                                 id="pws-map-osm-container-$generated_id" 
                                 data-min-width="$min_width" 
                                 data-min-height="$min_height"  
                                 data-marker-color="$marker_color"
                                 data-center-lat="$center_lat"
                                 data-center-long="$center_long"
                                 data-zoom="$zoom"    
                                 data-user-has-location="$user_has_location"
                                 data-marker-url="$marker_url"                         
                                 style="width: 100%; height: 400px"
                             >
                                 $gps_control
                                <div class="pws-map__osm__info" style="display:none;"></div>
                             </div>                   
         MAP_TEMPLATE;
    }
}
