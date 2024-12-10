<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Tapin_Pishtaz_Method' ) ) {
	return;
} // Stop if the class already exists

/**
 * Class WC_Tapin_Method
 *
 * @author mahdiy
 *
 */
class Tapin_Pishtaz_Method extends PWS_Tapin_Method {

	public function __construct( $instance_id = 0 ) {

		$this->id                 = 'Tapin_Pishtaz_Method';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'پست پیشتاز - تاپین';
		$this->method_description = 'ارسال کالا با استفاده از پست پیشتاز - تاپین';

		parent::__construct();
	}

	public static function calculate_rates( array $args ): int {

		$weight = $args['weight'];

		$gateway = $args['gateway'] ?? 'tapin';

		$additions = [ 1 ];

		if ( $gateway == 'tapin' ) {

			if ( $args['from_province'] == $args['to_province'] ) {
				$vicinity = 'in';
			} elseif ( PWS()->check_states_beside( $args['from_province'], $args['to_province'] ) ) {
				$vicinity = 'beside';
			} else {
				$vicinity = 'out';
			}

			$box_size = max( 1, min( 10, $args['box_size'] ) );

			if ( in_array( $box_size, range( 1, 3 ) ) ) {

				if ( $weight >= 2500 ) {
					$additions[] = 1.25;
				}

				if ( $args['content_type'] != 1 ) {
					$additions[] = 1.25;
				}

			}

			$box_rates    = include PWS_DIR . '/data/pishtaz-rates.php';
			$weight_index = min( ceil( $weight / 1000 ) * 1000, 30000 );
			$weight_index = max( 1000, $weight_index );

			$cost = $box_rates[ $weight_index ][ $box_size ][ $vicinity ];

		} else {

			switch ( true ) {
				case $weight <= 500:
					$cost = 183400;
					break;
				case $weight >= 501 && $weight <= 1000:
					$cost = 202400;
					break;
				case $weight >= 1001 && $weight <= 2000:
					$cost = 247400;
					break;
				case $weight >= 2001 && $weight <= 2500:
					$cost = 297400;
					break;
				default:
					$cost = 300120;
			}

			// calculate
			if ( $weight > 3000 ) {
				$cost += 50000 * ceil( ( $weight - 3000 ) / 1000 );
			}

			if ( $weight >= 2500 ) {
				$additions[] = 1.25;
			}

			if ( $args['content_type'] != 1 ) {
				$additions[] = 1.25;
			}

		}

		$cost *= max( $additions );

		if ( in_array( $args['to_city'], [ 1, 91, 61, 51, 71, 81, 31 ] ) ) {
			$cost *= 1.15;
		}

		// INSURANCE
		if ( $args['price'] >= 40000000 ) {

			switch ( true ) {
				case $args['price'] >= 700000000:
					$rate = 0.0035;
					break;
				case $args['price'] >= 500000000:
					$rate = 0.003;
					break;
				case $args['price'] >= 300000000:
					$rate = 0.0025;
					break;
				default:
					$rate = 0.002;
					break;
			}

			$cost += $args['price'] * $rate;

		} else {
			$cost += 40000;
		}

		// COD
		if ( $args['is_cod'] ) {

			switch ( true ) {
				case $args['price'] >= 200000000:
					$cost += 120000;
					break;
				case $args['price'] >= 50000000:
					$cost += 90000;
					break;
				case $args['price'] >= 10000000:
					$cost += 80000;
					break;
				case $args['price'] >= 5000000:
					$cost += 70000;
					break;
				default:
					$cost += $args['price'] * 0.01;
					break;
			}

		}

		// TAX
		$cost += $cost * 0.1;

		return intval( $cost );
	}
}
