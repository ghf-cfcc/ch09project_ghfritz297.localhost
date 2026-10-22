<?php

namespace EverCheckout\RestAPI\Controllers;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Settings REST controller.
 *
 * One endpoint serves every group: the browser reads groups, fields and values
 * in a single request and renders whichever group the screen is showing.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Settings extends Controller {

	/**
	 * Field keys the browser never sees.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	private const PRIVATE_KEYS = array( 'show_in_rest', 'capability' );

	/**
	 * Get the settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Groups, fields and values.
	 */
	public function get_items( $request ): WP_REST_Response {
		$settings = ecout()->settings;
		$stored   = $settings->get_values();
		$fields   = array();
		$values   = array();

		foreach ( $this->readable( $settings->get_fields() ) as $name => $field ) {
			$fields[ $name ] = array_diff_key( $field, array_flip( self::PRIVATE_KEYS ) );

			if ( array_key_exists( $name, $stored ) ) {
				$values[ $name ] = $stored[ $name ];
			}
		}

		return rest_ensure_response(
			array(
				'groups' => (object) $settings->get_groups(),
				'fields' => (object) $fields,
				'values' => (object) $values,
			)
		);
	}

	/**
	 * Save the settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Groups, fields and the values as they now stand.
	 */
	public function update_items( WP_REST_Request $request ): WP_REST_Response {
		$settings = ecout()->settings;

		/*
		 * Narrowed by value, never by field map: `get_fields()` strips `option`
		 * and `sanitize`, so handing it back as the map to save would clean every
		 * value as plain text and let a `section` row write an option.
		 */
		$settings->save_values(
			array_intersect_key(
				(array) $request->get_json_params(),
				$this->readable( $settings->get_fields() )
			)
		);

		return $this->get_items( $request );
	}

	/**
	 * Drop the fields this request may not read.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $fields Fields keyed by name.
	 * @return array<string, array<string, mixed>> Fields the current user may read.
	 */
	protected function readable( array $fields ): array {
		return array_filter(
			$fields,
			static function ( $field ) {
				if ( isset( $field['show_in_rest'] ) && false === $field['show_in_rest'] ) {
					return false;
				}

				return empty( $field['capability'] ) || current_user_can( $field['capability'] );
			}
		);
	}
}
