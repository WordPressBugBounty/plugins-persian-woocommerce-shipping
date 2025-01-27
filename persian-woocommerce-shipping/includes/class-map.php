<?php
/**
 * Map implementation
 * The map configurator class
 * @since 4.0.4
 */

defined( 'ABSPATH' ) || exit;

class PWS_Map {

	public function __construct() {

		// Action hooks for admin
		add_action( 'add_meta_boxes', [ $this, 'add_map_order_meta_box' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'add_map_location_field_to_order_form' ] );

		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_map_location_order_meta' ] );

		add_action( 'woocommerce_order_details_after_customer_details', [ $this, 'my_account_show_map_callback' ], 100 );

		// Set active map
		$provider = PWS()->get_option( 'map.provider', 'OSM' );

		switch ( $provider ) {
			case 'neshan' :
				new PWS_Map_Neshan();
				break;
			case 'OSM' :
				new PWS_Map_OSM();
				break;
			case 'mapp' :
				new PWS_Map_Mapp();
				break;
			default:
				new PWS_Map_OSM();
		}

	}

	/**
	 * Check if map is enabled and showing in checkout
	 *
	 * @return bool
	 */
	public static function is_enable(): bool {
		return in_array( self::get_checkout_placement(), [ 'after_form', 'before_form' ] );
	}


	/**
	 * Get the map placement in checkout form
	 * Map feature is disabled by default
	 *
	 * @return string
	 */
	public static function get_checkout_placement(): string {
		return apply_filters( 'pws_map_checkout_placement', PWS()->get_option( 'map.checkout_placement', 'none' ) );
	}



	/**
	 * Map should only load in this shipping methods
	 * Contains a list of shipping methods
	 *
	 * @return array
	 */
	public static function get_shipping_methods(): array {
		return apply_filters( 'pws_map_enabled_shipping_methods', PWS()->get_option( 'map.shipping_methods', [ 'all_shipping_methods' ] ) );
	}

	/**
	 * Get the map location requirement status
	 *
	 * @return bool
	 */
	public static function required_location(): bool {
		$is_location_required = PWS()->get_option( 'map.required_location', 1 ) == 1;

		return apply_filters( 'pws_map_required_location', $is_location_required );
	}


