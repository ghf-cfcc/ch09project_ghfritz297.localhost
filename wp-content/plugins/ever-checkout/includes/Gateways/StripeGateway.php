<?php

namespace EverCheckout\Gateways;

use EverCheckout\Models\Order;
use EverCheckout\Orders;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Stripe payment gateway.
 *
 * Card data never reaches this code: the customer is redirected to a Stripe-hosted
 * Checkout Session and the order is reconciled from the webhook.
 *
 * @since   1.0.0
 * @package EverCheckout\Gateways
 */
class StripeGateway extends Gateway {

	/**
	 * Stripe API base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const API = 'https://api.stripe.com/v1/';

	/**
	 * Gateway id.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $id = 'stripe';

	/**
	 * Capabilities this gateway supports.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	public array $supports = array( 'refunds', 'partial_refunds', 'redirect' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->title              = __( 'Card', 'ever-checkout' );
		$this->method_title       = __( 'Stripe', 'ever-checkout' );
		$this->method_description = __( 'Take card payments through Stripe. Customers pay on a Stripe-hosted page, so no card details reach your site.', 'ever-checkout' );
		$this->icon               = ecout()->assets_url( 'gateways/stripe.svg' );

		parent::__construct();
	}

	/**
	 * Whether the gateway still needs configuring.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function needs_setup(): bool {
		return '' === $this->get_secret_key() || '' === (string) $this->get_option( $this->get_mode() . '_webhook_secret' );
	}

	/**
	 * Get the mode the gateway is running in.
	 *
	 * @since 1.0.0
	 * @return string Either `test` or `live`.
	 */
	public function get_mode(): string {
		return $this->test_mode ? 'test' : 'live';
	}

	/**
	 * Get the secret key for the active mode.
	 *
	 * @since 1.0.0
	 * @return string Secret key.
	 */
	protected function get_secret_key(): string {
		return trim( (string) $this->get_option( $this->get_mode() . '_secret_key' ) );
	}

	/**
	 * Start a payment.
	 *
	 * @since 1.0.0
	 * @param Order $order Order being paid.
	 * @return string|WP_Error Where to send the customer, or why it failed.
	 */
	public function process_payment( Order $order ) {
		$body = array(
			'mode'                  => 'payment',
			'success_url'           => $this->get_return_url( $order ),
			'cancel_url'            => wp_get_referer() ? wp_get_referer() : home_url( '/' ),
			'client_reference_id'   => $order->order_key,
			'customer_email'        => (string) $order->email,
			'allow_promotion_codes' => 'yes' === $this->get_option( 'allow_promotion_codes' ) ? 'true' : 'false',
			'metadata[order_id]'    => (int) $order->id,
			'metadata[order_key]'   => (string) $order->order_key,
		);

		foreach ( array_values( (array) $order->get_relation_value( 'items' ) ) as $index => $item ) {
			$body[ "line_items[{$index}][quantity]" ]                       = max( 1, (int) $item->get( 'quantity' ) );
			$body[ "line_items[{$index}][price_data][currency]" ]           = strtolower( (string) $order->currency );
			$body[ "line_items[{$index}][price_data][unit_amount]" ]        = (int) $item->get( 'price' );
			$body[ "line_items[{$index}][price_data][product_data][name]" ] = (string) $item->get( 'product_name' );
		}

		$session = $this->request( 'checkout/sessions', $body, md5( $order->order_key . ':session' ) );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$order->gateway        = $this->id;
		$order->transaction_id = (string) ( $session['id'] ?? '' );
		$saved                 = $order->save();

		return is_wp_error( $saved ) ? $saved : (string) ( $session['url'] ?? '' );
	}

