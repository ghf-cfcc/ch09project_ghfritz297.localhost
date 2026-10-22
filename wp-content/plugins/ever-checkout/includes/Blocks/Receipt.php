<?php

namespace EverCheckout\Blocks;

use EverCheckout\Models\Order;
use EverCheckout\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Receipt block.
 *
 * @since   1.0.0
 * @package EverCheckout\Blocks
 */
class Receipt extends Block {

	/**
	 * Block name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $name = 'ever-checkout/receipt';

	/**
	 * Get the order the current request names.
	 *
	 * The key is the credential: a customer arrives from the gateway with no session.
	 *
	 * @since 1.0.0
	 * @return \EverCheckout\Models\Order|null Order, or null when the key is missing or unknown.
	 */
	public function get_order() {
		$key = ecout()->request->sanitize( get_query_var( Plugin::QUERY_VARS['receipt'] ), 'alpha_num' );

		return '' === $key ? null : ecout()->order->find( array( 'order_key' => $key ) );
	}

	/**
	 * Get which of the receipt's four states an order is in.
	 *
	 * @since 1.0.0
	 * @param Order|null $order Order the page is for, when found.
	 * @return string One of `not-found`, `pending`, `completed` or `closed`.
	 */
	public function get_state( ?Order $order ): string {
		if ( ! $order ) {
			return 'not-found';
		}

		if ( 'pending' === $order->status ) {
			return 'pending';
		}

		return in_array( $order->status, array( 'completed', 'partially_refunded' ), true ) ? 'completed' : 'closed';
	}

	/**
	 * Get the receipt strings, falling back to the defaults where the block left one empty.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string> Strings keyed by attribute name.
	 */
	public function get_strings( array $attributes ): array {
		$defaults = array(
			'completedHeading' => __( 'Thanks, your download is ready', 'ever-checkout' ),
			/* translators: %s: customer email. */
			'completedNote'    => __( 'A copy of these links was sent to %s. Keep that email; it is your receipt.', 'ever-checkout' ),
			'closedHeading'    => __( 'This order is closed.', 'ever-checkout' ),
			'pendingHeading'   => __( 'Thanks, one more step', 'ever-checkout' ),
			/* translators: %s: customer email. */
			'pendingNote'      => __( 'Your order is reserved. We will email your download links to %s as soon as the payment arrives.', 'ever-checkout' ),
			'notFoundHeading'  => __( 'We could not find that order.', 'ever-checkout' ),
			'downloadLabel'    => __( 'Download', 'ever-checkout' ),
			'helpText'         => __( 'Trouble with a file? Reply to your receipt email and we will sort it out.', 'ever-checkout' ),
		);

		foreach ( $defaults as $key => $default ) {
			$value            = trim( (string) ( $attributes[ $key ] ?? '' ) );
			$defaults[ $key ] = '' !== $value ? $value : $default;
		}

		return $defaults;
	}
}