	/**
	 * Save admin changed map location to the order
	 * @HPOS_COMPATIBLE
	 *
	 * @param $order_id int
	 */
	public function save_map_location_order_meta( int $order_id ) {
		$location_json  = $_POST['pws_map_location'] ?? '';
		$location_json  = stripslashes( $location_json );
		$location_array = json_decode( $location_json, true );

		if ( empty( $location_array ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( is_a( $order, 'WC_Order' ) ) {
			$order->update_meta_data( 'pws_map_location', $location_array );
			$order->save_meta_data();
		}

	}

	public function add_map_location_field_to_order_form( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		$current_screen   = get_current_screen();
		$is_edit_order    = isset( $order ) && is_a( $order, 'WC_Order' );
		$is_add_order     = $current_screen && 'shop_order' === $current_screen->post_type && 'add' === $current_screen->action;
		$default_location = apply_filters( 'pws_map_user_default_location', [ 'lat' => '35.6997006457524', 'long' => '51.33774439566025' ] );

		// There's only two condition which order will add pws_map_location field
		// Otherwise it should return nothing and abort
		if ( $is_edit_order ) {

			$map_location = $order->get_meta( 'pws_map_location' );

			if ( empty( $map_location ) ) {
				$map_location = $default_location;
			}

		}


		if ( $is_add_order ) {
			$map_location = $default_location;
		}

		if ( empty( $map_location ) || ! is_array( $map_location ) ) {
			return;
		}

		$map_location_json = wp_json_encode( $map_location, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		echo <<<LOCATION_INPUT
                   <div class="custom-hidden-input-field">
                       <input type="hidden"
                       id="pws_map_location"
                        name="pws_map_location"
                        value="$map_location_json" 
                        >
                    </div>
        LOCATION_INPUT;
	}

	public function add_map_order_meta_box() {
		add_meta_box( 'pws-map-order-meta-box', __( 'نقشه' ), [ $this, 'map_order_meta_box_callback', ], [
			'woocommerce_page_wc-orders',
			wc_get_page_screen_id( 'shop-order' ),
		], 'advanced', 'high' );
	}

	/**
	 *
	 * Show map in admin order area
	 *
	 * @HPOS_COMPATIBLE
	 *
	 * @param WC_Order|WP_Post $post_or_order_object
	 *
	 * @return void
	 *
	 */
	public function map_order_meta_box_callback( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$map_location_meta_value = PWS()->get_map_order_location( $order );
		$default_location        = apply_filters( 'pws_map_user_default_location', [ 'lat' => '35.6997006457524', 'long' => '51.33774439566025' ] );
		$enable_edit             = '';

		if ( empty( $map_location_meta_value ) ) {
			$enable_edit = 'checked';
		}

		$center_lat  = $map_location_meta_value['lat'] ?? $default_location['lat'];
		$center_long = $map_location_meta_value['long'] ?? $default_location['long'];
		$map         = do_shortcode( "[pws_map center-lat='$center_lat' center-long='$center_long' min-width='200px' min-height='200px']" );

		$neshan_share_link = PWS()->get_map_share_link( $center_lat, $center_long );
		$neshan_logo_link  = PWS_URL . 'assets/images/neshan.png';

		$balad_share_link = PWS()->get_map_share_link( $center_lat, $center_long, 'balad' );
		$balad_logo_link  = PWS_URL . 'assets/images/balad.png';


		echo <<<ORDER_MAP_SECTION
                    <div class="pws-order__map__shipping_section">
                        <div class="value map">$map</div>
                        <div class="info">
                            <div class="pws-order__map__coords"></div>
                            <div class="pws-order__map__shipping__information"></div>
                             
                            <div class="pws-order__map__share__links__container">
                                 <span class="pws-order__map__share__links__custom__alert">لینک مسیریابی سفارش، با موفقیت کپی شد!</span>
                                 <div class="pws-order__map__neshan__share__link" title="برای کپی، کلیک کنید."><img src="$neshan_logo_link" alt="neshan"><span class="url">$neshan_share_link</span></div>
                                 <div class="pws-order__map__balad__share__link" title="برای کپی، کلیک کنید."><img src="$balad_logo_link" alt="balad"><span class="url">$balad_share_link</span></div>
                            </div>
                        </div>  
                        <div class="action">
                            <input id="pws-map-admin-edit" type="checkbox" $enable_edit/>
                            <label for="pws-map-admin-edit" class="button">ویرایش نقشه</label>
                        </div>
                    </div>
            ORDER_MAP_SECTION;
	}

	/**
	 * Show map only in my-account/orders
	 * $name is based on two type of addresses in the address area
	 * Billing, Shipping
	 */

	public function my_account_show_map_callback( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! is_a( $order, 'WC_Order' ) || empty( $order ) ) {
			return;
		}

		$customer_id = get_current_user_id();

		if ( empty( $customer_id ) ) {
			return;
		}

		$map_location_meta_value = PWS()->get_map_order_location( $order );

		if ( ! isset( $map_location_meta_value['lat'], $map_location_meta_value['long'] ) ) {
			echo 'مختصات ارسال سفارش ثبت نشده.';

			return;
		}
		$center_lat  = $map_location_meta_value['lat'];
		$center_long = $map_location_meta_value['long'];

		$map = do_shortcode( "[pws_map center-lat='$center_lat' center-long='$center_long' min-width='200px' min-height='200px']" );

		echo <<<MYACCOUNT_MAP_SECTION
                    <div class="pws-account__map__shipping_section" style="width: 100%;">
                        <div class="value">$map</div>  
                    </div>
            MYACCOUNT_MAP_SECTION;
	}

}

new PWS_Map();