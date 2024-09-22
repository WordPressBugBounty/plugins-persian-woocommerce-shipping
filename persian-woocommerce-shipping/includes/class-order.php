<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

class PWS_Order {

	public static function get_weight( WC_Order $order ): float {

		$weight = $order->get_meta( 'tapin_weight' );

		if ( empty( $weight ) ) {

			$weight = floatval( PWS()->get_option( 'tools.package_weight', 500 ) );

			foreach ( $order->get_items() as $order_item ) {

				/** @var WC_Product $product */
				$product = $order_item->get_product();

				if ( is_bool( $product ) || $product->is_virtual() ) {
					continue;
				}

				$weight += PWS_Product::get_weight( $product ) * $order_item->get_quantity();
			}

		}

		return apply_filters( 'pws_order_weight', $weight, $order );
	}

	public static function get_shipping_method( WC_Order $order, $label = false ) {

		$shipping_method = null;

		foreach ( $order->get_shipping_methods() as $shipping_item ) {
			if ( strpos( $shipping_item->get_method_id(), 'Tapin_Pishtaz_Method' ) === 0 ) {
				$shipping_method = 1;
			}
		}

		$labels = [
			'سفارشی',
			'پیشتاز',
		];

		if ( $label ) {
			return $labels[ $shipping_method ] ?? null;
		}

		return $shipping_method;
	}

	public static function get_content_type( WC_Order $order ) {

		$content_type = $order->get_meta( 'tapin_content_type' );

		if ( empty( $content_type ) ) {
			$content_type = PWS()->get_option( 'tapin.content_type', 4 );
		}

		return $content_type;
	}

	public static function get_box_size( WC_Order $order ) {

		$box_size = $order->get_meta( 'tapin_box_size' );

		if ( empty( $box_size ) ) {
			$box_size = PWS()->get_option( 'tapin.box_size', 1 );
		}

		return $box_size;
	}

}
