<?php
/**
 * Update functions.
 *
 * @package OhMyLMS
 */


defined( 'ABSPATH' ) || exit;

/**
 * Update user membership table
 *
 * @return void
 * @since 1.0.1
 */
function ohmylms_update_101_user_membership_table() {
	if ( ohmylms_is_pro() ) {
		global $wpdb;
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM `{$wpdb->prefix}ohmylms_user_membership` LIKE 'subscription_id';" ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN `subscription_id` BIGINT(20) UNSIGNED NOT NULL default 0;', $wpdb->prefix . 'ohmylms_user_membership' ) );
		}
	}
}

/**
 * Update database version to 1.0.1
 *
 * @return void
 * @since 1.0.1
 */
function ohmylms_update_101_db_version() {
	\OhMyLMS\Install::update_db_version( '1.0.1' );
}

/**
 * Create video progress table
 *
 * @return void
 * @since 1.2.1
 */
function ohmylms_update_121_create_video_progress_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'ohmylms_video_progress';

	// Check if table already exists.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			user_id bigint(20) NOT NULL,
			lesson_id bigint(20) NOT NULL,
			course_id bigint(20) NOT NULL,
			watched_duration decimal(10,2) NOT NULL DEFAULT 0,
			total_duration decimal(10,2) NOT NULL DEFAULT 0,
			watch_percentage decimal(5,2) NOT NULL DEFAULT 0,
			last_position decimal(10,2) NOT NULL DEFAULT 0,
			is_completed tinyint(1) NOT NULL DEFAULT 0,
			completed_date datetime NULL,
			last_updated datetime NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_date datetime NOT NULL default CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_lesson (user_id, lesson_id),
			KEY user_id (user_id),
			KEY lesson_id (lesson_id),
			KEY course_id (course_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}

/**
 * Update database version to 1.2.1
 *
 * @return void
 * @since 1.2.1
 */
function ohmylms_update_121_db_version() {
	\OhMyLMS\Install::update_db_version( '1.2.1' );
}

/**
 * Create separate student pages (dashboard, profile, my-courses) for existing installations.
 *
 * @return void
 * @since 1.2.5
 */
function ohmylms_update_125_create_student_pages() {
	\OhMyLMS\Install::create_student_pages_for_existing_users();
}

/**
 * Update database version to 1.2.5
 *
 * @return void
 * @since 1.2.5
 */
function ohmylms_update_125_db_version() {
	\OhMyLMS\Install::update_db_version( '1.2.5' );
}

/**
 * Register the dedicated 'ohmylms_student' role.
 *
 * @return void
 * @since 1.2.12
 */
function ohmylms_update_1212_create_student_role() {
	\OhMyLMS\Install::create_student_role();
}

/**
 * Kick off the background migration of existing enrolled 'subscriber' users
 * to the new 'ohmylms_student' role. Runs via WP-Cron in small batches so it
 * never blocks an admin page load or times out on large user tables.
 *
 * @return void
 * @since 1.2.12
 */
function ohmylms_update_1212_schedule_student_migration() {
	if ( ! wp_next_scheduled( 'ohmylms_migrate_students_batch' ) ) {
		wp_schedule_single_event( time() + 10, 'ohmylms_migrate_students_batch' );
	}
}

/**
 * Update database version to 1.2.12
 *
 * @return void
 * @since 1.2.12
 */
function ohmylms_update_1212_db_version() {
	\OhMyLMS\Install::update_db_version( '1.2.12' );
}

/**
 * Delete the stored data of the removed administrator assistant: its settings (including the encrypted
 * provider key) and the development worker's configuration and job records.
 *
 * @return void
 * @since 1.2.21
 */
function ohmylms_update_1221_remove_admin_assistant() {
	foreach ( array( 'ohmylms_admin_assistant', 'ohmylms_coding_worker', 'ohmylms_coding_jobs' ) as $option ) {
		delete_option( $option );
	}
}

/**
 * Update database version to 1.2.21
 *
 * @return void
 * @since 1.2.21
 */
function ohmylms_update_1221_db_version() {
	\OhMyLMS\Install::update_db_version( '1.2.21' );
}

/**
 * Process one batch of the 'subscriber' -> 'ohmylms_student' migration.
 *
 * Scoped to users who have an actual course enrollment record (not every
 * 'subscriber' on the site) and are still on the 'subscriber' role. Safe to
 * run repeatedly: each batch only ever selects users not yet migrated, so it
 * self-terminates once none are left, and re-running it after an interrupted
 * run (e.g. site restart) just picks up where it left off.
 *
 * @return void
 * @since 1.2.12
 */
function ohmylms_run_student_migration_batch() {
	global $wpdb;

	if ( ! function_exists( 'ohmylms_get_student_role' ) ) {
		return;
	}

	$role_slug = ohmylms_get_student_role();

	if ( ! get_role( $role_slug ) ) {
		\OhMyLMS\Install::create_student_role();
	}

	if ( ! get_role( $role_slug ) ) {
		return; // Role could not be created for some reason — don't touch any user.
	}

	$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $enrollment_table ) ) !== $enrollment_table ) {
		// No enrollment table means nothing can ever match — mark done so the
		// admin_init watchdog doesn't reschedule this batch forever.
		update_option( 'ohmylms_student_migration_done', '1' );
		return;
	}

	$batch_size = 100;

	$user_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT DISTINCT ue.user_id FROM %i ue
			INNER JOIN %i um ON um.user_id = ue.user_id AND um.meta_key = %s
			WHERE um.meta_value LIKE %s
			LIMIT %d',
			$enrollment_table,
			$wpdb->usermeta,
			$wpdb->prefix . 'capabilities',
			'%"subscriber"%',
			$batch_size
		)
	);

	$migrated = 0;

	foreach ( $user_ids as $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'subscriber', (array) $user->roles, true ) ) {
			continue; // Already migrated, corrupt caps meta, or user no longer exists.
		}
		$user->remove_role( 'subscriber' );
		$user->add_role( $role_slug );
		++$migrated;
	}

	// Schedule the next pass only while we're still making progress. A full
	// batch with zero migrations means every remaining match is permanently
	// unmigratable (skipped by the guard above) — rescheduling would loop
	// forever on the same rows, since the query has no offset.
	if ( count( $user_ids ) === $batch_size && $migrated > 0 ) {
		wp_schedule_single_event( time() + 30, 'ohmylms_migrate_students_batch' );
		return;
	}

	update_option( 'ohmylms_student_migration_done', '1' );
}
add_action( 'ohmylms_migrate_students_batch', 'ohmylms_run_student_migration_batch' );
