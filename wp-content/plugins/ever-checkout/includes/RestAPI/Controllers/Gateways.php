<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Gateways\Gateway;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Gateways REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Gateways extends Controller {

	/**
	 * Get every registered gateway.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Gateways in their sort order.
	 */
	public function get_items( $request ): WP_REST_Response {
		$items = array();

		foreach ( ecout()->gateways->get_gateways() as $gateway ) {
			$items[] = $this->prepare_item_for_response( $gateway, $request )->get_data();
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Get one gateway with its settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Gateway, or why it was not found.
	 */
	public function get_item( $request ) {
		$gateway = ecout()->gateways->get_gateway( (string) $request->get_param( 'id' ) );

		if ( ! $gateway ) {
			return $this->not_found();
		}

		return $this->prepare_item_for_response( $gateway, $request, true );
	}

	/**
	 * Update a gateway's settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Gateway, or why it was not found.
	 */
	public function update_item( $request ) {
		$gateway = ecout()->gateways->get_gateway( (string) $request->get_param( 'id' ) );

		if ( ! $gateway ) {
			return $this->not_found();
		}

		$fields = $gateway->get_settings_fields();
		$values = array_intersect_key( (array) $request->get_json_params(), $fields );
		$values = ecout()->request->sanitize( $values, wp_list_pluck( $fields, 'sanitize' ) );
		$valid  = $gateway->validate_settings( $values );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		foreach ( $values as $key => $value ) {
			$gateway->update_option( $key, $value, $fields[ $key ]['autoload'] ?? null );
		}

		return $this->get_item( $request );
	}

	/**
	 * Hand an inbound callback to the gateway it is addressed to.
	 *
	 * The route is public; the gateway verifies the request itself.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request the gateway sent.
	 * @return WP_REST_Response|WP_Error Response, or why the request was refused.
	 */
	public function handle_callback( $request ) {
		// A gateway's event body carries its own `id`, which would shadow the route's.
		$gateway = ecout()->gateways->get_gateway( (string) ( $request->get_url_params()['id'] ?? '' ) );

		if ( ! $gateway ) {
			return $this->not_found();
		}

		return $gateway->handle_callback( $request );
	}

	/**
	 * Prepare a gateway for the response.
	 *
	 * @since 1.0.0
	 * @param Gateway         $item     Gateway.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $settings Whether to include the settings fields and values.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request, bool $settings = false ): WP_REST_Response {
		$data = array(
			'id'                 => $item->id,
			'title'              => $item->title,
			'method_title'       => $item->method_title,
			'method_description' => $item->method_description,
			'icon'               => $item->icon,
			'enabled'            => $item->enabled,
			'test_mode'          => $item->test_mode,
			'needs_setup'        => $item->needs_setup(),
			'supports'           => $item->supports,
			'order'              => $item->order,
			'webhook_url'        => $item->get_webhook_url(),
		);

		if ( $settings ) {
			$fields = $item->get_settings_fields();
			$values = array();

			foreach ( $fields as $key => $field ) {
				$values[ $key ] = $item->get_option( $key, $field['default'] ?? '' );
			}

			$data['fields'] = (object) $fields;
			$data['values'] = (object) $values;
		}

		return new WP_REST_Response( ecout()->apply_filters( 'rest_prepare_gateway', $data, $item, $request ) );
	}

	/**
	 * Get the error for a gateway that is not registered.
	 *
	 * @since 1.0.0
	 * @return WP_Error Not-found error.
	 */
	protected function not_found(): WP_Error {
		return new WP_Error( 'rest_gateway_not_found', __( 'No gateway found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
	}
}
