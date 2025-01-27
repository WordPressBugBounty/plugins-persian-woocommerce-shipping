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

		if ( ! parent::enqueue_scripts( $hook_suffix ) ) {
			return false;
		};

		wp_enqueue_script( 'pws-map-OSM', PWS_URL . 'assets/maps/osm/osm-leaflet.js', [ 'pws-map-general', 'pws-map-leaflet', 'jquery' ], PWS_VERSION );

		wp_localize_script( 'pws-map-OSM', 'pws_map_params', $this->get_map_params() );

		return true;
	}

	public function shortcode_callback( $atts ) {
		$store_marker_enable = PWS()->get_option( 'map.store_marker_enable', true );

		// the main thing here is json! in the default settings we have to convert array.
		$store_location = '{"lat":"35.6997006457524","long":"51.33774439566025"}';

		if ( is_admin() || $store_marker_enable ) {
			$store_location = PWS()->get_option( 'map.store_location', $store_location );
		}

		$store_location = json_decode( $store_location, true );
		$store_lat      = $center_lat = $store_location['lat'] ?? '35.6997006457524';
		$store_long     = $center_long = $store_location['long'] ?? '51.33774439566025';

		$store_marker_image    = apply_filters( 'pws_map_store_marker_image', PWS_URL . 'assets/images/store-marker.png' );
		$store_draw_line_color = apply_filters( 'pws_map_store_draw_line_color', 'green' );

		$show_distance_type = PWS()->get_option( 'map.store_calculate_distance', 'none' );
		$user_marker_image  = apply_filters( 'pws_map_user_marker_image', PWS_URL . 'assets/images/map-marker.png' );
		$user_marker_color  = apply_filters( 'pws_map_user_marker_color', '#FF8330' );
		$user_has_location  = false;
		$required_location  = PWS()->get_option( 'map.required_location', true );
		$map_location       = [];

		if ( is_user_logged_in() && ! $this->is_admin_tools_page() ) {
			$map_location = get_user_meta( get_current_user_id(), 'pws_map_location', true );
		}

		if ( isset( $map_location['lat'], $map_location_['long'] ) ) {

			$center_lat        = $map_location['lat'];
			$center_long       = $map_location['long'];
			$user_has_location = true;

		}

		$atts = shortcode_atts( [
			'min-width'          => '400px',
			'min-height'         => '400px',
			'width'              => '100%',
			'user-marker-color'  => $user_marker_color,
			'center-lat'         => $center_lat,
			'center-long'        => $center_long,
			'store-lat'          => $store_lat,
			'store-long'         => $store_long,
			'zoom'               => '6',
			'type'               => 'vector',
			'user-has-location'  => $user_has_location,
			'user-marker-url'    => $user_marker_image,
			'store-marker-url'   => $store_marker_image,
			'show-distance-type' => $show_distance_type

		], $atts, 'pws_map' );

		$min_width        = $atts['min-width'];
		$min_height       = $atts['min-height'];
		$marker_color     = $atts['user-marker-color'];
		$center_lat       = $atts['center-lat'];
		$center_long      = $atts['center-long'];
		$store_lat        = $atts['store-lat'];
		$store_long       = $atts['store-long'];
		$zoom             = $atts['zoom'];
		$user_marker_url  = $atts['user-marker-url'];
		$store_marker_url = $atts['store-marker-url'];
		$width            = $atts['width'];

		$generated_id = rand( 0, 300 );

		$enabled_shipping_methods = PWS_Map::get_shipping_methods();

		// In this situation, map always loads in all shipping methods
		$enabled_shipping_methods = wp_json_encode( $enabled_shipping_methods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return <<<MAP_TEMPLATE
                             <div class="pws-map__container"
                                 id="pws-map-OSM-container-$generated_id" 
                                 data-min-width="$min_width" 
                                 data-min-height="$min_height"  
                                 data-user-marker-color="$marker_color"
                                 data-center-lat="$center_lat"
                                 data-center-long="$center_long"
                                 data-store-lat="$store_lat"
                                 data-store-long="$store_long"
                                 data-zoom="$zoom"
                                 data-user-has-location="$user_has_location"
                                 data-user-marker-url="$user_marker_url"
                                 data-store-marker-url="$store_marker_url"
                                 data-store-marker-enable="$store_marker_enable"
                                 data-store-draw-line-color="$store_draw_line_color"
                                 data-show-distance-type="$show_distance_type"                         
                                 style="width: $width; height: 400px"
                             >
                                 
                                <div class="pws-map__OSM__info" style="display:none;"></div>
                                <input type="hidden" value='$enabled_shipping_methods' name="pws_map_enabled_shipping_methods">
                                <input type="hidden" value='$required_location' name="pws_map_required_location">
                             </div>
         MAP_TEMPLATE;

	}
}
