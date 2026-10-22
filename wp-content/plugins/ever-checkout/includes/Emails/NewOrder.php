<?php

namespace EverCheckout\Emails;

use EverCheckout\Models\Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * New order email, sent to the seller when a payment completes.
 *
 * @since   1.0.0
 * @package EverCheckout\Emails
 */
class NewOrder extends Email {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id              = 'new_order';
		$this->title           = __( 'New order', 'ever-checkout' );
		$this->description     = __( 'Sent to you when a payment completes.', 'ever-checkout' );
		$this->recipient_type  = 'admin';
		$this->default_subject = __( 'New order: {product_name}, {order_total}', 'ever-checkout' );
		$this->default_heading = __( 'New order', 'ever-checkout' );
		$this->default_body    = implode(
			"\n",
			array(
				'<p><strong>{product_name}</strong><br>{order_total}</p>',
				'<p>' . __( 'Customer: {customer_email}', 'ever-checkout' ) . '<br>' . __( 'Order: {order_id}', 'ever-checkout' ) . '<br>' . __( 'Paid with: {payment_method}', 'ever-checkout' ) . '</p>',
				'<p><a href="{admin_order_url}">' . __( 'View the order', 'ever-checkout' ) . '</a></p>',
			)
		);

		parent::__construct();

		ecout()->on_action( 'order_status_completed', array( $this, 'trigger' ) );
	}

	/**
	 * Send the notification for a completed order.
	 *
	 * @since 1.0.0
	 * @param mixed ...$args The order, then the status it left.
	 * @return void
	 */
	public function trigger( ...$args ): void {
		$order = $args[0] ?? null;

		if ( ! $order instanceof Order ) {
			return;
		}

		$this->object    = $order;
		$this->recipient = (string) $this->get_option( 'recipient', get_option( 'admin_email' ) );

		$this->populate_placeholders();
		$this->send();
	}

	/**
	 * Get the placeholders the seller may use, keyed by tag.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Descriptions keyed by tag.
	 */
	public function get_placeholders(): array {
		return array(
			'{product_name}'    => __( 'Product name', 'ever-checkout' ),
			'{order_total}'     => __( 'Order total', 'ever-checkout' ),
			'{customer_email}'  => __( 'Customer email', 'ever-checkout' ),
			'{order_id}'        => __( 'Order number', 'ever-checkout' ),
			'{payment_method}'  => __( 'Payment method', 'ever-checkout' ),
			'{admin_order_url}' => __( 'Link to the order', 'ever-checkout' ),
		) + parent::get_placeholders();
	}

	/**
	 * Fill the placeholder values for the order.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function populate_placeholders(): void {
		parent::populate_placeholders();

		$order = $this->object;

		if ( ! $order instanceof Order ) {
			return;
		}

		$this->placeholders['{product_name}']    = esc_html( $order->get_product_names() );
		$this->placeholders['{order_total}']     = esc_html( ecout()->money->format( (int) $order->total, (string) $order->currency ) );
		$this->placeholders['{customer_email}']  = esc_html( (string) $order->email );
		$this->placeholders['{order_id}']        = (string) $order->id;
		$this->placeholders['{payment_method}']  = esc_html( (string) $order->payment_method );
		$this->placeholders['{admin_order_url}'] = esc_url( admin_url( 'admin.php?page=ever-checkout#/orders/' . $order->id ) );
	}

	/**
	 * Get the settings fields the seller can edit.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Fields keyed by setting key.
	 */
	public function get_settings_fields(): array {
		$fields = parent::get_settings_fields();

		return array(
			'enabled'   => $fields['enabled'],
			'recipient' => array(
				'type'     => 'text',
				'label'    => __( 'Recipient(s)', 'ever-checkout' ),
				'help'     => __( 'Comma separated.', 'ever-checkout' ),
				'default'  => get_option( 'admin_email' ),
				'sanitize' => static function ( $value ) {
					$addresses = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );

					if ( array() === $addresses || array_filter( $addresses, static fn( string $address ) => ! is_email( $address ) ) ) {
						return new WP_Error( 'ecout_email_recipient', __( 'Enter one or more email addresses, separated by commas.', 'ever-checkout' ), array( 'status' => 400 ) );
					}

					return implode( ', ', array_map( 'sanitize_email', $addresses ) );
				},
			),
		) + $fields;
	}
}
