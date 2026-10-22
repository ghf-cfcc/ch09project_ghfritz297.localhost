<?php

namespace EverCheckout;

use EverCheckout\B8\Component;
use EverCheckout\Models\Download;
use EverCheckout\Models\Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles file delivery.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @extends Component<Plugin>
 */
class Downloads extends Component {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'handle_download' ) );
	}

	/**
	 * Serve a file the order that holds the link is entitled to.
	 *
	 * The order key is the credential: a link in an inbox carries no nonce and
	 * no session. The file id follows the 32-character key in the same value.
	 *
	 * @since 1.0.0
	 * @param \WP $wp Current request.
	 * @return void
	 */
	public function handle_download( \WP $wp ): void {
		$value = $this->app->request->sanitize( $wp->query_vars[ Plugin::QUERY_VARS['download'] ] ?? '', 'alpha_num' );

		if ( '' === $value ) {
			return;
		}

		$order    = $this->app->order->find( array( 'order_key' => substr( $value, 0, 32 ) ) );
		$download = $this->app->download->find( absint( substr( $value, 32 ) ) );

		if ( ! $order || ! $download ) {
			$this->download_error( __( 'This download link is not valid.', 'ever-checkout' ) );
		}

		$valid = $this->validate( $order, $download );

		if ( is_wp_error( $valid ) ) {
			$this->download_error( $valid->get_error_message() );
		}

		if ( 'relative' === $download->get_file_type() && ! $this->app->fs->exists( $download->get_full_path() ) ) {
			$this->download_error( __( 'This file is missing. Please contact the seller.', 'ever-checkout' ) );
		}

		$logged = $this->app->download_log->insert(
			array(
				'order_id'    => (int) $order->id,
				'product_id'  => (int) $download->product_id,
				'download_id' => (int) $download->id,
				'customer_id' => (int) $order->customer_id,
				'ip_address'  => $this->app->request->ip_address(),
				'user_agent'  => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
			)
		);

		if ( is_wp_error( $logged ) ) {
			$this->download_error( $logged->get_error_message() );
		}

		/**
		 * Fires when a file is served to a customer.
		 *
		 * @since 1.0.0
		 *
		 * @param Order    $order    Order the link belongs to.
		 * @param Download $download File being served.
		 */
		$this->app->do_action( 'order_download_served', $order, $download );

		$this->download_file( $download );
	}

	/**
	 * Check whether an order may download a file right now.
	 *
	 * Checked when the file is served, never when the link is made, so a refund
	 * stops a link that is already sitting in an inbox.
	 *
	 * @since 1.0.0
	 * @param Order    $order    Order the link belongs to.
	 * @param Download $download File being asked for.
	 * @return true|WP_Error True when it may be served, or why not.
	 */
	public function validate( Order $order, Download $download ) {
		if ( 'refunded' === $order->status ) {
			return new WP_Error( 'ecout_download_revoked', __( 'This download was cancelled when the order was refunded.', 'ever-checkout' ) );
		}

		if ( ! $order->can_download() ) {
			return new WP_Error( 'ecout_download_unavailable', __( 'This download is not available for this order.', 'ever-checkout' ) );
		}

		$item = $order->get_download_item( $download );

		if ( ! $item ) {
			return new WP_Error( 'ecout_download_unavailable', __( 'This file is no longer available.', 'ever-checkout' ) );
		}

		$product = $item->get_relation_value( 'product' );
		$expires = (string) $order->get_download_expiry( $product );

		if ( '' !== $expires && strtotime( $expires ) < time() ) {
			return new WP_Error( 'ecout_download_expired', __( 'This download link has expired.', 'ever-checkout' ) );
		}

		$limit = $product ? $product->get_download_limit() : (int) $this->app->options->get( 'download_limit', 0 );

		if ( $limit > 0 && $order->get_download_count( $download ) >= $limit ) {
			return new WP_Error( 'ecout_download_limit_reached', __( 'This download has been used the maximum number of times.', 'ever-checkout' ) );
		}

		return true;
	}

	/**
	 * Send the file by the configured download method.
	 *
	 * @since 1.0.0
	 * @param Download $download Download to send.
	 * @return never
	 */
	protected function download_file( Download $download ) {
		$path = (string) $download->file_path;

		if ( 'absolute' === $download->get_file_type() ) {
			add_filter( 'allowed_redirect_hosts', static fn( array $hosts ) => array_merge( $hosts, array( (string) wp_parse_url( $path, PHP_URL_HOST ) ) ) );
			wp_safe_redirect( $path );
			exit;
		}

		$method = (string) $this->app->options->get( 'download_method', 'force' );
		$local  = $download->get_full_path();

		if ( 'xsendfile' === $method && $this->app->fs->exists( $local ) ) {
			$sendfile = $this->get_sendfile_header( $local );

			if ( '' !== $sendfile ) {
				nocache_headers();
				header( 'Content-Type: ' . ( '' !== (string) $download->mime_type ? $download->mime_type : 'application/octet-stream' ) );
				header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) $download->file_name ) . '"' );
				header( $sendfile );
				exit;
			}

			$this->app->logger->notice( sprintf( '%s could not be served with X-Accel-Redirect/X-Sendfile; falling back to a forced download.', $local ) );
		}

		$sent = $this->app->fs->download( $local, (string) $download->file_name );

		if ( is_wp_error( $sent ) ) {
			$this->download_error( __( 'This file is missing. Please contact the seller.', 'ever-checkout' ) );
		}

		exit;
	}

	/**
	 * Get the header that hands a file to the web server.
	 *
	 * @since 1.0.0
	 * @param string $path Absolute path of the file.
	 * @return string Complete header line, or an empty string when the server cannot serve the file itself.
	 */
	protected function get_sendfile_header( string $path ): string {
		$server = strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) );

		if ( function_exists( 'apache_get_modules' ) && in_array( 'mod_xsendfile', apache_get_modules(), true ) ) {
			return 'X-Sendfile: ' . $path;
		}

		if ( false !== strpos( $server, 'lighttpd' ) ) {
			return 'X-Lighttpd-Sendfile: ' . $path;
		}

		if ( false === strpos( $server, 'nginx' ) && false === strpos( $server, 'cherokee' ) ) {
			return '';
		}

		$uploads = wp_upload_dir( null, false );
		$base    = wp_normalize_path( (string) realpath( (string) $uploads['basedir'] ) );
		$real    = wp_normalize_path( (string) realpath( $path ) );
		$uri     = '' !== $base && 0 === strpos( $real, trailingslashit( $base ) )
			? (string) wp_parse_url( (string) $uploads['baseurl'], PHP_URL_PATH ) . substr( $real, strlen( $base ) )
			: '';

		/**
		 * Filters the URI handed to nginx in the X-Accel-Redirect header.
		 *
		 * @since 1.0.0
		 * @param string $uri  URI relative to the site root, or an empty string when the file is outside the uploads folder.
		 * @param string $path Absolute path of the file.
		 */
		$uri = (string) $this->app->apply_filters( 'download_xsendfile_uri', $uri, $path );

		return '' !== $uri ? 'X-Accel-Redirect: ' . $uri : '';
	}

	/**
	 * Refuse a download.
	 *
	 * @since 1.0.0
	 * @param string $message Why it was refused.
	 * @return never
	 */
	protected function download_error( string $message ) {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Download unavailable', 'ever-checkout' ),
			array( 'response' => 403 )
		);
	}
}
