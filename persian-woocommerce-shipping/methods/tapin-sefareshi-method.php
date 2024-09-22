<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Tapin_Sefareshi_Method' ) ) {
	return;
} // Stop if the class already exists

/**
 * Class WC_Tapin_Method
 *
 * @author mahdiy
 *
 */
class Tapin_Sefareshi_Method extends PWS_Tapin_Method {

	public function __construct( $instance_id = 0 ) {

		$this->id                 = 'Tapin_Sefareshi_Method';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'پست سفارشی - تاپین';
		$this->method_description = 'در حال حاضر توسط اداره پست پشتیبانی نمی‌شود، لطفا از پست پیشتاز استفاده کنید.';

		parent::__construct();
	}

	public static function calculate_rates( array $args ): int {

		$weight_indicator = 9999;

		$weight = $args['weight'];

		switch ( true ) {
			case $weight <= 1000:
				$weight_indicator = 1000;
				break;
			case $weight <= 2000:
				$weight_indicator = 2000;
				break;
			case $weight <= 5000:
				$weight_indicator = 5000;
				break;
		}

		$checked_state = PWS()->check_states_beside( $args['from_province'], $args['to_province'] );

		$rates = [
			1000 => [
				'in'     => 80460,
				'beside' => 107460,
				'out'    => 114960,
			],
			2000 => [
				'in'     => 113460,
				'beside' => 137460,
				'out'    => 147960,
			],
			5000 => [
				'in'     => 110460,
				'beside' => 167460,
				'out'    => 179460,
			],
			9999 => 14000,
		];

		// calculate
		if ( $weight_indicator != 9999 ) {
			$cost = $rates[ $weight_indicator ][ $checked_state ];
		} else {
			$cost = $rates[5000][ $checked_state ] + ( $rates[ $weight_indicator ] * ceil( ( $weight - 5000 ) / 1000 ) );
		}

		if ( $args['content_type'] != 1 || $weight >= 2500 ) {
			$cost *= 1.25;
		}

		if ( in_array( $args['to_city'], [ 1, 91, 61, 51, 71, 81, 31 ] ) ) {
			$cost *= 1.1;
		}

		// INSURANCE
		if ( $args['price'] >= 10000000 ) {
			$cost += $args['price'] * 0.002;
		} else {
			$cost += 10000;
		}

		// COD
		if ( $args['is_cod'] ) {
			$cost += $args['price'] * 0.01;
		}

		// TAX
		$cost += $cost * 0.09;

		return intval( $cost );
	}
}
