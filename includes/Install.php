<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin install implementation.
 */
class Install {

	/**
	 * Database updates to be applied.
	 *
	 * @var array $db_updates {
	 *     An associative array where the key is the version number and the value is an array of callback functions to run for that version.
	 *
	 *     @type array $version {
	 *         @type string $callback The name of the callback function to run for the specified version.
	 *     }
	 * }
	 *
	 * e.g.
	 * private static $db_updates = array(
	 *      '2.0.0' => array(
	 *          'ohmylms_update_200_db_version',
	 *      ),
	 * );
	 */
	private static $db_updates = array(
		'1.0.1'  => array(
			'ohmylms_update_101_user_membership_table',
			'ohmylms_update_101_db_version',
		),
		'1.2.1'  => array(
			'ohmylms_update_121_create_video_progress_table',
			'ohmylms_update_121_db_version',
		),
		'1.2.5'  => array(
			'ohmylms_update_125_create_student_pages',
			'ohmylms_update_125_db_version',
		),
		'1.2.12' => array(
			'ohmylms_update_1212_create_student_role',
			'ohmylms_update_1212_schedule_student_migration',
			'ohmylms_update_1212_db_version',
		),
	);


	/**
	 * Init the install class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Idempotent (bails on get_role() hit) — guarantees the student role
		// exists on every request, even after a file-level update (auto-update,
		// FTP deploy) where the activation hook never fired and no admin has
		// visited wp-admin yet to trigger the upgrade routine.
		add_action( 'init', array( __CLASS__, 'create_student_role' ) );
		add_action( 'admin_init', array( __CLASS__, 'update' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_resume_student_migration' ), 6 );
	}


	/**
	 * Install OhMyLMS
	 *
	 * @since 1.0.0
	 */
	public static function install() {
		set_transient( 'ohmylms_installing', true, MINUTE_IN_SECONDS * 10 );
		try {
			self::maybe_create_pages();
			self::bundled_maybe_create_pages();
			self::create_tables();
			self::create_roles();
			self::maybe_update_db_version();
			if ( self::is_new_install() ) {
				self::save_default_permalink_settings();
				self::save_default_design_settings();
				self::save_default_email_settings();
			} else {
				// For existing installations, create the new student pages.
				self::create_student_pages_for_existing_users();
			}
			self::maybe_set_activation_transients();
		} finally {
			delete_transient( 'ohmylms_installing' );
		}

		add_option( 'ohmylms_admin_install_timestamp', time() );

		// Set flag to indicate student pages have been created.
		update_option( 'ohmylms_student_pages_created', '1' );

		flush_rewrite_rules( true );

		/**
		 * Fires after OhMyLMS has been installed.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_installed' );
	}


	/**
	 * Update OhMyLMS version to current.
	 *
	 * @since 1.0.0
	 */
	public static function update_ohmylms_version() {
		update_option( 'ohmylms_version', ohmylms()::VERSION );
	}


	/**
	 * See if we need to show or run database updates during install.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_update_db_version() {
		if ( ! self::needs_db_update() ) {
			self::update_db_version();
		}
	}


	/**
	 * Record activation without opening the setup wizard.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_set_activation_transients() {
		delete_transient( '_ohmylms_activation_redirect' );
		self::update_ohmylms_version();
	}


	/**
	 * Update DB version to current.
	 *
	 * @param string|null $version Version.
	 * @since 1.0.0
	 */
	public static function update_db_version( $version = null ) {
		update_option( 'ohmylms_db_version', is_null( $version ) ? ohmylms()::VERSION : $version );
	}


	/**
	 * Is a DB update needed?
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	public static function needs_db_update() {
		$current_db_version = get_option( 'ohmylms_db_version', null );
		$updates            = self::get_db_update_callbacks();
		$update_versions    = array_keys( $updates );
		usort( $update_versions, 'version_compare' );
		return ! is_null( $current_db_version ) && version_compare( $current_db_version, end( $update_versions ), '<' );
	}


	/**
	 * Get list of DB update callbacks.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_db_update_callbacks() {
		return self::$db_updates;
	}


	/**
	 * Run the DB updates.
	 *
	 * @since 1.0.0
	 */
	public static function update() {
		$updates = self::get_db_update_callbacks();
		$current = get_option( 'ohmylms_db_version', null );
		$to      = ohmylms()::VERSION;

		if ( is_null( $current ) ) {
			$current = '1.0.0';
		}

		$updates = array_filter(
			$updates,
			function ( $version ) use ( $current, $to ) {
				return version_compare( $current, $version, '<' ) && version_compare( $version, $to, '<=' );
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $updates as $version => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_callable( $callback ) ) {
					call_user_func( $callback );
				}
			}
		}

		$current_version = get_option( 'ohmylms_version', null );
		if ( version_compare( $current, $current_version, '<' ) ) {
			self::update_db_version();
		}
	}


