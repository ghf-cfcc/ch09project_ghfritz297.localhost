<?php

namespace EverCheckout\Emails;

use EverCheckout\Models\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Receipt email, sent to the customer with their download links.
 *
 * @since   1.0.0
 * @package EverCheckout\Emails
 */
class Receipt extends Email {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id              = 'receipt';
		$this->title           = __( 'Receipt', 'ever-checkout' );
		$this->description     = __( 'Sent to the customer when a payment completes, with their download links.', 'ever-checkout' );
		$this->recipient_type  = 'customer';
		$this->default_subject = __( 'Your download from {site_name}', 'ever-checkout' );
		$this->default_heading = __( 'Thanks for your purchase', 'ever-checkout' );
		$this->default_body    = implode(
			"\n",
			array(
				'<p>' . __( 'Hi {customer_name},', 'ever-checkout' ) . '</p>',
				'<p>' . __( 'Thanks for buying {product_name}. Your download is ready.', 'ever-checkout' ) . '</p>',
				'{download_links}',
				'<p>' . __( 'Order {order_id} | {order_total} | {order_date}', 'ever-checkout' ) . '</p>',
				'<p>' . __( "If a link stops working, reply to this email and we'll sort it out.", 'ever-checkout' ) . '</p>',
			)
		);

		parent::__construct();

		ecout()->on_action( 'order_status_completed', array( $this, 'trigger' ) );
	}

	/**
	 * Send the receipt for a completed order.
	 *
	 * @since 1.0.0
	 * @param mixed ...$args The order, then the status it left.
	 * @return void
	 */
	public function trigger( ...$args ): void {
		$order = $args[0] ?? null;

		if ( ! $order instanceof Order || '' === (string) $order->email ) {
			return;
		}

		$this->object    = $order;
		$this->recipient = (string) $order->email;

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
			'{customer_name}'  => __( 'Customer name', 'ever-checkout' ),
			'{product_name}'   => __( 'Product name', 'ever-checkout' ),
			'{download_links}' => __( 'Download links', 'ever-checkout' ),
			'{order_id}'       => __( 'Order number', 'ever-checkout' ),
			'{order_total}'    => __( 'Order total', 'ever-checkout' ),
			'{order_date}'     => __( 'Order date', 'ever-checkout' ),
		) + parent::get_placeholders();
	}

	/**
	 * Fill the placeholder values for the order.
	 *
	 * Every substitution is escaped: a product name is seller input landing in markup.
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

		$customer = $order->customer_id ? ecout()->customer->find( $order->customer_id ) : null;

		$this->placeholders['{customer_name}']  = esc_html( $customer && $customer->name ? $customer->name : __( 'there', 'ever-checkout' ) );
		$this->placeholders['{product_name}']   = esc_html( $order->get_product_names() );
		$this->placeholders['{order_id}']       = (string) $order->id;
		$this->placeholders['{order_total}']    = esc_html( ecout()->money->format( (int) $order->total, (string) $order->currency ) );
		$this->placeholders['{order_date}']     = esc_html( (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $order->created_at ) ) );
		$this->placeholders['{download_links}'] = $this->get_download_links( $order );
	}

	/**
	 * Build the download list.
	 *
	 * Each link reads as the file name, never as a URL, and says nothing about
	 * limits a customer does not have.
	 *
	 * @since 1.0.0
	 * @param Order $order Order the email is about.
	 * @return string Markup listing every file the order granted.
	 */
	protected function get_download_links( Order $order ): string {
		$items = array();

		if ( ! $order->can_download() ) {
			return '';
		}

		foreach ( $order->get_downloads() as $entry ) {
			$product = $entry['product'];
			$expires = (string) $order->get_download_expiry( $product );
			$limit   = $product ? $product->get_download_limit() : (int) ecout()->options->get( 'download_limit', 0 );

			foreach ( $entry['files'] as $download ) {
				$label = $download->file_name ? $download->file_name : __( 'Your file', 'ever-checkout' );
				$note  = '';

				if ( '' !== $expires ) {
					/* translators: %s: expiry date. */
					$note = sprintf( __( 'Available until %s', 'ever-checkout' ), (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( $expires ) ) );
				} elseif ( $limit > 0 ) {
					$remaining = max( 0, $limit - $order->get_download_count( $download ) );
					/* translators: %d: downloads left. */
					$note = sprintf( _n( '%d download remaining', '%d downloads remaining', $remaining, 'ever-checkout' ), $remaining );
				}

				$items[] = sprintf(
					'<li><a href="%1$s">%2$s</a>%3$s</li>',
					esc_url( $order->get_download_url( $download ) ),
					esc_html( $label ),
					'' !== $note ? '<br><small>' . esc_html( $note ) . '</small>' : ''
				);
			}
		}

		return empty( $items ) ? '' : '<ul>' . implode( '', $items ) . '</ul>';
	}
}
