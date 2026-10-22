<?php

namespace EverCheckout\RestAPI\Controllers;

use EverCheckout\Models\Order;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard REST controller.
 *
 * @since   1.0.0
 * @package EverCheckout\RestAPI\Controllers
 */
class Dashboard extends Controller {

	/**
	 * Days covered by each period.
	 *
	 * @since 1.0.0
	 * @var array<string, int>
	 */
	const PERIODS = array(
		'7d'  => 7,
		'30d' => 30,
		'90d' => 90,
		'12m' => 365,
	);

	/**
	 * Period used when none is requested.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_PERIOD = '30d';

	/**
	 * Colour each order status reports.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const STATUS_INTENT = array(
		'pending'            => 'warning',
		'completed'          => 'success',
		'partially_refunded' => 'warning',
		'refunded'           => 'danger',
		'failed'             => 'danger',
		'cancelled'          => 'default',
	);

	/**
	 * Get the dashboard.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Dashboard payload.
	 */
	public function get_items( $request ): WP_REST_Response {
		$period = (string) $request->get_param( 'period' );
		$period = isset( self::PERIODS[ $period ] ) ? $period : self::DEFAULT_PERIOD;
		$days   = self::PERIODS[ $period ];
		$start  = time() - ( $days - 1 ) * DAY_IN_SECONDS;
		$since  = gmdate( 'Y-m-d H:i:s', $start );
		$before = gmdate( 'Y-m-d H:i:s', $start - $days * DAY_IN_SECONDS );

		return rest_ensure_response(
			array(
				'period'     => $period,
				'stats'      => $this->get_stats( $since, $before ),
				'chart'      => $this->get_chart( $period, $since ),
				'onboarding' => $this->get_onboarding(),
				'widgets'    => $this->get_widgets(),
			)
		);
	}

	/**
	 * Get the figures for a window of time.
	 *
	 * @since 1.0.0
	 * @param string $since Start of the window, MySQL datetime. Empty for all time.
	 * @param string $until End of the window, MySQL datetime. Empty for now.
	 * @return array<string, int> Revenue, orders, refunded and customers, money in minor units.
	 */
	protected function get_figures( string $since = '', string $until = '' ): array {
		$sales     = ecout()->order->query()->where( 'type', '=', 'sale' )->where( 'status', 'in', Order::PAID_STATUSES );
		$refunds   = ecout()->order->query()->where( 'type', '=', 'refund' );
		$customers = ecout()->customer->query();

		foreach ( array( $sales, $refunds, $customers ) as $query ) {
			if ( '' !== $since ) {
				$query->where( 'created_at', '>=', $since );
			}
			if ( '' !== $until ) {
				$query->where( 'created_at', '<', $until );
			}
		}

		$refunded = (int) $refunds->aggregate( 'SUM', 'total' );

		return array(
			'revenue'   => (int) $sales->aggregate( 'SUM', 'total' ) - $refunded,
			'orders'    => (int) $sales->count(),
			'refunded'  => $refunded,
			'customers' => (int) $customers->count(),
		);
	}

	/**
	 * Get the headline figures, each trended against the preceding period.
	 *
	 * @since 1.0.0
	 * @param string $since  Start of the period, MySQL datetime.
	 * @param string $before Start of the preceding period, MySQL datetime.
	 * @return array<int, array<string, mixed>> Stats.
	 */
	protected function get_stats( string $since, string $before ): array {
		$current  = $this->get_figures( $since );
		$previous = $this->get_figures( $before, $since );

		$labels = array(
			'revenue'   => array( __( 'Net revenue', 'ever-checkout' ), true, '/orders' ),
			'orders'    => array( __( 'Orders', 'ever-checkout' ), false, '/orders' ),
			'refunded'  => array( __( 'Refunded', 'ever-checkout' ), true, '' ),
			'customers' => array( __( 'New customers', 'ever-checkout' ), false, '/customers' ),
		);

		$stats = array();

		foreach ( $labels as $id => list( $title, $money, $route ) ) {
			$value = $current[ $id ];
			$base  = $previous[ $id ];
			$delta = 0 === $base ? 0.0 : round( ( $value - $base ) / abs( $base ) * 100, 1 );

			$stats[] = array(
				'id'    => $id,
				'title' => $title,
				'value' => $money ? ecout()->money->format( $value ) : $value,
				'route' => $route,
				'trend' => array(
					'value'     => abs( $delta ),
					'direction' => 0.0 === $delta ? 'default' : ( $delta > 0 ? 'up' : 'down' ),
				),
			);
		}

		/**
		 * Filters the dashboard stats.
		 *
		 * @since 1.0.0
		 * @param array<int, array<string, mixed>> $stats Stats.
		 * @param string                           $since Start of the period, MySQL datetime.
		 */
		return ecout()->apply_filters( 'dashboard_stats', $stats, $since );
	}