	/**
	 * Refuse a key that is not the kind Stripe issues, so a bad paste fails here rather than at the first checkout.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $values Sanitised values keyed by field.
	 * @return true|WP_Error
	 */
	public function validate_settings( array $values ) {
		$prefixes = array(
			'test_publishable_key' => 'pk_test_',
			'test_secret_key'      => 'sk_test_',
			'test_webhook_secret'  => 'whsec_',
			'live_publishable_key' => 'pk_live_',
			'live_secret_key'      => 'sk_live_',
			'live_webhook_secret'  => 'whsec_',
		);

		foreach ( $prefixes as $key => $prefix ) {
			$value = trim( (string) ( $values[ $key ] ?? '' ) );

			if ( '' !== $value && ! str_starts_with( $value, $prefix ) ) {
				/* translators: %s: expected key prefix. */
				return new WP_Error( 'ecout_stripe_bad_key', sprintf( __( 'That does not look like a Stripe key; it should start with %s.', 'ever-checkout' ), $prefix ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Refund an amount against an order.
	 *
	 * @since 1.0.0
	 * @param Order  $order  Order being refunded.
	 * @param int    $amount Amount in minor units.
	 * @param string $reason Reason given by the seller.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function process_refund( Order $order, int $amount, string $reason = '' ) {
		$intent = empty( $order->paid_at ) ? '' : (string) $order->transaction_id;

		if ( '' === $intent ) {
			return new WP_Error( 'ecout_no_payment', __( 'This order has no completed payment to refund.', 'ever-checkout' ) );
		}

		$refund = $this->request(
			'refunds',
			array(
				'payment_intent' => $intent,
				'amount'         => $amount,
				'metadata[note]' => $reason,
			),
			md5( $order->order_key . ':refund:' . $amount . ':' . $order->refunds()->count() )
		);

		return is_wp_error( $refund ) ? $refund : true;
	}

	/**
	 * Handle an inbound Stripe webhook.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request Stripe sent.
	 * @return WP_REST_Response|WP_Error Response, or why the request was refused.
	 */
	public function handle_callback( WP_REST_Request $request ) {
		$payload = (string) $request->get_body();

		if ( ! $this->is_signature_valid( $payload, (string) $request->get_header( 'stripe-signature' ) ) ) {
			ecout()->logger->warning( 'Stripe: rejected a webhook with a missing or invalid signature.' );

			return new WP_Error( 'ecout_stripe_bad_signature', __( 'Invalid signature.', 'ever-checkout' ), array( 'status' => 400 ) );
		}

		$event  = json_decode( $payload, true );
		$type   = is_array( $event ) ? (string) ( $event['type'] ?? '' ) : '';
		$object = is_array( $event ) ? (array) ( $event['data']['object'] ?? array() ) : array();

		if ( in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			$this->handle_session_completed( $object );
		} elseif ( 'charge.refunded' === $type ) {
			$this->handle_charge_refunded( $object );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Whether a webhook carries a signature we issued.
	 *
	 * @since 1.0.0
	 * @param string $payload Raw request body.
	 * @param string $header  Value of the Stripe-Signature header.
	 * @return bool
	 */
	protected function is_signature_valid( string $payload, string $header ): bool {
		$secret = trim( (string) $this->get_option( $this->get_mode() . '_webhook_secret' ) );

		if ( '' === $secret || '' === $header || '' === $payload ) {
			return false;
		}

		$timestamp  = '';
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );

			if ( 't' === $pair[0] ) {
				$timestamp = $pair[1] ?? '';
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1] ?? '';
			}
		}

		if ( '' === $timestamp || empty( $signatures ) ) {
			return false;
		}

		// Stripe's own tolerance, so a replayed body cannot be posted back days later.
		if ( abs( time() - (int) $timestamp ) > 5 * MINUTE_IN_SECONDS ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Complete the order a finished Checkout Session belongs to.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $session Session object Stripe sent.
	 * @return void
	 */
	protected function handle_session_completed( array $session ): void {
		$reference = (string) ( $session['id'] ?? '' );

		// A session completes before an asynchronous method has actually paid; a 100% code owes nothing.
		if ( ! in_array( $session['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true ) ) {
			return;
		}

		$order = $this->find_order( $reference, (string) ( $session['client_reference_id'] ?? '' ) );

		if ( ! $order ) {
			ecout()->logger->warning( 'Stripe: a completed session named an order we do not have: ' . $reference );

			return;
		}

		$charged  = (int) ( $session['amount_subtotal'] ?? $session['amount_total'] ?? -1 );
		$currency = strtoupper( (string) ( $session['currency'] ?? '' ) );
		$live     = ! empty( $session['livemode'] );

		if ( (int) $order->total !== $charged || (string) $order->currency !== $currency || ( 'live' === $order->mode ) !== $live ) {
			ecout()->logger->warning( sprintf( 'Stripe: session %s does not match order #%d (%d %s, %s).', $reference, $order->id, $charged, $currency, $live ? 'live' : 'test' ) );

			return;
		}

		/*
		 * The reference moves from the session id to the payment intent when a
		 * session completes, so a replay cannot be recognised by the session id
		 * alone. Money already confirmed on the order is the condition.
		 */
		if ( ! empty( $order->paid_at ) ) {
			return;
		}

		// An event carries the session unexpanded: the card and the promotion code are only on the session itself.
		$expanded = $this->request(
			'checkout/sessions/' . rawurlencode( $reference ),
			array( 'expand' => array( 'payment_intent.latest_charge', 'discounts.promotion_code' ) ),
			'',
			'GET'
		);

		if ( ! is_wp_error( $expanded ) ) {
			$session = $expanded;
		}

		$details  = (array) ( $session['customer_details'] ?? array() );
		$intent   = $session['payment_intent'] ?? $reference;
		$discount = (int) ( $session['total_details']['amount_discount'] ?? 0 );

		$order->gateway        = $this->id;
		$order->transaction_id = (string) ( is_array( $intent ) ? $intent['id'] ?? $reference : $intent );
		$order->payment_method = $this->describe_payment_method( $session );
		$order->paid_at        = current_time( 'mysql', true );

		if ( $discount > 0 ) {
			$order->discount      = $discount;
			$order->discount_code = $this->get_promotion_code( $session );
			$order->total         = (int) ( $session['amount_total'] ?? $order->total );
		}

		$completed = ecout()->get( Orders::class )->payment_complete(
			$order,
			array(
				'email'   => (string) ( $details['email'] ?? '' ),
				'name'    => (string) ( $details['name'] ?? '' ),
				'country' => (string) ( $details['address']['country'] ?? '' ),
			)
		);

		if ( is_wp_error( $completed ) ) {
			ecout()->logger->error( sprintf( 'Stripe: session %s paid order #%d but it could not be completed: %s', $reference, $order->id, $completed->get_error_message() ) );
		}
	}

	/**
	 * Record a refund Stripe reports as a refund order and move the sale's status.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $charge Charge object Stripe sent.
	 * @return void
	 */
	protected function handle_charge_refunded( array $charge ): void {
		$intent = (string) ( $charge['payment_intent'] ?? '' );
		$order  = $this->find_order( $intent, '' );

		if ( ! $order || empty( $order->paid_at ) ) {
			ecout()->logger->warning( 'Stripe: a refund named a payment we do not have: ' . $intent );

			return;
		}

		$refunded = (int) ( $charge['amount_refunded'] ?? 0 );
		$already  = 0;

		foreach ( (array) $order->refunds()->get() as $existing ) {
			$already += (int) $existing->get( 'total' );
		}

		$amount = $refunded - $already;

		if ( $amount <= 0 ) {
			return;
		}

		$refund = ecout()->order->insert(
			array(
				'parent_id'      => $order->id,
				'type'           => 'refund',
				'status'         => 'completed',
				'customer_id'    => $order->customer_id,
				'email'          => $order->email,
				'currency'       => $order->currency,
				'mode'           => $order->mode,
				'total'          => $amount,
				'subtotal'       => $amount,
				'gateway'        => $this->id,
				'transaction_id' => (string) ( $charge['refunds']['data'][0]['id'] ?? $charge['id'] ?? '' ),
				'payment_method' => $order->payment_method,
				'paid_at'        => current_time( 'mysql', true ),
			)
		);

		if ( is_wp_error( $refund ) ) {
			return;
		}

		$order->status = $refunded >= (int) $order->total ? 'refunded' : 'partially_refunded';
		$order->save();
	}

	/**
	 * Get the promotion code a session applied.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $session Session object Stripe sent.
	 * @return string Promotion code, or an empty string when none applied.
	 */
	protected function get_promotion_code( array $session ): string {
		foreach ( (array) ( $session['discounts'] ?? array() ) as $discount ) {
			$code = $discount['promotion_code']['code'] ?? '';

			if ( '' !== (string) $code ) {
				return (string) $code;
			}
		}

		return '';
	}

	/**
	 * Describe what the customer paid with.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $session Session object Stripe sent.
	 * @return string Human-readable payment method.
	 */
	protected function describe_payment_method( array $session ): string {
		$details = (array) ( $session['payment_intent']['latest_charge']['payment_method_details'] ?? array() );
		$type    = (string) ( $details['type'] ?? '' );

		if ( '' === $type ) {
			$types = (array) ( $session['payment_method_types'] ?? array() );
			$type  = (string) ( empty( $types ) ? 'card' : reset( $types ) );
		}

		$card = (array) ( $details[ $type ] ?? array() );

		return trim( ucfirst( (string) ( $card['brand'] ?? $type ) ) . ' ' . (string) ( $card['last4'] ?? '' ) );
	}

	/**
	 * Find the order a webhook is about.
	 *
	 * @since 1.0.0
	 * @param string $reference Session or payment intent id the order holds.
	 * @param string $order_key Order key the session carried, if any.
	 * @return Order|null Order, or null when nothing matches.
	 */
	protected function find_order( string $reference, string $order_key ): ?Order {
		$order = '' === $reference ? null : ecout()->order->find(
			array(
				'gateway'        => $this->id,
				'transaction_id' => $reference,
			)
		);

		if ( ! $order && '' !== $order_key ) {
			$order = ecout()->order->find( array( 'order_key' => $order_key ) );
		}

		return $order instanceof Order ? $order : null;
	}

	/**
	 * Get the deep link to a payment in the Stripe dashboard.
	 *
	 * @since 1.0.0
	 * @param Order $order Order whose payment to link to.
	 * @return string
	 */
	public function get_transaction_url( Order $order ): string {
		$reference = (string) $order->transaction_id;

		if ( '' === $reference ) {
			return '';
		}

		$paths = array(
			'cs_' => 'checkout/sessions',
			're_' => 'refunds',
		);
		$path  = $paths[ substr( $reference, 0, 3 ) ] ?? 'payments';

		return sprintf(
			'https://dashboard.stripe.com/%s%s/%s',
			$this->test_mode ? 'test/' : '',
			$path,
			rawurlencode( $reference )
		);
	}

	/**
	 * Get the gateway's settings fields.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled'               => array(
				'type'     => 'switch',
				'label'    => __( 'Enable Stripe', 'ever-checkout' ),
				'default'  => 'no',
				'sanitize' => 'yes_no',
			),
			'test_mode'             => array(
				'type'     => 'switch',
				'label'    => __( 'Test mode', 'ever-checkout' ),
				'help'     => __( 'Use your test keys. No money moves.', 'ever-checkout' ),
				'default'  => 'yes',
				'sanitize' => 'yes_no',
			),
			'test_publishable_key'  => array(
				'type'     => 'text',
				'label'    => __( 'Test publishable key', 'ever-checkout' ),
				'sanitize' => 'string',
			),
			'test_secret_key'       => array(
				'type'     => 'password',
				'label'    => __( 'Test secret key', 'ever-checkout' ),
				'sanitize' => 'string',
				'autoload' => false,
			),
			'test_webhook_secret'   => array(
				'type'     => 'password',
				'label'    => __( 'Test webhook signing secret', 'ever-checkout' ),
				'sanitize' => 'string',
				'autoload' => false,
			),
			'live_publishable_key'  => array(
				'type'     => 'text',
				'label'    => __( 'Live publishable key', 'ever-checkout' ),
				'sanitize' => 'string',
			),
			'live_secret_key'       => array(
				'type'     => 'password',
				'label'    => __( 'Live secret key', 'ever-checkout' ),
				'sanitize' => 'string',
				'autoload' => false,
			),
			'live_webhook_secret'   => array(
				'type'     => 'password',
				'label'    => __( 'Live webhook signing secret', 'ever-checkout' ),
				'sanitize' => 'string',
				'autoload' => false,
			),
			'allow_promotion_codes' => array(
				'type'     => 'switch',
				'label'    => __( 'Accept promotion codes', 'ever-checkout' ),
				'help'     => __( 'Lets Stripe collect a coupon code you created in your Stripe dashboard.', 'ever-checkout' ),
				'default'  => 'no',
				'sanitize' => 'yes_no',
			),
		);
	}

	/**
	 * Call the Stripe API.
	 *
	 * @since 1.0.0
	 * @param string               $endpoint        Endpoint, relative to the API base.
	 * @param array<string, mixed> $body            Request body, or the query on a GET.
	 * @param string               $idempotency_key Key Stripe uses to return the first result of a repeated request.
	 * @param string               $method          HTTP method.
	 * @return array<string, mixed>|WP_Error Decoded response, or the error Stripe reported.
	 */
	protected function request( string $endpoint, array $body, string $idempotency_key = '', string $method = 'POST' ) {
		$key = $this->get_secret_key();

		if ( '' === $key ) {
			return new WP_Error( 'ecout_stripe_no_key', __( 'Stripe is not configured.', 'ever-checkout' ) );
		}

		$headers = array(
			'Authorization'  => 'Bearer ' . $key,
			'Content-Type'   => 'application/x-www-form-urlencoded',
			'Stripe-Version' => '2024-06-20',
		);

		if ( '' !== $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$response = wp_remote_request(
			self::API . $endpoint . ( 'GET' === $method && ! empty( $body ) ? '?' . http_build_query( $body ) : '' ),
			array(
				'method'  => $method,
				'timeout' => 30,
				'headers' => $headers,
				'body'    => 'GET' === $method ? array() : $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'ecout_stripe_bad_response', __( 'Stripe sent a response we could not read.', 'ever-checkout' ) );
		}

		if ( isset( $decoded['error'] ) ) {
			ecout()->logger->error( 'Stripe: ' . wp_json_encode( $decoded['error'] ) );

			return new WP_Error(
				'ecout_stripe_error',
				(string) ( $decoded['error']['message'] ?? __( 'Stripe refused the request.', 'ever-checkout' ) )
			);
		}

		return $decoded;
	}
}
