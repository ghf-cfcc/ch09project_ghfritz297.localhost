<?php

namespace EverCheckout\Emails;

use EverCheckout\B8\Component;
use EverCheckout\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the email registry.
 *
 * @since   1.0.0
 * @package EverCheckout\Emails
 *
 * @extends Component<Plugin>
 */
class Emails extends Component {

	/**
	 * Email instances keyed by id.
	 *
	 * @since 1.0.0
	 * @var array<string, Email>
	 */
	protected array $emails = array();

	/**
	 * Register hooks.
	 *
	 * Each email hooks its own trigger when constructed. They are loaded on
	 * `init`, once translations may be loaded, and before any order can complete.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load' ), 0 );
	}

	/**
	 * Load the emails so their triggers are hooked.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load(): void {
		$this->get_emails();
	}

	/**
	 * Get every registered email.
	 *
	 * @since 1.0.0
	 * @return array<string, Email> Emails keyed by id.
	 */
	public function get_emails(): array {
		if ( ! empty( $this->emails ) ) {
			return $this->emails;
		}

		/**
		 * Filters the registered email classes.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, class-string<Email>> $emails Fully-qualified Email subclass names.
		 */
		$classes = $this->app->apply_filters( 'email_classes', array( Receipt::class, NewOrder::class ) );

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) || ! is_subclass_of( $class, Email::class ) ) {
				continue;
			}

			$email = new $class();

			if ( '' !== $email->id ) {
				$this->emails[ $email->id ] = $email;
			}
		}

		return $this->emails;
	}

	/**
	 * Get one email.
	 *
	 * @since 1.0.0
	 * @param string $id Email id.
	 * @return Email|null Email, or null when it is not registered.
	 */
	public function get_email( string $id ): ?Email {
		return $this->get_emails()[ $id ] ?? null;
	}
}
