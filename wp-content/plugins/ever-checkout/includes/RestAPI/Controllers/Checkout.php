<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Checkout as Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout REST controller.
 *
 * Public routes: a customer has no account and no nonce. `start()` guards itself
 * with a rate limit, and the status route reveals nothing the receipt page does
 * not already show to whoever holds the order key.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Checkout extends Controller {

	/**
	 * Start a checkout.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Where the gateway takes the customer next, or why it refused.
	 */
	public function create_item( $request ) {
		$fields = ecout()->request->sanitize(
			array_filter( (array) $request->get_json_params(), 'is_scalar' ),
			array(
				'product_id'      => 'integer',
				'gateway'         => 'key',
				'name'            => 'string',
				'email'           => 'trim|lower',
				Service::HONEYPOT => 'string',
			)
		);

		$result = ecout()->get( Service::class )->start( $fields );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array(
			'order_key' => (string) $result['order']->order_key,
			'gateway'   => (string) ( $fields['gateway'] ?? '' ),
		);

		if ( ! empty( $result['redirect'] ) ) {
			$data['redirect'] = $result['redirect'];
		} else {
			$data['params'] = $result['params'];
		}

		return new WP_REST_Response( $data, 201 );
	}

	/**
	 * Get what the overlay shows for a product.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Product summary, or why there is none.
	 */
	public function get_product( $request ) {
		$product = ecout()->product->find( (int) $request->get_param( 'id' ) );

		if ( ! $product || 'active' !== $product->status ) {
			return new WP_Error( 'rest_product_not_for_sale', __( 'That product is not for sale.', 'ever-checkout' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( ecout()->get( Service::class )->get_product_summary( $product ) );
	}

	/**
	 * Get where an order stands, for the block to poll after an overlay or embedded payment.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Status and, once complete, the receipt URL.
	 */
	public function get_item( $request ) {
		$order = ecout()->order->find( array( 'order_key' => (string) $request->get_param( 'order_key' ) ) );

		if ( ! $order ) {
			return new WP_Error( 'rest_order_not_found', __( 'No order found with that key.', 'ever-checkout' ), array( 'status' => 404 ) );
		}

		$data = array( 'status' => (string) $order->status );

		if ( 'completed' === $order->status ) {
			$page            = get_permalink( (int) ecout()->options->get( 'receipt_page' ) );
			$data['receipt'] = add_query_arg( \EverCheckout\Plugin::QUERY_VARS['receipt'], $order->order_key, $page ? $page : home_url( '/' ) );
		}

		return rest_ensure_response( $data );
	}
}
