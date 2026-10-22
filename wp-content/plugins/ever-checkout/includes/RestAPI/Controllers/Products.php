<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Models\Product;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Products REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Products extends Controller {

	/**
	 * Get the products.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$query = ecout()->product->query()->with( 'downloads' );

		foreach ( $request->get_params() as $key => $value ) {
			$query->set_var( $key, $value );
		}

		$query->where_filters( (array) $request->get_param( 'filters' ) );
		$query = ecout()->apply_filters( 'rest_product_query', $query, $request );

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
	 * Get a product.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not found.
	 */
	public function get_item( $request ) {
		$item = ecout()->product->find( $request->get_param( 'id' ) );

		if ( ! $item ) {
			return $this->not_found();
		}

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Create a product.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not saved.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request->get_param( 'id' ) ) ) {
			return new WP_Error( 'rest_product_exists', __( 'Cannot create a product that already has an id.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		$item = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item = ecout()->apply_filters( 'rest_pre_insert_product', $item, $request );
		$item = $item->save();

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$this->attach_downloads( $item, $request );

		$response = $this->prepare_item_for_response( $item, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $request->get_route() . '/' . $item->id ) );

		return $response;
	}

	/**
	 * Update a product.
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

		$item = ecout()->apply_filters( 'rest_pre_insert_product', $item, $request );
		$item = $item->save();

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$this->attach_downloads( $item, $request );

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Delete a product.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object, or why it was not deleted.
	 */
	public function delete_item( $request ) {
		$item = ecout()->product->find( $request->get_param( 'id' ) );

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
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_options( $request ) {
		$options = array(
			'statuses'       => $this->prepare_options( Product::get_statuses() ),
			'types'          => $this->prepare_options( Product::get_types() ),
			'download_types' => array_keys( ecout()->download->get_allowed_types() ),
			'download_max'   => wp_max_upload_size(),
			'import_fields'  => array(
				array(
					'id'       => 'name',
					'label'    => __( 'Name', 'ever-checkout' ),
					'required' => true,
				),
				array(
					'id'       => 'price',
					'label'    => __( 'Price', 'ever-checkout' ),
					'required' => true,
				),
				array(
					'id'    => 'status',
					'label' => __( 'Status', 'ever-checkout' ),
				),
				array(
					'id'    => 'description',
					'label' => __( 'Description', 'ever-checkout' ),
				),
				array(
					'id'    => 'download_limit',
					'label' => __( 'Download limit', 'ever-checkout' ),
				),
				array(
					'id'    => 'download_expiry_days',
					'label' => __( 'Download expiry (days)', 'ever-checkout' ),
				),
			),
		);

		/**
		 * Filters the product form options.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $options Options data.
		 * @param \WP_REST_Request     $request Request object.
		 */
		return rest_ensure_response( ecout()->apply_filters( 'rest_product_options', $options, $request ) );
	}


	/**
	 * Prepare a product for the response.
	 *
	 * @since 1.0.0
	 * @param Product         $item    Product.
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

		$data['image_url']       = $item->image_id ? (string) wp_get_attachment_image_url( $item->image_id, 'thumbnail' ) : '';
		$data['price_formatted'] = ecout()->money->format( (int) $item->price );
		$data['downloads']       = array();

		foreach ( (array) $item->get_relation_value( 'downloads' ) as $download ) {
			$data['downloads'][] = $download->to_array();
		}

		return ecout()->apply_filters( 'rest_prepare_product', rest_ensure_response( $data ), $item, $request );
	}

	/**
	 * Prepare a product for the database.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return Product|WP_Error A product, or why it was not found.
	 */
	public function prepare_item_for_database( $request ) {
		$id   = $request->get_url_params()['id'] ?? $request->get_param( 'id' );
		$item = $id ? ecout()->product->find( $id ) : ecout()->product;

		if ( ! $item ) {
			return $this->not_found();
		}

		foreach ( $this->get_writable_params( $request ) as $key => $value ) {
			switch ( $key ) {
				case 'created_at':
				case 'updated_at':
					$item->$key = $this->prepare_date_for_database( $value );
					break;
				case 'downloads':
					break;
				default:
					$item->$key = $value;
					break;
			}
		}

		return $item;
	}

	/**
	 * Attach the unclaimed uploads the request names to the product.
	 *
	 * @since 1.0.0
	 * @param Product         $item    Saved product.
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	protected function attach_downloads( Product $item, WP_REST_Request $request ): void {
		foreach ( array_map( 'absint', (array) $request->get_param( 'downloads' ) ) as $id ) {
			$download = $id ? ecout()->download->find( $id ) : null;

			if ( $download && 0 === (int) $download->product_id ) {
				$item->downloads()->save( $download );
			}
		}
	}

	/**
	 * Get the error for a product that does not exist.
	 *
	 * @since 1.0.0
	 * @return WP_Error Not-found error.
	 */
	protected function not_found(): WP_Error {
		return new WP_Error( 'rest_product_not_found', __( 'No product found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
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
			'title'      => 'product',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier.', 'ever-checkout' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'name'                 => array(
					'description' => __( 'Product name.', 'ever-checkout' ),
					'type'        => 'string',
					'maxLength'   => 255,
					'required'    => true,
				),
				'type'                 => array(
					'description' => __( 'What kind of product this is.', 'ever-checkout' ),
					'type'        => 'string',
					'enum'        => array_keys( Product::get_types() ),
					'default'     => 'simple',
				),
				'status'               => array(
					'description' => __( 'Whether the product is on sale.', 'ever-checkout' ),
					'type'        => 'string',
					'enum'        => array_keys( Product::get_statuses() ),
					'default'     => 'active',
				),
				'description'          => array(
					'description' => __( 'Product description.', 'ever-checkout' ),
					'type'        => 'string',
				),
				'image_id'             => array(
					'description' => __( 'Media library attachment shown as the product image.', 'ever-checkout' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'default'     => 0,
				),
				'price'                => array(
					'description' => __( 'Price in the smallest unit of the store currency.', 'ever-checkout' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'default'     => 0,
				),
				'download_limit'       => array(
					'description' => __( 'Times each file may be downloaded. Zero falls back to the store setting.', 'ever-checkout' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'default'     => 0,
				),
				'download_expiry_days' => array(
					'description' => __( 'Days a download link stays valid. Zero falls back to the store setting.', 'ever-checkout' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'default'     => 0,
				),
				'downloads'            => array(
					'description' => __( 'Ids of uploaded files to attach to the product.', 'ever-checkout' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
				),
				'created_at'           => array(
					'description' => __( 'Date created.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'updated_at'           => array(
					'description' => __( 'Date updated.', 'ever-checkout' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}
}