	/**
	 * Re-arm the student role migration if it never finished.
	 *
	 * The 1.2.12 upgrade routine schedules the migration as a single WP-Cron
	 * event and then bumps the DB version, so a lost cron event (cleared by a
	 * cron plugin, server restart before it fired, broken object-cache cron)
	 * would otherwise mean the migration silently never runs and never
	 * retries. This watchdog keeps rescheduling until the batch worker
	 * records completion in the 'ohmylms_student_migration_done' option.
	 *
	 * @since 1.2.12
	 */
	public static function maybe_resume_student_migration() {
		if ( get_option( 'ohmylms_student_migration_done' ) ) {
			return;
		}

		$db_version = get_option( 'ohmylms_db_version', null );
		if ( is_null( $db_version ) || version_compare( $db_version, '1.2.12', '<' ) ) {
			return; // Upgrade routine hasn't run yet — it will do the initial scheduling.
		}

		if ( ! wp_next_scheduled( 'ohmylms_migrate_students_batch' ) ) {
			wp_schedule_single_event( time() + 10, 'ohmylms_migrate_students_batch' );
		}
	}


	/**
	 * Is this a brand new OhMyLMS install?
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	public static function is_new_install() {
		return is_null( get_option( 'ohmylms_version', null ) );
	}


	/**
	 * Create necessary database tables for OhMyLMS.
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		// No need check if it is new install as this method will be called only on plugin activation.
		// So if we add new tables later, they'll also will be created automatically on plugin activation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::get_schema() . self::bundled_get_schema() );
	}

	/**
	 * Get schema
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function get_schema() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$tables          = "CREATE TABLE {$wpdb->prefix}ohmylms_sessions (
			  session_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  session_key char(32) NOT NULL,
			  session_value longtext NOT NULL,
			  session_expiry bigint(20) unsigned NOT NULL,
			  PRIMARY KEY  (session_id),
			  UNIQUE KEY session_key (session_key)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_chapter_relationship (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  course_id INT(11) NOT NULL,
			  chapter_id INT(11) NOT NULL,
			  order_number INT(11) DEFAULT 0,
			  PRIMARY KEY (id),
			  UNIQUE KEY unique_course_chapter (course_id, chapter_id),
			  KEY chapter_id (chapter_id),
			  KEY course_id (course_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_content_relationship (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  chapter_id INT(11) NOT NULL,
			  content_id INT(11) NOT NULL,
			  content_type VARCHAR(225) NOT NULL,
			  order_number INT(11) DEFAULT 0,
			  PRIMARY KEY (id),
			  UNIQUE KEY unique_chapter_content (chapter_id, content_id, content_type),
			  KEY chapter_id (chapter_id),
			  KEY content_id (content_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_certificate_relationship (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  course_id INT(11) NOT NULL,
			  certificate_id INT(11) NOT NULL,
			  PRIMARY KEY (id),
			  UNIQUE KEY unique_course_certificate (course_id, certificate_id),
			  KEY course_id (course_id),
			  KEY certificate_id (certificate_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_user_enrollment (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  user_id bigint(20) NOT NULL,
			  course_id bigint(20) NOT NULL,
			  membership_id bigint(20) default NULL,
			  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			  status varchar(45) NOT NULL DEFAULT '',
			  progress varchar(45) NOT NULL DEFAULT '',
			  start_date datetime NOT NULL default '0000-00-00 00:00:00',
			  end_date datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY (id),
			  KEY user_id (user_id),
			  KEY course_id (course_id),
			  KEY order_id (order_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_earning (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  membership_id bigint(20) default NULL,
			  course_id bigint(20) default NULL,
			  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			  status varchar(45) NOT NULL DEFAULT '',
 			  start_date datetime NOT NULL default '0000-00-00 00:00:00',
			  end_date datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY (id),
			  KEY order_id (order_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_user_progress (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  enrollment_id bigint(20) NOT NULL,
			  content_id bigint(20) NOT NULL,
			  content_type varchar(45) NOT NULL DEFAULT '',
			  status varchar(45) NOT NULL DEFAULT '',
			  start_date datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY (id),
			  KEY enrollment_id (enrollment_id),
			  KEY course_id (content_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_video_progress (
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
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_quiz_questions_relationship (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  quiz_id INT(11) NOT NULL,
			  question_id INT(11) NOT NULL,
			  order_number INT(11) DEFAULT 0,
			  PRIMARY KEY (id),
			  UNIQUE KEY id (id),
			  KEY quiz_id (quiz_id),
			  KEY question_id (question_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_quiz_attempts (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  course_id bigint(20) NOT NULL,
			  quiz_id bigint(20) NOT NULL,
			  student_id bigint(20) unsigned NOT NULL DEFAULT 0,
			  total decimal(12,4) NOT NULL DEFAULT 0,
			  status varchar(45) NOT NULL DEFAULT '',
			  start_date datetime NOT NULL default '0000-00-00 00:00:00',
			  end_date datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY (id),
			  KEY course_id (course_id),
			  KEY quiz_id (quiz_id),
			  KEY student_id (student_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_quiz_attempts_answers (
			  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  student_id bigint(20) unsigned NOT NULL DEFAULT 0,
			  quiz_id bigint(20) NOT NULL,
			  question_id bigint(20) NOT NULL,
			  quiz_attempt_id bigint(20) NOT NULL,
			  given_answer longtext NOT NULL,
			  question_marks decimal(12,4) DEFAULT 0,
			  achive_mark decimal(12,4) DEFAULT 0,
			  minus_mark decimal(12,4) DEFAULT 0,
			  is_correct boolean  NULL DEFAULT 0,
			  is_manually_reviewed boolean  NULL DEFAULT 0,
			  PRIMARY KEY (id),
			  KEY quiz_attempt_id (quiz_attempt_id),
			  KEY quiz_id (quiz_id),
			  KEY student_id (student_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_question_answers (
			  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  question_id bigint(20) NOT NULL,
			  answer longtext NOT NULL,
			  order_number INT(11) DEFAULT 0,
			  is_correct boolean NOT NULL DEFAULT 0,
			  PRIMARY KEY  (id),
			  UNIQUE KEY id (id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_question_answermeta (
			  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  answer_id bigint(20) NOT NULL,
			  meta_key char(32) NOT NULL,
			  meta_value longtext NOT NULL,
			  PRIMARY KEY  (id),
			  UNIQUE KEY id (id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_order_items (
			  order_item_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  order_item_name text NOT NULL,
			  order_item_type varchar(200) NOT NULL DEFAULT '',
			  order_id bigint(20) unsigned NOT NULL,
			  PRIMARY KEY  (order_item_id),
			  KEY order_id (order_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_order_itemmeta (
			  meta_id bigint(20) unsigned NOT NULL auto_increment,
			  order_item_id bigint(20) unsigned NOT NULL,
			  meta_key varchar(255) default NULL,
			  meta_value longtext NULL,
			  PRIMARY KEY  (meta_id),
			  KEY order_item_id (order_item_id),
			  KEY meta_key (meta_key(32))
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_notifications (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				student_id BIGINT(20) NOT NULL,
				course_id BIGINT(20) NOT NULL,
				email VARCHAR(255) NOT NULL,
				subject TEXT NOT NULL,
				message LONGTEXT NOT NULL,
				status VARCHAR(50) DEFAULT 'sent',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY student_id (student_id),
				KEY course_id (course_id)
				) $charset_collate;
			";
		// The webhooks tables are defined once, next to their migration.
		$tables .= \OhMyLMS\Integrations\Webhooks\Includes\WebhooksMigration::get_schema( $charset_collate );
		return $tables;
	}


	/**
	 * Create roles and capabilities.
	 */
	public static function create_roles() {
		$admin = get_role( 'administrator' );

		$capabilities = self::get_core_capabilities();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		self::create_student_role();
	}