	/**
	 * Get the revenue chart, one row per day, or per month over a year.
	 *
	 * @since 1.0.0
	 * @param string $period Period key.
	 * @param string $since  Start of the period, MySQL datetime.
	 * @return array<string, mixed> Chart definition.
	 */
	protected function get_chart( string $period, string $since ): array {
		$monthly  = '12m' === $period;
		$timezone = wp_timezone();
		$format   = $monthly ? 'Y-m' : 'Y-m-d';
		$start    = ( new \DateTimeImmutable( $since, new \DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone );
		$start    = $start->setDate( (int) $start->format( 'Y' ), (int) $start->format( 'm' ), $monthly ? 1 : (int) $start->format( 'd' ) )->setTime( 0, 0 );
		$now      = new \DateTimeImmutable( 'now', $timezone );
		$buckets  = array();

		for ( $time = $start; $time <= $now; $time = $time->modify( $monthly ? '+1 month' : '+1 day' ) ) {
			$buckets[ $time->format( $format ) ] = 0;
		}

		$orders = ecout()->order->query()
			->select( array( 'type', 'status', 'total', 'created_at' ) )
			->where( 'created_at', '>=', $since )
			->limit( 0 )
			->get();

		foreach ( (array) $orders as $order ) {
			$type = (string) $order->get( 'type' );

			if ( 'sale' === $type && ! in_array( (string) $order->get( 'status' ), Order::PAID_STATUSES, true ) ) {
				continue;
			}

			$bucket = wp_date( $format, (int) strtotime( (string) $order->get( 'created_at' ) ), $timezone );

			if ( isset( $buckets[ $bucket ] ) ) {
				$buckets[ $bucket ] += ( 'sale' === $type ? 1 : -1 ) * (int) $order->get( 'total' );
			}
		}

		$exponent = (int) ecout()->money->get_config()['exponent'];
		$rows     = array();

		foreach ( $buckets as $bucket => $revenue ) {
			$day    = new \DateTimeImmutable( $monthly ? $bucket . '-01' : $bucket, $timezone );
			$rows[] = array(
				'label'   => wp_date( $monthly ? 'M Y' : 'j M', $day->getTimestamp(), $timezone ),
				'revenue' => $revenue / pow( 10, $exponent ),
			);
		}

		return array(
			'chart'       => 'line',
			'categoryKey' => 'label',
			'money'       => true,
			'series'      => array(
				array(
					'key'   => 'revenue',
					'label' => __( 'Net revenue', 'ever-checkout' ),
				),
			),
			'data'        => $rows,
		);
	}

	/**
	 * Get the setup progress.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Steps, and how many are done.
	 */
	protected function get_onboarding(): array {
		$steps = array(
			array(
				'key'       => 'gateway',
				'label'     => __( 'Connect a payment gateway', 'ever-checkout' ),
				'completed' => ! empty( ecout()->gateways->get_available_gateways() ),
				'route'     => '/settings/payments',
			),
			array(
				'key'       => 'receipt',
				'label'     => __( 'Choose a receipt page', 'ever-checkout' ),
				'completed' => (bool) ecout()->options->get( 'receipt_page' ),
				'route'     => '/settings/store',
			),
			array(
				'key'       => 'product',
				'label'     => __( 'Add your first product', 'ever-checkout' ),
				'completed' => ecout()->product->query()->count() > 0,
				'route'     => '/products/new',
			),
			array(
				'key'       => 'sale',
				'label'     => __( 'Make your first sale', 'ever-checkout' ),
				'completed' => ecout()->order->query()->count() > 0,
				'route'     => '/orders',
			),
		);

		return array(
			'steps'     => $steps,
			'completed' => count( array_filter( wp_list_pluck( $steps, 'completed' ) ) ),
			'total'     => count( $steps ),
		);
	}

	/**
	 * Get the widgets.
	 *
	 * @since 1.0.0
	 * @return array<int, array<string, mixed>> Widgets, each naming its type and area.
	 */
	protected function get_widgets(): array {
		$widgets  = array( $this->get_recent_orders_widget() );
		$lifetime = $this->get_figures();

		$widgets[] = array(
			'id'       => 'lifetime',
			'type'     => 'list',
			'title'    => __( 'All time', 'ever-checkout' ),
			'area'     => 'sidebar',
			'priority' => 10,
			'items'    => array(
				array(
					'id'    => 'revenue',
					'title' => __( 'Revenue', 'ever-checkout' ),
					'extra' => ecout()->money->format( $lifetime['revenue'] ),
					'route' => '/orders',
				),
				array(
					'id'    => 'orders',
					'title' => __( 'Orders', 'ever-checkout' ),
					'extra' => number_format_i18n( $lifetime['orders'] ),
					'route' => '/orders',
				),
				array(
					'id'    => 'refunded',
					'title' => __( 'Refunded', 'ever-checkout' ),
					'extra' => ecout()->money->format( $lifetime['refunded'] ),
				),
				array(
					'id'    => 'customers',
					'title' => __( 'Customers', 'ever-checkout' ),
					'extra' => number_format_i18n( $lifetime['customers'] ),
					'route' => '/customers',
				),
				array(
					'id'    => 'products',
					'title' => __( 'Products', 'ever-checkout' ),
					'extra' => number_format_i18n( ecout()->product->query()->count() ),
					'route' => '/products',
				),
			),
		);

		$attention = $this->get_attention_items();

		if ( ! empty( $attention ) ) {
			$widgets[] = array(
				'id'       => 'attention',
				'type'     => 'list',
				'title'    => __( 'Needs attention', 'ever-checkout' ),
				'area'     => 'sidebar',
				'priority' => 20,
				'items'    => $attention,
			);
		}

		/**
		 * Filters the dashboard widgets.
		 *
		 * @since 1.0.0
		 * @param array<int, array<string, mixed>> $widgets Widgets.
		 */
		$widgets = ecout()->apply_filters( 'dashboard_widgets', $widgets );

		usort( $widgets, static fn( $a, $b ) => ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 ) );

		return $widgets;
	}

