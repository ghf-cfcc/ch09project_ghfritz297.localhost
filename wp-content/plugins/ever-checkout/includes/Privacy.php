<?php

namespace EverCheckout;

use EverCheckout\B8\Component;
use EverCheckout\Models\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the WordPress privacy tools.
 *
 * Suggests the policy text, and answers the personal data export and erasure
 * requests under Tools by the customer's email.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @extends Component<Plugin>
 */
class Privacy extends Component {

	/**
	 * Orders handled per export or erasure page.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggest the privacy policy text.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_policy_content(): void {
		$content = '<p class="privacy-policy-tutorial">' . esc_html__( 'This sample text covers what the checkout collects. Edit it to match how you handle orders and which payment providers you use.', 'ever-checkout' ) . '</p>'
			. '<h2>' . esc_html__( 'Purchases', 'ever-checkout' ) . '</h2>'
			. '<p>' . esc_html__( 'When you buy from us we collect your email address, the products you bought, the amount paid, and the IP address the order was placed from. Each time a file is downloaded we record the time, the IP address and the browser it was sent to. We use this to deliver your download, send your receipt, protect the store from fraud and keep the accounting records the law requires. The order record is kept for as long as those records must be retained.', 'ever-checkout' ) . '</p>'
			. '<h2>' . esc_html__( 'Payments', 'ever-checkout' ) . '</h2>'
			. '<p>' . esc_html__( 'Payment details are entered on and processed by the payment provider you choose at checkout. We never see or store your card number. The provider shares the payment outcome and the email address on the payment with us so the order can be completed.', 'ever-checkout' ) . '</p>';

		wp_add_privacy_policy_content( 'Ever Checkout', wp_kses_post( wpautop( $content, false ) ) );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>> Exporters with ours added.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['ecout'] = array(
			'exporter_friendly_name' => __( 'Ever Checkout', 'ever-checkout' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>> Erasers with ours added.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['ecout'] = array(
			'eraser_friendly_name' => __( 'Ever Checkout', 'ever-checkout' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export the customer and orders behind an email address, one page at a time.
	 *
	 * @since 1.0.0
	 * @param string $email Email address the request is for.
	 * @param int    $page  Page of orders to export, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool} Export items and whether this was the last page.
	 */
	public function export( string $email, int $page = 1 ): array {
		$data = array();

		$customer = 1 === $page ? $this->app->customer->find( array( 'email' => $email ) ) : null;

		if ( $customer ) {
			$data[] = array(
				'group_id'    => 'ecout_customer',
				'group_label' => __( 'Customer', 'ever-checkout' ),
				'item_id'     => 'ecout-customer-' . $customer->id,
				'data'        => $this->prepare_fields(
					array(
						__( 'Email', 'ever-checkout' )   => $customer->email,
						__( 'Name', 'ever-checkout' )    => $customer->name,
						__( 'Country', 'ever-checkout' ) => $customer->country,
					)
				),
			);
		}

		$orders = $this->query_orders( $email, $page );

		foreach ( $orders as $order ) {
			$data[] = array(
				'group_id'    => 'ecout_orders',
				'group_label' => __( 'Orders', 'ever-checkout' ),
				'item_id'     => 'ecout-order-' . $order->id,
				'data'        => $this->prepare_fields(
					array(
						__( 'Order number', 'ever-checkout' ) => $order->id,
						__( 'Date', 'ever-checkout' )     => (string) $order->created_at,
						__( 'Status', 'ever-checkout' )   => $order->status,
						__( 'Products', 'ever-checkout' ) => $order->get_product_names(),
						__( 'Total', 'ever-checkout' )    => $this->app->money->format( (int) $order->total, (string) $order->currency ),
						__( 'Email', 'ever-checkout' )    => $order->email,
						__( 'IP address', 'ever-checkout' ) => $order->ip_address,
					)
				),
			);

			foreach ( (array) $order->logs()->get() as $log ) {
				$data[] = array(
					'group_id'    => 'ecout_downloads',
					'group_label' => __( 'Downloads', 'ever-checkout' ),
					'item_id'     => 'ecout-download-log-' . $log->get( 'id' ),
					'data'        => $this->prepare_fields(
						array(
							__( 'Order number', 'ever-checkout' ) => $log->get( 'order_id' ),
							__( 'Date', 'ever-checkout' ) => (string) $log->get( 'created_at' ),
							__( 'IP address', 'ever-checkout' ) => $log->get( 'ip_address' ),
							__( 'Browser', 'ever-checkout' ) => $log->get( 'user_agent' ),
						)
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => count( $orders ) < self::PER_PAGE,
		);
	}

	/**
	 * Erase the personal data behind an email address, one page at a time.
	 *
	 * The customer row goes; the orders stay as accounting records with the
	 * email and IP address blanked and the customer link cut.
	 *
	 * @since 1.0.0
	 * @param string $email Email address the request is for.
	 * @param int    $page  Page of orders to erase, from 1.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool} What happened on this page.
	 */
	public function erase( string $email, int $page = 1 ): array {
		global $wpdb;

		$removed  = false;
		$retained = false;
		$messages = array();

		$customer = 1 === $page ? $this->app->customer->find( array( 'email' => $email ) ) : null;

		if ( $customer && ! is_wp_error( $customer->delete() ) ) {
			$removed = true;
		}

		// Every erased order drops out of the match, so each page is the first page.
		$orders = $this->query_orders( $email, 1 );

		foreach ( $orders as $order ) {
			$order->fill(
				array(
					'customer_id' => 0,
					'email'       => '',
					'ip_address'  => '',
				)
			);

			if ( is_wp_error( $order->save() ) ) {
				$retained = true;
				/* translators: %d: order number. */
				$messages[] = sprintf( __( 'Order #%d could not be anonymised.', 'ever-checkout' ), $order->id );
				continue;
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk write to the plugin's own table; its model cache is flushed after the loop.
				$wpdb->prefix . $this->app->download_log->get_table(),
				array(
					'ip_address' => '',
					'user_agent' => '',
				),
				array( 'order_id' => (int) $order->id )
			);

			$removed = true;
		}

		$this->app->download_log->flush_cache();

		if ( $removed && 1 === $page ) {
			$messages[] = __( 'Orders were kept as accounting records with the personal data removed.', 'ever-checkout' );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => count( $orders ) < self::PER_PAGE,
		);
	}

	/**
	 * Get one page of the orders placed under an email address.
	 *
	 * @since 1.0.0
	 * @param string $email Customer email.
	 * @param int    $page  Page, from 1.
	 * @return array<int, Order> Orders on that page.
	 */
	protected function query_orders( string $email, int $page ): array {
		$orders = $this->app->order->query()
			->where( 'email', $email )
			->limit( self::PER_PAGE, ( max( 1, $page ) - 1 ) * self::PER_PAGE )
			->get();

		return array_filter( (array) $orders, static fn( $order ) => $order instanceof Order );
	}

	/**
	 * Shape labelled values the way the privacy tools expect.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $fields Values keyed by their label.
	 * @return array<int, array{name: string, value: string}> Name/value pairs, empty values dropped.
	 */
	protected function prepare_fields( array $fields ): array {
		$data = array();

		foreach ( $fields as $name => $value ) {
			if ( '' !== (string) $value ) {
				$data[] = array(
					'name'  => $name,
					'value' => (string) $value,
				);
			}
		}

		return $data;
	}
}
