<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Emails\Email;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Emails REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Emails extends Controller {

	/**
	 * Get every registered email.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Emails.
	 */
	public function get_items( $request ): WP_REST_Response {
		$items = array();

		foreach ( ecout()->emails->get_emails() as $email ) {
			$items[] = $this->prepare_item_for_response( $email, $request )->get_data();
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Get one email with its settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Email, or why it was not found.
	 */
	public function get_item( $request ) {
		$email = ecout()->emails->get_email( (string) $request->get_param( 'id' ) );

		if ( ! $email ) {
			return $this->not_found();
		}

		return $this->prepare_item_for_response( $email, $request, true );
	}

	/**
	 * Update an email's settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Email, or why it was not found.
	 */
	public function update_item( $request ) {
		$email = ecout()->emails->get_email( (string) $request->get_param( 'id' ) );

		if ( ! $email ) {
			return $this->not_found();
		}

		$fields = $email->get_settings_fields();
		$values = array_intersect_key( (array) $request->get_json_params(), $fields );

		$values = ecout()->request->sanitize( $values, wp_list_pluck( $fields, 'sanitize' ) );

		foreach ( $values as $value ) {
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}

		foreach ( $values as $key => $value ) {
			$email->update_option( $key, $value );
		}

		return $this->get_item( $request );
	}

	/**
	 * Prepare an email for the response.
	 *
	 * @since 1.0.0
	 * @param Email           $item     Email.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $settings Whether to include the settings fields and values.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request, bool $settings = false ): WP_REST_Response {
		$data = array(
			'id'             => $item->id,
			'title'          => $item->title,
			'description'    => $item->description,
			'recipient_type' => $item->recipient_type,
			'enabled'        => $item->enabled,
			'placeholders'   => (object) $item->get_placeholders(),
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

		return new WP_REST_Response( ecout()->apply_filters( 'rest_prepare_email', $data, $item, $request ) );
	}

	/**
	 * Get the error for an email that is not registered.
	 *
	 * @since 1.0.0
	 * @return WP_Error Not-found error.
	 */
	protected function not_found(): WP_Error {
		return new WP_Error( 'rest_email_not_found', __( 'No email found with that id.', 'ever-checkout' ), array( 'status' => 404 ) );
	}
}
