<?php

namespace EverCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Formats money held as integers in a currency's minor unit.
 *
 * @since   1.0.0
 * @package EverCheckout\Services
 */
class Money {

	/**
	 * Get how a currency is displayed, the store's overrides applied.
	 *
	 * The exponent is the currency's ISO minor unit and never comes from a
	 * setting: `precision` decides how many decimals are shown, not what an
	 * amount is divided by.
	 *
	 * @since 1.0.0
	 * @param string $currency Currency code. Defaults to the store currency.
	 * @return array{code: string, symbol: string, position: string, decimal: string, thousand: string, precision: int, exponent: int}
	 */
	public function get_config( string $currency = '' ): array {
		$options  = ecout()->options;
		$store    = (string) $options->get( 'currency', 'USD' );
		$code     = '' !== $currency ? strtoupper( $currency ) : $store;
		$defaults = ecout()->locale->get_defaults( $code );
		$is_store = $code === $store;

		return array(
			'code'      => $code,
			'symbol'    => (string) $defaults['symbol'],
			'position'  => $is_store ? (string) $options->get( 'currency_position', $defaults['position'] ) : (string) $defaults['position'],
			'decimal'   => $is_store ? (string) $options->get( 'currency_decimal', $defaults['decimal'] ) : (string) $defaults['decimal'],
			'thousand'  => $is_store ? (string) $options->get( 'currency_thousand', $defaults['thousand'] ) : (string) $defaults['thousand'],
			'precision' => $is_store ? (int) $options->get( 'currency_precision', $defaults['exponent'] ) : (int) $defaults['exponent'],
			'exponent'  => ecout()->locale->get_exponent( $code ),
		);
	}

	/**
	 * Format an amount for display.
	 *
	 * @since 1.0.0
	 * @param int    $amount   Amount in minor units.
	 * @param string $currency Currency code. Defaults to the store currency.
	 * @return string The formatted amount.
	 */
	public function format( int $amount, string $currency = '' ): string {
		$config = $this->get_config( $currency );
		$number = number_format( abs( $amount ) / pow( 10, $config['exponent'] ), $config['precision'], $config['decimal'], $config['thousand'] );
		$number = 'after' === $config['position'] ? $number . $config['symbol'] : $config['symbol'] . $number;

		return $amount < 0 ? '-' . $number : $number;
	}
}
