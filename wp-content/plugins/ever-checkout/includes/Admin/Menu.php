<?php

namespace EverCheckout\Admin;

use EverCheckout\B8\Component;
use EverCheckout\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the admin menu.
 *
 * @since   1.0.0
 * @package EverCheckout\Admin
 *
 * @extends Component<Plugin>
 */
class Menu extends Component {

	/**
	 * Parent menu slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $parent = 'ever-checkout';

	/**
	 * Registered screen IDs.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected array $screen_ids = array();

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menu(): void {
		$this->screen_ids[] = add_menu_page(
			__( 'Ever Checkout', 'ever-checkout' ),
			__( 'Ever Checkout', 'ever-checkout' ),
			'manage_options',
			$this->parent,
			array( $this, 'render' ),
			'dashicons-money-alt',
			58
		);

		/**
		 * Filters the admin pages configuration.
		 *
		 * @since 1.0.0
		 * @param array<int, array<string, mixed>> $pages Admin page configurations.
		 */
		$pages = $this->app->apply_filters(
			'admin_pages',
			array(
				array(
					'title'    => __( 'Dashboard', 'ever-checkout' ),
					'path'     => '/',
					'position' => 10,
				),
				array(
					'title'    => __( 'Products', 'ever-checkout' ),
					'path'     => '/products',
					'position' => 20,
				),
				array(
					'title'    => __( 'Orders', 'ever-checkout' ),
					'path'     => '/orders',
					'position' => 30,
				),
				array(
					'title'    => __( 'Customers', 'ever-checkout' ),
					'path'     => '/customers',
					'position' => 40,
				),
				array(
					'title'    => __( 'Settings', 'ever-checkout' ),
					'path'     => '/settings',
					'position' => 90,
				),
			)
		);

		usort( $pages, static fn( $a, $b ) => ( $a['position'] ?? 10 ) <=> ( $b['position'] ?? 10 ) );

		foreach ( $pages as $page ) {
			$page = wp_parse_args(
				$page,
				array(
					'title'      => '',
					'slug'       => '',
					'path'       => '',
					'capability' => 'manage_options',
					'position'   => 10,
					'handle'     => null,
					'callback'   => array( $this, 'render' ),
				)
			);

			// The leading slash is part of the route path the JS router matches.
			if ( ! empty( $page['path'] ) ) {
				$page['slug'] = $this->parent . '#/' . ltrim( $page['path'], '/' );
			}

			if ( ! $page['title'] || ! $page['slug'] ) {
				continue;
			}

			$hook = add_submenu_page(
				$this->parent,
				$page['title'],
				$page['title'],
				$page['capability'],
				$page['slug'],
				$this->app->callback( $page['callback'] ),
				$page['position']
			);

			if ( ! $hook ) {
				continue;
			}

			$this->screen_ids[] = $hook;

			if ( is_callable( $page['handle'] ) ) {
				add_action( "load-{$hook}", $this->app->callback( $page['handle'] ) );
			}
		}

		// Parent is a container; drop the auto-created duplicate submenu.
		remove_submenu_page( $this->parent, $this->parent );
	}

	/**
	 * Render the mount node.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div id="ecout-admin"></div>
		<?php
	}

	/**
	 * Get the screen ids.
	 *
	 * @since 1.0.0
	 * @return array<int, string> Screen IDs.
	 */
	public function get_screen_ids(): array {
		return $this->screen_ids;
	}
}
