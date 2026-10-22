<?php

namespace EverCheckout\B8\Services;

use EverCheckout\B8\App;

defined( 'ABSPATH' ) || exit;

/**
 * Handles request data.
 *
 * Provides data cleaning via pipe-separated rule strings.
 *
 * @since 1.0.0
 * @package B8
 */
class Request {

	/**
	 * Application instance.
	 *
	 * @since 1.2.0
	 * @var App
	 */
	protected App $app;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @param App $app Application instance.
	 */
	public function __construct( App $app ) {
		$this->app = $app;
	}

	/**
	 * Sanitize request data.
	 *
	 * @since 1.0.0
	 * @param mixed                                          $data  Field values keyed by name, or one value.
	 * @param array<string, string|\Closure>|string|\Closure $rules Rules keyed by field, or one rule chain. Default string.
	 * @return mixed Sanitized data.
	 */
	public function sanitize( $data, $rules = '' ) {
		if ( is_array( $rules ) ) {
			$sanitized = array();
			foreach ( $data as $field => $value ) {
				$sanitized[ $field ] = $this->sanitize( $value, $rules[ $field ] ?? '' );
			}

			return $sanitized;
		}

		$data = $data ?? '';

		if ( $rules instanceof \Closure ) {
			return $rules( $data );
		}

		if ( is_array( $data ) ) {
			return array_map( fn( $item ) => $this->sanitize( $item, $rules ), $data );
		}

		foreach ( explode( '|', $rules ) as $rule ) {
			$parameters = array();
			if ( str_contains( $rule, ':' ) ) {
				list( $rule, $params_string ) = explode( ':', $rule, 2 );
				$parameters                   = array_map( 'trim', explode( ',', $params_string ) );
			}

			switch ( trim( $rule ) ) {
				case 'integer':
				case 'int':
					$data = (int) $data;
					break;
				case 'absint':
					$data = absint( $data );
					break;
				case 'number':
					$data = (float) $data;
					break;
				case 'boolean':
					$data = (bool) filter_var( $data, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'tinyint':
					$data = filter_var( $data, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
					break;
				case 'yes_no':
					$data = filter_var( $data, FILTER_VALIDATE_BOOLEAN ) ? 'yes' : 'no';
					break;
				case 'textarea':
					$data = sanitize_textarea_field( $data );
					break;
				case 'html':
					$data = wp_kses_post( $data );
					break;
				case 'strip_tags':
					$data = wp_strip_all_tags( $data );
					break;
				case 'email':
					$data = sanitize_email( $data );
					break;
				case 'url':
					$data = sanitize_url( $data );
					break;
				case 'key':
					$data = sanitize_key( $data );
					break;
				case 'slug':
					$data = sanitize_title( $data );
					break;
				case 'file_name':
					$data = sanitize_file_name( $data );
					break;
				case 'user':
					$data = sanitize_user( $data );
					break;
				case 'html_class':
					$data = sanitize_html_class( $data );
					break;
				case 'hex_color':
					$data = (string) sanitize_hex_color( $data );
					break;
				case 'mime_type':
					$data = sanitize_mime_type( $data );
					break;
				case 'date':
					$data = preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $data, $m ) && wp_checkdate( (int) $m[2], (int) $m[3], (int) $m[1], $data ) ? (string) $data : null;
					break;
				case 'time':
					$data = preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $data ) ? (string) $data : null;
					break;
				case 'datetime':
					$data = preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T]([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $data, $m ) && wp_checkdate( (int) $m[2], (int) $m[3], (int) $m[1], $data ) ? (string) $data : null;
					break;
				case 'enum':
					$allowed = array_map( 'strval', $parameters );
					$data    = in_array( (string) $data, $allowed, true ) ? (string) $data : ( $allowed[0] ?? '' );
					break;
				case 'trim':
					$data = trim( sanitize_text_field( (string) $data ) );
					break;
				case 'collapse_ws':
					$data = preg_replace( '/\s+/', ' ', trim( sanitize_text_field( (string) $data ) ) );
					break;
				case 'lower':
					$data = strtolower( sanitize_text_field( (string) $data ) );
					break;
				case 'upper':
					$data = strtoupper( sanitize_text_field( (string) $data ) );
					break;
				case 'alpha':
					$data = preg_replace( '/[^a-zA-Z]/', '', (string) $data );
					break;
				case 'alpha_num':
					$data = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $data );
					break;
				case 'alpha_dash':
					$data = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $data );
					break;
				case 'numeric':
					$data = preg_replace( '/[^0-9.-]/', '', (string) $data );
					break;
				case 'limit':
					$limit = isset( $parameters[0] ) ? (int) $parameters[0] : 100;
					$data  = sanitize_text_field( (string) $data );
					$data  = mb_strlen( $data ) <= $limit ? $data : mb_substr( $data, 0, $limit ) . ( $parameters[1] ?? '' );
					break;
				case 'fn':
					$callback = $parameters[0] ?? '';
					$data     = is_callable( $callback ) ? $callback( $data ) : $this->sanitize( $data );
					break;
				case 'string':
				case 'text':
				default:
					$data = is_scalar( $data ) ? sanitize_text_field( (string) $data ) : $data;
					break;
			}
		}

		return $data;
	}

	/**
	 * Get the client IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $anonymize Whether to anonymize the IP address (GDPR compliance).
	 *
	 * @return string Client IP address.
	 */
	public function ip_address( bool $anonymize = false ): string {
		/**
		 * Filters the forwarding headers a trusted proxy in front of this site sets.
		 *
		 * @since 1.2.0
		 * @param array<int, string> $headers $_SERVER keys, e.g. HTTP_CF_CONNECTING_IP. Default empty.
		 */
		$ip_headers = (array) $this->app->apply_filters( 'trusted_proxy_headers', array() );

		$ip = '';

		foreach ( $ip_headers as $header ) {
			$value = isset( $_SERVER[ $header ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) : '';

			if ( str_contains( $value, ',' ) ) {
				$value = trim( explode( ',', $value )[0] );
			}

			if ( filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				$ip = $value;
				break;
			}
		}

		if ( '' === $ip ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
		}

		return $anonymize ? wp_privacy_anonymize_ip( $ip ) : $ip;
	}
}
