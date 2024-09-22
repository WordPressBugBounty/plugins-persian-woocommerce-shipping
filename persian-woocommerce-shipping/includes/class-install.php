<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

class PWS_Install {

	public function __construct() {
		add_action( 'admin_init', [ $this, 'activated_plugin' ], 50 );
	}

	public function activated_plugin() {

		if ( ! file_exists( PWS_DIR . '/.activated' ) ) {
			return;
		}

		$installed_version = get_option( PWS_Version::VERSION_KEY );

		if ( empty( $installed_version ) ) {
			update_option( PWS_Version::VERSION_KEY, PWS_VERSION, 'yes' );
		}

		if ( 'yes' === get_transient( 'pws_admin_installing' ) ) {
			return;
		}

		set_transient( 'pws_admin_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		set_time_limit( 0 );

		self::install_cities();

		delete_transient( 'pws_admin_installing' );

		update_option( 'pws_version', PWS_VERSION );

		unlink( PWS_DIR . '/.activated' );
	}

	public function install_cities() {
		global $wp_filter;

		unset( $wp_filter['delete_state_city'] );
		unset( $wp_filter['edited_state_city'] );
		unset( $wp_filter['created_state_city'] );

		if ( get_option( 'sabira_set_iran_cities', 0 ) || get_option( 'pws_install_cities', 0 ) ) {
			return;
		}

		require_once( PWS_DIR . '/data/state_city.php' );

		foreach ( PWS_get_states() as $key => $state ) {
			$term = wp_insert_term( $state, 'state_city', [ 'slug' => $key, 'description' => "استان $state" ] );

			if ( is_wp_error( $term ) ) {
				continue;
			}

			foreach ( PWS_get_state_city( $key ) as $city ) {
				wp_insert_term( $city, 'state_city', [
					'parent'      => $term['term_id'],
					'slug'        => $city,
					'description' => "$state - $city",
				] );
			}
		}

		update_option( 'pws_install_cities', 1 );
	}
}

new PWS_Install();
