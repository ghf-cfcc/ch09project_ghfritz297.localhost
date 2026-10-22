<?php

namespace EverCheckout\Models;

use EverCheckout\B8\Models\Relations\BelongsTo;
use EverCheckout\B8\Models\Utilities\DateTime;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Download model.
 *
 * A file attached to a product, served to the orders that bought the product.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int                  $id         Download ID.
 * @property int                  $product_id Product ID, 0 while uploaded but not yet attached.
 * @property string               $name       Seller's label for the file.
 * @property string               $file_path  Where the file lives.
 * @property string               $file_name  Original filename, restored on download.
 * @property int                  $file_size  File size in bytes, 0 when unknown.
 * @property string               $mime_type  MIME type captured at upload.
 * @property bool                 $enabled    Whether the file is offered.
 * @property int                  $position   Sort order.
 * @property DateTime|string|null $created_at Date created.
 * @property DateTime|string|null $updated_at Date updated.
 */
class Download extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'ecout_downloads';

	/**
	 * Object cache group for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $cache_group = 'ecout_downloads';

	/**
	 * The table columns of the model.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $columns = array(
		'id',
		'product_id',
		'name',
		'file_path',
		'file_name',
		'file_size',
		'mime_type',
		'enabled',
		'position',
	);

	/**
	 * The model's attributes.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $attributes = array(
		'enabled'    => 1,
		'position'   => 0,
		'product_id' => 0,
		'file_size'  => 0,
	);

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = array(
		'product_id' => 'int',
		'name'       => 'string',
		'file_path'  => 'string',
		'file_name'  => 'string',
		'file_size'  => 'int',
		'mime_type'  => 'string',
		'enabled'    => 'tinyint',
		'position'   => 'int',
	);

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $rules = array(
		'product_id' => 'int|min:0',
		'file_path'  => 'required|string|max:2000',
		'name'       => 'string|max:255',
	);

	/**
	 * The attributes that are searchable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $searchable = array(
		'name',
		'file_name',
	);

	/*
	|--------------------------------------------------------------------------
	| Accessors & Mutators
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the file path type.
	 *
	 * Classified at read time, never stored: a stored copy can disagree with the path.
	 *
	 * @since 1.0.0
	 * @return string One of `absolute` or `relative`.
	 */
	public function get_file_type(): string {
		$path = (string) $this->file_path;

		if ( '' !== wp_parse_url( $path, PHP_URL_HOST ) && in_array( wp_parse_url( $path, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return 'absolute';
		}

		return 'relative';
	}

	/**
	 * Get the absolute path of a stored file.
	 *
	 * @since 1.0.0
	 * @return string Absolute path, or an empty string for a file hosted elsewhere.
	 */
	public function get_full_path(): string {
		return 'relative' === $this->get_file_type() ? trailingslashit( ECOUT_UPLOAD_DIR ) . ltrim( (string) $this->file_path, '/' ) : '';
	}

	/*
	|--------------------------------------------------------------------------
	| Relationships
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the product.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Product relation.
	 */
	public function product(): BelongsTo {
		return $this->belongs_to( Product::class, 'product_id' );
	}

	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Store an uploaded file under a randomised name and describe it.
	 *
	 * The file lands in `uploads/ever-checkout/YYYY/MM/` behind the deny rule,
	 * never in the media library, so it has no public URL.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $download One entry of `$_FILES`: `name`, `tmp_name`, `size`, `error`.
	 * @return array<string, mixed>|WP_Error `file_path`, `file_name`, `file_size` and `mime_type`, or why it was refused.
	 */
	public function upload( array $download ) {
		if ( ! empty( $download['error'] ) || empty( $download['tmp_name'] ) || empty( $download['name'] ) ) {
			return new WP_Error( 'ecout_upload_failed', __( 'The file did not upload. Please try again.', 'ever-checkout' ) );
		}

		$name    = sanitize_file_name( (string) $download['name'] );
		$ext     = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$allowed = $this->get_allowed_types();

		if ( ! isset( $allowed[ $ext ] ) ) {
			return new WP_Error(
				'ecout_upload_type',
				sprintf(
					/* translators: %s: comma-separated list of allowed extensions. */
					__( 'That file type is not allowed. Allowed: %s', 'ever-checkout' ),
					implode( ', ', array_keys( $allowed ) )
				)
			);
		}

		if ( (int) $download['size'] > wp_max_upload_size() ) {
			return new WP_Error( 'ecout_upload_size', __( 'That file is larger than this site allows.', 'ever-checkout' ) );
		}

		$fs     = ecout()->fs;
		$folder = gmdate( 'Y/m' );
		$dir    = trailingslashit( ECOUT_UPLOAD_DIR ) . $folder;

		if ( ! $fs->exists( $dir ) ) {
			wp_mkdir_p( $dir );
			$fs->protect( ECOUT_UPLOAD_DIR );
			$fs->protect( $dir );
		}

		$into_protected_dir = static fn( array $uploads ) => array_merge(
			$uploads,
			array(
				'path'    => $dir,
				'subdir'  => '/' . $folder,
				'basedir' => ECOUT_UPLOAD_DIR,
			)
		);

		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		add_filter( 'upload_dir', $into_protected_dir );

		$stored = wp_handle_sideload(
			$download,
			array(
				'test_form'                => false,
				'mimes'                    => $allowed,
				'unique_filename_callback' => static fn( string $directory, string $filename, string $extension ) => wp_unique_filename( $directory, wp_generate_uuid4() . $extension ),
			)
		);

		remove_filter( 'upload_dir', $into_protected_dir );

		if ( isset( $stored['error'] ) ) {
			return new WP_Error( 'ecout_upload_move', (string) $stored['error'] );
		}

		return array(
			'file_path' => $folder . '/' . basename( (string) $stored['file'] ),
			'file_name' => $name,
			'file_size' => (int) $fs->size( (string) $stored['file'] ),
			'mime_type' => (string) ( empty( $stored['type'] ) ? $allowed[ $ext ] : $stored['type'] ),
		);
	}

	/**
	 * Delete the file with its row.
	 *
	 * @since 1.0.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		$path   = $this->get_full_path();
		$result = parent::delete();

		if ( ! is_wp_error( $result ) && '' !== $path && ecout()->fs->exists( $path ) ) {
			ecout()->fs->delete( $path );
		}

		return $result;
	}

	/*
	|--------------------------------------------------------------------------
	| Helper Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the file types a seller may upload, keyed by extension.
	 *
	 * @since 1.0.0
	 * @return array<string, string> MIME types keyed by extension.
	 */
	public function get_allowed_types(): array {
		/**
		 * Filters the file types a seller may upload.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $types MIME types keyed by extension.
		 */
		return ecout()->apply_filters(
			'download_allowed_types',
			array(
				'zip'   => 'application/zip',
				'rar'   => 'application/vnd.rar',
				'7z'    => 'application/x-7z-compressed',
				'gz'    => 'application/gzip',
				'pdf'   => 'application/pdf',
				'epub'  => 'application/epub+zip',
				'mobi'  => 'application/x-mobipocket-ebook',
				'doc'   => 'application/msword',
				'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'xls'   => 'application/vnd.ms-excel',
				'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ppt'   => 'application/vnd.ms-powerpoint',
				'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'csv'   => 'text/csv',
				'txt'   => 'text/plain',
				'md'    => 'text/markdown',
				'jpg'   => 'image/jpeg',
				'jpeg'  => 'image/jpeg',
				'png'   => 'image/png',
				'gif'   => 'image/gif',
				'webp'  => 'image/webp',
				'psd'   => 'image/vnd.adobe.photoshop',
				'ai'    => 'application/postscript',
				'mp3'   => 'audio/mpeg',
				'wav'   => 'audio/wav',
				'flac'  => 'audio/flac',
				'mp4'   => 'video/mp4',
				'mov'   => 'video/quicktime',
				'webm'  => 'video/webm',
				'ttf'   => 'font/ttf',
				'otf'   => 'font/otf',
				'woff'  => 'font/woff',
				'woff2' => 'font/woff2',
				'json'  => 'application/json',
				'xml'   => 'application/xml',
			)
		);
	}
}
