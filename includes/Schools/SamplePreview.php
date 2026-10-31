<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Sample school with classes and people that an administrator can explore and remove in one step.
 *
 * Everything this class creates carries a marker (user meta and a recorded ledger), and removal only
 * ever touches marked records. The sample users get random passwords that are never shown or stored,
 * and a non-deliverable email address, so nobody can sign in as them: only View as can use them.
 * REST: `ohmylms/v1/account-hub/sample-school` (`Rest\V1\AccountHubSampleSchoolController`).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Explicit, administrator-only sample data for exploring schools and previewing each role. */
final class SamplePreview {

	/** User meta marking a sample account; the value is the sample person key. */
	const META = '_ohmylms_sample_preview';

	/** Ledger of the records this feature created. */
	const OPTION = 'ohmylms_sample_preview';

	/** Non-deliverable address domain used by every sample account. */
	const DOMAIN = 'example.invalid';

	/** Slug of the sample school. */
	const SCHOOL_SLUG = 'sample-preview-school';

	/**
	 * What the feature creates, in dependency order.
	 *
	 * @return array Keys: school, year (names), classes (key => name, grade, subject) and people (key => definition).
	 */
	public static function definitions() {
		$people = array(
			'school'  => array(
				'login'  => 'sample-preview-school-admin',
				'name'   => 'Sample School Admin',
				'role'   => 'ohmylms_school_admin',
				'member' => 'school_admin',
				'class'  => '',
			),
			'teacher' => array(
				'login'  => 'sample-preview-teacher',
				'name'   => 'Sample Teacher',
				'role'   => 'ohmylms_teacher',
				'member' => 'teacher',
				'class'  => '',
			),
			'parent'  => array(
				'login'  => 'sample-preview-parent',
				'name'   => 'Sample Parent',
				'role'   => 'ohmylms_parent',
				'member' => '',
				'class'  => '',
			),
		);
		for ( $i = 1; $i <= 6; $i++ ) {
			$people[ 1 === $i ? 'student' : 'student-' . $i ] = array(
				'login'  => '',
				'name'   => 'Sample Student ' . $i,
				'role'   => '',
				'member' => 'student',
				'class'  => $i <= 3 ? 'a' : 'b',
			);
		}

		return array(
			'school'  => 'Sample Preview School',
			'year'    => 'Sample Preview Year',
			'classes' => array(
				'a' => array(
					'name'    => 'Sample Grade 7A',
					'grade'   => '7',
					'subject' => 'Mathematics',
				),
				'b' => array(
					'name'    => 'Sample Grade 8B',
					'grade'   => '8',
					'subject' => 'Science',
				),
			),
			'people'  => $people,
		);
	}

	/**
	 * Keys of the sample students.
	 *
	 * @return string[]
	 */
	private static function students() {
		return array_keys(
			array_filter(
				self::definitions()['people'],
				static function ( $person ) {
					return 'student' === $person['member'];
				}
			)
		);
	}

	/**
	 * Every step in creation order.
	 *
	 * @return string[]
	 */
	public static function steps() {
		$names = self::definitions();
		$steps = array( 'school', 'year' );
		foreach ( array_keys( $names['classes'] ) as $class ) {
			$steps[] = 'class:' . $class;
		}
		foreach ( array_keys( $names['people'] ) as $key ) {
			$steps[] = 'user:' . $key;
		}
		$steps[] = 'member:school';
		$steps[] = 'member:teacher';
		foreach ( array_keys( $names['classes'] ) as $class ) {
			$steps[] = 'roster:' . $class . ':teacher';
		}
		foreach ( self::students() as $key ) {
			$steps[] = 'roster:' . $names['people'][ $key ]['class'] . ':' . $key;
		}
		$steps[] = 'guardian';

		return $steps;
	}

	/**
	 * Steps still to do, so a second click reuses what exists and only fills gaps.
	 *
	 * @param array $done Map of step name => true when it already exists.
	 * @return string[]
	 */
	public static function plan_create( array $done ) {
		return array_values(
			array_filter(
				self::steps(),
				static function ( $step ) use ( $done ) {
					return empty( $done[ $step ] );
				}
			)
		);
	}

	/**
	 * Whether an account is a sample account this feature may delete.
	 *
	 * @param mixed  $marker User meta value.
	 * @param string $email Account email.
	 * @return bool
	 */
	public static function is_sample_user( $marker, $email ) {
		return is_string( $marker ) && isset( self::definitions()['people'][ $marker ] ) && str_ends_with( strtolower( (string) $email ), '@' . self::DOMAIN );
	}