	/**
	 * Register the dedicated OhMyLMS student role.
	 *
	 * Safe to call on every activation/update — bails immediately if the role
	 * already exists, and falls back to a minimal capability set if the
	 * built-in 'subscriber' role is ever missing (heavily modified sites).
	 *
	 * @since 1.2.12
	 */
	public static function create_student_role() {
		if ( ! function_exists( 'ohmylms_get_student_role' ) ) {
			return;
		}

		$role_slug = ohmylms_get_student_role();

		if ( get_role( $role_slug ) ) {
			return; // Already registered, nothing to do.
		}

		$subscriber   = get_role( 'subscriber' );
		$capabilities = ( $subscriber && ! empty( $subscriber->capabilities ) ) ? $subscriber->capabilities : array( 'read' => true );

		add_role( $role_slug, __( 'Student', 'ohmylms' ), $capabilities );
	}


	/**
	 * Get capabilities for OhMyLMS
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_core_capabilities() {
		$capabilities = array();

		$capabilities['core'] = array(
			'manage_ohmylms',
		);

		$capability_types = array( 'ohmylms-course', 'ohmylms-order', 'ohmylms-coupon', 'ohmylms-lesson', 'ohmylms-topic', 'ohmylms-question' );

		foreach ( $capability_types as $capability_type ) {
			$capabilities[ $capability_type ] = array(
				// Post type.
				"edit_{$capability_type}s",
				"read_{$capability_type}s",
				"delete_{$capability_type}s",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",
			);
		}

		return $capabilities;
	}


	/**
	 * Maybe create default pages for OhMyLMS if not already created.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_create_pages() {
		if ( empty( get_option( 'ohmylms_db_version' ) ) ) {
			self::create_pages();
		}
	}


	/**
	 * Create default pages for OhMyLMS.
	 *
	 * @since 1.0.0
	 */
	public static function create_pages() {
		$pages = apply_filters(
			'ohmylms_default_pages',
			array(
				'course'            => array(
					'name'    => _x( 'ohmylms-all-courses', 'Page slug', 'ohmylms' ),
					'title'   => _x( 'All Course', 'Page title', 'ohmylms' ),
					'content' => '',
				),
				'checkout'          => array(
					'name'     => _x( 'ohmylms-checkout', 'Page slug', 'ohmylms' ),
					'title'    => _x( 'OhMy Checkout', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_checkout]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-checkout',
				),
				'student_dashboard' => array(
					'name'     => _x( 'my-dashboard', 'Page slug', 'ohmylms' ),
					'title'    => _x( 'My Dashboard', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_dashboard]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-dashboard',
				),
				'student_profile'   => array(
					'name'     => _x( 'my-profile', 'Page slug', 'ohmylms' ),
					'title'    => _x( 'My Profile', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_profile]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-profile',
				),
				'student_courses'   => array(
					'name'     => _x( 'my-courses', 'Page slug', 'ohmylms' ),
					'title'    => _x( 'My Courses', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_my_courses]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-my-courses',
				),
			)
		);

		foreach ( $pages as $key => $page ) {
			// $existing_page = get_page_by_path( $page['name'], OBJECT, 'page' );

			// if ( $existing_page ) {
			// wp_update_post(
			// array(
			// 'ID'           => $existing_page->ID,
			// 'post_title'   => $page['title'],
			// 'post_content' => $page['content'],
			// 'post_name'      => $page['name'], // Ensuring slug consistency
			// )
			// );
			// $page_id = $existing_page->ID;
			// } else {
			// Create the page if it doesn't exist
			// $page_id = wp_insert_post(
			// array(
			// 'post_title'     => $page['title'],
			// 'post_status'    => 'publish',
			// 'post_type'      => 'page',
			// 'post_content'   => $page['content'],
			// 'post_author'    => get_current_user_id(),
			// 'comment_status' => 'closed',
			// 'post_name'      => $page['name'], // Ensuring slug consistency
			// )
			// );
			// }.

			// Create the page if it doesn't exist.
			$page_id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_content'   => $page['content'],
					'post_author'    => get_current_user_id(),
					'comment_status' => 'closed',
					'post_name'      => $page['name'], // Ensuring slug consistency.
				)
			);

