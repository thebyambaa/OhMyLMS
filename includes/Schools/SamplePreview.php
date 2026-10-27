<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Sample school, class and people that an administrator can open with View as.
 *
 * Everything this class creates carries a marker (user meta and a recorded ledger), and removal only
 * ever touches marked records. The sample users get random passwords that are never shown or stored,
 * and a non-deliverable email address, so nobody can sign in as them: only View as can use them.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Explicit, administrator-only sample data for previewing each role. */
final class SamplePreview {

	/** User meta marking a sample account; the value is the sample role key. */
	const META = '_ohmylms_sample_preview';

	/** Ledger of the records this feature created. */
	const OPTION = 'ohmylms_sample_preview';

	/** Non-deliverable address domain used by every sample account. */
	const DOMAIN = 'example.invalid';

	/** Slug of the sample school. */
	const SCHOOL_SLUG = 'sample-preview-school';

	/** Register the REST routes. */
	public static function register() {
		$routes = array(
			'sample-preview'        => 'GET',
			'sample-preview/create' => 'POST',
			'sample-preview/remove' => 'POST',
		);
		foreach ( $routes as $path => $method ) {
			register_rest_route(
				'ohmylms/v1',
				'/school/' . $path,
				array(
					'methods'             => $method,
					'permission_callback' => static function () {
						if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
							return new \WP_Error( 'sample_preview_forbidden', 'Only an administrator can manage sample preview people.', array( 'status' => 403 ) );
						}
						return true;
					},
					'callback'            => static function ( $request ) use ( $path ) {
						try {
							$response = rest_ensure_response( self::handle( $path, $request ) );
							$response->header( 'Cache-Control', 'private, no-store' );
							return $response;
						} catch ( \Throwable $error ) {
							return new \WP_Error( 'sample_preview_error', $error->getMessage(), array( 'status' => in_array( $error->getCode(), array( 400, 403, 409 ), true ) ? $error->getCode() : 400 ) );
						}
					},
				)
			);
		}
	}

	/**
	 * Route one request after the capability check.
	 *
	 * @param string           $path Route suffix.
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private static function handle( $path, $request ) {
		Access::require_access( current_user_can( 'manage_options' ) );
		if ( 'sample-preview/create' === $path ) {
			return self::create();
		}
		if ( 'sample-preview/remove' === $path ) {
			return self::remove( ! empty( $request['confirm'] ) );
		}
		return self::status();
	}

	/**
	 * What the feature creates, in dependency order.
	 *
	 * @return array Keys: school, year, class (names) and people (role key => definition).
	 */
	public static function definitions() {
		return array(
			'school' => 'Sample Preview School',
			'year'   => 'Sample Preview Year',
			'class'  => 'Sample Preview Class',
			'people' => array(
				'school'  => array(
					'login'  => 'sample-preview-school-admin',
					'name'   => 'Sample School Admin',
					'role'   => 'ohmylms_school_admin',
					'member' => 'school_admin',
				),
				'teacher' => array(
					'login'  => 'sample-preview-teacher',
					'name'   => 'Sample Teacher',
					'role'   => 'ohmylms_teacher',
					'member' => 'teacher',
				),
				'parent'  => array(
					'login'  => 'sample-preview-parent',
					'name'   => 'Sample Parent',
					'role'   => 'ohmylms_parent',
					'member' => '',
				),
				'student' => array(
					'login'  => '',
					'name'   => 'Sample Student',
					'role'   => '',
					'member' => 'student',
				),
			),
		);
	}

	/**
	 * Every step in creation order.
	 *
	 * @return string[]
	 */
	public static function steps() {
		return array( 'school', 'year', 'class', 'user:school', 'user:teacher', 'user:parent', 'user:student', 'member:school', 'member:teacher', 'class:teacher', 'class:student', 'guardian' );
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
				$remove[] = sprintf( 'School "%s" (#%d) with its academic year, class, memberships and guardian link', $facts['school']['name'], $facts['school']['id'] );
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
	 * @return array Keys: school_id, year_id, class_id, users (role key => ID).
	 */
	private static function ledger() {
		global $wpdb;
		$ledger = get_option( self::OPTION, array() );
		$ledger = is_array( $ledger ) ? $ledger : array();
		$ledger = array_merge(
			array(
				'school_id' => 0,
				'year_id'   => 0,
				'class_id'  => 0,
				'users'     => array(),
			),
			$ledger
		);
		foreach ( get_users(
			array(
				'fields'     => 'all',
				'number'     => 20,
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
		$done           = array();
		$school         = $ledger['school_id'] ? Service::row( 'schools', $ledger['school_id'] ) : null;
		$year           = $ledger['year_id'] ? Service::row( 'academic_years', $ledger['year_id'] ) : null;
		$class          = $ledger['class_id'] ? Service::row( 'classes', $ledger['class_id'] ) : null;
		$sid            = $ledger['school_id'];
		$done['school'] = self::is_sample_school( $school );
		$done['year']   = $done['school'] && $year && (int) $year['school_id'] === $sid;
		$done['class']  = $done['school'] && $class && (int) $class['school_id'] === $sid;
		foreach ( array_keys( self::definitions()['people'] ) as $key ) {
			$user = ! empty( $ledger['users'][ $key ] ) ? get_userdata( $ledger['users'][ $key ] ) : false;

			$done[ 'user:' . $key ] = $user && self::is_sample_user( get_user_meta( $user->ID, self::META, true ), $user->user_email );
		}
		$count = static function ( $table, $where, $args ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ' . $where, array_merge( array( Schema::table( $table ) ), $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Fresh existence check; the fragment is a fixed literal.
		};
		$users = $ledger['users'];
		foreach ( array( 'school', 'teacher' ) as $key ) {
			$done[ 'member:' . $key ] = $done['school'] && ! empty( $users[ $key ] ) && $count( 'school_memberships', "school_id=%d AND user_id=%d AND role=%s AND status='active'", array( $sid, $users[ $key ], self::definitions()['people'][ $key ]['member'] ) ) > 0;
		}
		foreach ( array( 'teacher', 'student' ) as $key ) {
			$done[ 'class:' . $key ] = $done['class'] && ! empty( $users[ $key ] ) && $count( 'class_memberships', "class_id=%d AND user_id=%d AND role=%s AND status='active'", array( $ledger['class_id'], $users[ $key ], $key ) ) > 0;
		}
		$done['guardian'] = $done['school'] && ! empty( $users['parent'] ) && ! empty( $users['student'] ) && $count( 'guardian_links', "school_id=%d AND guardian_user_id=%d AND student_user_id=%d AND status='active'", array( $sid, $users['parent'], $users['student'] ) ) > 0;
		return $done;
	}

	/**
	 * Current state for the Account Hub home tab.
	 *
	 * @return array
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
		$missing = self::plan_create( $done );
		return array(
			'exists'    => count( $missing ) < count( self::steps() ),
			'complete'  => ! $missing,
			'school_id' => (int) $ledger['school_id'],
			'people'    => $people,
		);
	}

	/**
	 * Create (or complete) the sample school and people.
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
			$sid = $ledger['school_id'];
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
			} elseif ( 'class' === $step ) {
				$ledger['class_id'] = (int) Service::create_class(
					$sid,
					array(
						'academic_year_id' => $ledger['year_id'],
						'name'             => $names['class'],
						'subject'          => 'Sample',
					)
				)['id'];
			} elseif ( 0 === strpos( $step, 'user:' ) ) {
				$key                     = substr( $step, 5 );
				$ledger['users'][ $key ] = self::create_user( $key, $sid );
			} elseif ( 0 === strpos( $step, 'member:' ) ) {
				$key = substr( $step, 7 );
				Service::membership(
					'school_memberships',
					array(
						'school_id' => $sid,
						'user_id'   => $ledger['users'][ $key ],
						'role'      => $names['people'][ $key ]['member'],
					)
				);
			} elseif ( 0 === strpos( $step, 'class:' ) ) {
				$key = substr( $step, 6 );
				Service::class_member(
					$ledger['class_id'],
					array(
						'user_id' => $ledger['users'][ $key ],
						'role'    => $key,
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
	 * @param string $key Sample role key.
	 * @param int    $school_id Sample school.
	 * @return int User ID.
	 * @throws \RuntimeException When the login is taken or the account cannot be created.
	 */
	private static function create_user( $key, $school_id ) {
		$definition = self::definitions()['people'][ $key ];
		if ( 'student' === $key ) {
			$id = (int) Service::create_student(
				$school_id,
				array(
					'name'                => $definition['name'],
					'external_student_id' => 'SAMPLE-1',
				)
			)['id'];
			wp_update_user(
				array(
					'ID'         => $id,
					'user_email' => 'sample-preview-student@' . self::DOMAIN,
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
		$in     = $ids ? implode( ',', array_map( 'intval', $ids ) ) : '0';
		$count  = static function ( $table, $where, $args = array() ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ' . $where, array_merge( array( Schema::table( $table ) ), $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Fresh safety check; the fragment is a fixed literal and the ID list is cast to integers.
		};
		$checks = array(
			'People who are not sample accounts belong to the school' => $count( 'school_memberships', "school_id=%d AND user_id NOT IN ($in)", array( $sid ) ),
			'Other classes were added to the school'      => $count( 'classes', 'school_id=%d AND id<>%d', array( $sid, $ledger['class_id'] ) ),
			'Other academic years were added to the school' => $count( 'academic_years', 'school_id=%d AND id<>%d', array( $sid, $ledger['year_id'] ) ),
			'Someone outside the sample joined the class' => $count( 'class_memberships', "class_id=%d AND user_id NOT IN ($in)", array( $ledger['class_id'] ) ),
			'Invitations were sent for the school'        => $count( 'school_invitations', 'school_id=%d', array( $sid ) ),
			'Assignments were created for the school'     => $count( 'learning_assignments', 'school_id=%d', array( $sid ) ),
			'Guardian links outside the sample exist'     => $count( 'guardian_links', "school_id=%d AND (guardian_user_id NOT IN ($in) OR student_user_id NOT IN ($in))", array( $sid ) ),
		);
		foreach ( $checks as $label => $found ) {
			if ( $found ) {
				$facts['outsiders'][] = $label;
			}
		}
		if ( ! empty( $ledger['users']['student'] ) ) {
			$student = (int) $ledger['users']['student'];
			$used    = array(
				'The sample student is enrolled in a course' => $count( 'user_enrollment', 'user_id=%d', array( $student ) ),
				'The sample student has quiz attempts' => $count( 'quiz_attempts', 'student_id=%d', array( $student ) ),
				'The sample student has assignments'   => $count( 'assignment_recipients', 'student_user_id=%d', array( $student ) ),
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
				$class = (int) $ledger['class_id'];
				foreach ( array(
					'guardian_links'          => 'school_id',
					'school_student_profiles' => 'school_id',
					'school_memberships'      => 'school_id',
					'school_audit_log'        => 'school_id',
				) as $table => $column ) {
					$wpdb->delete( Schema::table( $table ), array( $column => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only rows of the verified sample school.
				}
				$wpdb->delete( Schema::table( 'class_memberships' ), array( 'class_id' => $class ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the sample class roster.
				$wpdb->delete( Schema::table( 'classes' ), array( 'school_id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the sample school's class.
				$wpdb->delete( Schema::table( 'academic_years' ), array( 'school_id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the sample school's year.
				$wpdb->delete( Schema::table( 'schools' ), array( 'id' => $sid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes only the verified sample school.
			}
		);
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $facts['users'] as $key => $user ) {
			if ( ! empty( $user['marked'] ) ) {
				wp_delete_user( $user['id'] );
				delete_metadata( 'user', 0, ViewAs::LAST_META . $key, $user['id'], true );
			}
		}
		delete_option( self::OPTION );
		Service::audit( 0, 'sample_preview_removed', $sid );
		return array_merge( $plan, array( 'removed' => true ) );
	}
}