	/**
	 * Whether a school row is the marked sample school.
	 *
	 * @param array|null $row School row.
	 * @return bool
	 */
	public static function is_sample_school( $row ) {
		if ( ! is_array( $row ) ) {
			return false;
		}
		$name = (string) ( $row['name'] ?? '' );
		return self::definitions()['school'] === $name && 0 === strpos( (string) ( $row['slug'] ?? '' ), self::SCHOOL_SLUG );
	}

	/**
	 * Decide what removal may touch.
	 *
	 * Only marked records are ever listed; anything unmarked is skipped, and removal is refused while
	 * anything suggests the sample data was used or extended for real.
	 *
	 * @param array $facts Keys: school (row|null), users (role key => array id, name, marked), outsiders (string[]), activity (string[]).
	 * @return array Keys: remove (string[]), skip (string[]), blockers (string[]), allowed (bool).
	 */
	public static function plan_removal( array $facts ) {
		$remove = array();
		$skip   = array();
		if ( ! empty( $facts['school'] ) ) {
			if ( self::is_sample_school( $facts['school'] ) ) {
				$remove[] = sprintf( 'School "%s" (#%d) with its academic year, classes, memberships and guardian link', $facts['school']['name'], $facts['school']['id'] );
			} else {
				$skip[] = sprintf( 'School #%d is not marked as a sample', $facts['school']['id'] ?? 0 );
			}
		}
		foreach ( (array) ( $facts['users'] ?? array() ) as $user ) {
			if ( ! empty( $user['marked'] ) ) {
				$remove[] = sprintf( 'Account "%s" (#%d)', $user['name'], $user['id'] );
			} else {
				$skip[] = sprintf( 'Account #%d is not marked as a sample', $user['id'] ?? 0 );
			}
		}
		$blockers = array_merge( array_values( (array) ( $facts['outsiders'] ?? array() ) ), array_values( (array) ( $facts['activity'] ?? array() ) ) );
		return array(
			'remove'   => $blockers ? array() : $remove,
			'skip'     => $skip,
			'blockers' => $blockers,
			'allowed'  => ! $blockers && (bool) $remove,
		);
	}

