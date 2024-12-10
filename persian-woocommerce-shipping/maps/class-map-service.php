<?php
/**
 * Map implementation
 * The map configurator class
 * @since 4.0.4
 */

use Automattic\WooCommerce\Blocks\Package;
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
		$this->checkout_placement = PWS()->get_option( 'map.checkout_placement', 'none' );
		$this->required_location  = PWS()->get_option( 'map.required_location', true );

		$this->set_map_params( 'ORS_token', PWS()->get_option( 'map.ORS_token', true ) );
		$this->set_map_params( 'is_admin', is_admin() );
		$this->set_map_params( 'checkout_placement', $this->checkout_placement );
		$this->set_map_params( 'pws_url', PWS_URL );

		add_action( 'wp_loaded', function () {
			// The rest_url() depends on WordPress environment
			$this->set_map_params( 'rest_url', rest_url( 'pws/map/' ) );
		} );

		// Action and Filter WordPress Integration
		$this->init_hooks();

	}

	public function init_hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Rest API registration
		add_action( 'rest_api_init', [ $this, 'register_rest_api' ] );

		// Enable shortcode as [pws_map]
		add_action( 'init', [ $this, 'add_map_shortcode' ], 100 );

		// Add hidden inputs to the checkout form
		add_filter( 'woocommerce_checkout_fields', [ $this, 'add_map_location_field_to_checkout_form' ], 100 );
		add_filter( 'woocommerce_checkout_get_value', [ $this, 'disable_map_location_field_get_value' ], 101, 2 );

		// Save the location order meta
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_map_location_meta' ], 100 );

		// Filters for customization
		add_filter( 'pws_map_store_marker_image', [ $this, 'store_marker_image' ] );
		add_filter( 'pws_map_user_marker_image', [ $this, 'user_marker_image' ] );
		add_filter( 'pws_map_user_marker_color', [ $this, 'user_marker_color' ] );
		add_filter( 'pws_map_store_marker_color', [ $this, 'store_marker_image' ] );
		add_filter( 'pws_map_user_default_location', [ $this, 'user_default_location' ] );
		add_filter( 'pws_map_store_default_location', [ $this, 'store_default_location' ] );

		// Validate the location if its required
		if ( $this->required_location ) {
			add_action( 'woocommerce_checkout_process', [ $this, 'validate_map_location_field' ] );
		}

		if ( $this->checkout_placement !== 'none' ) {

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
	 * Callback for pws_user_marker_filter which shows on map
	 */
	public function user_marker_image( $input ) {
		if ( ! empty( $input ) ) {
			return $input;
		}

		return PWS_URL . 'assets/images/map-marker.png';
	}

	public function user_marker_color( $input ) {
		if ( ! empty( $input ) ) {
			return $input;
		}

		return '#FF8330';
	}
	/**
	 * Callback for pws_user_marker_co
	 */
	/**
	 * Callback for pws_store_marker_filter to pass custom icon as store marker
	 */
	public function store_marker_image( $input ) {
		if ( ! empty( $input ) ) {
			return $input;
		}

		return PWS_URL . 'assets/images/store-marker.png';
	}

	public function user_default_location( $input ) {
		if ( ! empty( $input ) ) {
			return $input;
		}

		return [ 'lat' => '35.6997006457524', 'long' => '51.33774439566025' ];
	}

	public function store_default_location( $input ) {
		if ( ! empty( $input ) ) {
			return $input;
		}

		return [ 'lat' => '35.6997006457524', 'long' => '51.33774439566025' ];
	}

	/**
	 * General styles and scripts
	 * @return bool
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		global $post;

		// Get the current screen, this method is only available in admin area
		$screen = is_admin() ? get_current_screen() : null;

		// Check if pws_map shortcode is executing in current post
		$post_has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'pws_map' );

		// Check if it's the WooCommerce Orders admin page
		// The is_admin() condition is already on $screen variable, so I won't repeat it here
		$is_wc_orders_admin_page = ! empty( $screen ) && isset( $screen->id ) && ( $screen->id === 'shop_order' || $screen->id === 'woocommerce_page_wc-orders' );

		// Check if it's the Checkout page
		$is_checkout_page = ! is_admin() && function_exists( 'is_checkout' ) && is_checkout();

		// Check if it's my account page
		$is_my_account_page = is_account_page();

		// Validate the project page
		$is_valid_page = $this->is_admin_tools_page() || $is_wc_orders_admin_page || $is_checkout_page || $is_my_account_page || $post_has_shortcode;

		// Return early if user is not on either of these pages
		if ( ! $is_valid_page ) {
			return false;
		}


		wp_enqueue_script( 'pws-map-leaflet', PWS_URL . 'assets/maps/leaflet/leaflet.js', [], PWS_VERSION );

		wp_enqueue_style( 'pws-map-leaflet', PWS_URL . 'assets/maps/leaflet/leaflet.css', [], PWS_VERSION );

		wp_enqueue_script( 'pws-map-general', PWS_URL . 'assets/maps/map.js', [ 'jquery' ], PWS_VERSION );

		return true;

	}

	public function is_admin_tools_page() {
		return is_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'pws-tools';
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

		return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * If the required location is enabled, the pws_map_location would have value
	 *
	 * @return void
	 */
	public function validate_map_location_field() {
		$required_location = ( isset( $_POST ) && ! empty( $_POST['pws_map_required_location'] ) && $_POST['pws_map_required_location'] === '1' );

		if ( ( ! isset( $_POST ) || ( empty( $_POST['pws_map_location'] ) ) && $required_location ) ) {
			wc_add_notice( __( 'لطفا موقعیت خود را روی نقشه انتخاب کنید.' ), 'error' );

			return;
		}

		// If it's not required, then it'll pass the validation!
		if ( ! $required_location ) {
			return;
		}

		// We need to fix json first, the JSON.stringify and input value will create html with special characters
		$map_location_json = $_POST['pws_map_location'] ?? '';
		$map_location_json = stripslashes( $map_location_json );
		$map_location      = json_decode( $map_location_json, true );

		if ( ! empty( json_last_error() ) || empty( $map_location ) ) {
			wc_add_notice( __( 'مقصد تعیین شده صحیح نمی باشد. لطفاً موقعیت دیگری را روی نقشه انتخاب کنید.' ), 'error' );
			error_log( 'PWS error parsing JSON : ' . json_last_error_msg() );

			return;
		}

		$map_location_exists = ! empty( $map_location['lat'] ) && ! empty( $map_location['long'] );

		if ( $map_location_exists && ! $this->is_iran_location( $map_location['lat'], $map_location['long'] ) ) {
			wc_add_notice( __( 'موقعیت مقصد خود را روی نقشه، در ایران ثبت کنید.' ), 'error' );

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

		// We need to fix json first, the JSON.stringify and input value will create html with special characters
		$map_location_json  = $_POST['pws_map_location'] ?? '';
		$map_location_json  = stripslashes( $map_location_json );
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

	/**
	 * Register map api
	 */
	public function register_rest_api() {
		register_rest_route( 'pws/map', '/distance/', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'calculate_user_distance' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'user_coords' => [
					'required' => true,
				],
				'type'        => [
					'required' => true,
				]
			],
		] );

	}


	public function calculate_user_distance( WP_REST_Request $request ): WP_REST_Response {
		header( 'Content-Type: application/json; charset=utf-8' );

		$user_coords = $request->get_param( 'user_coords' );
		$type        = $request->get_param( 'type' );

		if ( empty( $user_coords ) || empty( $type ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'پارامترهای نامعتبر برای محاسبه فاصله'
			], 400 );
		}

		$user_coords = json_decode( $user_coords, true );

		$cache_key = 'order_distance_' . $user_coords['lat'] . '_' . $user_coords['long'];
		$distance  = get_transient( $cache_key );

		if ( ! empty( $distance ) ) {

			return new WP_REST_Response( [
				'success'  => true,
				'distance' => $distance,
				'message'  => 'فاصله با موفقیت دریافت شد',
			], 200 );

		}

		switch ( $type ) {
			case 'direct' :
				$distance = $this->calculate_direct_distance( $user_coords );
				break;
			case 'real' :
				$distance = $this->calculate_real_distance( $user_coords );
				break;
			case 'default':
				$distance = $this->calculate_direct_distance( $user_coords );
		}

		return new WP_REST_Response( [
			'success'  => true,
			'distance' => $distance,
			'message'  => 'فاصله با موفقیت محاسبه شد',
		], 200 );
	}


	/**
	 * Calculate direct distance between user and store
	 *
	 * @param array $user_coords
	 *
	 * @return string
	 */
	public function calculate_direct_distance( array $user_coords ): string {
		$store_coords = PWS()->get_option( 'map.store_location', '' );

		if ( empty( $store_coords ) || empty( $user_coords ) ) {
			return '';
		}

		$user_lat = $user_coords['lat'];
		$user_lng = $user_coords['long'];

		$store_coords = json_decode( $store_coords, true );
		$store_lat    = $store_coords['lat'];
		$store_lng    = $store_coords['long'];

		// Earth's radius in kilometers
		$earth_radius = 6371;

		// Convert degrees to radians
		$user_lat_rad = deg2rad( $user_lat );
		$user_lng_rad = deg2rad( $user_lng );

		$store_lat_rad = deg2rad( $store_lat );
		$store_lng_rad = deg2rad( $store_lng );

		// Haversine formula to calculate the distance between two points
		$dlat = $store_lat_rad - $user_lat_rad;
		$dlng = $store_lng_rad - $user_lng_rad;

		$a = sin( $dlat / 2 ) * sin( $dlat / 2 ) + cos( $user_lat_rad ) * cos( $store_lat_rad ) * sin( $dlng / 2 ) * sin( $dlng / 2 );
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		// Calculate the distance in meters
		$distance = $earth_radius * $c * 1000;

		if ( $distance < 1000 ) {
			$distance = number_format( $distance, 2 ) . ' متر';
		} else {
			$distance = number_format( $distance / 1000, 2 ) . ' کیلومتر';
		}

		$distance_display = sprintf( 'فاصله %1$s تا کاربر: %2$s', 'شعاعی (مستقیم)', $distance );

		// Set cache of distance
		$cache_key = 'order_distance_' . $user_lat . '_' . $user_lng;
		set_transient( $cache_key, $distance_display, 24 * 60 * 60 * 30 ); // 30 days cache expiration

		return $distance_display;
	}


	public function calculate_real_distance( array $user_coords ): string {
		$store_coords = PWS()->get_option( 'map.store_location', '' );

		if ( empty( $store_coords ) || empty( $user_coords ) ) {
			return '';
		}

		$user_lat = $user_coords['lat'];
		$user_lng = $user_coords['long'];

		$store_coords = json_decode( $store_coords, true );
		$store_lat    = $store_coords['lat'];
		$store_lng    = $store_coords['long'];

		$url     = 'https://api.openrouteservice.org/v2/directions/driving-car';
		$api_key = PWS()->get_option( 'map.ORS_token', '' );

		$body = [
			'coordinates' => [
				[ $user_lng, $user_lat ],
				[ $store_lng, $store_lat ]
			]
		];

		$args = [
			'method'  => 'POST',
			'body'    => json_encode( $body ),
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key
			],
		];

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return 'خطا: ' . $response->get_error_message();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data['routes'][0]['summary']['distance'] ) ) {
			return 'خطا در محاسبه فاصله';
		}

		$distance = $data['routes'][0]['summary']['distance'];

		if ( $distance < 1000 ) {
			$distance = number_format( $distance, 2 ) . ' متر';
		} else {
			$distance = number_format( $distance / 1000, 2 ) . ' کیلومتر';
		}

		$distance_display = sprintf( 'فاصله %1$s تا کاربر: %2$s', 'مسیریابی (واقعی)', $distance );

		// Set cache of distance
		$cache_key = 'order_distance_' . $user_lat . '_' . $user_lng;
		set_transient( $cache_key, $distance_display, 24 * 60 * 60 * 30 ); // 30 days cache expiration

		return $distance_display;
	}

}


