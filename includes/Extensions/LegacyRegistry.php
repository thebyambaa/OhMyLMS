<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

defined( 'ABSPATH' ) || exit;

/** Owns the Extensions operations delegated by CommonHook. */
final class LegacyRegistry {

	/**
	 * Pro data stores for Common hook.
	 *
	 * @param mixed $stores Stores.
	 */
	public function pro_data_stores( $stores ) {
		$stores['membership-pro'] = 'OhMyLMS\DataStores\MembershipStore';
		$stores['assignment-pro'] = 'OhMyLMS\DataStores\AssignmentStore';
		$stores['course-pro']     = 'OhMyLMS\DataStores\CourseStore';
		$stores['lesson-pro']     = 'OhMyLMS\DataStores\LessonStore';
		$stores['session-pro']    = 'OhMyLMS\DataStores\SessionStore';
		return $stores;
	}

	/**
	 * Ohmylms is pro for Common hook.
	 *
	 * @param mixed $is_pro Is pro.
	 */
	public function ohmylms_is_pro( $is_pro ) {
		return true;
	}

	/**
	 * Pro modules for Common hook.
	 *
	 * @param mixed $modules Modules.
	 */
	public function pro_modules( $modules ) {
		array_push( $modules, 'membership', 'assignment' );
		return $modules;
	}
}
