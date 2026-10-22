<?php

namespace EverCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Currency reference data.
 *
 * @since   1.0.0
 * @package EverCheckout\Services
 */
class Locale {

	/**
	 * Get the supported currencies.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Currency data keyed by ISO 4217 code.
	 */
	public function currencies(): array {
		/**
		 * Filters the supported currencies.
		 *
		 * `exponent` must be the ISO 4217 minor unit.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, mixed>> $currencies Currency data keyed by ISO 4217 code.
		 */
		return ecout()->apply_filters(
			'currencies',
			array(
				'USD' => array(
					'name'     => 'US Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'EUR' => array(
					'name'     => 'Euro',
					'symbol'   => '€',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'GBP' => array(
					'name'     => 'Pound Sterling',
					'symbol'   => '£',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'JPY' => array(
					'name'     => 'Yen',
					'symbol'   => '¥',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 0,
				),
				'AUD' => array(
					'name'     => 'Australian Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ' ',
					'exponent' => 2,
				),
				'CAD' => array(
					'name'     => 'Canadian Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'CHF' => array(
					'name'     => 'Swiss Franc',
					'symbol'   => 'CHF',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'CNY' => array(
					'name'     => 'Yuan Renminbi',
					'symbol'   => '¥',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'HKD' => array(
					'name'     => 'Hong Kong Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'NZD' => array(
					'name'     => 'New Zealand Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'SEK' => array(
					'name'     => 'Swedish Krona',
					'symbol'   => 'kr',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => ' ',
					'exponent' => 2,
				),
				'NOK' => array(
					'name'     => 'Norwegian Krone',
					'symbol'   => 'kr',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'DKK' => array(
					'name'     => 'Danish Krone',
					'symbol'   => 'kr',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'SGD' => array(
					'name'     => 'Singapore Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'INR' => array(
					'name'     => 'Indian Rupee',
					'symbol'   => '₹',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'BRL' => array(
					'name'     => 'Brazilian Real',
					'symbol'   => 'R$',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'MXN' => array(
					'name'     => 'Mexican Peso',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'ZAR' => array(
					'name'     => 'Rand',
					'symbol'   => 'R',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'PLN' => array(
					'name'     => 'Zloty',
					'symbol'   => 'zł',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => ' ',
					'exponent' => 2,
				),
				'CZK' => array(
					'name'     => 'Czech Koruna',
					'symbol'   => 'Kč',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'HUF' => array(
					'name'     => 'Forint',
					'symbol'   => 'Ft',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'RON' => array(
					'name'     => 'New Romanian Leu',
					'symbol'   => 'Lei',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'TRY' => array(
					'name'     => 'Turkish Lira',
					'symbol'   => '₺',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'AED' => array(
					'name'     => 'UAE Dirham',
					'symbol'   => 'د.إ',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'SAR' => array(
					'name'     => 'Saudi Riyal',
					'symbol'   => 'ر.س',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'ILS' => array(
					'name'     => 'New Israeli Sheqel',
					'symbol'   => '₪',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'KRW' => array(
					'name'     => 'Won',
					'symbol'   => '₩',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 0,
				),
				'RUB' => array(
					'name'     => 'Russian Ruble',
					'symbol'   => '₽',
					'position' => 'after',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'MYR' => array(
					'name'     => 'Malaysian Ringgit',
					'symbol'   => 'RM',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'THB' => array(
					'name'     => 'Baht',
					'symbol'   => '฿',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'PHP' => array(
					'name'     => 'Philippine Peso',
					'symbol'   => '₱',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'IDR' => array(
					'name'     => 'Rupiah',
					'symbol'   => 'Rp',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'VND' => array(
					'name'     => 'Dong',
					'symbol'   => '₫',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 0,
				),
				'BDT' => array(
					'name'     => 'Bangladeshi Taka',
					'symbol'   => '৳',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'PKR' => array(
					'name'     => 'Pakistan Rupee',
					'symbol'   => '₨',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'NGN' => array(
					'name'     => 'Naira',
					'symbol'   => '₦',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'KES' => array(
					'name'     => 'Kenyan Shilling',
					'symbol'   => 'KSh',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'EGP' => array(
					'name'     => 'Egyptian Pound',
					'symbol'   => 'ج.م',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'CLP' => array(
					'name'     => 'Chilean Peso',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 0,
				),
				'ARS' => array(
					'name'     => 'Argentine Peso',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'COP' => array(
					'name'     => 'Colombian Peso',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 2,
				),
				'TWD' => array(
					'name'     => 'New Taiwan Dollar',
					'symbol'   => '$',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 2,
				),
				'ISK' => array(
					'name'     => 'Iceland Krona',
					'symbol'   => 'kr',
					'position' => 'before',
					'decimal'  => ',',
					'thousand' => '.',
					'exponent' => 0,
				),
				'KWD' => array(
					'name'     => 'Kuwaiti Dinar',
					'symbol'   => 'د.ك',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 3,
				),
				'BHD' => array(
					'name'     => 'Bahraini Dinar',
					'symbol'   => 'ب.د',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 3,
				),
				'OMR' => array(
					'name'     => 'Rial Omani',
					'symbol'   => 'ر.ع.',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 3,
				),
				'JOD' => array(
					'name'     => 'Jordanian Dinar',
					'symbol'   => 'د.ا',
					'position' => 'before',
					'decimal'  => '.',
					'thousand' => ',',
					'exponent' => 3,
				),
			)
		);
	}

	/**
	 * Get one currency.
	 *
	 * @since 1.0.0
	 * @param string $code ISO 4217 currency code.
	 * @return array<string, mixed>|null Currency data, or null when it is not supported.
	 */
	public function get_currency( string $code ): ?array {
		$currencies = $this->currencies();
		$code       = strtoupper( $code );

		return $currencies[ $code ] ?? null;
	}

	/**
	 * Get the display defaults of a currency.
	 *
	 * @since 1.0.0
	 * @param string $code ISO 4217 currency code.
	 * @return array<string, mixed> Format defaults, falling back to the US dollar shape.
	 */
	public function get_defaults( string $code ): array {
		return $this->get_currency( $code ) ?? array(
			'name'     => $code,
			'symbol'   => '$',
			'position' => 'before',
			'decimal'  => '.',
			'thousand' => ',',
			'exponent' => 2,
		);
	}

	/**
	 * Get the ISO 4217 minor unit of a currency.
	 *
	 * @since 1.0.0
	 * @param string $code ISO 4217 currency code.
	 * @return int Number of minor-unit digits.
	 */
	public function get_exponent( string $code ): int {
		$currency = $this->get_currency( $code );

		return null === $currency ? 2 : (int) $currency['exponent'];
	}

	/**
	 * Get the currencies as select options.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Labels keyed by ISO 4217 code.
	 */
	public function get_currency_options(): array {
		$options = array();

		foreach ( $this->currencies() as $code => $currency ) {
			$options[ $code ] = sprintf( '%s (%s)', $currency['name'], $currency['symbol'] );
		}

		return $options;
	}
}
