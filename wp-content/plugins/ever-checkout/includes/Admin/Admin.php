<?php

namespace EverCheckout\Admin;

use EverCheckout\B8\Component;
use EverCheckout\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the plugin admin.
 *
 * @since   1.0.0
 * @package EverCheckout\Admin
 *
 * @extends Component<Plugin>
 */
class Admin extends Component {

	/**
	 * Child components.
	 *
	 * @since 1.0.0
	 * @var array<int|string, class-string>
	 */
	public array $components = array(
		Menu::class,
	);

	/**
	 * Whether to load.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function autoload(): bool {
		return is_admin();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register the admin assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_assets(): void {
		$this->app->scripts->register_script( 'ever-checkout-admin', 'admin.js' );
		$this->app->scripts->register_style( 'ever-checkout-admin', 'style-admin.css' );

		wp_localize_script( 'ever-checkout-admin', 'ecout_admin_vars', $this->get_admin_vars() );
	}

	/**
	 * Enqueue the admin assets on the plugin screens.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		$screen = get_current_screen();

		/**
		 * Filters the screens the admin bundle loads on.
		 *
		 * @since 1.0.0
		 * @param array<int, string> $screens Screen ids and hook suffixes.
		 */
		$screens = $this->app->apply_filters( 'admin_screens_ids', $this->app->get( Menu::class )->get_screen_ids() );

		if ( ! in_array( $hook, $screens, true ) && ! in_array( $screen ? $screen->id : '', $screens, true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_script( 'ever-checkout-admin' );
		wp_enqueue_style( 'ever-checkout-admin' );
	}

	/**
	 * Get the admin vars.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Admin data.
	 */
	protected function get_admin_vars(): array {
		$user = wp_get_current_user();

		$data = array(
			'slug'      => $this->app->slug,
			'version'   => $this->app->version,
			'rest'      => array(
				'url'       => esc_url_raw( rest_url( $this->app->rest_prefix ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'namespace' => $this->app->rest_prefix,
			),
			'adminUrl'  => admin_url(),
			'assetsUrl' => $this->app->assets_url(),
			'user'      => array(
				'id'           => $user->ID,
				'name'         => $user->display_name,
				'email'        => $user->user_email,
				'capabilities' => array( 'manage_options' => current_user_can( 'manage_options' ) ),
			),
			'locale'    => get_locale(),
		);

		/**
		 * Filters the localized admin data.
		 *
		 * @since 1.0.0
		 * @param array<string, mixed> $data Admin data.
		 */
		return $this->app->apply_filters( 'admin_vars', $data );
	}
}
