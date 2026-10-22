<?php

namespace EverCheckout;

use EverCheckout\B8\Component;
use EverCheckout\Gateways\Gateway;
use EverCheckout\Models\Order;
use EverCheckout\Models\Product;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the checkout.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @extends Component<Plugin>
 */
class Checkout extends Component {

	/**
	 * Script and style handle of the overlay.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HANDLE = 'ever-checkout';

	/**
	 * Field a customer never sees; anything in it marks the submission as a bot.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HONEYPOT = 'website';

	/**
	 * Orders one address may start per minute.
	 *
	 * Every start costs a gateway API call, so an unattended loop must not be
	 * able to run one up.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RATE_LIMIT = 10;

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_dialog' ) );
	}

	/**
	 * Register the overlay assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_assets(): void {
		$this->app->scripts->register_script( self::HANDLE, 'checkout.js', array(), true );
		$this->app->scripts->register_style( self::HANDLE, 'style-checkout.css', array( 'wp-block-button' ) );

		wp_localize_script(
			self::HANDLE,
			'ecout_checkout_vars',
			array(
				'restUrl' => esc_url_raw( rest_url( $this->app->rest_prefix . '/checkout' ) ),
				'nonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'i18n'    => array(
					'pay'         => __( 'Pay', 'ever-checkout' ),
					/* translators: %s: formatted price. */
					'payWith'     => __( 'Pay %s', 'ever-checkout' ),
					'digital'     => __( 'Digital download', 'ever-checkout' ),
					'failed'      => __( 'We could not start the payment. Please try again.', 'ever-checkout' ),
					'unsupported' => __( 'This payment method cannot run here.', 'ever-checkout' ),
				),
			)
		);
	}

	/**
	 * Render the checkout dialog once, when something on the page can open it.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dialog(): void {
		if ( ! wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE );

		$logo_id = (int) $this->app->options->get( 'store_logo', 0 );

		$this->app->template->render(
			'checkout-dialog',
			array(
				'gateways'   => $this->app->gateways->get_available_gateways(),
				'store_name' => (string) $this->app->options->get( 'store_name', get_bloginfo( 'name' ) ),
				'store_logo' => $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '',
				'honeypot'   => self::HONEYPOT,
			)
		);
	}

	/**
	 * Get what the overlay shows for a product.
	 *
	 * @since 1.0.0
	 * @param Product $product Product for sale.
	 * @return array<string, mixed> Name, description, formatted price and image URL.
	 */
	public function get_product_summary( Product $product ): array {
		return array(
			'id'          => (int) $product->id,
			'name'        => (string) $product->name,
			'description' => wp_strip_all_tags( (string) $product->description ),
			'price'       => $this->app->money->format( (int) $product->price ),
			'image'       => $product->image_id ? (string) wp_get_attachment_image_url( (int) $product->image_id, 'thumbnail' ) : '',
		);
	}

	/**
	 * Start a checkout: write the pending order and hand it to the gateway.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $fields Sanitised customer input: product_id, gateway, name, email, and the gateway's own fields.
	 * @return array<string, mixed>|WP_Error `order`, plus `redirect` or `params`; or why the checkout could not start.
	 */
	public function start( array $fields ) {
		if ( '' !== (string) ( $fields[ self::HONEYPOT ] ?? '' ) ) {
			return new WP_Error( 'ecout_checkout_spam', __( 'We could not start the payment. Please try again.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		if ( $this->is_rate_limited() ) {
			return new WP_Error( 'ecout_checkout_rate_limited', __( 'Too many attempts. Please wait a minute and try again.', 'ever-checkout' ), array( 'status' => 429 ) );
		}

		$product = $this->app->product->find( (int) ( $fields['product_id'] ?? 0 ) );

		if ( ! $product || 'active' !== $product->status ) {
			return new WP_Error( 'ecout_checkout_not_for_sale', __( 'That product is not for sale.', 'ever-checkout' ), array( 'status' => 404 ) );
		}

		$available = $this->app->gateways->get_available_gateways();
		$chosen    = (string) ( $fields['gateway'] ?? '' );
		$gateway   = $available[ $chosen ] ?? null;

		if ( ! $gateway && '' === $chosen && 1 === count( $available ) ) {
			$gateway = reset( $available );
		}

		if ( ! $gateway ) {
			return new WP_Error( 'ecout_checkout_no_gateway', __( 'This store cannot take payments yet.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		$name  = (string) ( $fields['name'] ?? '' );
		$email = (string) ( $fields['email'] ?? '' );

		if ( '' === $name ) {
			return new WP_Error( 'ecout_checkout_name_required', __( 'Enter your name.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'ecout_checkout_email_required', __( 'Enter the email address the download should be sent to.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		$valid = $gateway->validate_fields( $fields );

		if ( is_wp_error( $valid ) ) {
			$valid->add_data( array( 'status' => 400 ) );

			return $valid;
		}

		$order = $this->create_order(
			array( $product ),
			$gateway,
			array(
				'name'  => $name,
				'email' => sanitize_email( $email ),
			)
		);

		if ( is_wp_error( $order ) ) {
			$this->app->logger->error( 'Checkout: ' . $order->get_error_message() );

			return new WP_Error( 'ecout_checkout_failed', __( 'We could not start the payment. Please try again.', 'ever-checkout' ), array( 'status' => 502 ) );
		}

		$payment = $gateway->process_payment( $order );

		if ( is_wp_error( $payment ) ) {
			$this->app->logger->error( 'Checkout: ' . $payment->get_error_code() . ' ' . $payment->get_error_message() );

			return new WP_Error( 'ecout_checkout_failed', __( 'We could not start the payment. Please try again.', 'ever-checkout' ), array( 'status' => 502 ) );
		}

		if ( is_string( $payment ) && '' !== $payment ) {
			return array(
				'order'    => $order,
				'redirect' => $payment,
			);
		}

		if ( is_array( $payment ) ) {
			return array(
				'order'  => $order,
				'params' => $payment,
			);
		}

		return new WP_Error( 'ecout_checkout_failed', __( 'We could not start the payment. Please try again.', 'ever-checkout' ), array( 'status' => 502 ) );
	}

	/**
	 * Write the pending order a checkout starts from, one line per product.
	 *
	 * @since 1.0.0
	 * @param Product[]            $products Products being bought.
	 * @param Gateway              $gateway  Gateway that will take the payment.
	 * @param array<string, mixed> $data     Customer details: name and email.
	 * @return Order|WP_Error Order, or why it could not be written.
	 */
	protected function create_order( array $products, Gateway $gateway, array $data ) {
		$total = array_sum( array_map( static fn( Product $product ) => (int) $product->price, $products ) );
		$order = $this->app->order->insert(
			array(
				'name'       => (string) ( $data['name'] ?? '' ),
				'email'      => (string) ( $data['email'] ?? '' ),
				'subtotal'   => $total,
				'total'      => $total,
				'currency'   => (string) $this->app->options->get( 'currency', 'USD' ),
				'mode'       => $gateway->test_mode ? 'test' : 'live',
				'ip_address' => $this->app->request->ip_address(),
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		foreach ( array_values( $products ) as $position => $product ) {
			$item = $this->app->order_item->insert(
				array(
					'order_id'     => $order->id,
					'product_id'   => $product->id,
					'product_name' => $product->name,
					'price'        => $product->price,
					'quantity'     => 1,
					'position'     => $position,
				)
			);

			if ( is_wp_error( $item ) ) {
				return $item;
			}
		}

		return $order;
	}

	/**
	 * Whether this address, or the store as a whole, has started too many orders in the last minute.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected function is_rate_limited(): bool {
		$since = gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS );
		$mine  = $this->app->order->query()
			->where( 'ip_address', $this->app->request->ip_address() )
			->where( 'created_at', '>', $since )
			->count();

		if ( $mine >= self::RATE_LIMIT ) {
			return true;
		}

		/**
		 * Filters how many orders the whole store may start per minute, whatever the address.
		 *
		 * @since 1.0.0
		 *
		 * @param int $limit Orders per minute.
		 */
		$store_limit = (int) $this->app->apply_filters( 'checkout_store_rate_limit', self::RATE_LIMIT * 5 );

		return $this->app->order->query()->where( 'created_at', '>', $since )->count() >= $store_limit;
	}
}
