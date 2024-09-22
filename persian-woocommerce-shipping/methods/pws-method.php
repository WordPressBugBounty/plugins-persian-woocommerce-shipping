<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'PWS_Shipping_Method' ) ) {
	return;
} // Stop if the class already exists

/**
 * Class PWS_Shipping_Method
 *
 * @author mahdiy
 *
 *
 */
class PWS_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Shipping method description for the frontend.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Free shipping if order total is grater than free fee
	 *
	 * @var string
	 */
	public $free_fee = '';

	/**
	 * Cart total
	 *
	 * @var string
	 */
	public $cart_total = 0;

	/**
	 * Cart weight
	 *
	 * @var string
	 */
	public $cart_weight = 0;

	/**
	 * Is available
	 *
	 * @var bool
	 */
	public $is_available = true;

	public function __construct() {

		$this->supports = [
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		];

		$this->init();
	}

	public function init() {

		$this->init_settings();

		// Prepend
		$this->instance_form_fields = [
			'title'       => [
				'title'   => 'عنوان',
				'type'    => 'text',
				'default' => $this->method_title,
			],
			'description' => [
				'title'       => 'توضیحات',
				'type'        => 'text',
				'description' => 'توضیحاتی که می‌خواهید در زیر هر روش حمل و نقل نمایش داده شود را وارد نمایید.',
				'default'     => null,
				'desc_tip'    => true,
			],
		];

		if ( ! defined( 'PWS_PRO_VERSION' ) ) {
			$description = sprintf( 'نمایش توضیحات فقط در <a href="%s" target="_blank">نسخه حرفه‌ای</a> فعال می‌باشد.', PWS()->pws_pro_url( 'description' ) );

			$this->instance_form_fields['description']['description'] = $description;
			$this->instance_form_fields['description']['desc_tip']    = false;
		}

		$this->init_form_fields();

		$this->instance_form_fields = apply_filters( 'pws_method_fields', $this->instance_form_fields + [
				'minimum_fee' => [
					'title'       => 'حداقل خرید',
					'type'        => 'text',
					'description' => 'در صورتی که مبلغ سفارش کمتر از این مبلغ باشد، این روش حمل و نقل مخفی می شود.',
					'default'     => 0,
					'desc_tip'    => true,
				],
				'free_fee'    => [
					'title'       => 'آستانه حمل و نقل رایگان',
					'type'        => 'text',
					'description' => 'در صورتی که مبلغ سفارش بیشتر از این مبلغ باشد، هزینه حمل و نقل برای مشتری رایگان می شود.',
					'default'     => '',
					'desc_tip'    => true,
				],
				'img_url'     => [
					'title'       => 'تصویر روش حمل و نقل',
					'type'        => 'text',
					'description' => 'آدرس تصویر مورد نظر برای این روش حمل و نقل را وارد کنید',
					'default'     => '',
					'css'         => 'direction: ltr;',
					'desc_tip'    => true,
				],
			], $this );

		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description' );

		if ( ! empty( $this->description ) ) {
			$this->method_description = $this->description;
		}

		$this->minimum_fee = $this->get_option( 'minimum_fee', 0 );
		$this->free_fee    = $this->get_option( 'free_fee', '' );
		$this->cart_total  = isset( WC()->cart ) ? WC()->cart->get_cart_contents_total() : 0;
		$this->cart_weight = PWS_Cart::get_weight();
	}

	public function is_available( $package ): bool {

		$available = $this->is_enabled() && $this->is_available;

		if ( empty( $package ) ) {
			$available = false;
		}

		if ( $package['destination']['country'] != 'IR' ) {
			$available = false;
		}

		if ( is_null( PWS()->get_state( $package['destination']['state'] ) ) ) {
			$available = false;
		}

		if ( is_null( PWS()->get_city( $package['destination']['city'] ) ) ) {
			$available = false;
		}

		if ( $this->minimum_fee > $this->cart_total ) {
			$available = false;
		}

		$available = apply_filters( 'woocommerce_shipping_pws_methods_is_available', $available, $package, $this );

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this );
	}

	public function free_shipping( $package = [] ): bool {

		$has_free_shipping = $this->free_fee !== '' && $this->free_fee <= $this->cart_total;
		$has_free_shipping = apply_filters( 'pws_has_free_shipping', $has_free_shipping, $package, $this );

		if ( $has_free_shipping ) {

			$this->add_rate_cost( 0, $package );

			return true;
		}

		return false;
	}

	public function add_rate_cost( $cost, $package ) {

		$rate = apply_filters( 'pws_add_rate', [
			'id'    => $this->get_rate_id(),
			'label' => $this->title,
			'cost'  => $cost,
		], $package, $this );

		$rate['cost'] = max( $rate['cost'], 0 );

		$this->add_rate( $rate );
	}

	public function get_destination( array $package ) {

		if ( ! isset( $package['destination']['district'] ) || empty( $package['destination']['district'] ) ) {
			return $package['destination']['city'];
		}

		return intval( $package['destination']['district'] );
	}
}
