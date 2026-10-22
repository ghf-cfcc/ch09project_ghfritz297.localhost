<?php

namespace EverCheckout\B8\Services;

use EverCheckout\B8\App;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin settings.
 *
 * A declaration is groups keyed by id, each holding its own properties plus
 * one flat `fields` map keyed by id. A section is a row in that map, and a
 * field or a nested section names its parent with `section`:
 *
 *     array(
 *         'general' => array(
 *             'title'  => 'General',
 *             'fields' => array(
 *                 'store'      => array( 'type' => 'section', 'title' => 'Store' ),
 *                 'store_name' => array( 'label' => 'Store name', 'section' => 'store' ),
 *                 'display'    => array( 'type' => 'section', 'title' => 'Display', 'section' => 'store' ),
 *                 'layout'     => array( 'label' => 'Layout', 'section' => 'display' ),
 *                 'invoice'    => array(
 *                     'label'  => 'Invoice number',
 *                     'inputs' => array( 'prefix' => array( 'default' => 'INV-' ) ),
 *                 ),
 *             ),
 *         ),
 *     )
 *
 * `inputs` are sub-values stored in the parent's own option row.
 *
 * @since   1.0.0
 * @package B8
 */
class Settings {

	/**
	 * Keys a field carries for the server only.
	 *
	 * @since 2.0.0
	 * @var array<int, string>
	 */
	const INTERNAL_KEYS = array( 'option', 'sanitize', 'autoload' );

	/**
	 * Application instance.
	 *
	 * @since 1.0.0
	 * @var App
	 */
	protected App $app;

	/**
	 * Groups and the normalized field map.
	 *
	 * @since 1.0.0
	 * @var array{groups: array<string, array<string, mixed>>, fields: array<string, array<string, mixed>>}|null
	 */
	protected ?array $settings = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param App $app Application instance.
	 */
	public function __construct( App $app ) {
		$this->app = $app;
	}

	/**
	 * Get the groups and the normalized field map.
	 *
	 * @since 1.0.0
	 * @return array{groups: array<string, array<string, mixed>>, fields: array<string, array<string, mixed>>} Groups and fields, both keyed by id, in render order.
	 */
	public function get_settings(): array {
		if ( null === $this->settings ) {
			$this->settings = array(
				'groups' => array(),
				'fields' => array(),
			);

			/**
			 * Filters the settings declaration.
			 *
			 * @since 1.0.0
			 * @param array<string, mixed> $settings Field maps keyed by group.
			 */
			$settings = (array) $this->app->apply_filters( 'settings', $this->define_settings() );

			foreach ( $settings as $group => $data ) {
				/**
				 * Filters one group's declaration.
				 *
				 * @since 1.0.0
				 * @param array<string, mixed> $data Group properties and its fields.
				 */
				$data   = (array) $this->app->apply_filters( $group . '_settings', $data );
				$fields = (array) ( $data['fields'] ?? array() );

				unset( $data['fields'] );

				uasort( $fields, static fn( $a, $b ) => ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 ) );

				foreach ( $fields as $name => $field ) {
					$field            = (array) $field;
					$field['group']   = (string) $group;
					$field['section'] = $field['section'] ?? '';

					if ( 'section' === ( $field['type'] ?? '' ) ) {
						$field['option'] = false;
					}

					$this->settings['fields'][ (string) $name ] = $field;
				}

				$this->settings['groups'][ $group ] = array_merge( $data, array( 'id' => $group ) );
			}
		}

		return $this->settings;
	}

	/**
	 * Get the groups.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Groups keyed by id, with their own properties.
	 */
	public function get_groups(): array {
		return $this->get_settings()['groups'];
	}

	/**
	 * Get the fields.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Fields keyed by name, internal keys removed.
	 */
	public function get_fields(): array {
		return $this->format_fields( $this->get_settings()['fields'] );
	}

	/**
	 * Get the current values, typed.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Values keyed by name, falling back to defaults.
	 */
	public function get_values(): array {
		$values = array();

		foreach ( $this->get_settings()['fields'] as $name => $field ) {
			if ( ! ( $field['option'] ?? true ) ) {
				continue;
			}

			$values[ $name ] = $this->app->options->get( $name, $field['default'] ?? null );
		}

		return $values;
	}

	/**
	 * Save the submitted values.
	 *
	 * @since 2.0.0
	 * @param array<string, mixed>                $values Values keyed by name; a name not sent is left untouched.
	 * @param array<string, array<string, mixed>> $fields Optional. Fields to save. Default all of them.
	 * @return int Number of values written.
	 */
	public function save_values( array $values, array $fields = array() ): int {
		$fields = empty( $fields ) ? $this->get_settings()['fields'] : $fields;
		$saved  = 0;

		foreach ( array_intersect_key( $values, $fields ) as $name => $value ) {
			$field = $fields[ $name ];

			if ( ! ( $field['option'] ?? true ) ) {
				continue;
			}

			if ( $this->app->options->update( $name, $this->save_value( $value, $field ), $field['autoload'] ?? null ) ) {
				++$saved;
			}
		}

		return $saved;
	}

	/**
	 * Define the settings.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Field maps keyed by group.
	 */
	protected function define_settings(): array {
		return array();
	}

	/**
	 * Remove the internal keys from a field map.
	 *
	 * @since 2.0.0
	 * @param array<string, array<string, mixed>> $fields Fields keyed by name.
	 * @return array<string, array<string, mixed>> Fields without their internal keys, at every depth.
	 */
	protected function format_fields( array $fields ): array {
		$internal = array_flip( self::INTERNAL_KEYS );

		foreach ( $fields as $name => $field ) {
			$field = array_diff_key( $field, $internal );

			if ( ! empty( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
				$field['inputs'] = $this->format_fields( $field['inputs'] );
			}

			$fields[ $name ] = $field;
		}

		return $fields;
	}

	/**
	 * Clean a submitted value with the rule its field carries.
	 *
	 * The rule is the declared `sanitize`, else `string`. Anything
	 * `Request::sanitize()` understands is a rule.
	 *
	 * @since 2.0.0
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field declaration.
	 * @return mixed Clean value; every input cleaned by its own rule, undeclared keys dropped.
	 */
	protected function save_value( $value, array $field ) {
		if ( isset( $field['inputs'] ) ) {
			$clean = array();

			foreach ( array_intersect_key( (array) $value, $field['inputs'] ) as $key => $input ) {
				$clean[ $key ] = $this->save_value( $input, (array) $field['inputs'][ $key ] );
			}

			return $clean;
		}

		return $this->app->request->sanitize( $value, $field['sanitize'] ?? '' );
	}
}
