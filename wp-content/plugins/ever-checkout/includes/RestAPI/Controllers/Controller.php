<?php

namespace EverCheckout\RestAPI\Controllers;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Base REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI
 */
abstract class Controller extends WP_REST_Controller {

	/**
	 * Get the item schema.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Item schema data.
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
			),
		);
	}

	/**
	 * Get the collection params.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['per_page']['default'] = 20;

		return $params;
	}

	/**
	 * Get the params a client may write, in schema order.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, mixed> Submitted values keyed by writable field.
	 */
	protected function get_writable_params( WP_REST_Request $request ): array {
		$values = array();

		foreach ( (array) ( $this->get_item_schema()['properties'] ?? array() ) as $key => $property ) {
			if ( empty( $property['readonly'] ) && $request->has_param( $key ) ) {
				$values[ $key ] = $request->get_param( $key );
			}
		}

		return $values;
	}

	/**
	 * Prepare the date for the response.
	 *
	 * @since 1.0.0
	 * @param string|null $date MySQL datetime in UTC.
	 * @return string|null RFC3339 date in the site timezone, or null when empty.
	 */
	protected function prepare_date_response( ?string $date ): ?string {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return null;
		}

		return mysql_to_rfc3339( get_date_from_gmt( $date ) );
	}

	/**
	 * Prepare the date for the database.
	 *
	 * @since 1.0.0
	 * @param string|null $date RFC3339 datetime, site-local unless it carries an offset.
	 * @return string|null MySQL datetime in UTC, or null when empty or unparsable.
	 */
	protected function prepare_date_for_database( ?string $date ): ?string {
		$dates = empty( $date ) ? null : rest_get_date_with_gmt( $date );

		return $dates ? $dates[1] : null;
	}

	/**
	 * Run the create, update and delete operations of a batch request.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Each operation's outcome, or why the batch was refused.
	 */
	protected function process_batch( WP_REST_Request $request ) {
		$items    = $request->get_params();
		$response = array();

		foreach ( array( 'create', 'update', 'delete' ) as $operation ) {
			$items[ $operation ] = $items[ $operation ] ?? array();

			if ( ! wp_is_numeric_array( $items[ $operation ] ) || ( 'delete' !== $operation && array_filter( $items[ $operation ], static fn( $data ) => ! is_array( $data ) ) ) ) {
				/* translators: %s: batch operation name. */
				return new WP_Error( 'rest_batch_invalid', sprintf( __( 'The %s list must be an array of items.', 'ever-checkout' ), $operation ), array( 'status' => 400 ) );
			}
		}

		/**
		 * Filters the maximum number of items allowed in a batch request.
		 *
		 * @since 1.0.0
		 * @param int $limit Maximum batch size.
		 */
		$limit = ecout()->apply_filters( 'rest_batch_limit', 100 );
		$total = count( $items['create'] ) + count( $items['update'] ) + count( $items['delete'] );

		if ( $total > $limit ) {
			/* translators: %d: maximum batch size. */
			return new WP_Error( 'rest_batch_too_large', sprintf( __( 'Batch limit is %d items.', 'ever-checkout' ), $limit ), array( 'status' => 413 ) );
		}

		foreach ( $items['create'] as $data ) {
			$item = new WP_REST_Request( 'POST' );
			$item->set_attributes( array( 'args' => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ) ) );
			$item->set_body_params( $data );
			$item->sanitize_params();
			$result               = $item->has_valid_params();
			$result               = is_wp_error( $result ) ? $result : $this->create_item( $item );
			$response['create'][] = is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result->get_data();
		}

		foreach ( $items['update'] as $data ) {
			$item = new WP_REST_Request( 'PUT' );
			$item->set_attributes( array( 'args' => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ) ) );
			$item->set_body_params( $data );
			$item->sanitize_params();
			$result               = $item->has_valid_params();
			$result               = is_wp_error( $result ) ? $result : $this->update_item( $item );
			$response['update'][] = is_wp_error( $result ) ? array(
				'id'    => $data['id'] ?? 0,
				'error' => $result->get_error_message(),
			) : $result->get_data();
		}

		foreach ( $items['delete'] as $id ) {
			$id   = is_array( $id ) ? ( $id['id'] ?? 0 ) : (int) $id;
			$item = new WP_REST_Request( 'DELETE' );
			$item->set_param( 'id', $id );
			$result               = $this->delete_item( $item );
			$response['delete'][] = is_wp_error( $result ) ? array(
				'id'    => $id,
				'error' => $result->get_error_message(),
			) : $result->get_data();
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Convert items into value/label options array.
	 *
	 * Accepts two formats:
	 * 1. Associative array (key => label): prepare_options( ['active' => 'Active', 'inactive' => 'Inactive'] )
	 * 2. Array of arrays/objects with pluck keys: prepare_options( $items, 'id', 'name' )
	 *
	 * @since 1.0.0
	 * @param array<int|string, mixed> $items     Items to convert.
	 * @param string                   $value_key Key to pluck for value. Empty = treat as associative key => label.
	 * @param string                   $label_key Key to pluck for label.
	 * @return array<int, array<string, mixed>> Options array of [ ['value' => ..., 'label' => ...], ... ].
	 */
	protected function prepare_options( array $items, string $value_key = '', string $label_key = '' ): array {
		if ( empty( $items ) ) {
			return array();
		}

		if ( empty( $value_key ) ) {
			$options = array();
			foreach ( $items as $value => $label ) {
				$options[] = array(
					'value' => $value,
					'label' => $label,
				);
			}
			return $options;
		}

		return array_map(
			function ( $item ) use ( $value_key, $label_key ) {
				$item = (array) $item;
				return array(
					'value' => $item[ $value_key ] ?? '',
					'label' => $item[ $label_key ] ?? '',
				);
			},
			array_values( $items )
		);
	}
}
