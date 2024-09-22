<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

class PWS_Version {

	const VERSION_KEY = 'pws_version';

	public function __construct() {

		$installed_version = get_option( self::VERSION_KEY, '2.1.6' );

		if ( in_array( $installed_version, [ '3.0.10', '3.0.11' ] ) ) {
			$installed_version = '3.1.0';
		}

		if ( $installed_version == PWS_VERSION ) {
			return;
		}

		if ( 'yes' === get_transient( 'pws_admin_updating' ) ) {
			return;
		}

		set_transient( 'pws_admin_updating', 'yes', MINUTE_IN_SECONDS * 10 );

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		$installed_version = (int) str_replace( '.', '', $installed_version );
		$pws_version       = (int) str_replace( '.', '', PWS_VERSION );

		for ( $version = $installed_version; $version <= $pws_version; $version ++ ) {
			if ( method_exists( $this, "update_{$version}" ) ) {
				$this->{"update_{$version}"}();
			}
		}

		delete_transient( 'pws_admin_updating' );

		update_option( self::VERSION_KEY, PWS_VERSION );
	}

	public function update_219() {
		global $wpdb;

		// Update zone methods

		$table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';

		$sql = "SELECT * FROM {$table}";

		$methods = $wpdb->get_results( $sql );

		foreach ( $methods as $method ) {

			if ( $method->method_id != 'WC_Tapin_Method' ) {
				continue;
			}

			$settings = get_option( "woocommerce_WC_Tapin_Method_{$method->instance_id}_settings" );

			$new_method = 'Tapin_Pishtaz_Method';

			unset( $settings['post_type'] );

			if ( isset( $settings['pay_type'] ) ) {
				unset( $settings['pay_type'] );
			}

			if ( isset( $settings['default_weight'] ) ) {
				unset( $settings['default_weight'] );
			}

			if ( isset( $settings['package_weight'] ) ) {
				unset( $settings['package_weight'] );
			}

			if ( isset( $settings['post_gateway'] ) ) {
				unset( $settings['post_gateway'] );
			}

			$wpdb->update( $table, [
				'method_id' => $new_method,
			], [
				'zone_id'     => $method->zone_id,
				'instance_id' => $method->instance_id,
			] );

			update_option( "woocommerce_{$new_method}_{$method->instance_id}_settings", $settings );
			delete_option( "woocommerce_WC_Tapin_Method_{$method->instance_id}_settings" );
		}

		// Update order shipping method

		$order_items    = $wpdb->prefix . 'woocommerce_order_items';
		$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

		$sql = "SELECT * FROM {$wpdb->postmeta} WHERE `meta_key` = 'tapin_method'";

		$post_meta = $wpdb->get_results( $sql );

		$post_meta = wp_list_pluck( $post_meta, 'meta_value', 'post_id' );

		if ( ! count( $post_meta ) ) {
			return;
		}

		$order_ids = implode( ',', array_keys( $post_meta ) );

		$sql = "SELECT * FROM {$order_itemmeta} INNER JOIN {$order_items}
				WHERE {$order_itemmeta}.meta_value = 'WC_Tapin_Method'
				AND {$order_items}.`order_item_type` = 'shipping'
				AND {$order_items}.`order_id` IN ({$order_ids})";

		$items = $wpdb->get_results( $sql );

		$items = wp_list_pluck( $items, 'meta_id', 'order_id' );

		foreach ( $post_meta as $order_id => $settings ) {

			$new_method = 'Tapin_Pishtaz_Method';

			$wpdb->update( $order_itemmeta, [
				'meta_value' => $new_method,
			], [
				'meta_id' => $items[ $order_id ],
			] );

			delete_post_meta( $order_id, 'tapin_method' );
		}

	}

	public function update_220() {
		// Update settings

		if ( ! function_exists( 'PW' ) ) {
			return;
		}

		$keys = [
			// Tools
			'pws_status_enable'       => 'tools.status_enable',
			'pws_hide_when_free'      => 'tools.hide_when_free',
			'pws_hide_when_courier'   => 'tools.hide_when_courier',

			// Tapin
			'pws_tapin_enable'        => 'tapin.enable',
			'pws_tapin_enable_credit' => 'tapin.show_credit',
			'pws_tapin_gateway'       => 'tapin.gateway',
			'pws_product_weight'      => 'tapin.product_weight',
			'pws_package_weight'      => 'tapin.package_weight',
			'pws_tapin_token'         => 'tapin.token',
			'pws_tapin_shop_id'       => 'tapin.shop_id',
		];

		foreach ( $keys as $old_key => $new_key ) {

			$value = PW()->get_options( $old_key );
			$value = str_replace( 'yes', 1, $value );
			$value = str_replace( 'no', 0, $value );
			PWS()->set_option( $new_key, $value );

		}

	}

	public function update_230() {

		if ( get_option( 'sabira_set_iran_cities', 0 ) ) {
			update_option( 'pws_install_cities', 1 );
			delete_option( 'sabira_set_iran_cities' );
		}

	}

	public function update_300() {
		global $wp_filter, $wpdb;

		unset( $wp_filter['delete_state_city'] );
		unset( $wp_filter['edited_state_city'] );
		unset( $wp_filter['created_state_city'] );

		require_once( PWS_DIR . '/data/state_city.php' );

		foreach ( PWS_get_states() as $key => $state ) {
			$term = get_term_by( 'slug', $key, 'state_city' );

			if ( $term === false ) {
				continue;
			}

			$cities = PWS_Core::cities( $term->term_id );

			foreach ( PWS_get_state_city( $key ) as $city ) {

				$arabic_city = str_replace( [ 'ک', 'ی' ], [ 'ك', 'ي', ], $city );

				if ( in_array( $city, $cities ) || in_array( $arabic_city, $cities ) ) {
					continue;
				}

				wp_insert_term( $city, 'state_city', [
					'parent'      => $term->term_id,
					'slug'        => $city,
					'description' => "$state - $city",
				] );
			}
		}

		PWS_City::flush_cache();

		PWS()->set_option( 'tools.product_weight', intval( PWS()->get_option( 'tapin.product_weight' ) ) );
		PWS()->set_option( 'tools.package_weight', intval( PWS()->get_option( 'tapin.package_weight' ) ) );

		PWS()->set_option( 'tapin.roundup_price', 1 );

		$options = $wpdb->get_col( "SELECT option_name FROM `{$wpdb->options}` WHERE `option_name` LIKE ('sabira_taxonomy_%');" );

		foreach ( $options as $option ) {
			$data = get_option( $option );

			if ( $data ) {
				add_option( str_replace( 'sabira', 'nabik', $option ), $data );
			}

			delete_option( $option );
		}

	}
}

add_action( 'admin_init', function () {
	new PWS_Version();
}, 110 );
