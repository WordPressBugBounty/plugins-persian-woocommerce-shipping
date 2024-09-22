<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

class PWS_Settings_Tapin extends PWS_Settings {

	protected static $_instance = null;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function get_sections(): array {
		return [
			[
				'id'    => 'pws_tapin',
				'title' => 'پیشخوان مجازی پست',
			],
		];
	}

	public function get_fields(): array {

		if ( PWS_Tapin::is_enable() ) {
			$shop = get_transient( 'pws_tapin_shop' );

			if ( $shop === false || count( (array) $shop ) == 0 ) {
				PWS_Tapin::shop();
				$shop = '<span style="color: red;">اطلاعات فروشگاه بارگذاری نشده است و ممکن است هزینه های ارسال بطور دقیق محاسبه نشود.</span>';
			} else {
				$shop = sprintf( '%s | %s %s | نرخ خدمات: %s', $shop->title, PWS()::get_state( $shop->province_code ), PWS()::get_city( $shop->city_code ), wc_price( $shop->total_price, [ 'currency' => 'IRR' ] ) );
				$shop = "اطلاعات فروشگاه: <span style='color: seagreen'>{$shop}</span>";
			}

		} else {
			$shop = '';
		}

		if ( isset( $_GET['tapin_check_ip'] ) ) {

			$response = wp_remote_get( 'https://checkip.tapin.ir/' );
			$response = wp_remote_retrieve_body( $response );

			if ( $response ) {
				$shop .= ' | ' . $response;
			}

		}

		if ( ( $_GET['page'] ?? null ) == 'pws-tapin' ) {
			do_action( 'pws_state_city_updated' );
		}

		return [
			'pws_tapin' => [
				[
					'name' => 'banner',
					'desc' => '<a href="https://yun.ir/pwstd" target="_blank"><img src="' . PWS_URL . 'assets/images/tapin.jpg" style="width: 100%;"></a>',
					'type' => 'html',
				],
				[
					'label'   => 'فعالسازی تاپین',
					'name'    => 'enable',
					'default' => '0',
					'type'    => 'checkbox',
					'desc'    => 'در صورت فعالسازی پیشخوان مجازی تاپین، لیست استان ها و شهرها از وب سرویس های این سامانه بارگذاری می شود.',
				],
				[
					'label'   => 'نمایش اعتبار تاپین',
					'name'    => 'show_credit',
					'default' => '0',
					'type'    => 'checkbox',
					'desc'    => 'اعتبار پنل تاپین در منو بالا مدیریت نمایش داده می شود.',
				],
				[
					'label'   => 'رند کردن هزینه پست',
					'name'    => 'roundup_price',
					'default' => 1,
					'type'    => 'checkbox',
					'desc'    => 'هزینه‌های پست بر مبنای ۱۰۰۰ ریال رند به بالا می‌شود.',
				],
				[
					'label'   => 'عنوان محصول',
					'name'    => 'product_title',
					'default' => null,
					'type'    => 'text',
					'desc'    => 'در صورتی که این فیلد را پر کنید، بجای نام محصول، این متن برای عنوان محصول به تاپین ارسال می‌شود.',
				],
				[
					'label'   => 'نوع مرسوله پیشفرض',
					'name'    => 'content_type',
					'default' => 4,
					'type'    => 'select',
					'desc'    => 'بسته‌های تاپین به صورت پیشفرض با این نوع ثبت می‌شوند. (در بخش ویرایش سفارش، برای هر سفارش قابل تغییر و شخصی سازی است)',
					'options' => [
						1 => 'عادی',
						2 => 'شکستنی',
						3 => 'مایعات',
						4 => 'غیراستاندارد',
					],
				],
				[
					'label'   => 'حجم مرسوله پیشفرض',
					'name'    => 'box_size',
					'default' => 1,
					'type'    => 'select',
					'desc'    => 'نرخ‌نامه پستی به صورت پیشفرض براساس این حجم محاسبه می‌شود. (در بخش ویرایش سفارش، برای هر سفارش قابل تغییر و شخصی سازی است)',
					'options' => PWS_Tapin::box_sizes(),
				],
				[
					'label'   => 'وضعیت ثبت مرسوله',
					'name'    => 'register_type',
					'default' => 1,
					'type'    => 'select',
					'options' => [
						1 => 'آماده به پرینت',
						2 => 'آماده به ارسال',
					],
					'desc'    => 'بسته‌ها در کدام وضعیت در تاپین ثبت شوند؟',
				],
				[
					'label'   => 'درگاه پست',
					'name'    => 'gateway',
					'default' => 'tapin',
					'type'    => 'select',
					'desc'    => 'در صورتی که فروشگاه کتاب است، پست کتاب و در غیر اینصورت پست تاپین را انتخاب کنید.',
					'options' => [
						'tapin'      => 'پست تاپین - tapin.ir',
						'posteketab' => 'پست کتاب - posteketab.com',
					],
				],
				[
					'label'   => 'توکن',
					'name'    => 'token',
					'default' => '',
					'type'    => 'text',
					'desc'    => 'توکن خود را از <a href="https://my.tapin.ir/" target="_blank">پیشخوان مجازی تاپین</a> دریافت کنید. آدرس آی.پی شما: ' . esc_attr( $_SERVER['SERVER_ADDR'] ?? '-' ),
				],
				[
					'label'   => 'شناسه فروشگاه',
					'name'    => 'shop_id',
					'default' => '',
					'type'    => 'text',
					'desc'    => $shop,
				],
				[
					'name' => 'notes',
					'desc' => 'نکات:<ol>
<li>پست پیشتاز بسته با حداکثر وزن ۳۰ کیلوگرم را می پذیرد.</li>
<li>بیمه غرامت پست برای محصولات با حداکثر ارزش ۱۰۰ میلیون تومان پرداخت می شود.</li>
</ol>',
					'type' => 'html',
				],
			],
		];
	}

	public static function output() {

		$instance = self::instance();

		echo '<div class="wrap">';

		$instance->show_navigation();
		$instance->show_forms();

		echo '</div>';
	}
}
