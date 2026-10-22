<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Models\DownloadLog;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Download logs REST controller.
 *
 * A log row is a fact about a file that was served; the API reads them and never
 * writes or removes one.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class DownloadLogs extends Controller {

	/**
	 * Get the download logs.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$query = ecout()->download_log->query()->with( array( 'download', 'product' ) );

		foreach ( $request->get_params() as $key => $value ) {
			$query->set_var( $key, $value );
		}

		if ( ! $request->get_param( 'orderby' ) ) {
			$query->set_var( 'orderby', 'id' );
			$query->set_var( 'order', 'DESC' );
		}

		$query->where_filters( (array) $request->get_param( 'filters' ) );
		$query = ecout()->apply_filters( 'rest_download_log_query', $query, $request );

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
	 * Get a download log.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not found.
	 */
	public function get_item( $request ) {
		$item = ecout()->download_log->find( $request->get_param( 'id' ) );

		if ( ! $item ) {
			return new WP_Error( 'rest_download_log_not_found', __( 'No download log found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Prepare a download log for the response.
	 *
	 * @since 1.0.0
	 * @param DownloadLog     $item    Download log.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$data = array();

		foreach ( $item->to_array() as $key => $value ) {
			switch ( $key ) {
				case 'created_at':
					$data[ $key ] = $this->prepare_date_response( $value );
					break;
				default:
					$data[ $key ] = $value;
					break;
			}
		}

		$download = $item->get_relation_value( 'download' );
		$product  = $item->get_relation_value( 'product' );

		$data['name']         = $download ? (string) ( $download->name ? $download->name : $download->file_name ) : '';
		$data['product_name'] = $product ? (string) $product->name : '';

		return ecout()->apply_filters( 'rest_prepare_download_log', rest_ensure_response( $data ), $item, $request );
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
			'title'      => 'download_log',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'order_id'    => array(
					'description' => __( 'The order the file was served to.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'product_id'  => array(
					'description' => __( 'The product the file belongs to.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'download_id' => array(
					'description' => __( 'The file that was served.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'customer_id' => array(
					'description' => __( 'The customer the file was served to.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'ip_address'  => array(
					'description' => __( 'Address the file was served to.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'user_agent'  => array(
					'description' => __( 'Browser the file was served to.', 'ever-checkout' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'created_at'  => array(
					'description' => __( 'Date served.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}
}
