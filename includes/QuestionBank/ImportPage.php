<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Admin page for importing question rows: check first, then import.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

defined( 'ABSPATH' ) || exit;

/** Question review and bulk import page (no submenu entry; imports start in Content Hub > Import). */
final class ImportPage {

	const SLUG      = 'ohmylms-import-questions';
	const MAX_BYTES = 2097152;
	const MAX_ROWS  = 1000;

	/** Register the menu entry. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 32 );
	}

	/**
	 * Keep the page reachable without a menu entry: importing now lives in Content Hub > Import,
	 * and this page remains for reviewing questions that AI assistants added.
	 */
	public static function menu() {
		add_submenu_page( 'options.php', __( 'Review imported questions', 'ohmylms' ), __( 'Review imported questions', 'ohmylms' ), 'edit_posts', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Read the pasted text or uploaded file.
	 *
	 * @return string|\WP_Error
	 */
	private static function submitted_text() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked by the caller before this runs.
		if ( ! empty( $_FILES['file']['tmp_name'] ) && UPLOAD_ERR_OK === absint( $_FILES['file']['error'] ?? 0 ) ) {
			$path = sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) );
			$size = absint( $_FILES['file']['size'] ?? 0 );
			if ( $size > self::MAX_BYTES || ! is_uploaded_file( $path ) ) {
				return new \WP_Error( 'ohmylms_import_file', __( 'The file is too large (limit 2 MB) or was not uploaded correctly.', 'ohmylms' ) );
			}
			return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the uploaded temporary file.
		}
		return isset( $_POST['text'] ) ? (string) wp_unslash( $_POST['text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed and validated row by row; never output unescaped.
		// phpcs:enable
	}

	/**
	 * Check or import every chunk, stopping safely.
	 *
	 * @param array $rows Raw rows.
	 * @param array $options Import options.
	 * @return array|\WP_Error Combined report.
	 */
	private static function process( array $rows, array $options ) {
		if ( count( $rows ) > self::MAX_ROWS ) {
			/* translators: %d: maximum rows per submission. */
			return new \WP_Error( 'ohmylms_import_limit', sprintf( __( 'Import at most %d rows per submission. Split the file, or use the REST route in batches.', 'ohmylms' ), self::MAX_ROWS ) );
		}
		$chunks = array_chunk( $rows, BulkImport::MAX_ROWS );
		$real   = empty( $options['dry_run'] );
		if ( $real ) {
			// Check every chunk first so a mistake in row 900 cannot leave a half-finished import.
			$gate = self::combine( self::run_chunks( $chunks, array_merge( $options, array( 'dry_run' => true ) ) ) );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
			if ( $gate['counts']['error'] && empty( $options['skip_invalid'] ) ) {
				$gate['stopped'] = __( 'Nothing was imported because some rows have errors. Fix them, or choose to skip invalid rows.', 'ohmylms' );
				return $gate;
			}
		}
		return self::combine( self::run_chunks( $chunks, $options ) );
	}

	/**
	 * Run each chunk in turn.
	 *
	 * @param array[] $chunks Row chunks.
	 * @param array   $options Import options.
	 * @return array[]|\WP_Error
	 */
	private static function run_chunks( array $chunks, array $options ) {
		$reports = array();
		foreach ( $chunks as $position => $chunk ) {
			$report = BulkImport::run( $chunk, $options );
			if ( is_wp_error( $report ) ) {
				return $report;
			}
			foreach ( $report['rows'] as &$row ) {
				$row['row'] += $position * BulkImport::MAX_ROWS;
			}
			unset( $row );
			$reports[] = $report;
		}
		return $reports;
	}

	/**
	 * Merge chunk reports.
	 *
	 * @param array[]|\WP_Error $reports Chunk reports.
	 * @return array|\WP_Error
	 */
	private static function combine( $reports ) {
		if ( is_wp_error( $reports ) ) {
			return $reports;
		}
		$total = array(
			'dry_run'   => true,
			'committed' => false,
			'approved'  => 0,
			'counts'    => array(
				'create'    => 0,
				'update'    => 0,
				'unchanged' => 0,
				'error'     => 0,
			),
			'rows'      => array(),
		);
		foreach ( $reports as $report ) {
			$total['dry_run']   = $report['dry_run'];
			$total['committed'] = $total['committed'] || $report['committed'];
			$total['approved'] += $report['approved'];
			$total['rows']      = array_merge( $total['rows'], $report['rows'] );
			foreach ( $report['counts'] as $status => $count ) {
				$total['counts'][ $status ] += $count;
			}
			if ( ! empty( $report['stopped'] ) ) {
				$total['stopped'] = $report['stopped'];
			}
		}
		return $total;
	}

	/** Render the page and handle its form. */
	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$result   = null;
		$message  = '';
		$reviewed = array();
		if ( isset( $_POST['ohmylms_review_decision'] ) && check_admin_referer( 'ohmylms_review_questions' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer verifies it.
			$ids      = isset( $_POST['review_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['review_ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			$decision = 'approve' === sanitize_key( wp_unslash( $_POST['ohmylms_review_decision'] ) ) ? 'approve' : 'dismiss'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			$reviewed = $ids ? ReviewQueue::decide( array_slice( $ids, 0, 50 ), $decision ) : array();
		}
		$posted = isset( $_POST['ohmylms_import_action'] ) && check_admin_referer( 'ohmylms_import_questions' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer verifies it.
		if ( $posted ) {
			$text = self::submitted_text();
			$rows = is_wp_error( $text ) ? $text : BulkImport::parse( $text, isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'auto' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			if ( is_wp_error( $rows ) ) {
				$message = $rows->get_error_message();
			} else {
				$check  = 'check' === sanitize_key( wp_unslash( $_POST['ohmylms_import_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
				$result = self::process(
					$rows,
					array(
						'dry_run'         => $check,
						'bank_id'         => isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
						'approve'         => ! empty( $_POST['approve'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
						'update_existing' => ! empty( $_POST['update_existing'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
						'skip_invalid'    => ! empty( $_POST['skip_invalid'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
					)
				);
				if ( is_wp_error( $result ) ) {
					$message = $result->get_error_message();
					$result  = null;
				}
			}
		}
		ohmylms_get_template(
			'admin/import-questions.php',
			array(
				'banks'    => Banks::for_user(),
				'review'   => ReviewQueue::pending( 1, 50 ),
				'reviewed' => $reviewed,
				'result'   => $result,
				'message'  => $message,
				'posted'   => $posted,
			)
		);
	}
}
