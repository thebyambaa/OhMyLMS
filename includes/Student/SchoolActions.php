<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Native school administration forms with nonce checks and fixed return destinations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Service;
use OhMyLMS\Schools\Lifecycle;
use OhMyLMS\Schools\Settings;

defined( 'ABSPATH' ) || exit;

/** Every operation is authorized by the school services against the signed-in user. */
final class SchoolActions {

	const ACTION = 'ohmylms_school_admin';
	const FLASH  = 'ohmylms_school_admin_flash_';

	/** Register the signed-in form handler. */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . SchoolGradebook::EXPORT_ACTION, array( SchoolGradebook::class, 'export' ) );
	}

	/**
	 * Hidden fields for a school operation.
	 *
	 * @param string $operation Operation name.
	 * @param int    $school_id School ID.
	 * @param string $section Section to return to.
	 */
	public static function fields( $operation, $school_id, $section ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		echo '<input type="hidden" name="school_id" value="' . esc_attr( $school_id ) . '">';
		echo '<input type="hidden" name="return_section" value="' . esc_attr( $section ) . '">';
		Hub::nonce_field( self::ACTION . '_' . $operation );
	}

	/**
	 * Apply an operation as the signed-in user; callable by integration tests.
	 *
	 * @param string $operation Operation name.
	 * @param int    $school_id School ID (ignored when creating a school).
	 * @param array  $data Scalar form fields.
	 * @return array Flash data.
	 * @throws \RuntimeException For an unknown operation or an unauthorized request.
	 */
	public static function apply( $operation, $school_id, array $data ) {
		if ( 'create_school' === $operation ) {
			// A teacher may create their own school and becomes its administrator.
			$created = Service::create_school( $data );
			return array(
				'message'   => __( 'School created. Add an academic year, then invite teachers and students.', 'ohmylms' ),
				'school_id' => (int) $created['id'],
			);
		}
		if ( 'leave_school' === $operation ) {
			Service::need( 'yes' === ( $data['confirm'] ?? '' ), 'Confirm leaving this school.' );
			Lifecycle::leave( absint( $school_id ) );
			return array( 'message' => __( 'You left the school. Your account and learning history were kept.', 'ohmylms' ) );
		}
		Access::require_access( SchoolWorkspace::available() );
		$school_id = absint( $school_id );
		switch ( $operation ) {
			case 'student_recovery':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), 'Confirm replacing this student’s password recovery link.' );
				$invite = Lifecycle::recovery( $school_id, absint( $data['student_user_id'] ?? 0 ) );
				return array(
					'message'    => __( 'Password recovery link created. Earlier unused links were revoked. Share this private link with the student within 48 hours. Their password changes only when they choose a new one.', 'ohmylms' ),
					'invite_url' => $invite['url'],
				);
			case 'rollover_preview':
				$preview = Lifecycle::preview( $school_id, absint( $data['from_year'] ?? 0 ), absint( $data['to_year'] ?? 0 ) );
				return array(
					'message'  => __( 'Review the rollover below. Nothing has changed yet.', 'ohmylms' ),
					'rollover' => $preview,
				);
			case 'rollover_commit':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), 'Confirm archiving the source year and its classes.' );
				$result = Lifecycle::rollover( $school_id, absint( $data['from_year'] ?? 0 ), absint( $data['to_year'] ?? 0 ) );
				return array( 'message' => sprintf( /* translators: %d: number of new empty classes. */ _n( 'Rollover complete: %d empty class created. The source year and classes were archived; learning history was kept.', 'Rollover complete: %d empty classes created. The source year and classes were archived; learning history was kept.', $result['classes_created'], 'ohmylms' ), $result['classes_created'] ) );
			case 'grade_submission':
				$learning = Service::row( 'learning_assignments', absint( $data['learning_id'] ?? 0 ) );
				SchoolReports::classroom( $school_id, $learning ? (int) $learning['class_id'] : 0 );
				Access::require_access( $learning && (int) $learning['school_id'] === $school_id );
				Service::grade( (int) $learning['id'], absint( $data['attempt_id'] ?? 0 ), $data );
				return array( 'message' => __( 'Grade saved.', 'ohmylms' ) );
			case 'update_school':
				Service::update_school( $school_id, $data );
				return array( 'message' => __( 'School details saved.', 'ohmylms' ) );
			case 'update_design':
				Settings::save( $school_id, $data );
				return array( 'message' => __( 'School design saved.', 'ohmylms' ) );
			case 'remove_member':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), 'Confirm removing this person from the school.' );
				Service::remove_member( $school_id, absint( $data['member_id'] ?? 0 ) );
				return array( 'message' => __( 'Person removed from the school. Their account and history were kept.', 'ohmylms' ) );
			case 'import_preview':
			case 'import_commit':
				$commit = 'import_commit' === $operation;
				$rows   = Service::import_roster(
					$school_id,
					array(
						'csv'    => (string) ( $data['csv'] ?? '' ),
						'commit' => $commit,
					)
				);
				$counts = array_count_values( array_column( $rows, 'status' ) );
				return array(
					'message'     => $commit
						/* translators: %d: number of students created. */
						? sprintf( __( 'Import finished: %d students created. Existing school IDs were skipped.', 'ohmylms' ), (int) ( $counts['created'] ?? 0 ) )
						/* translators: 1: new students, 2: existing, 3: rows with errors. */
						: sprintf( __( 'Preview: %1$d new, %2$d already in the school, %3$d with errors. Nothing was imported yet.', 'ohmylms' ), (int) ( $counts['new'] ?? 0 ), (int) ( $counts['existing'] ?? 0 ), (int) ( $counts['error'] ?? 0 ) ),
					'import_rows' => $rows,
					'csv'         => $commit ? '' : (string) ( $data['csv'] ?? '' ),
					'can_commit'  => ! $commit && ! empty( $counts['new'] ),
				);
			case 'create_year':
				Service::create_year( $school_id, $data );
				return array( 'message' => __( 'Academic year created.', 'ohmylms' ) );
			case 'create_class':
				Service::create_class( $school_id, $data );
				return array( 'message' => __( 'Class created.', 'ohmylms' ) );
			case 'archive_class':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), 'Confirm archiving this class.' );
				$class = Service::row( 'classes', absint( $data['class_id'] ?? 0 ) );
				Access::require_access( $class && (int) $class['school_id'] === $school_id );
				Service::archive_class( (int) $class['id'] );
				return array( 'message' => __( 'Class archived. Its roster and records were kept.', 'ohmylms' ) );
			case 'create_student':
				$created = Service::create_student( $school_id, $data );
				return array(
					'message' => sprintf(
						/* translators: %s: new student's sign-in name. */
						__( 'Student created. Their sign-in name is %s. Create an activation invitation so they can choose a password.', 'ohmylms' ),
						$created['username']
					),
				);
			case 'invite':
				$invite = Service::invite( $school_id, $data );
				return array(
					'message'    => __( 'Private invitation created. Share this link only with the recipient within 48 hours. No email was sent.', 'ohmylms' ),
					'invite_url' => $invite['url'],
				);
			case 'revoke_invite':
				Service::revoke_invite( $school_id, absint( $data['invitation_id'] ?? 0 ) );
				return array( 'message' => __( 'Invitation revoked.', 'ohmylms' ) );
			case 'revoke_guardian':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), 'Confirm removing this parent link.' );
				Service::revoke_guardian( $school_id, absint( $data['guardian_link_id'] ?? 0 ) );
				return array( 'message' => __( 'Parent link removed.', 'ohmylms' ) );
		}
		throw new \RuntimeException( 'Unknown operation.', 400 );
	}

	/**
	 * Handle a form post and return to the workspace section with a one-time result.
	 *
	 * @throws \RuntimeException Caught locally and shown as a safe message.
	 */
	public static function handle() {
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $operation );
		$school_id = isset( $_POST['school_id'] ) ? absint( $_POST['school_id'] ) : 0;
		$section   = isset( $_POST['return_section'] ) && is_string( $_POST['return_section'] ) ? sanitize_key( wp_unslash( $_POST['return_section'] ) ) : 'school-overview';
		$section   = in_array( $section, array( 'school-overview', 'school-classes', 'school-people', 'school-invites', 'school-settings', 'school-gradebook' ), true ) ? $section : 'school-overview';
		try {
			if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				throw new \RuntimeException( 'Invalid request.', 400 );
			}
			$data = array();
			foreach ( array( 'name', 'timezone', 'member_id', 'label', 'starts_at', 'ends_at', 'subject', 'grade', 'academic_year_id', 'class_id', 'external_student_id', 'role', 'email', 'student_user_id', 'invitation_id', 'guardian_link_id', 'confirm', 'learning_id', 'attempt_id', 'score', 'from_year', 'to_year', 'accent', 'remove_logo', 'reset_accent' ) as $key ) {
				$data[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			}
			$data['csv']  = isset( $_POST['csv'] ) && is_string( $_POST['csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csv'] ) ) : '';
			$data['note'] = isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			if ( 'update_design' === $operation ) {
				$data['logo_id'] = self::upload_logo( $school_id );
			}
			$flash = self::apply( $operation, $school_id, $data );
			if ( ! empty( $flash['school_id'] ) ) {
				$school_id = (int) $flash['school_id'];
			}
		} catch ( \Throwable $error ) {
			$flash = array( 'error' => in_array( $error->getCode(), array( 400, 403, 409 ), true ) ? $error->getMessage() : __( 'Unable to complete this request.', 'ohmylms' ) );
		}
		set_transient( self::FLASH . get_current_user_id(), $flash, 5 * MINUTE_IN_SECONDS );
		$return_url = SchoolWorkspace::url( $section, '', $school_id );
		if ( 'leave_school' === $operation ) {
			$return_url = Hub::url( 'account', 'classes' );
		}
		if ( 'school-gradebook' === $section ) {
			$return_url = add_query_arg( 'hub_class', isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0, $return_url );
		}
		wp_safe_redirect( $return_url );
		exit;
	}

	/**
	 * Store an uploaded school logo in the media library and return its attachment ID.
	 *
	 * Authorizes the school first, so only its administrators can upload. Raster images only (no SVG), 1 MB at most.
	 *
	 * @param int $school_id School ID.
	 * @return int Attachment ID, or 0 when no file was sent.
	 * @throws \RuntimeException For an unauthorized, oversized or non-image upload.
	 */
	private static function upload_logo( $school_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in handle(); wp_handle_upload validates the file and type.
		$file = isset( $_FILES['logo'] ) && is_array( $_FILES['logo'] ) ? $_FILES['logo'] : array();
		if ( empty( $file['name'] ) || UPLOAD_ERR_NO_FILE === ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return 0;
		}
		Access::require_access( Access::school( absint( $school_id ) ) );
		Service::need( UPLOAD_ERR_OK === $file['error'] && (int) $file['size'] <= MB_IN_BYTES, 'The logo must be an image of 1 MB or less.' );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);
		Service::need( empty( $uploaded['error'] ) && ! empty( $uploaded['file'] ), 'The logo must be a JPG, PNG or WebP image.' );
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $uploaded['type'],
				'post_title'     => sanitize_text_field( 'School logo ' . $school_id ),
				'post_status'    => 'inherit',
			),
			$uploaded['file']
		);
		Service::need( $id && ! is_wp_error( $id ), 'Unable to save the logo.' );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $uploaded['file'] ) );
		return (int) $id;
	}

	/**
	 * One-time result of the last action for this user.
	 *
	 * @return array|null
	 */
	public static function flash() {
		$key  = self::FLASH . get_current_user_id();
		$data = get_transient( $key );
		if ( false === $data ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $data ) ? $data : null;
	}
}
