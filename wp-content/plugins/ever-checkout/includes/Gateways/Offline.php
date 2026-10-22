<?php

namespace EverCheckout\Gateways;

use EverCheckout\Models\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Offline payment gateway.
 *
 * The customer pays outside the site by the seller's instructions; the seller
 * completes the order by hand once the money arrives.
 *
 * @since   1.0.0
 * @package EverCheckout\Gateways
 */
class Offline extends Gateway {

	/**
	 * Gateway id.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $id = 'offline';

	/**
	 * Capabilities this gateway supports.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	public array $supports = array( 'redirect' );

	/**
	 * Sort order among gateways.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public int $order = 20;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->title              = __( 'Bank transfer', 'ever-checkout' );
		$this->method_title       = __( 'Offline payment', 'ever-checkout' );
		$this->method_description = __( 'Show the customer how to pay you outside the site, then complete the order yourself once the money arrives.', 'ever-checkout' );
		$this->icon               = ecout()->assets_url( 'gateways/offline.svg' );

		parent::__construct();

		$this->description = (string) $this->get_option( 'instructions' );
	}

	/**
	 * Start a payment.
	 *
	 * The order stays pending; the receipt page shows the instructions.
	 *
	 * @since 1.0.0
	 * @param Order $order Order being paid.
	 * @return string|\WP_Error Where to send the customer, or why it failed.
	 */
	public function process_payment( Order $order ) {
		$order->gateway = $this->id;
		$saved          = $order->save();

		return is_wp_error( $saved ) ? $saved : $this->get_return_url( $order );
	}

	/**
	 * Get the gateway's settings fields.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled'      => array(
				'type'     => 'switch',
				'label'    => __( 'Enable offline payment', 'ever-checkout' ),
				'default'  => 'no',
				'sanitize' => 'yes_no',
			),
			'instructions' => array(
				'type'        => 'textarea',
				'label'       => __( 'Payment instructions', 'ever-checkout' ),
				'help'        => __( 'Shown to the customer on the receipt page. Include where to send the money and what to reference.', 'ever-checkout' ),
				'sanitize'    => 'textarea',
				'orientation' => 'vertical',
			),
		);
	}
}
