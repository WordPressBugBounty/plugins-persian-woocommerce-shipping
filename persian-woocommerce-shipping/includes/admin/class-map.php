<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

class PWS_Settings_Map {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_map_order_meta_box' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address',
            [ $this, 'add_map_location_field_to_order_form' ] );
        add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_map_location_order_meta' ] );
    }

    /**
     * Save admin changed map location to the order
     * @HPOS_COMPATIBLE
     *
     * @param $order_id int
     */
    public function save_map_location_order_meta( $order_id ) {
        $location_json  = $_POST['pws_map_location'] ?? '';
        $location_array = json_decode( $location_json, true );

        if ( empty( $location_array ) ) {
            return;
        }

        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order = wc_get_order( $order_id );
            if ( ! is_a( $order, 'WC_Order' ) ) {
                return;
            }
            $order->update_meta_data( 'pws_map_location', $location_array );
        } else {
            update_post_meta( $order_id, 'pws_map_location', $location_array );
        }

    }

    function add_map_location_field_to_order_form( $order ) {

        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $map_location = $order->get_meta( 'pws_map_location' );
        } else {
            $map_location = get_post_meta( $order->get_id(), 'pws_map_location', true );
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
        add_meta_box(
            'pws-map-order-meta-box',
            __( 'نقطه ارسال سفارش' ),
            [ $this, 'map_order_meta_box_callback' ],
            [ 'woocommerce_page_wc-orders', wc_get_page_screen_id( 'shop-order' ) ],
            'side',
            'high'
        );
    }

    /**
     *
     * Show map in admin area
     *
     * @HPOS_COMPATIBLE
     *
     *
     */
    public function map_order_meta_box_callback() {
        global $post;
        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order_id                = (int) $_GET['id'] ?? false;
            $order                   = wc_get_order( $order_id );
            $map_location_meta_value = $order->get_meta( 'pws_map_location' );
        } else {
            $map_location_meta_value = get_post_meta( $post->ID, 'pws_map_location', true );
        }

        /**
         *[
         *  0 => latitude,
         *  1 => longitude,
         * ]
         */
        if ( empty( $map_location_meta_value ) ) {
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

new PWS_Settings_Map();