			// Update the page template if required.
			if ( ! empty( $page['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $page['template'] );
			}

			// Store the page ID in options.
			update_option( 'ohmylms_' . $key . '_page_id', $page_id );
		}

		flush_rewrite_rules( true );
	}

	/**
	 * Create student pages for existing installations.
	 * This method is called when the plugin is updated to create the new student pages.
	 *
	 * @since 1.0.0
	 */
	public static function create_student_pages_for_existing_users() {
		// Check if student pages have already been created.
		if ( get_option( 'ohmylms_student_pages_created' ) ) {
			return;
		}

		// Handle the old profile page - update it to use the legacy shortcode.
		$old_profile_page_id = get_option( 'ohmylms_profile_page_id' );
		if ( $old_profile_page_id && get_post( $old_profile_page_id ) ) {
			// Update the old profile page to use the legacy shortcode.
			wp_update_post(
				array(
					'ID'           => $old_profile_page_id,
					'post_content' => '<!-- wp:shortcode -->[ohmylms_my_profile]<!-- /wp:shortcode -->',
				)
			);
		}

		// Create the new student pages.
		$student_pages = array(
			'student_dashboard' => array(
				'name'     => _x( 'my-dashboard', 'Page slug', 'ohmylms' ),
				'title'    => _x( 'My Dashboard', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_dashboard]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-dashboard',
			),
			'student_profile'   => array(
				'name'     => _x( 'my-profile', 'Page slug', 'ohmylms' ),
				'title'    => _x( 'My Profile', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_profile]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-profile',
			),
			'student_courses'   => array(
				'name'     => _x( 'my-courses', 'Page slug', 'ohmylms' ),
				'title'    => _x( 'My Courses', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_my_courses]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-my-courses',
			),
		);

		foreach ( $student_pages as $key => $page ) {
			// Check if page already exists.
			$existing_page_id = get_option( 'ohmylms_' . $key . '_page_id' );
			if ( $existing_page_id && get_post( $existing_page_id ) ) {
				continue; // Skip if page already exists.
			}

			// For student_profile, we need to handle the slug conflict.
			if ( 'student_profile' === $key ) {
				// Check if there's already a page with 'my-profile' slug.
				$existing_my_profile = get_page_by_path( 'my-profile' );
				if ( $existing_my_profile ) {
					// Draft the old profile page to avoid slug conflict and frontend confusion.
					wp_update_post(
						array(
							'ID'          => $existing_my_profile->ID,
							'post_name'   => 'legacy-profile',
							'post_status' => 'draft',
						)
					);
				}
			}

			// Create the page.
			$page_id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_content'   => $page['content'],
					'post_author'    => get_current_user_id(),
					'comment_status' => 'closed',
					'post_name'      => $page['name'],
				)
			);

