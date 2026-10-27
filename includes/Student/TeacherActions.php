<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Native teacher forms with nonce checks and fixed return destinations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\ClassManagement;
use OhMyLMS\Schools\Service;

defined( 'ABSPATH' ) || exit;

/** Every mutation rechecks current teacher and resource permissions. */
final class TeacherActions {

	const ACTION = 'ohmylms_teacher_class';
	const FLASH  = 'ohmylms_teacher_class_flash_';

	/** Register signed-in form handling only. */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . SchoolGradebook::TEACHER_EXPORT_ACTION, array( SchoolGradebook::class, 'export_teacher' ) );
	}

	/** Retire the old teacher portal entry while leaving file and invitation flows intact. */
	public static function redirect_legacy() {
		LegacyPages::redirect_portal();
	}

	/**
	 * Shared hidden form fields.
	 *
	 * @param string $operation Mutation name.
	 * @param int    $class_id Class ID.
	 */
	public static function fields( $operation, $class_id = 0 ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		echo '<input type="hidden" name="class_id" value="' . esc_attr( $class_id ) . '">';
		Hub::nonce_field( self::ACTION . '_' . $operation );
	}

	/**
	 * Apply an authorized operation; callable independently for integration tests.
	 *
	 * @param string $operation Mutation name.
	 * @param array  $data Sanitized, scalar form fields.
	 * @return array Flash data and fixed destination parameters.
	 */
	public static function apply( $operation, array $data ) {
		\OhMyLMS\Schools\Access::require_access( TeacherWorkspace::available() );
		$class_id = absint( $data['class_id'] ?? 0 );
		$result   = array(
			'message'  => __( 'Your class was updated.', 'ohmylms' ),
			'class_id' => $class_id,
			'section'  => 'roster',
		);
		switch ( $operation ) {
			case 'create':
				$year   = absint( $data['academic_year_id'] ?? 0 );
				$record = $year ? Service::row( 'academic_years', $year ) : null;
				Service::need( ! $year || $record, __( 'Select an available academic year.', 'ohmylms' ) );
				$created            = Service::create_class( $record ? (int) $record['school_id'] : 0, $data );
				$result['class_id'] = $created['id'];
				$result['message']  = __( 'Class created. Add students to your roster.', 'ohmylms' );
				break;
			case 'edit':
				ClassManagement::edit( $class_id, $data );
				$result['section'] = 'class-settings';
				break;
			case 'add':
				Service::class_member(
					$class_id,
					array(
						'user_id' => absint( $data['student_id'] ?? 0 ),
						'role'    => 'student',
						'status'  => 'active',
					)
				);
				$result['message'] = __( 'Student added to the roster.', 'ohmylms' );
				break;
			case 'remove':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), __( 'Confirm removal from this class.', 'ohmylms' ) );
				ClassManagement::remove_student( $class_id, absint( $data['student_id'] ?? 0 ) );
				$result['message'] = __( 'Student removed from this class. Their account and learning history were kept.', 'ohmylms' );
				break;
			case 'invite':
				$invite               = ClassManagement::invite( $class_id, $data['email'] ?? '' );
				$result['invite_url'] = $invite['url'];
				$result['message']    = __( 'Private invitation created. Share this link with the intended student within 48 hours. No email was sent.', 'ohmylms' );
				break;
			case 'revoke':
				ClassManagement::revoke( $class_id, absint( $data['invitation_id'] ?? 0 ) );
				$result['message'] = __( 'Invitation revoked.', 'ohmylms' );
				break;
			case 'archive':
				Service::need( 'yes' === ( $data['confirm'] ?? '' ), __( 'Confirm archiving this class.', 'ohmylms' ) );
				Service::archive_class( $class_id );
				$result['class_id'] = 0;
				$result['message']  = __( 'Class archived. Its roster and learning records were kept.', 'ohmylms' );
				$result['section']  = 'classes';
				break;
			case 'restore':
				ClassManagement::restore( $class_id );
				$result['message'] = __( 'Class restored.', 'ohmylms' );
				break;
			default:
				Service::need( false, __( 'That class action is not available.', 'ohmylms' ) );
		}
		return $result;
	}

	/** Validate the request before passing fields to domain services. */
	public static function handle() {
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The operation selects the nonce verified below.
		if ( ! is_user_logged_in() || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION . '_' . $operation ) ) {
			wp_die( esc_html__( 'This form has expired. Reload the page and try again.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$data = array();
		foreach ( array( 'class_id', 'name', 'subject', 'grade', 'academic_year_id', 'student_id', 'email', 'invitation_id', 'confirm' ) as $field ) {
			if ( isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ) {
				$data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		try {
			$result = self::apply( $operation, $data );
		} catch ( \Throwable $error ) {
			$result = array(
				'error'    => true,
				'message'  => 400 === $error->getCode() ? $error->getMessage() : __( 'This change could not be saved. Check your class access and try again.', 'ohmylms' ),
				'section'  => 'classes',
				'class_id' => 0,
			);
		}
		set_transient( self::FLASH . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		$url = TeacherWorkspace::url( $result['section'] );
		if ( $result['class_id'] ) {
			$url = add_query_arg( 'hub_class', $result['class_id'], $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Consume only the current actor's flash, including any newly created private link.
	 *
	 * @return array
	 */
	public static function flash() {
		$key    = self::FLASH . get_current_user_id();
		$result = get_transient( $key );
		delete_transient( $key );
		return is_array( $result ) ? $result : array();
	}
}
