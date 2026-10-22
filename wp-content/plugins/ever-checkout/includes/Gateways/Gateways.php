<?php

namespace EverCheckout\Gateways;

use EverCheckout\B8\Component;
use EverCheckout\Plugin;
use EverCheckout\Models\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the payment gateways.
 *
 * @since   1.0.0
 * @package EverCheckout\Gateways
 *
 * @extends Component<Plugin>
 */
class Gateways extends Component {

	/**
	 * Gateway instances keyed by id.
	 *
	 * @since 1.0.0
	 * @var array<string, Gateway>
	 */
	protected array $gateways = array();

	/**
	 * Get every registered gateway.
	 *
	 * @since 1.0.0
	 * @return array<string, Gateway> Gateways keyed by id, in their sort order.
	 */
	public function get_gateways(): array {
		if ( ! empty( $this->gateways ) ) {
			return $this->gateways;
		}

		/**
		 * Filters the registered gateway classes.
		 *
		 * Append a class name to offer another gateway; there is no privileged route.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, class-string<Gateway>> $gateways Fully-qualified Gateway subclass names.
		 */
		$classes = $this->app->apply_filters( 'payment_gateways', array( StripeGateway::class, Offline::class, Test::class ) );

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) || ! is_subclass_of( $class, Gateway::class ) ) {
				continue;
			}

			$gateway = new $class();

			if ( '' !== $gateway->id ) {
				$this->gateways[ $gateway->id ] = $gateway;
			}
		}

		uasort( $this->gateways, static fn( $a, $b ) => $a->order <=> $b->order );

		return $this->gateways;
	}

	/**
	 * Get one gateway.
	 *
	 * @since 1.0.0
	 * @param string $id Gateway id.
	 * @return Gateway|null Gateway, or null when it is not registered.
	 */
	public function get_gateway( string $id ): ?Gateway {
		return $this->get_gateways()[ $id ] ?? null;
	}

	/**
	 * Get the gateways that can take an order.
	 *
	 * @since 1.0.0
	 * @param Order|null $order Order being paid, when one exists yet.
	 * @return array<string, Gateway> Available gateways keyed by id.
	 */
	public function get_available_gateways( ?Order $order = null ): array {
		return array_filter(
			$this->get_gateways(),
			static fn( Gateway $gateway ) => $gateway->is_available( $order )
		);
	}
}