			// Update the page template if required.
			if ( ! empty( $page['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $page['template'] );
			}

			// Store the page ID in options.
			update_option( 'ohmylms_' . $key . '_page_id', $page_id );
		}

		// Update settings for existing users if they haven't been customized.
		self::maybe_update_student_page_settings();

		// Set flag to indicate student pages have been created.
		update_option( 'ohmylms_student_pages_created', '1' );

		flush_rewrite_rules( true );
	}

	/**
	 * Update student page settings for existing users if they haven't been customized.
	 *
	 * @since 1.0.0
	 */
	private static function maybe_update_student_page_settings() {
		// Get current profile page setting.
		$current_profile_page_id = get_option( 'ohmylms_profile_page_id' );
		$default_profile_page_id = get_option( 'ohmylms_profile_page_id' );

		// Update the profile page setting to point to the new student profile page.
		$new_student_profile_page_id = get_option( 'ohmylms_student_profile_page_id' );
		if ( $new_student_profile_page_id ) {
			// Keep the old profile page setting as is (for legacy shortcode)
			// The new student profile page will be used via the new settings.
		}
	}

	/**
	 * Save default permalink settings for Install.
	 */
	public static function save_default_permalink_settings() {
		$permalink          = new \OhMyLMS\Admin\Settings\Permalink();
		$permalink_settings = $permalink->get_settings();
		if ( is_array( $permalink_settings ) ) {
			foreach ( $permalink_settings as $setting ) {
				if ( isset( $setting['id'], $setting['default'] ) ) {
					if ( 'ohmylms_permalink' === $setting['id'] ) {
						update_option( $setting['id'], $setting['default'] );
						flush_rewrite_rules( true );
					}
				}
			}
		}
	}

	/**
	 * Save default design settings for Install.
	 */
	public static function save_default_design_settings() {
		$design          = new \OhMyLMS\Admin\Settings\Design();
		$design_settings = $design->get_settings();
		if ( is_array( $design_settings ) ) {
			foreach ( $design_settings as $setting ) {
				if ( isset( $setting['id'], $setting['default'] ) ) {
					update_option( $setting['id'], $setting['default'] );
				}
			}
		}
	}

	/**
	 * Save default email settings for Install.
	 */
	public static function save_default_email_settings() {
		$email          = new \OhMyLMS\Admin\Settings\EmailSettings();
		$email_settings = $email->get_settings();
		if ( is_array( $email_settings ) ) {
			foreach ( $email_settings as $setting ) {
				if ( isset( $setting['id'], $setting['default'] ) ) {
					update_option( $setting['id'], $setting['default'] );
				}
			}
		}
	}

