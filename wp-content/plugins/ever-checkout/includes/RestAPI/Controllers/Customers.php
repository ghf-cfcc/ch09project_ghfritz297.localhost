<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Models\Customer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Customers REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Customers extends Controller {

	/**
	 * Get the customers.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$query = ecout()->customer->query();

		foreach ( $request->get_params() as $key => $value ) {
			$query->set_var( $key, $value );
		}

		$query->where_filters( (array) $request->get_param( 'filters' ) );
		$query = ecout()->apply_filters( 'rest_customer_query', $query, $request );

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
	 * Get a customer.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not found.
	 */
	public function get_item( $request ) {
		$item = ecout()->customer->find( $request->get_param( 'id' ) );

		if ( ! $item ) {
			return $this->not_found();
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Create a customer.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not saved.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request->get_param( 'id' ) ) ) {
			return new WP_Error( 'rest_customer_exists', __( 'Cannot create a customer that already has an id.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		$item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item = ecout()->apply_filters( 'rest_pre_insert_customer', $item, $request );
		$item = $item->save();

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$response = $this->prepare_item_for_response( $item, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $request->get_route() . '/' . $item->id ) );

		return $response;
	}

	/**
	 * Update a customer.
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

		$item = ecout()->apply_filters( 'rest_pre_insert_customer', $item, $request );
		$item = $item->save();

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Delete a customer.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not deleted.
	 */
	public function delete_item( $request ) {
		$item = ecout()->customer->find( $request->get_param( 'id' ) );

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
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_options( $request ) {
		$options = array();

		return rest_ensure_response( ecout()->apply_filters( 'rest_customer_options', $options, $request ) );
	}

	/**
	 * Prepare a customer for the response.
	 *
	 * @since 1.0.0
	 * @param Customer        $item    Customer.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$data = array();

		foreach ( $item->to_array() as $key => $value ) {
			switch ( $key ) {
				case 'created_at':
				case 'updated_at':
					$data[ $key ] = $this->prepare_date_response( $value );
					break;
				default:
					$data[ $key ] = $value;
					break;
			}
		}

		return ecout()->apply_filters( 'rest_prepare_customer', rest_ensure_response( $data ), $item, $request );
	}

	/**
	 * Prepare a customer for the database.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return Customer|WP_Error Customer, or why it was not found.
	 */
	public function prepare_item_for_database( $request ) {
		$id   = $request->get_url_params()['id'] ?? $request->get_param( 'id' );
		$item = $id ? ecout()->customer->find( $id ) : ecout()->customer;

		if ( ! $item ) {
			return $this->not_found();
		}

		foreach ( $this->get_writable_params( $request ) as $key => $value ) {
			switch ( $key ) {
				case 'created_at':
				case 'updated_at':
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
	 * Get the error for a customer that does not exist.
	 *
	 * @since 1.0.0
	 * @return WP_Error Not-found error.
	 */
	protected function not_found(): WP_Error {
		return new WP_Error( 'rest_customer_not_found', __( 'No customer found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
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
			'title'      => 'customer',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'description' => __( 'Unique identifier.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'email'      => array(
					'description' => __( 'Email address the customer is keyed by.', 'ever-checkout' ),
					'type'        => 'string',
					'format'      => 'email',
					'maxLength'   => 100,
				),
				'name'       => array(
					'description' => __( 'Customer name, as the gateway reported it.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 255,
				),
				'country'    => array(
					'description' => __( 'Two-letter country code.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 2,
				),
				'user_id'    => array(
					'description' => __( 'The WordPress user this customer belongs to, if any.', 'ever-checkout' ),
					'type'        => 'integer',
				),
				'created_at' => array(
					'description' => __( 'Date created.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'updated_at' => array(
					'description' => __( 'Date updated.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}
}
