<?php

namespace EverCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @property Services\Locale    $locale       Currency reference data.
 * @property Services\Money     $money        Money formatting.
 * @property Services\Settings  $settings     Plugin settings.
 * @property Gateways\Gateways  $gateways     Payment gateway registry.
 * @property Emails\Emails      $emails       Email registry.
 * @property Models\Product     $product      Product model.
 * @property Models\Download    $download     Download model.
 * @property Models\DownloadLog $download_log Download log model.
 * @property Models\Customer    $customer     Customer model.
 * @property Models\Order       $order        Order model.
 * @property Models\OrderItem   $order_item   Order item model.
 */
class Plugin extends B8\App {

	/**
	 * Public query vars the plugin answers to.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const QUERY_VARS = array(
		'download' => 'ecout_download',
		'receipt'  => 'ecout_receipt',
	);

	/**
	 * Components to register.
	 *
	 * @since 1.0.0
	 * @var array<int|string, class-string>
	 */
	protected array $components = array(
		Installer::class,
		Gateways\Gateways::class,
		Orders::class,
		Checkout::class,
		Downloads::class,
		Emails\Emails::class,
		RestAPI\Routes::class,
		Blocks\Blocks::class,
		Privacy::class,
		Admin\Admin::class,
	);

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bootstrap(): void {
		$upload_dir = wp_upload_dir( null, false );

		define( 'ECOUT_FILE', $this->file );
		define( 'ECOUT_VERSION', $this->version );
		define( 'ECOUT_UPLOAD_DIR', $upload_dir['basedir'] . '/ever-checkout' );

		$this->bind( 'locale', Services\Locale::class );
		$this->bind( 'money', Services\Money::class );
		$this->bind( 'settings', Services\Settings::class );
		$this->bind( 'gateways', Gateways\Gateways::class );
		$this->bind( 'emails', Emails\Emails::class );

		$this->factory( 'product', Models\Product::class );
		$this->factory( 'download', Models\Download::class );
		$this->factory( 'download_log', Models\DownloadLog::class );
		$this->factory( 'customer', Models\Customer::class );
		$this->factory( 'order', Models\Order::class );
		$this->factory( 'order_item', Models\OrderItem::class );

		$this->on_activation( Installer::class . '@install' );
		$this->on_deactivation( Installer::class . '@deactivate' );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 0 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'plugin_action_links_' . $this->basename(), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Boot the plugin components.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function plugins_loaded(): void {
		$this->boot( $this->components );

		/**
		 * Fires after the plugin has booted its components.
		 *
		 * @since 1.0.0
		 */
		$this->do_action( 'loaded' );
	}

	/**
	 * Register the public query vars the plugin answers to.
	 *
	 * @since 1.0.0
	 * @param array<int, string> $vars Public query vars.
	 * @return array<int, string>
	 */
	public function add_query_vars( array $vars ): array {
		return array_merge( $vars, array_values( self::QUERY_VARS ) );
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param array<string, string> $links Plugin action links.
	 * @return array<string, string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( (string) $this->get( 'settings_url' ) ),
			esc_html__( 'Settings', 'ever-checkout' )
		);

		return array_merge( array( 'settings' => $settings ), $links );
	}
}