	/**
	 * Bundled get schema for Install.
	 */
	private static function bundled_get_schema() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$tables          = "CREATE TABLE {$wpdb->prefix}ohmylms_user_membership (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  user_id bigint(20) NOT NULL,
			  membership_id bigint(20) NOT NULL,
			  subscription_id bigint(20) NOT NULL DEFAULT 0,
			  order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			  status varchar(45) NOT NULL DEFAULT '',
			  progress varchar(45) NOT NULL DEFAULT '',
			  start_date datetime NOT NULL default '0000-00-00 00:00:00',
			  end_date datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY (id),
			  KEY user_id (user_id),
			  KEY membership_id (membership_id),
			  KEY order_id (order_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_assignment_attempts (
			  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  user_id bigint(20) NOT NULL,
			  course_id bigint(20) NOT NULL,
			  assignment_id bigint(20) NOT NULL,
			  content longtext NOT NULL,
			  total_attempt INT(11) DEFAULT 0,
			  score INT(11) DEFAULT 0,
			  files longtext NOT NULL,
			   status varchar(45) NOT NULL DEFAULT '',
			   note longtext NULL,
				start_date datetime NOT NULL default '0000-00-00 00:00:00',
			  end_date datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY  (id),
			  UNIQUE KEY id (id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_user_achievement (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  user_id bigint(20) NOT NULL,
			  course_id bigint(20) NULL,
			  membership_id bigint(20) NULL,
			  content_id bigint(20) NULL,
			  points bigint(20),
			  level_id varchar(45) NULL,
			  badge_id varchar(45) NULL,
			  reason longtext NOT NULL,
			  type varchar(45) NOT NULL DEFAULT '',
			  status varchar(45) NOT NULL DEFAULT '',
			  date_created datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY (id),
			  KEY membership_id (membership_id),
			  KEY course_id (course_id),
			  KEY content_id (content_id),
			  KEY badge_id (badge_id),
			  KEY level_id (level_id)
			) $charset_collate;
			CREATE TABLE {$wpdb->prefix}ohmylms_cohorts (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				course_id BIGINT(20) UNSIGNED NOT NULL,
				title VARCHAR(255) DEFAULT NULL,
				start_date DATETIME NULL,
				end_date DATETIME NULL,
				enrollment_end DATETIME NULL,
				has_capacity BOOLEAN NOT NULL DEFAULT 0,
				capacity INT(11) DEFAULT NULL,
				status TEXT DEFAULT NULL,
				meta LONGTEXT DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY course_id (course_id)
			) $charset_collate;
			";
		return $tables;
	}

	/**
	 * Bundled maybe create pages for Install.
	 */
	public static function bundled_maybe_create_pages() {
		if ( empty( get_option( 'ohmylms_pro_db_version' ) ) ) {
			self::bundled_create_pages();
		}
	}

	/**
	 * Bundled create pages for Install.
	 */
	public static function bundled_create_pages() {
		$pages = apply_filters(
			'ohmylms_pro_default_pages',
			array(
				'membership' => array(
					'name'    => _x( 'ohmylms-all-membership', 'Page slug', 'ohmylms' ),
					'title'   => _x( 'All Membership', 'Page title', 'ohmylms' ),
					'content' => '',
				),
			)
		);

		foreach ( $pages as $key => $page ) {
			$existing_page = get_page_by_path( $page['name'], OBJECT, 'page' );

			if ( $existing_page ) {
				if ( ! get_option( 'ohmylms_' . $key . '_page_id' ) ) {
					update_option( 'ohmylms_' . $key . '_page_id', $existing_page->ID );
				}
				continue;
			} else {
				// Create the page if it doesn't exist.
				$page_id = wp_insert_post(
					array(
						'post_title'     => $page['title'],
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'post_content'   => $page['content'],
						'post_author'    => get_current_user_id(),
						'comment_status' => 'closed',
						'post_name'      => $page['name'], // Ensuring slug consistency.
					)
				);
			}

			// Update the page template if required.
			if ( ! empty( $page['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $page['template'] );
			}

			// Store the page ID in options.
			update_option( 'ohmylms_' . $key . '_page_id', $page_id );
		}

		flush_rewrite_rules();
	}
}
