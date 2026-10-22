<?php

namespace EverCheckout\Gateways;

use EverCheckout\Plugin;
use EverCheckout\Models\Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Base payment gateway.
 *
 * @since   1.0.0
 * @package EverCheckout\Gateways
 */
abstract class Gateway {

	/**
	 * Gateway id.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $id = '';

	/**
	 * Title shown to the customer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $title = '';

	/**
	 * Description shown to the customer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $description = '';

	/**
	 * Icon markup or URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $icon = '';

	/**
	 * Title shown to the seller.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $method_title = '';

	/**
	 * Description shown to the seller.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $method_description = '';

	/**
	 * Whether the gateway is switched on.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public bool $enabled = false;

	/**
	 * Whether the gateway is running against test credentials.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public bool $test_mode = false;

	/**
	 * Whether the gateway renders fields of its own at checkout.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public bool $has_fields = false;

	/**
	 * Sort order among the registered gateways.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public int $order = 10;

	/**
	 * Capabilities this gateway supports.
	 *
	 * Checkout shape: `redirect`, `overlay`, `embedded`, `express`. Money:
	 * `refunds`, `partial_refunds`.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	public array $supports = array( 'refunds' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->enabled   = 'yes' === $this->get_option( 'enabled' );
		$this->test_mode = 'yes' === $this->get_option( 'test_mode', 'yes' );
	}

	/**
	 * Start a payment.
	 *
	 * A redirect gateway returns the URL to send the customer to. An overlay or
	 * embedded gateway returns the parameters its script module mounts with.
	 *
	 * @since 1.0.0
	 * @param Order $order Order being paid.
	 * @return string|array<string, mixed>|WP_Error Redirect URL, client parameters, or why it failed.
	 */
	abstract public function process_payment( Order $order );

	/**
	 * Refund an amount against an order.
	 *
	 * @since 1.0.0
	 * @param Order  $order  Order being refunded.
	 * @param int    $amount Amount in minor units.
	 * @param string $reason Reason given by the seller.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function process_refund( Order $order, int $amount, string $reason = '' ) {
		return new WP_Error( 'unsupported', __( 'This gateway cannot refund.', 'ever-checkout' ) );
	}

	/**
	 * Handle an inbound gateway callback.
	 *
	 * Reached at `POST /ecout/gateways/{id}/webhook`. The route is public, so the
	 * gateway must verify the request before acting on it.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request the gateway sent.
	 * @return WP_REST_Response|WP_Error Response, or why the request was refused.
	 */
	public function handle_callback( WP_REST_Request $request ) {
		return new WP_Error( 'ecout_no_callback', __( 'This gateway does not accept callbacks.', 'ever-checkout' ), array( 'status' => 404 ) );
	}

	/**
	 * Whether this gateway runs its own renewal schedule.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function schedules_renewals(): bool {
		return false;
	}

	/**
	 * Get the deep link to a payment in the gateway's own dashboard.
	 *
	 * @since 1.0.0
	 * @param Order $order Order whose payment to link to.
	 * @return string
	 */
	public function get_transaction_url( Order $order ): string {
		return '';
	}

	/**
	 * Render the gateway's own checkout fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function payment_fields(): void {}

	/**
	 * Validate the gateway's own checkout fields.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $data Submitted data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_fields( array $data ) {
		return true;
	}

	/**
	 * Whether the gateway can take this order.
	 *
	 * @since 1.0.0
	 * @param Order|null $order Order being paid, when one exists yet.
	 * @return bool
	 */
	public function is_available( ?Order $order = null ): bool {
		return $this->enabled && ! $this->needs_setup();
	}

	/**
	 * Whether the gateway still needs configuring.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function needs_setup(): bool {
		return false;
	}

	/**
	 * Whether the gateway supports a capability.
	 *
	 * @since 1.0.0
	 * @param string $feature Capability name.
	 * @return bool
	 */
	public function supports( string $feature ): bool {
		return in_array( $feature, $this->supports, true );
	}

	/**
	 * Enqueue the gateway's checkout assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {}

	/**
	 * Get the data the gateway's script needs.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		return array();
	}

	/**
	 * Get the gateway's settings fields.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_settings_fields(): array {
		return array();
	}

	/**
	 * Get a gateway option.
	 *
	 * @since 1.0.0
	 * @param string $key      Option key, without the prefix.
	 * @param mixed  $fallback Value when the option is unset.
	 * @return mixed
	 */
	public function get_option( string $key, $fallback = '' ) {
		return ecout()->options->get( "gateway_{$this->id}_{$key}", $fallback );
	}

	/**
	 * Get the receipt URL a customer lands on after paying.
	 *
	 * @since 1.0.0
	 * @param Order $order Order being paid.
	 * @return string Receipt page URL carrying the order key.
	 */
	public function get_return_url( Order $order ): string {
		$page = get_permalink( (int) ecout()->options->get( 'receipt_page' ) );

		return add_query_arg( Plugin::QUERY_VARS['receipt'], $order->order_key, $page ? $page : home_url( '/' ) );
	}

	/**
	 * Get the URL the gateway delivers callbacks to.
	 *
	 * @since 1.0.0
	 * @return string Webhook URL.
	 */
	public function get_webhook_url(): string {
		return rest_url( ecout()->rest_prefix . "/gateways/{$this->id}/webhook" );
	}

	/**
	 * Update a gateway option.
	 *
	 * @since 1.0.0
	 * @param string    $key      Option key, without the prefix.
	 * @param mixed     $value    Value to store.
	 * @param bool|null $autoload Whether to load the option on every request; null keeps the default.
	 * @return bool
	 */
	public function update_option( string $key, $value, ?bool $autoload = null ): bool {
		return (bool) ecout()->options->update( "gateway_{$this->id}_{$key}", $value, $autoload );
	}

	/**
	 * Check submitted settings before they are stored.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $values Sanitised values keyed by field.
	 * @return true|WP_Error True when they may be stored, or why not.
	 */
	public function validate_settings( array $values ) {
		return true;
	}
}
