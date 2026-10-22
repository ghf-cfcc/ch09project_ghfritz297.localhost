<?php
/**
 * Plugin Name:       Ever Checkout
 * Description:       Sell digital products from WordPress with your own checkout and Stripe.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            ByteEver
 * Author URI:        https://byteever.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ever-checkout
 * Domain Path:       /languages
 *
 * @package           EverCheckout
 * @author            ByteEver <support@byteever.com>
 * @copyright         2026 ByteEver
 * @license           GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

use EverCheckout\Plugin;

defined( 'ABSPATH' ) || exit;

// Load the Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

Plugin::create(
	__FILE__,
	array(
		'version'      => '1.0.0',
		'short_name'   => 'ecout',
		'hook_prefix'  => 'ecout',
		'settings_url' => admin_url( 'admin.php?page=ever-checkout#/settings' ),
	)
);

/**
 * Get the main plugin instance.
 *
 * @since 1.0.0
 * @return Plugin Plugin instance.
 */
function ecout(): Plugin {
	return Plugin::instance();
}

// Boot the plugin.
ecout()->bootstrap();
