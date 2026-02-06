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
		$this->method_title       = 'پست پیشتاز - تاپین';
		$this->method_description = 'ارسال کالا با استفاده از پست پیشتاز - تاپین';

		$this->supports[] = 'postpaid';

		parent::__construct( $instance_id );
	}

	public static function calculate_rates( array $args ): int {

		$weight = $args['weight'];

		$gateway = $args['gateway'] ?? 'tapin';

		$additions = [ 1 ];

		$box_size = max( 1, min( 10, $args['box_size'] ) );

		if ( $args['from_province'] == $args['to_province'] ) {
			$vicinity = 'in';
		} elseif ( PWS()->check_states_beside( $args['from_province'], $args['to_province'] ) ) {
			$vicinity = 'beside';
		} else {
			$vicinity = 'out';
		}

		$box_rates = include PWS_DIR . '/data/rates/tapin-pishtaz.php';

		if ( in_array( $args['from_province'], [ 3, 4, 5, 7, 15, 16, 18, 19, 21, 23, 26, 27, 29, 30 ] ) ) {
			$box_rates = include PWS_DIR . '/data/rates/tapin-pishtaz-border.php';
		}

		$weight_index = min( ceil( $weight / 1000 ) * 1000, 30000 );
		$weight_index = max( 1000, $weight_index );

		$cost = $base_cost = $box_rates[ $weight_index ][ $box_size ][ $vicinity ];

		if ( in_array( $args['to_city'], [ 91, 61, 51, 71, 81 ] ) ) {
			$cost += $base_cost * 0.15;
		} else if ( in_array( $args['to_city'], [ 1, 31 ] ) ) {
			$cost += $base_cost * 0.20;
		}

		if ( $gateway == 'posteketab' ) {
			$cost *= 0.7;
		}

		if ( $args['content_type'] != 1 ) {
			$cost += $base_cost * 0.25;
		}

		// INSURANCE
		if ( $args['price'] >= 50_000_000 ) {

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
			$cost += 50_000;
		}

		// COD
		if ( $args['is_cod'] ) {

			switch ( true ) {
				case $args['price'] >= 200_000_000:
					$cost += 150_000;
					break;
				case $args['price'] >= 50_000_000:
					$cost += 120_000;
					break;
				case $args['price'] >= 10_000_000:
					$cost += 100_000;
					break;
				case $args['price'] >= 5_000_000:
					$cost += 90_000;
					break;
				default:
					$cost += $args['price'] * 0.01;
					break;
			}

			$cost += 37_000;
		}

		// TAX
		$cost += $cost * 0.1;

		return intval( $cost );
	}
}
