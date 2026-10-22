<?php

namespace EverCheckout\RestAPI;

use EverCheckout\B8\Component;
use EverCheckout\Plugin;
use EverCheckout\RestAPI\Controllers\Checkout;
use EverCheckout\RestAPI\Controllers\Customers;
use EverCheckout\RestAPI\Controllers\Dashboard;
use EverCheckout\RestAPI\Controllers\DownloadLogs;
use EverCheckout\RestAPI\Controllers\Downloads;
use EverCheckout\RestAPI\Controllers\Emails;
use EverCheckout\RestAPI\Controllers\Gateways;
use EverCheckout\RestAPI\Controllers\Orders;
use EverCheckout\RestAPI\Controllers\Products;
use EverCheckout\RestAPI\Controllers\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the REST routes.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI
 *
 * @extends Component<Plugin>
 */
class Routes extends Component {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the REST routes.
	 *
	 * Every admin route sits inside one group gated on `manage_options`, which the router
	 * turns into a `current_user_can()` permission callback. Core's REST cookie
	 * authentication rejects a request whose `wp_rest` nonce is missing or stale
	 * before that runs, so a mutation needs both.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		$this->app->router->group(
			array( 'permission' => 'manage_options' ),
			static function ( $router ): void {
				$router->get( 'dashboard', array( Dashboard::class, 'get_items' ) );

				$router->get( 'settings', array( Settings::class, 'get_items' ) );
				$router->post( 'settings', array( Settings::class, 'update_items' ) );
				$router->get( 'gateways', array( Gateways::class, 'get_items' ) );
				$router->get( 'gateways/(?P<id>[a-z0-9_-]+)', array( Gateways::class, 'get_item' ) );
				$router->post( 'gateways/(?P<id>[a-z0-9_-]+)', array( Gateways::class, 'update_item' ) );
				$router->get( 'emails', array( Emails::class, 'get_items' ) );
				$router->get( 'emails/(?P<id>[a-z0-9_-]+)', array( Emails::class, 'get_item' ) );
				$router->post( 'emails/(?P<id>[a-z0-9_-]+)', array( Emails::class, 'update_item' ) );

				$router->resource( 'products', Products::class );
				$router->resource( 'downloads', Downloads::class );
				$router->resource( 'download-logs', DownloadLogs::class, array( 'only' => array( 'get_items', 'get_item' ) ) );
				$router->resource( 'orders', Orders::class );
				$router->resource( 'customers', Customers::class );
			}
		);

		// Public by design: a gateway verifies its own signature, a customer has no account.
		$this->app->router->group(
			array( 'permission' => '__return_true' ),
			static function ( $router ): void {
				$router->post( 'gateways/(?P<id>[a-z0-9_-]+)/webhook', array( Gateways::class, 'handle_callback' ) );
				$router->post( 'checkout', array( Checkout::class, 'create_item' ) );
				$router->get( 'checkout/products/(?P<id>[\d]+)', array( Checkout::class, 'get_product' ) );
				$router->get( 'checkout/(?P<order_key>[a-f0-9]{32})', array( Checkout::class, 'get_item' ) );
			}
		);
	}
}
