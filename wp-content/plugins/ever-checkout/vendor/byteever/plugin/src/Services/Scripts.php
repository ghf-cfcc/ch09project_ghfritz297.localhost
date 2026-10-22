<?php

namespace EverCheckout\B8\Services;

use EverCheckout\B8\App;

defined( 'ABSPATH' ) || exit;

/**
 * Handles asset registration.
 *
 * Handles registration and enqueuing of scripts and styles, resolving
 * versioned URLs and paths from the build directory.
 *
 * @since 1.0.0
 * @package B8\Services
 */
class Scripts {
	/**
	 * The application instance.
	 *
	 * @since 1.0.0
	 * @var App
	 */
	protected $app;

	/**
	 * Handles registered with translations.
	 *
	 * @since 1.2.0
	 * @var array<string, bool>
	 */
	protected array $translated = array();

	/**
	 * Constructor.
	 *
	 * @param App $app The application instance.
	 *
	 * @since 1.0.0
	 */
	public function __construct( App $app ) {
		$this->app = $app;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'upgrader_process_complete', array( $this, 'combine_installed_translations' ), 10, 2 );
		add_action( 'activated_plugin', array( $this, 'combine_activated_translations' ) );
		add_filter( 'load_script_translation_file', array( $this, 'load_script_translation_file' ), 10, 3 );
	}

	/**
	 * Combine the chunk translations of a language pack once it is installed.
	 *
	 * @since 1.2.0
	 * @param \WP_Upgrader         $upgrader   Upgrader that ran.
	 * @param array<string, mixed> $hook_extra What it installed.
	 * @return void
	 */
	public function combine_installed_translations( $upgrader, $hook_extra ): void {
		if ( 'translation' !== ( $hook_extra['type'] ?? '' ) ) {
			return;
		}

		foreach ( (array) ( $hook_extra['translations'] ?? array() ) as $translation ) {
			$type = (string) ( $translation['type'] ?? '' );
			$slug = (string) ( $translation['slug'] ?? '' );

			if ( 'plugin' === $type && $this->app->text_domain === $slug ) {
				$this->combine_translations( (string) $translation['language'] );
			}
		}
	}

	/**
	 * Combine the chunk translations of the current locale when the plugin is activated.
	 *
	 * @since 1.2.0
	 * @param string $plugin Basename of the activated plugin.
	 * @return void
	 */
	public function combine_activated_translations( $plugin ): void {
		if ( $this->app->basename() === $plugin ) {
			$this->combine_translations( determine_locale() );
		}
	}

	/**
	 * Merge every language-pack JSON that references the build directory into one file.
	 *
	 * Language packs ship one JSON per source file, so the strings inside a
	 * lazy chunk land in a file core never loads for the entry handle.
	 *
	 * @since 1.2.0
	 * @param string $locale Locale to combine.
	 * @return void
	 */
	public function combine_translations( string $locale ): void {
		$fs       = $this->app->fs;
		$domain   = $this->app->text_domain;
		$header   = array();
		$messages = array();
		$files    = glob( WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '-*.json' );

		foreach ( (array) $files as $file ) {
			$file = (string) $file;

			if ( $this->get_combined_translations_file( $locale ) === $file ) {
				continue;
			}

			$data      = json_decode( (string) $fs->get( $file ), true );
			$reference = (string) ( $data['comment']['reference'] ?? $data['source'] ?? '' );

			if ( ! str_starts_with( $reference, $this->app->build_dir . '/' ) ) {
				continue;
			}

			foreach ( (array) ( $data['locale_data'] ?? array() ) as $entries ) {
				if ( array() === $header ) {
					$header = (array) ( $entries[''] ?? array() );
				}

				$messages = array_replace( $messages, array_diff_key( (array) $entries, array( '' => true ) ) );
			}
		}

		if ( array() === $messages ) {
			return;
		}

		$fs->put(
			$this->get_combined_translations_file( $locale ),
			(string) wp_json_encode(
				array(
					'domain'      => $domain,
					'locale_data' => array( $domain => array( '' => $header ) + $messages ),
				)
			)
		);
	}

	/**
	 * Get the path of the combined translation file for a locale.
	 *
	 * @since 1.2.0
	 * @param string $locale Locale.
	 * @return string
	 */
	public function get_combined_translations_file( string $locale ): string {
		return WP_LANG_DIR . '/plugins/' . $this->app->text_domain . '-' . $locale . '-' . md5( $this->app->build_dir ) . '.json';
	}

	/**
	 * Hand core the combined file for a script that carries this plugin's translations.
	 *
	 * @since 1.2.0
	 * @param string|false $file   File core is about to read.
	 * @param string       $handle Script handle.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public function load_script_translation_file( $file, $handle, $domain ) {
		if ( $domain !== $this->app->text_domain || ! isset( $this->translated[ $handle ] ) ) {
			return $file;
		}

		$combined = $this->get_combined_translations_file( determine_locale() );

		return $this->app->fs->exists( $combined ) ? $combined : $file;
	}

	/**
	 * Register a script.
	 *
	 * @param string             $handle Script handle. Should be unique.
	 * @param string             $src Script source path relative to the build directory or absolute URL.
	 * @param array<int, string> $deps Array of script dependencies. Default empty array.
	 * @param bool               $in_footer Whether to enqueue in footer. Default false.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function register_script( $handle, $src, $deps = array(), bool $in_footer = false ): bool {
		$url  = $this->get_asset_url( $src );
		$path = $this->get_asset_path( $src );

		if ( '' === $handle || '' === $url ) {
			return false;
		}

		$asset_file = str_replace( '.js', '.asset.php', $path );
		$asset_data = file_exists( $asset_file ) ? require $asset_file : array();
		$asset_data = wp_parse_args(
			$asset_data,
			array(
				'dependencies' => array(),
				'version'      => file_exists( $path ) ? (string) filemtime( $path ) : $this->app->version,
			)
		);

		$merged_deps = array_merge( $asset_data['dependencies'], $deps );

		$registered = wp_register_script( $handle, $url, $merged_deps, $asset_data['version'], $in_footer );

		if ( $registered && in_array( 'wp-i18n', $merged_deps, true ) ) {
			wp_set_script_translations( $handle, $this->app->text_domain, $this->app->plugin_path( ltrim( $this->app->domain_path, '/' ) ) );
			$this->translated[ $handle ] = true;
		}

		return $registered;
	}

	/**
	 * Register a stylesheet.
	 *
	 * @param string             $handle Style handle. Should be unique.
	 * @param string             $src Style source path relative to the build directory or absolute URL.
	 * @param array<int, string> $deps Array of style dependencies. Default empty array.
	 * @param string             $media Media type for stylesheet. Default 'all'.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function register_style( $handle, $src, $deps = array(), string $media = 'all' ): bool {
		$url  = $this->get_asset_url( $src );
		$path = $this->get_asset_path( $src );

		$asset_file = str_replace( '.css', '.asset.php', $path );
		$asset_data = file_exists( $asset_file ) ? require $asset_file : array();
		$asset_data = wp_parse_args(
			$asset_data,
			array(
				'version' => file_exists( $path ) ? (string) filemtime( $path ) : $this->app->version,
			)
		);

		$registered = wp_register_style( $handle, $url, $deps, $asset_data['version'], $media );

		if ( $registered && is_rtl() && file_exists( str_replace( '.css', '-rtl.css', $path ) ) ) {
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}

		return $registered;
	}

	/**
	 * Enqueue a script.
	 *
	 * @param string             $handle Script handle.
	 * @param string|null        $src Script source path. Required if not already registered.
	 * @param array<int, string> $deps Array of script dependencies. Default empty array.
	 * @param bool               $in_footer Whether to enqueue in footer. Default false.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function enqueue_script( $handle, $src = null, $deps = array(), bool $in_footer = false ): bool {
		if ( '' === $handle ) {
			return false;
		}

		if ( ! wp_script_is( $handle, 'registered' ) && ! empty( $src ) ) {
			$this->register_script( $handle, $src, $deps, $in_footer );
		}

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return false;
		}

		wp_enqueue_script( $handle );
		return true;
	}

	/**
	 * Enqueue a stylesheet.
	 *
	 * @param string             $handle Style handle.
	 * @param string|null        $src Style source path. Required if not already registered.
	 * @param array<int, string> $deps Array of style dependencies. Default empty array.
	 * @param string             $media Media type for stylesheet. Default 'all'.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function enqueue_style( $handle, $src = null, $deps = array(), string $media = 'all' ): bool {
		if ( ! wp_style_is( $handle, 'registered' ) && ! empty( $src ) ) {
			$this->register_style( $handle, $src, $deps, $media );
		}

		if ( ! wp_style_is( $handle, 'registered' ) ) {
			return false;
		}

		wp_enqueue_style( $handle );
		return true;
	}

	/**
	 * Get the asset URL.
	 *
	 * @param string $src Asset source path or URL.
	 *
	 * @since 1.0.0
	 * @return string Asset URL.
	 */
	protected function get_asset_url( $src ): string {
		return preg_match( '/^(https?:)?\/\//', $src )
			? $src
			: $this->app->plugin_url( $this->app->build_dir . '/' . $src );
	}

	/**
	 * Get the asset path.
	 *
	 * @param string $src Asset source path or URL.
	 *
	 * @since 1.0.0
	 * @return string Asset file path.
	 */
	protected function get_asset_path( $src ): string {
		return preg_match( '/^(https?:)?\/\//', $src )
			? str_replace( $this->app->plugin_url(), $this->app->plugin_path(), $src )
			: $this->app->plugin_path( $this->app->build_dir . '/' . $src );
	}
}