	/**
	 * Get the recent orders as a table widget.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Widget.
	 */
	protected function get_recent_orders_widget(): array {
		$orders = ecout()->order->query()
			->with( 'items' )
			->where( 'type', 'sale' )
			->where( 'status', 'not_in', array( 'pending', 'cancelled' ) )
			->order_by( 'created_at', 'DESC' )
			->limit( 8 )
			->get();

		$statuses = Order::get_statuses();
		$rows     = array();

		foreach ( (array) $orders as $order ) {
			$rows[] = array(
				'id'       => (int) $order->id,
				'route'    => '/orders/' . (int) $order->id,
				'number'   => '#' . (int) $order->id,
				'customer' => (string) $order->email,
				'product'  => $order->get_product_names(),
				'status'   => array(
					'label'  => $statuses[ $order->status ] ?? (string) $order->status,
					'intent' => self::STATUS_INTENT[ $order->status ] ?? 'default',
				),
				'date'     => $this->prepare_date_response( (string) $order->created_at ),
				'total'    => ecout()->money->format( (int) $order->total, (string) $order->currency ),
			);
		}

		return array(
			'id'       => 'recent-orders',
			'type'     => 'table',
			'title'    => __( 'Recent orders', 'ever-checkout' ),
			'area'     => 'main',
			'priority' => 10,
			'columns'  => array(
				array(
					'key'    => 'number',
					'label'  => __( 'Order', 'ever-checkout' ),
					'format' => 'link',
					'width'  => 90,
				),
				array(
					'key'      => 'customer',
					'label'    => __( 'Customer', 'ever-checkout' ),
					'minWidth' => 220,
				),
				array(
					'key'      => 'product',
					'label'    => __( 'Product', 'ever-checkout' ),
					'minWidth' => 200,
				),
				array(
					'key'   => 'status',
					'label' => __( 'Status', 'ever-checkout' ),
					'width' => 140,
				),
				array(
					'key'   => 'total',
					'label' => __( 'Total', 'ever-checkout' ),
					'align' => 'end',
					'width' => 110,
				),
				array(
					'key'    => 'date',
					'label'  => __( 'Date', 'ever-checkout' ),
					'format' => 'date',
					'width'  => 140,
				),
			),
			'data'     => $rows,
			'empty'    => __( 'Orders land here the moment someone pays.', 'ever-checkout' ),
			'footer'   => array(
				'label' => __( 'View all', 'ever-checkout' ),
				'route' => '/orders',
			),
		);
	}

	/**
	 * Get the things waiting on the seller.
	 *
	 * @since 1.0.0
	 * @return array<int, array<string, mixed>> List items.
	 */
	protected function get_attention_items(): array {
		$items   = array();
		$pending = ecout()->order->query()->where( 'type', '=', 'sale' )->where( 'status', '=', 'pending' )->count();
		$drafts  = ecout()->product->query()->where( 'status', '=', 'draft' )->count();

		if ( $pending > 0 ) {
			$items[] = array(
				'id'    => 'pending',
				'title' => __( 'Pending orders', 'ever-checkout' ),
				'extra' => number_format_i18n( $pending ),
				'route' => '/orders?' . $this->get_status_filter( 'pending' ),
			);
		}

		if ( $drafts > 0 ) {
			$items[] = array(
				'id'    => 'drafts',
				'title' => __( 'Draft products', 'ever-checkout' ),
				'extra' => number_format_i18n( $drafts ),
				'route' => '/products?' . $this->get_status_filter( 'draft' ),
			);
		}

		return $items;
	}

	/**
	 * Get the query string that opens a list filtered to one status.
	 *
	 * @since 1.0.0
	 * @param string $status Status to filter by.
	 * @return string URL-encoded `filters` parameter.
	 */
	protected function get_status_filter( string $status ): string {
		return http_build_query(
			array(
				'filters' => wp_json_encode(
					array(
						'match'   => 'all',
						'filters' => array(
							array(
								'id'       => 'status',
								'field'    => 'status',
								'operator' => 'eq',
								'value'    => $status,
							),
						),
					)
				),
			)
		);
	}
}
