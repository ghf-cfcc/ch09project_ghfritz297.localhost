<?php

namespace EverCheckout;

use EverCheckout\B8\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin installation.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @extends Component<Plugin>
 */
class Installer extends Component {

	/**
	 * Hook that runs pending upgrades.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const UPDATE_HOOK = 'ecout_run_update';

	/**
	 * Queued action that runs the daily housekeeping.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DAILY_HOOK = 'ecout_daily';

	/**
	 * Upgrade routines keyed by the target version.
	 *
	 * @since 1.0.0
	 * @var array<string, callable>
	 */
	protected array $updates = array();

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_update' ) );
		add_action( 'init', array( $this, 'schedule_daily' ) );
		add_action( self::UPDATE_HOOK, array( $this, 'run_update' ) );
		add_action( self::DAILY_HOOK, array( $this, 'schedule_daily' ), 999 );
	}

	/**
	 * Queue the next daily housekeeping run, unless one is already waiting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function schedule_daily(): void {
		if ( ! $this->app->queue->is_scheduled( self::DAILY_HOOK ) ) {
			$this->app->queue->schedule( time() + DAY_IN_SECONDS, self::DAILY_HOOK );
		}
	}

	/**
	 * Run a pending upgrade.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_update(): void {
		if ( version_compare( $this->app->version, $this->app->options->get_db_version(), '>' ) ) {
			if ( $this->app->queue->is_scheduled( self::UPDATE_HOOK ) ) {
				return;
			}

			$this->install();

			if ( array() === $this->updates ) {
				$this->app->options->update_db_version( $this->app->version, true );
			} else {
				$this->app->queue->add( self::UPDATE_HOOK );
			}
		}
	}

	/**
	 * Run the pending upgrades.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_update(): void {
		$installed = $this->app->options->get_db_version();

		uksort( $this->updates, static fn( string $a, string $b ): int => version_compare( $a, $b ) );

		foreach ( $this->updates as $version => $callback ) {
			if ( version_compare( $installed, $version, '<' ) ) {
				call_user_func( $callback );
				$this->app->options->update_db_version( $version, true );
			}
		}

		$this->app->options->update_db_version( $this->app->version, true );
	}

	/**
	 * Install the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function install(): void {
		$this->create_tables();
		$this->create_files();
		$this->create_defaults();

		$this->app->options->add( 'installed_on', gmdate( 'Y-m-d H:i:s' ) );
		$this->app->options->update_db_version( $this->app->version );
		$this->schedule_daily();
	}

	/**
	 * Clean up the plugin's runtime state.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate(): void {
		$this->app->queue->clear();
	}

	/**
	 * Create the database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_tables(): void {
		global $wpdb;
		$showing = $wpdb->hide_errors();
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		$tables = "
CREATE TABLE {$wpdb->prefix}ecout_products (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
name VARCHAR(255) NOT NULL DEFAULT '',
type VARCHAR(20) NOT NULL DEFAULT 'simple',
status VARCHAR(20) NOT NULL DEFAULT 'active',
description LONGTEXT NULL,
image_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
price BIGINT(20) NOT NULL DEFAULT 0,
download_limit INT(11) NOT NULL DEFAULT 0,
download_expiry_days INT(11) NOT NULL DEFAULT 0,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL DEFAULT NULL,
PRIMARY KEY (id),
KEY status (status)
) $collate;

CREATE TABLE {$wpdb->prefix}ecout_downloads (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
name VARCHAR(255) NOT NULL DEFAULT '',
file_path VARCHAR(2000) NOT NULL DEFAULT '',
file_name VARCHAR(255) NOT NULL DEFAULT '',
file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
mime_type VARCHAR(100) NOT NULL DEFAULT '',
enabled TINYINT(1) NOT NULL DEFAULT 1,
position INT(11) NOT NULL DEFAULT 0,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL DEFAULT NULL,
PRIMARY KEY (id),
KEY product_id (product_id),
KEY enabled (enabled)
) $collate;

CREATE TABLE {$wpdb->prefix}ecout_customers (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
email VARCHAR(100) NOT NULL DEFAULT '',
name VARCHAR(255) NOT NULL DEFAULT '',
country CHAR(2) NOT NULL DEFAULT '',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL DEFAULT NULL,
PRIMARY KEY (id),
UNIQUE KEY email (email),
KEY user_id (user_id)
) $collate;

CREATE TABLE {$wpdb->prefix}ecout_orders (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
order_key CHAR(32) NOT NULL DEFAULT '',
parent_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
type VARCHAR(20) NOT NULL DEFAULT 'sale',
status VARCHAR(20) NOT NULL DEFAULT 'pending',
customer_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
email VARCHAR(100) NOT NULL DEFAULT '',
name VARCHAR(255) NOT NULL DEFAULT '',
currency CHAR(3) NOT NULL DEFAULT '',
mode VARCHAR(10) NOT NULL DEFAULT 'live',
subtotal BIGINT(20) NOT NULL DEFAULT 0,
discount BIGINT(20) NOT NULL DEFAULT 0,
total BIGINT(20) NOT NULL DEFAULT 0,
discount_code VARCHAR(50) NOT NULL DEFAULT '',
ip_address VARCHAR(45) NOT NULL DEFAULT '',
gateway VARCHAR(50) NOT NULL DEFAULT '',
transaction_id VARCHAR(255) NOT NULL DEFAULT '',
payment_method VARCHAR(100) NOT NULL DEFAULT '',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL DEFAULT NULL,
paid_at DATETIME NULL DEFAULT NULL,
completed_at DATETIME NULL DEFAULT NULL,
PRIMARY KEY (id),
UNIQUE KEY order_key (order_key),
KEY parent_id (parent_id),
KEY customer_id (customer_id),
KEY type_status_created (type, status, created_at),
KEY created_at (created_at),
KEY transaction_id (transaction_id(191))
) $collate;

CREATE TABLE {$wpdb->prefix}ecout_order_items (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
product_name VARCHAR(255) NOT NULL DEFAULT '',
price BIGINT(20) NOT NULL DEFAULT 0,
quantity INT(11) NOT NULL DEFAULT 1,
subtotal BIGINT(20) NOT NULL DEFAULT 0,
total BIGINT(20) NOT NULL DEFAULT 0,
position INT(11) NOT NULL DEFAULT 0,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL DEFAULT NULL,
PRIMARY KEY (id),
KEY order_id (order_id),
KEY product_id (product_id)
) $collate;

CREATE TABLE {$wpdb->prefix}ecout_download_logs (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
download_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
customer_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
ip_address VARCHAR(45) NOT NULL DEFAULT '',
user_agent VARCHAR(255) NOT NULL DEFAULT '',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
KEY order_download (order_id, download_id),
KEY download_id (download_id),
KEY customer_id (customer_id),
KEY created_at (created_at)
) $collate;
";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $tables );
		$wpdb->show_errors( $showing );
	}

	/**
	 * Create the protected upload directory.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_files(): void {
		$this->app->fs->protect( ECOUT_UPLOAD_DIR );
	}

	/**
	 * Create the default options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_defaults(): void {
		$added = array();

		foreach ( $this->app->settings->get_values() as $name => $value ) {
			$added[ $name ] = $this->app->options->add( $name, $value );
		}

		if ( ! empty( $added['currency'] ) ) {
			$this->app->settings->set_currency_formats( (string) $this->app->options->get( 'currency', 'USD' ) );
		}
	}
}
