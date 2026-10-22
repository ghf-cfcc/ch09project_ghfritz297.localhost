<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Models\Download;
use EverCheckout\Models\Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Downloads REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Downloads extends Controller {

	/**
	 * Get the downloads.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$query = ecout()->download->query();

		foreach ( $request->get_params() as $key => $value ) {
			$query->set_var( $key, $value );
		}

		if ( ! $request->get_param( 'orderby' ) ) {
			$query->set_var( 'orderby', 'position' );
		}

		$query->where_filters( (array) $request->get_param( 'filters' ) );
		$query = ecout()->apply_filters( 'rest_download_query', $query, $request );

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
	 * Get a download.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not found.
	 */
	public function get_item( $request ) {
		$item = ecout()->download->find( $request->get_param( 'id' ) );

		if ( ! $item ) {
			return $this->not_found();
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Create a download.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not saved.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request->get_param( 'id' ) ) ) {
			return new WP_Error( 'rest_download_exists', __( 'Cannot create a download that already has an id.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		$item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item  = ecout()->apply_filters( 'rest_pre_insert_download', $item, $request );
		$saved = $item->save();

		if ( is_wp_error( $saved ) ) {
			if ( 'relative' === $item->get_file_type() && ecout()->fs->exists( $item->get_full_path() ) ) {
				ecout()->fs->delete( $item->get_full_path() );
			}

			return $saved;
		}

		$response = $this->prepare_item_for_response( $saved, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $request->get_route() . '/' . $item->id ) );

		return $response;
	}

	/**
	 * Update a download.
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

		$item = ecout()->apply_filters( 'rest_pre_insert_download', $item, $request );
		$item = $item->save();

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Delete a download.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not deleted.
	 */
	public function delete_item( $request ) {
		$item = ecout()->download->find( $request->get_param( 'id' ) );

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
	 * Retrieve the form options.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_options( $request ) {
		$options = array(
			'allowed_types' => array_keys( ecout()->download->get_allowed_types() ),
			'max_size'      => wp_max_upload_size(),
		);

		return rest_ensure_response( ecout()->apply_filters( 'rest_download_options', $options, $request ) );
	}

	/**
	 * Prepare a download for the response.
	 *
	 * @since 1.0.0
	 * @param Download        $item    Download.
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

		$data['name'] = $item->name ? $item->name : $item->file_name;
		$data['size'] = (int) $item->file_size;
		$data['type'] = (string) $item->mime_type;

		return ecout()->apply_filters( 'rest_prepare_download', rest_ensure_response( $data ), $item, $request );
	}

	/**
	 * Prepare a download for the response as one an order may fetch.
	 *
	 * @since 1.0.0
	 * @param Download        $item    Download.
	 * @param Order           $order   Order entitled to it.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_order( Download $item, Order $order, WP_REST_Request $request ): WP_REST_Response {
		$response = $this->prepare_item_for_response( $item, $request );
		$product  = $item->get_relation_value( 'product' );

		$response->set_data(
			(array) $response->get_data() + array(
				'product_name'   => $product ? (string) $product->name : '',
				'url'            => $order->get_download_url( $item ),
				'download_count' => $order->get_download_count( $item ),
				'download_limit' => $product ? $product->get_download_limit() : (int) ecout()->options->get( 'download_limit', 0 ),
				'expires_at'     => $this->prepare_date_response( $order->get_download_expiry( $product ) ),
			)
		);

		return $response;
	}

	/**
	 * Prepare a download for the database.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return Download|WP_Error Download, or why it was not found.
	 */
	public function prepare_item_for_database( $request ) {
		$id   = $request->get_url_params()['id'] ?? $request->get_param( 'id' );
		$item = $id ? ecout()->download->find( $id ) : ecout()->download;

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

		$files = $request->get_file_params();

		if ( ! empty( $files['file'] ) ) {
			$stored = ecout()->download->upload( (array) $files['file'] );

			if ( is_wp_error( $stored ) ) {
				$stored->add_data( array( 'status' => 400 ) );

				return $stored;
			}

			$item->fill( $stored );
		} elseif ( $request->has_param( 'file_path' ) && 'absolute' !== $item->get_file_type() ) {
			return new WP_Error( 'rest_download_file_path', __( 'Upload the file, or give the full URL of a file hosted elsewhere.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		return $item;
	}

	/**
	 * Get the error for a download that does not exist.
	 *
	 * @since 1.0.0
	 * @return WP_Error Not-found error.
	 */
	protected function not_found(): WP_Error {
		return new WP_Error( 'rest_download_not_found', __( 'No download found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
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
			'title'      => 'download',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'description' => __( 'Unique identifier.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'product_id' => array(
					'description' => __( 'The product this file belongs to.', 'ever-checkout' ),
					'type'        => 'integer',
					'minimum'     => 0,
				),
				'name'       => array(
					'description' => __( 'Label shown to the customer on the receipt.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 255,
				),
				'file_path'  => array(
					'description' => __( 'Full URL of a file hosted elsewhere. Set by the upload otherwise.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 2000,
				),
				'file_name'  => array(
					'description' => __( 'Original filename, restored when the file is served.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 255,
				),
				'file_size'  => array(
					'description' => __( 'File size in bytes, zero when unknown.', 'ever-checkout' ),
					'type'        => 'integer',
					'minimum'     => 0,
				),
				'mime_type'  => array(
					'description' => __( 'MIME type captured at upload.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 100,
				),
				'enabled'    => array(
					'description' => __( 'Whether the file is still offered to customers.', 'ever-checkout' ),
					'type'        => 'boolean',
					'default'     => true,
				),
				'position'   => array(
					'description' => __( 'Sort order within the product.', 'ever-checkout' ),
					'type'        => 'integer',
					'default'     => 0,
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
