<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

defined( 'ABSPATH' ) || exit;

class PWS_City {

	public function __construct() {

		add_filter( 'state_city_row_actions', [ $this, 'state_city_row_actions' ], 10, 2 );
		add_filter( 'get_edit_term_link', [ $this, 'remove_edit_term_link' ], 10, 4 );

		if ( get_option( 'pws_install_cities', 0 ) ) {
			add_action( 'delete_state_city', [ $this, 'flush_cache' ], 10 );
			add_action( 'edited_state_city', [ $this, 'flush_cache' ], 10 );
			add_action( 'created_state_city', [ $this, 'flush_cache' ], 10 );
		}

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );

		if ( PWS_Tapin::is_enable() ) {
			// @todo Replace with dedicated page
			add_filter( 'edit_state_city_per_page', [ $this, 'tapin_get_state_city_per_page' ] );
			add_action( 'admin_footer', [ $this, 'tapin_admin_footer' ] );
			add_filter( 'get_terms', [ $this, 'tapin_get_states' ], 20, 4 );
			add_filter( 'get_edit_term_link', [ $this, 'tapin_edit_term_link' ], 20, 4 );
		}
	}

	public function state_city_row_actions( array $actions, $term ): array {

		if ( PWS_Tapin::is_enable() ) {
			$actions = [];
		}

		if ( isset( $actions['edit'] ) ) {
			unset( $actions['edit'] );
		}

		if ( $term->parent ) {
			return $actions;
		}

		$edit_list_link = add_query_arg( 'term_id', $term->term_id, admin_url( 'admin.php?page=nabik_edit_state' ) );

		$actions['edit_list'] = "<a href='{$edit_list_link}'>ویرایش شهرها</a>";

		return $actions;
	}

	public function remove_edit_term_link( $location, $term_id, $taxonomy, $object_type ) {

		if ( $taxonomy == 'state_city' ) {
			return null;
		}

		return $location;
	}

	public static function flush_cache() {
		global $wpdb;

		$caches = $wpdb->get_col( "SELECT option_name FROM `{$wpdb->options}` WHERE `option_name` LIKE ('_transient_pws%');" );

		foreach ( $caches as $cache ) {
			delete_transient( str_replace( '_transient_', '', $cache ) );
		}

		do_action( 'pws_state_city_updated' );
	}

	public function admin_menu() {

		add_submenu_page( '', '', '', 'manage_woocommerce', 'nabik_edit_state', [
			$this,
			'nabik_edit_state_callback',
		] );

	}

	public function nabik_edit_state_callback() {

		if ( PWS_Tapin::is_enable() ) {
			$state_term          = new WP_Term( new stdClass() );
			$state_term->term_id = 'state_' . intval( $_GET['term_id'] );
			$state_term->name    = PWS()::get_state( intval( $_GET['term_id'] ) );
			$state_term->parent  = 0;
		} else {
			$state_term = get_term( intval( $_GET['term_id'] ), 'state_city' );
		}

		if ( is_null( $state_term ) || is_wp_error( $state_term ) ) {
			wp_redirect( admin_url( 'edit-tags.php?taxonomy=state_city' ) );

			return;
		}

		if ( $state_term->parent ) {
			wp_redirect( add_query_arg( 'term_id', $state_term->parent,
				admin_url( 'admin.php?page=nabik_edit_state' ) ) );

			return;
		}

		$state_term->name = 'استان ' . $state_term->name;

		if ( PWS_Tapin::is_enable() ) {

			$terms = [ - 1 => $state_term ];

			foreach ( PWS()::cities( intval( $_GET['term_id'] ) ) as $city_id => $city_name ) {
				$city_term          = new WP_Term( new stdClass() );
				$city_term->term_id = intval( $city_id );
				$city_term->name    = $city_name;
				$city_term->parent  = $state_term->term_id;
				$terms[]            = $city_term;
			}

		} else {
			$terms = [ - 1 => $state_term ] + get_terms( [
					'taxonomy'   => 'state_city',
					'hide_empty' => false,
					'child_of'   => $state_term->term_id,
				] );
		}

		$expected_terms = [];

		$message = null;

		if ( isset( $_POST['submit'], $_POST['taxonomy'], $_POST['tag_ID'], $_POST['action'], $_POST['term_meta'] ) ) {

			$expected_terms = array_flip( wp_list_pluck( $terms, 'term_id' ) );

			foreach ( $_POST['term_meta'] as $term_id => $term ) {

				unset( $expected_terms[ $term_id ] );

				$option = array_map( 'intval', array_filter( $term, 'is_numeric' ) );

				if ( $option ) {
					PWS()->set_term_option( $term_id, $option );
				} else {
					PWS()->delete_term_option( $term_id );
				}
			}

			$message = 'تنظیمات با موفقیت ذخیره شدند.';
		}

		foreach ( $expected_terms as $term_id => $_trap ) {
			PWS()->delete_term_option( $term_id );
		}

		$table_headers = apply_filters( 'pws_city_table_headers', [
			'tipax_on'      => '<input type="checkbox" title="فعالسازی/غیرفعالسازی تیپاکس" data-scope="tipax_on">',
			'tipax'         => 'هزینه پایه تیپاکس',
			'courier_on'    => '<input type="checkbox" title="فعالسازی/غیرفعالسازی پیک‌موتوری" data-scope="courier_on">',
			'courier'       => 'هزینه پایه پیک‌موتوری',
			'forehand_cost' => 'هزینه ثابت پست‌پیشتاز',
		] );

		?>
		<div class="wrap">
			<h1>ویرایش <?php echo esc_attr( $state_term->name ); ?></h1>
			<?php
			if ( $message ) {
				printf( '<div id="message" class="updated"><p><strong>%s</strong></p></div>', esc_attr( $message ) );
			}
			?>

			<form method="post" action="" class="validate">
				<input type="hidden" name="action" value="editedtag"/>
				<input type="hidden" name="tag_ID" value="<?php echo esc_attr( $state_term->term_id ) ?>"/>
				<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $state_term->taxonomy ) ?>"/>

				<table class="widefat" cellspacing="0">
					<thead>
					<tr>
						<th style="min-width: 150px;"><?php _e( 'نام شهر' ); ?></th>
						<th><?php echo implode( '</th><th>', array_map( function ( $header ) {
								return strip_tags( $header, '<input>' );
							}, $table_headers ) ); ?></th>
					</tr>
					</thead>
					<tfoot>
					<tr>
						<th><?php _e( 'نام شهر' ); ?></th>
						<th><?php echo implode( '</th><th>', array_map( function ( $header ) {
								return strip_tags( $header, '<input>' );
							}, $table_headers ) ); ?></th>
					</tr>
					</tfoot>
					<tbody>
					<?php foreach ( $terms as $term ) {
						$term_id     = $term->term_id;
						$term_option = PWS()->get_term_option( $term_id );

						$term_option = wp_parse_args( $term_option, [
							'tipax_on'      => false,
							'tipax_cost'    => null,
							'courier_on'    => false,
							'courier_cost'  => null,
							'forehand_cost' => null,
						] );

						$indent = str_repeat( "- ",
							max( count( get_ancestors( $term->term_id, 'state_city' ) ) - 1, 0 ) );

						$tipax_checked   = checked( $term_option['tipax_on'], 1, false );
						$courier_checked = checked( $term_option['courier_on'], 1, false );

						$row = [
							'tipax_on'      => "<input type='checkbox' name='%s' title='%s' class='%s' value='1' {$tipax_checked} data-parent='{$term->parent}'>",
							'tipax_cost'    => "<input type='text' name='%s' title='%s' class='%s' value='{$term_option['tipax_cost']}'>",
							'courier_on'    => "<input type='checkbox' name='%s' title='%s' class='%s' value='1' {$courier_checked} data-parent='{$term->parent}'>",
							'courier_cost'  => "<input type='text' name='%s' title='%s' class='%s' value='{$term_option['courier_cost']}'>",
							'forehand_cost' => "<input type='text' name='%s' title='%s' class='%s'  value='{$term_option['forehand_cost']}'>",
						];

						$row = apply_filters( 'pws_city_table_row', $row, $term, $term_option );

						printf( "<tr><td>%s%s</td>", esc_attr( $indent ), esc_attr( $term->name ) );

						foreach ( $row as $col => $input ) {
							$input_name = "term_meta[{$term_id}][{$col}]";
							printf( "<td>{$input}</td>", $input_name, $term->name, $col );
						}

						echo "</tr>";
					} ?>
					</tbody>
				</table>

				<?php
				submit_button( __( 'Update' ) );
				?>
			</form>
		</div>

		<style>
            tr:nth-child(even) {
                background: #CCC
            }

            tr:nth-child(odd) {
                background: #FFF
            }

            tbody tr:first-child {
                background: #52ACCC;
            }

            tr:hover {
                background: #72C8E5
            }

            input[type=text] {
                width: 100%;
            }

            th, td:not(:first-child) {
                text-align: center !important;
            }
		</style>
		<script>
            jQuery(document).ready(function ($) {
                $('form').submit(function () {
                    $(this).find(":input[type=text]").filter(function () {
                        return !this.value;
                    }).attr('disabled', 'disabled');

                    $(this).find(":input[type=checkbox]").filter(function () {
                        return !this.checked;
                    }).attr('disabled', 'disabled');

                    return true;
                });

                $("th>input[type=checkbox]").click(function () {
                    let checkBoxes = $("input." + $(this).data('scope').replace(':', '\\:'));

                    if ($(this).prop('checked')) {
                        checkBoxes.prop("checked", true);
                    } else {
                        checkBoxes.prop("checked", false);
                    }
                });

                $("td>input[type=checkbox]").click(function () {

                    let parent_class = $(this).attr('class').replace(':', '\\:');
                    let parent_id = $(this).data('parent');

                    if (parent_id === undefined) {
                        return;
                    }

                    $(`input[name=term_meta\\[${parent_id}\\]\\[${parent_class}\\]]`).prop("checked", true);
                });

                $("input[type=text]").attr('placeholder', '<?php echo esc_attr( html_entity_decode( get_woocommerce_currency_symbol() ) ); ?>');
            });
		</script>
		<?php
	}

	public function tapin_get_state_city_per_page(): int {
		return count( PWS()::states() );
	}

	public function tapin_admin_footer() {
		if ( ! isset( $_GET['taxonomy'] ) || $_GET['taxonomy'] != 'state_city' ) {
			return;
		}

		?>
		<style>
            .wrap .tablenav, .wrap .search-box, .wrap #col-left, .wrap input[type=checkbox] {
                display: none !important;
            }

            .wrap #col-right {
                width: 100% !important;
            }
		</style>
		<?php
	}

	public function tapin_get_states( $terms, $taxonomy, $query_vars, $term_query ): array {

		if ( isset( $query_vars['fields'] ) && $query_vars['fields'] == 'id=>parent' ) {
			return $terms;
		}

		if ( ! isset( $taxonomy[0] ) || $taxonomy[0] != 'state_city' ) {
			return $terms;
		}

		$terms = [];

		foreach ( PWS()::states() as $state_id => $state_name ) {
			$city_term              = new WP_Term( new stdClass() );
			$city_term->term_id     = intval( $state_id );
			$city_term->name        = $state_name;
			$city_term->slug        = $state_name;
			$city_term->taxonomy    = 'state_city';
			$city_term->description = 'استان ' . $state_name;
			$city_term->parent      = 0;

			wp_cache_add( $city_term->term_id, $city_term, 'terms' );

			$terms[] = $city_term;
		}

		return $terms;
	}

	public function tapin_edit_term_link( $location, $term_id, $taxonomy, $object_type ) {

		if ( $taxonomy === 'state_city' ) {
			return '';
		}

		return $location;
	}
}

return new PWS_City();