	/**
	 * Recorded sample records, completed from the markers when the ledger is missing.
	 *
	 * @return array Keys: school_id, year_id, classes (key => ID), users (person key => ID).
	 */
	private static function ledger() {
		global $wpdb;
		$ledger = get_option( self::OPTION, array() );
		$ledger = is_array( $ledger ) ? $ledger : array();
		$ledger = array_merge(
			array(
				'school_id' => 0,
				'year_id'   => 0,
				'classes'   => array(),
				'users'     => array(),
			),
			$ledger
		);
		// Ledgers of the one-class version recorded `class_id`.
		if ( ! empty( $ledger['class_id'] ) && empty( $ledger['classes']['a'] ) ) {
			$ledger['classes']['a'] = (int) $ledger['class_id'];
		}
		unset( $ledger['class_id'] );
		foreach ( get_users(
			array(
				'fields'     => 'all',
				'number'     => 50,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Marker lookup on a handful of rows.
					array(
						'key'     => self::META,
						'compare' => 'EXISTS',
					),
				),
			)
		) as $user ) {
			$key = (string) get_user_meta( $user->ID, self::META, true );
			if ( empty( $ledger['users'][ $key ] ) && isset( self::definitions()['people'][ $key ] ) ) {
				$ledger['users'][ $key ] = (int) $user->ID;
			}
		}
		if ( ! $ledger['school_id'] ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE slug=%s', Schema::table( 'schools' ), self::SCHOOL_SLUG ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh lookup by the fixed sample slug.
			if ( self::is_sample_school( $row ) ) {
				$ledger['school_id'] = (int) $row['id'];
			}
		}
		return $ledger;
	}

	/**
	 * Which steps already exist.
	 *
	 * @param array $ledger Recorded records.
	 * @return array Step => true.
	 */
	private static function done( array $ledger ) {
		global $wpdb;
		$names          = self::definitions();
		$done           = array();
		$school         = $ledger['school_id'] ? Service::row( 'schools', $ledger['school_id'] ) : null;
		$year           = $ledger['year_id'] ? Service::row( 'academic_years', $ledger['year_id'] ) : null;
		$sid            = (int) $ledger['school_id'];
		$done['school'] = self::is_sample_school( $school );
		$done['year']   = $done['school'] && $year && (int) $year['school_id'] === $sid;
		foreach ( array_keys( $names['classes'] ) as $key ) {
			$class                   = ! empty( $ledger['classes'][ $key ] ) ? Service::row( 'classes', $ledger['classes'][ $key ] ) : null;
			$done[ 'class:' . $key ] = $done['school'] && $class && (int) $class['school_id'] === $sid;
		}
		foreach ( array_keys( $names['people'] ) as $key ) {
			$user = ! empty( $ledger['users'][ $key ] ) ? get_userdata( $ledger['users'][ $key ] ) : false;

			$done[ 'user:' . $key ] = $user && self::is_sample_user( get_user_meta( $user->ID, self::META, true ), $user->user_email );
		}
		$count = static function ( $table, $where, $args ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ' . $where, array_merge( array( Schema::table( $table ) ), $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Fresh existence check; the fragment is a fixed literal.
		};
		$users = $ledger['users'];
		foreach ( array( 'school', 'teacher' ) as $key ) {
			$done[ 'member:' . $key ] = $done['school'] && ! empty( $users[ $key ] ) && $count( 'school_memberships', "school_id=%d AND user_id=%d AND role=%s AND status='active'", array( $sid, $users[ $key ], $names['people'][ $key ]['member'] ) ) > 0;
		}
		foreach ( array_keys( $names['classes'] ) as $class ) {
			$members = array( 'teacher' => 'teacher' );
			foreach ( self::students() as $key ) {
				if ( $class === $names['people'][ $key ]['class'] ) {
					$members[ $key ] = 'student';
				}
			}
			foreach ( $members as $key => $role ) {
				$done[ 'roster:' . $class . ':' . $key ] = ! empty( $done[ 'class:' . $class ] ) && ! empty( $users[ $key ] ) && $count( 'class_memberships', "class_id=%d AND user_id=%d AND role=%s AND status='active'", array( $ledger['classes'][ $class ], $users[ $key ], $role ) ) > 0;
			}
		}
		$done['guardian'] = $done['school'] && ! empty( $users['parent'] ) && ! empty( $users['student'] ) && $count( 'guardian_links', "school_id=%d AND guardian_user_id=%d AND student_user_id=%d AND status='active'", array( $sid, $users['parent'], $users['student'] ) ) > 0;
		return $done;
	}

	/**
	 * Current state.
	 *
	 * @return array Keys: exists, complete, school_id, classes (key => id), people (key => id, name).
	 */
	public static function status() {
		Access::require_access( current_user_can( 'manage_options' ) );
		$ledger = self::ledger();
		$done   = self::done( $ledger );
		$people = array();
		foreach ( $ledger['users'] as $key => $id ) {
			$user = get_userdata( $id );
			if ( $user && ! empty( $done[ 'user:' . $key ] ) ) {
				$people[ $key ] = array(
					'id'   => (int) $id,
					'name' => $user->display_name,
				);
			}
		}
		$classes = array();
		foreach ( (array) $ledger['classes'] as $key => $id ) {
			if ( ! empty( $done[ 'class:' . $key ] ) ) {
				$classes[ $key ] = (int) $id;
			}
		}
		$missing = self::plan_create( $done );
		return array(
			'exists'    => count( $missing ) < count( self::steps() ),
			'complete'  => ! $missing,
			'school_id' => ! empty( $done['school'] ) ? (int) $ledger['school_id'] : 0,
			'classes'   => $classes,
			'people'    => $people,
		);
	}

	/**
	 * Create (or complete) the sample school, classes and people.
	 *
	 * @return array Same shape as status().
	 */
	public static function create() {
		Access::require_access( current_user_can( 'manage_options' ) );
		$names  = self::definitions();
		$ledger = self::ledger();
		$save   = static function () use ( &$ledger ) {
			update_option( self::OPTION, $ledger, false );
		};
		$todo   = self::plan_create( self::done( $ledger ) );
		if ( ! $todo ) {
			return self::status();
		}
		foreach ( $todo as $step ) {
			$sid   = $ledger['school_id'];
			$parts = explode( ':', $step );
			if ( 'school' === $step ) {
				$ledger['school_id'] = (int) Service::create_school(
					array(
						'name'     => $names['school'],
						'slug'     => self::SCHOOL_SLUG,
						'timezone' => 'UTC',
					)
				)['id'];
			} elseif ( 'year' === $step ) {
				$ledger['year_id'] = (int) Service::create_year(
					$sid,
					array(
						'label'     => $names['year'],
						'starts_at' => gmdate( 'Y' ) . '-01-01',
						'ends_at'   => gmdate( 'Y' ) . '-12-31',
					)
				)['id'];
			} elseif ( 'class' === $parts[0] ) {
				$definition                     = $names['classes'][ $parts[1] ];
				$ledger['classes'][ $parts[1] ] = (int) Service::create_class(
					$sid,
					array(
						'academic_year_id' => $ledger['year_id'],
						'name'             => $definition['name'],
						'subject'          => $definition['subject'],
						'grade'            => $definition['grade'],
					)
				)['id'];
			} elseif ( 'user' === $parts[0] ) {
				$ledger['users'][ $parts[1] ] = self::create_user( $parts[1], $sid );
			} elseif ( 'member' === $parts[0] ) {
				Service::membership(
					'school_memberships',
					array(
						'school_id' => $sid,
						'user_id'   => $ledger['users'][ $parts[1] ],
						'role'      => $names['people'][ $parts[1] ]['member'],
					)
				);
			} elseif ( 'roster' === $parts[0] ) {
				Service::class_member(
					$ledger['classes'][ $parts[1] ],
					array(
						'user_id' => $ledger['users'][ $parts[2] ],
						'role'    => 'teacher' === $parts[2] ? 'teacher' : 'student',
					)
				);
			} elseif ( 'guardian' === $step ) {
				Service::insert(
					'guardian_links',
					array(
						'school_id'        => $sid,
						'guardian_user_id' => $ledger['users']['parent'],
						'student_user_id'  => $ledger['users']['student'],
						'status'           => 'active',
						'approved_by'      => get_current_user_id(),
						'approved_at'      => Service::now(),
					)
				);
			}
			$save();
		}
		Service::audit( $ledger['school_id'], 'sample_preview_created', $ledger['school_id'] );
		return self::status();
	}

	/**
	 * Create one marked sample account.
	 *
	 * @param string $key Sample person key.
	 * @param int    $school_id Sample school.
	 * @return int User ID.
	 * @throws \RuntimeException When the login is taken or the account cannot be created.
	 */
	private static function create_user( $key, $school_id ) {
		$definition = self::definitions()['people'][ $key ];
		if ( 'student' === $definition['member'] ) {
			$id = (int) Service::create_student(
				$school_id,
				array(
					'name'                => $definition['name'],
					'external_student_id' => 'SAMPLE-' . strtoupper( $key ),
				)
			)['id'];
			wp_update_user(
				array(
					'ID'         => $id,
					'user_email' => 'sample-preview-' . $key . '@' . self::DOMAIN,
				)
			);
		} else {
			Service::need( ! username_exists( $definition['login'] ), 'An account named ' . $definition['login'] . ' already exists and is not a marked sample. Rename or remove it first.' );
			$id = wp_insert_user(
				array(
					'user_login'   => $definition['login'],
					'user_email'   => $definition['login'] . '@' . self::DOMAIN,
					// Random and discarded: only View as can reach this account.
					'user_pass'    => wp_generate_password( 40 ),
					'display_name' => $definition['name'],
					'role'         => $definition['role'],
				)
			);
			if ( is_wp_error( $id ) ) {
				throw new \RuntimeException( esc_html( $id->get_error_message() ), 400 );
			}
		}
		update_user_meta( $id, self::META, $key );
		return (int) $id;
	}

	/**
	 * Facts that decide whether removal is safe.
	 *
	 * @param array $ledger Recorded records.
	 * @return array See plan_removal().
	 */
	private static function removal_facts( array $ledger ) {
		global $wpdb;
		$facts = array(
			'school'    => $ledger['school_id'] ? Service::row( 'schools', $ledger['school_id'] ) : null,
			'users'     => array(),
			'outsiders' => array(),
			'activity'  => array(),
		);
		$ids   = array();
		foreach ( $ledger['users'] as $key => $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}
			$ids[]                  = (int) $id;
			$facts['users'][ $key ] = array(
				'id'     => (int) $id,
				'name'   => $user->display_name,
				'marked' => self::is_sample_user( get_user_meta( $id, self::META, true ), $user->user_email ),
			);
		}
		$sid = (int) $ledger['school_id'];
		if ( ! $sid || ! self::is_sample_school( $facts['school'] ) ) {
			return $facts;
		}
		$in      = $ids ? implode( ',', array_map( 'intval', $ids ) ) : '0';
		$classes = array_values( array_filter( array_map( 'intval', (array) $ledger['classes'] ) ) );
		$own     = $classes ? implode( ',', $classes ) : '0';
		$count   = static function ( $table, $where, $args = array() ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ' . $where, array_merge( array( Schema::table( $table ) ), $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Fresh safety check; the fragment is a fixed literal and the ID lists are cast to integers.
		};
		$checks  = array(
			'People who are not sample accounts belong to the school' => $count( 'school_memberships', "school_id=%d AND user_id NOT IN ($in)", array( $sid ) ),
			'Other classes were added to the school'      => $count( 'classes', "school_id=%d AND id NOT IN ($own)", array( $sid ) ),
			'Other academic years were added to the school' => $count( 'academic_years', 'school_id=%d AND id<>%d', array( $sid, $ledger['year_id'] ) ),
			'Someone outside the sample joined a class'   => $count( 'class_memberships', "class_id IN ($own) AND user_id NOT IN ($in)" ),
			'Invitations were sent for the school'        => $count( 'school_invitations', 'school_id=%d', array( $sid ) ),
			'Assignments were created for the school'     => $count( 'learning_assignments', 'school_id=%d', array( $sid ) ),
			'Courses were linked to a sample class'       => $count( 'course_classes', "class_id IN ($own)" ),
			'Guardian links outside the sample exist'     => $count( 'guardian_links', "school_id=%d AND (guardian_user_id NOT IN ($in) OR student_user_id NOT IN ($in))", array( $sid ) ),
		);
		foreach ( $checks as $label => $found ) {
			if ( $found ) {
				$facts['outsiders'][] = $label;
			}
		}
		$students = array();
		foreach ( self::students() as $key ) {
			if ( ! empty( $ledger['users'][ $key ] ) ) {
				$students[] = (int) $ledger['users'][ $key ];
			}
		}
		if ( $students ) {
			$list = implode( ',', $students );
			$used = array(
				'A sample student is enrolled in a course' => $count( 'user_enrollment', "user_id IN ($list)" ),
				'A sample student has quiz attempts'       => $count( 'quiz_attempts', "student_id IN ($list)" ),
				'A sample student has assignments'         => $count( 'assignment_recipients', "student_user_id IN ($list)" ),
			);
			foreach ( $used as $label => $found ) {
				if ( $found ) {
					$facts['activity'][] = $label;
				}
			}
		}
		return $facts;
	}

	/**
	 * List, or with confirmation delete, the marked sample records.
	 *
	 * @param bool $confirm True once the administrator confirmed the listed records.
	 * @return array Plan from plan_removal() plus removed (bool).
	 */
	public static function remove( $confirm ) {
		Access::require_access( current_user_can( 'manage_options' ) );
		$ledger = self::ledger();
		$facts  = self::removal_facts( $ledger );
		$plan   = self::plan_removal( $facts );
		if ( ! $confirm || ! $plan['allowed'] ) {
			return array_merge( $plan, array( 'removed' => false ) );
		}
		global $wpdb;
		$sid = self::is_sample_school( $facts['school'] ) ? (int) $ledger['school_id'] : 0;
		Service::transaction(
			static function () use ( $wpdb, $sid, $ledger ) {
				if ( ! $sid ) {
					return;
				}
				foreach ( array(
					'guardian_links'          => 'school_id',
					'school_student_profiles' => 'school_id',
					'school_memberships'      => 'school_id',
					'school_audit_log'        => 'school_id',
				) as $table => $column ) {
					$wpdb->delete( Schema::table( $table ), array( $column => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only rows of the verified sample school.
				}
				foreach ( (array) $ledger['classes'] as $class ) {
					$wpdb->delete( Schema::table( 'class_memberships' ), array( 'class_id' => (int) $class ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the sample class rosters.
				}
				$wpdb->delete( Schema::table( 'classes' ), array( 'school_id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the sample school's classes.
				$wpdb->delete( Schema::table( 'academic_years' ), array( 'school_id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the sample school's year.
				$wpdb->delete( Schema::table( 'schools' ), array( 'id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the verified sample school.
			}
		);
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $facts['users'] as $key => $user ) {
			if ( ! empty( $user['marked'] ) ) {
				wp_delete_user( $user['id'] );
				$role = isset( self::definitions()['people'][ $key ] ) && 'student' === self::definitions()['people'][ $key ]['member'] ? 'student' : $key;
				delete_metadata( 'user', 0, ViewAs::LAST_META . $role, $user['id'], true );
			}
		}
		delete_option( self::OPTION );
		Service::audit( 0, 'sample_preview_removed', $sid );
		return array_merge( $plan, array( 'removed' => true ) );
	}
}
