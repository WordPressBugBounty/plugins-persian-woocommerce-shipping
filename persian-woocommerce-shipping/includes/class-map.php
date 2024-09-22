<?php
/**
 * Map implementation
 * The map configurator class
 * @since 4.0.4
 */

defined( 'ABSPATH' ) || exit;

class PWS_Map {

	/**
	 * General
	 * @bool
	 */
	private bool $enabled;

	public function __construct() {
		$this->enabled = PWS()->get_option( 'map.enable', false );

		// If the map is not enabled, the whole feature should be disabled
		if ( ! $this->enabled ) {
			return;
		}

		// Action hooks for admin
		add_action( 'add_meta_boxes', [ $this, 'add_map_order_meta_box' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address',
			[ $this, 'add_map_location_field_to_order_form' ] );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_map_location_order_meta' ] );

		// Set active map
		$provider = PWS()->get_option( 'map.provider', 'osm' );

		switch ( $provider ) {
			case 'neshan' :
				new PWS_Map_Neshan();
				break;
			case 'osm' :
				new PWS_Map_OSM();
				break;
			default:
				new PWS_Map_OSM();
		}
	}

	/**
	 * Save admin changed map location to the order
	 * @HPOS_COMPATIBLE
	 *
	 * @param $order_id int
	 */
	public function save_map_location_order_meta( int $order_id ) {
		$location_json  = $_POST['pws_map_location'] ?? '';
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


	public function add_map_location_field_to_order_form( $order ) {

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$map_location = $order->get_meta( 'pws_map_location' );

		if ( empty( $map_location ) || ! is_array( $map_location ) ) {
			return;
		}

		echo <<<LOCATION_INPUT
                   <div class="custom-hidden-input-field">
                       <input type="hidden"
                       id="pws_map_location"
                        name="pws_map_location"
                        value="[$map_location[0], $map_location[1]]" 
                        >
                    </div>
        LOCATION_INPUT;
	}

	public function add_map_order_meta_box() {
		add_meta_box( 'pws-map-order-meta-box', __( 'نقطه ارسال سفارش' ), [
			$this,
			'map_order_meta_box_callback',
		], [
			'woocommerce_page_wc-orders',
			wc_get_page_screen_id( 'shop-order' ),
		], 'side', 'high' );
	}

	/**
	 *
	 * Show map in admin area
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

		$map_location_meta_value = $order->get_meta( 'pws_map_location' );

		/**
		 *[
		 *  0 => latitude,
		 *  1 => longitude,
		 * ]
		 */
		if ( empty( $map_location_meta_value ) ) {
			echo 'مختصات ارسال سفارش ثبت نشده.';

			return;
		}

		$center_lat  = (string) $map_location_meta_value[0];
		$center_long = (string) $map_location_meta_value[1];
		$map         = do_shortcode( "[pws_map center-lat='$center_lat' center-long='$center_long' min-width='200px' min-height='200px']" );

		echo <<<ORDER_MAP_SECTION
                    <div class="pws-order__map__shipping_section">
                        <div class="value">$map</div>  
                        <div>
                            <input id="pws-map-admin-edit" type="checkbox"/>
                            <label for="pws-map-admin-edit">ویرایش نقشه</label>
                        </div>
                    </div>
            ORDER_MAP_SECTION;
	}
}

new PWS_Map();