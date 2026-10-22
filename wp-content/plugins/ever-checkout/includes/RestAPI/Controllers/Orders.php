<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Models\Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Orders REST controller.
 *
 * Orders are written by the checkout and the gateways; the API reads them and
 * moves their status, which the model's transition table guards.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Orders extends Controller {

	/**
	 * Get the orders.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$query = ecout()->order->query()->with( array( 'items', 'refunds' ) );

		foreach ( $request->get_params() as $key => $value ) {
			$query->set_var( $key, $value );
		}

		if ( null === $request->get_param( 'status' ) ) {
			$query->set_var( 'status__not_in', array( 'cancelled' ) );
		}

		$filters = (array) $request->get_param( 'filters' );
		$fields  = wp_list_pluck( array_filter( $filters['filters'] ?? $filters, 'is_array' ), 'field' );

		// A refund is shown on the sale it belongs to; the list is sales unless a type is asked for.
		if ( null === $request->get_param( 'type' ) && ! in_array( 'type', $fields, true ) ) {
			$query->set_var( 'type', 'sale' );
		}

		$query->where_filters( $filters );
		$query = ecout()->apply_filters( 'rest_order_query', $query, $request );

		$total    = $query->count();
		$per_page = max( 1, (int) $query->get_var( 'per_page', 20 ) );
		$results  = array();

		foreach ( $query->get() as $item ) {
			$results[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $item, $request ) );
		}

		$response = rest_ensure_response( $results );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ( $total > 0 ? (int) ceil( $total / $per_page ) : 0 ) );

		return $response;
	}

	/**
	 * Get an order.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not found.
	 */
	public function get_item( $request ) {
		$item = ecout()->order->find( $request->get_param( 'id' ) );

		if ( ! $item ) {
			return $this->not_found();
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Update an order.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not saved.
	 */
	public function update_item( $request ) {
		$item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item = ecout()->apply_filters( 'rest_pre_insert_order', $item, $request );
		$item = $item->save();

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Delete an order.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not deleted.
	 */
	public function delete_item( $request ) {
		$item = ecout()->order->find( $request->get_param( 'id' ) );

		if ( ! $item ) {
			return $this->not_found();
		}

		$previous = $this->prepare_item_for_response( $item, $request )->get_data();
		$deleted  = $item->delete();

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Batch operations.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function batch_items( $request ) {
		return $this->process_batch( $request );
	}

	/**
	 * Retrieve the form options.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function get_options( $request ) {
		$options = array(
			'statuses' => $this->prepare_options( Order::get_statuses() ),
			'types'    => $this->prepare_options( Order::get_types() ),
		);

		/**
		 * Filters the order form options.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $options Options data.
		 * @param \WP_REST_Request     $request Request object.
		 */
		return rest_ensure_response( ecout()->apply_filters( 'rest_order_options', $options, $request ) );
	}

	/**
	 * Prepare an order for the response.
	 *
	 * @since 1.0.0
	 * @param Order           $item    Order.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$data = array();

		foreach ( $item->to_array() as $key => $value ) {
			switch ( $key ) {
				case 'created_at':
				case 'updated_at':
				case 'paid_at':
				case 'completed_at':
					$data[ $key ] = $this->prepare_date_response( $value );
					break;
				default:
					$data[ $key ] = $value;
					break;
			}
		}

		$gateway = ecout()->gateways->get_gateway( (string) $item->gateway );
		$actions = array();

		// A partial refund carries an amount only a gateway knows, so it is never offered by hand.
		foreach ( array_diff( Order::TRANSITIONS[ (string) $item->status ] ?? array(), array( 'partially_refunded' ) ) as $status ) {
			$actions[] = array(
				'action' => 'status',
				'value'  => $status,
				'label'  => Order::get_statuses()[ $status ] ?? $status,
			);
		}

		/**
		 * Filters the actions the order screen offers for an order.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, string>> $actions Actions, each with an `action`, a `label` and its own fields.
		 * @param Order                             $order   Order being prepared.
		 */
		$data['actions']         = ecout()->apply_filters( 'rest_order_actions', $actions, $item );
		$data['transaction_url'] = $gateway ? $gateway->get_transaction_url( $item ) : '';
		$data['refunded']        = 0;
		$data['items']           = array();
		$data['downloads']       = array();

		foreach ( (array) $item->get_relation_value( 'refunds' ) as $refund ) {
			$data['refunded'] += (int) $refund->get( 'total' );
		}

		foreach ( (array) $item->get_relation_value( 'items' ) as $line ) {
			$data['items'][] = $line->to_array();
		}

		$downloads = ecout()->make( Downloads::class );

		foreach ( $item->can_download() ? $item->get_downloads() : array() as $entry ) {
			foreach ( $entry['files'] as $file ) {
				$data['downloads'][] = $downloads->prepare_item_for_order( $file, $item, $request )->get_data();
			}
		}

		return ecout()->apply_filters( 'rest_prepare_order', rest_ensure_response( $data ), $item, $request );
	}

	/**
	 * Prepare an order for the database.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return Order|WP_Error Order, or why it was not found.
	 */
	public function prepare_item_for_database( $request ) {
		$id   = $request->get_url_params()['id'] ?? $request->get_param( 'id' );
		$item = $id ? ecout()->order->find( $id ) : ecout()->order;

		if ( ! $item ) {
			return $this->not_found();
		}

		foreach ( $this->get_writable_params( $request ) as $key => $value ) {
			switch ( $key ) {
				case 'created_at':
				case 'updated_at':
				case 'paid_at':
				case 'completed_at':
					$item->$key = $this->prepare_date_for_database( $value );
					break;
				default:
					$item->$key = $value;
					break;
			}
		}

		return $item;
	}

	/**
	 * Get the error for an order that does not exist.
	 *
	 * @since 1.0.0
	 * @return WP_Error Not-found error.
	 */
	protected function not_found(): WP_Error {
		return new WP_Error( 'rest_order_not_found', __( 'No order found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
	}

	/**
	 * Get the item schema.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Item schema data.
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => array(
				'id'             => array(
					'description' => __( 'Order number.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'order_key'      => array(
					'description' => __( 'Public handle used by the receipt link.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'parent_id'      => array(
					'description' => __( 'The sale a refund points at.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'type'           => array(
					'description' => __( 'Whether the row is a sale or a refund.', 'ever-checkout' ),
					'type'        => 'string',
					'enum'        => array_keys( Order::get_types() ),
					'readonly'    => true,
				),
				'status'         => array(
					'description' => __( 'Order status.', 'ever-checkout' ),
					'type'        => 'string',
					'enum'        => array_keys( Order::get_statuses() ),
				),
				'customer_id'    => array(
					'description' => __( 'Customer, zero until the payment completes.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'email'          => array(
					'description' => __( 'Customer email, empty until the payment completes.', 'ever-checkout' ),
					'type'        => 'string',
					'format'      => 'email',
					'readonly'    => true,
				),
				'name'           => array(
					'description' => __( 'Buyer name as typed at checkout.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'currency'       => array(
					'description' => __( 'Currency code.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'mode'           => array(
					'description' => __( 'Whether live or test keys took the payment.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'subtotal'       => array(
					'description' => __( 'Subtotal in minor units.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'discount'       => array(
					'description' => __( 'Discount in minor units.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'total'          => array(
					'description' => __( 'Total in minor units.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'refunded'       => array(
					'description' => __( 'Amount refunded so far, in minor units.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'discount_code'  => array(
					'description' => __( 'Promotion code applied at checkout.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'ip_address'     => array(
					'description' => __( 'Customer IP address.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'gateway'        => array(
					'description' => __( 'Gateway that took the payment.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'transaction_id' => array(
					'description' => __( 'The gateway\'s own id for the payment.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'payment_method' => array(
					'description' => __( 'What the customer paid with.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'paid_at'        => array(
					'description' => __( 'Date the gateway confirmed the payment.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'created_at'     => array(
					'description' => __( 'Date created.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'updated_at'     => array(
					'description' => __( 'Date updated.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'items'          => array(
					'description' => __( 'The things sold, with their names and prices at purchase.', 'ever-checkout' ),
					'type'        => 'array',
					'readonly'    => true,
				),
				'completed_at'   => array(
					'description' => __( 'Date the payment completed.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}
}
