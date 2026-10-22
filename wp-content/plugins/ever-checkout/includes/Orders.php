<?php

namespace EverCheckout;

use EverCheckout\B8\Component;
use EverCheckout\Models\Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the order lifecycle.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @extends Component<Plugin>
 */
class Orders extends Component {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		$this->app->on_action( 'order_pre_status_completed', array( $this, 'maybe_create_customer' ) );
		$this->app->on_action( 'order_status_refunded', array( $this, 'create_refund' ), 5 );
		add_action( Installer::DAILY_HOOK, array( $this, 'cancel_unpaid_orders' ) );
	}

	/**
	 * Complete an order once its payment has arrived.
	 *
	 * @since 1.0.0
	 * @param Order                $order   Order that was paid.
	 * @param array<string, mixed> $details Customer details the gateway reported: email, name, country.
	 * @return Order|WP_Error The order, or why it could not be completed.
	 */
	public function payment_complete( Order $order, array $details = array() ) {
		$details = $this->app->request->sanitize(
			$details,
			array(
				'email'   => 'email|lower',
				'name'    => 'string',
				'country' => 'upper',
			)
		);

		if ( '' !== (string) ( $details['email'] ?? '' ) ) {
			$order->email = (string) $details['email'];
		}

		$order->status = 'completed';
		$saved         = $order->save();

		if ( is_wp_error( $saved ) || ! $order->customer_id ) {
			return $saved;
		}

		$customer = $this->app->customer->find( $order->customer_id );

		if ( $customer ) {
			$customer->name    = $customer->name ? $customer->name : (string) ( ( $details['name'] ?? '' ) ? $details['name'] : $order->name );
			$customer->country = $customer->country ? $customer->country : (string) ( $details['country'] ?? '' );
			$customer->save();
		}

		return $saved;
	}

	/**
	 * Create the customer an order is about to be completed for, when it has none.
	 *
	 * @since 1.0.0
	 * @param Order $order Order about to be completed.
	 * @return void
	 */
	public function maybe_create_customer( Order $order ): void {
		$email = (string) $order->email;

		if ( $order->customer_id || '' === $email ) {
			return;
		}

		$customer = $this->app->customer->find( array( 'email' => $email ) );

		if ( ! $customer ) {
			$customer = $this->app->customer->insert( array( 'email' => $email ) );
		}

		$order->customer_id = $customer instanceof Models\Customer ? $customer->id : 0;
	}

	/**
	 * Record the refund of whatever a refunded order has not yet refunded.
	 *
	 * A gateway that reports its own refunds has already written them; a status
	 * set by hand has not.
	 *
	 * @since 1.0.0
	 * @param Order $order Refunded order.
	 * @return void
	 */
	public function create_refund( Order $order ): void {
		$refunded = 0;

		foreach ( (array) $order->refunds()->get() as $refund ) {
			$refunded += (int) $refund->get( 'total' );
		}

		$amount = (int) $order->total - $refunded;

		if ( $amount <= 0 ) {
			return;
		}

		$refund = $this->app->order->insert(
			array(
				'parent_id'      => $order->id,
				'type'           => 'refund',
				'status'         => 'completed',
				'customer_id'    => $order->customer_id,
				'email'          => $order->email,
				'currency'       => $order->currency,
				'mode'           => $order->mode,
				'subtotal'       => $amount,
				'total'          => $amount,
				'gateway'        => $order->gateway,
				'payment_method' => $order->payment_method,
			)
		);

		if ( is_wp_error( $refund ) ) {
			$this->app->logger->error( sprintf( 'Order #%d was refunded but the refund could not be recorded: %s', $order->id, $refund->get_error_message() ) );
		}
	}

	/**
	 * Cancel orders nobody paid for.
	 *
	 * Nothing is deleted: a seller asking whether someone tried to buy and failed
	 * is asking a real question.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cancel_unpaid_orders(): void {
		$hours  = max( 1, (int) $this->app->options->get( 'abandoned_after_hours', 24 ) );
		$before = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$hours} hours" ) );

		$after = 0;

		do {
			$orders = $this->app->order->query()
				->where( 'status', 'pending' )
				->where( 'created_at', '<', $before )
				->where( 'id', '>', $after )
				->order_by( 'id', 'ASC' )
				->limit( 200 )
				->get();
			$found  = count( (array) $orders );

			foreach ( (array) $orders as $order ) {
				$after = (int) $order->get( 'id' );

				if ( empty( $order->get( 'paid_at' ) ) ) {
					$order->set( 'status', 'cancelled' );
					$order->save();
				}
			}
		} while ( 200 === $found );
	}
}
