<?php

namespace EverCheckout\Gateways;

use EverCheckout\Models\Order;
use EverCheckout\Orders;

defined( 'ABSPATH' ) || exit;

/**
 * Test payment gateway.
 *
 * Completes an order without taking money, so the whole flow can be walked
 * through before a real gateway is connected.
 *
 * @since   1.0.0
 * @package EverCheckout\Gateways
 */
class Test extends Gateway {

	/**
	 * Gateway id.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $id = 'test';

	/**
	 * Capabilities this gateway supports.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	public array $supports = array( 'refunds', 'partial_refunds', 'redirect' );

	/**
	 * Sort order among gateways.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public int $order = 100;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->title              = __( 'Test payment', 'ever-checkout' );
		$this->method_title       = __( 'Test payment', 'ever-checkout' );
		$this->method_description = __( 'Completes orders without taking any money. For trying the checkout only; never enable it on a live store.', 'ever-checkout' );
		$this->icon               = ecout()->assets_url( 'gateways/test.svg' );

		parent::__construct();

		$this->test_mode = true;
	}

	/**
	 * Whether the gateway may be offered; only to someone who can manage the store.
	 *
	 * @since 1.0.0
	 * @param Order|null $order Order being paid, when known.
	 * @return bool
	 */
	public function is_available( ?Order $order = null ): bool {
		return parent::is_available( $order ) && current_user_can( 'manage_options' );
	}

	/**
	 * Start a payment, which succeeds at once.
	 *
	 * @since 1.0.0
	 * @param Order $order Order being paid.
	 * @return string|\WP_Error Where to send the customer, or why it failed.
	 */
	public function process_payment( Order $order ) {
		$order->gateway        = $this->id;
		$order->transaction_id = 'test_' . $order->order_key;
		$order->payment_method = __( 'Test', 'ever-checkout' );
		$order->paid_at        = current_time( 'mysql', true );

		$completed = ecout()->get( Orders::class )->payment_complete( $order );

		return is_wp_error( $completed ) ? $completed : $this->get_return_url( $order );
	}

	/**
	 * Refund an amount against an order.
	 *
	 * @since 1.0.0
	 * @param Order  $order  Order being refunded.
	 * @param int    $amount Amount in minor units.
	 * @param string $reason Reason given by the seller.
	 * @return true
	 */
	public function process_refund( Order $order, int $amount, string $reason = '' ) {
		return true;
	}

	/**
	 * Get the gateway's settings fields.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled' => array(
				'type'     => 'switch',
				'label'    => __( 'Enable test payments', 'ever-checkout' ),
				'default'  => 'no',
				'sanitize' => 'yes_no',
			),
		);
	}
}
