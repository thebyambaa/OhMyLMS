<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * REST API for the front-end dashboard.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Dashboard\Front;
use OhMyLMS\Dashboard\FrontSummary;
use OhMyLMS\Design\DesignSystem;
use OhMyLMS\Design\Mascot;
use OhMyLMS\Design\Tokens;
use OhMyLMS\Schools\Service;
use OhMyLMS\Schools\Settings as SchoolSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Summary, people, classes, content and branding for /dashboard.
 */
class FrontDashboardController extends RestController {

	/** Register the routes. */
	public function register_routes() {
		$read = array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'can_open' ),
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/summary',
			array(
				$read + array( 'callback' => array( $this, 'summary' ) ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/export',
			array(
				$read + array( 'callback' => array( $this, 'export_csv' ) ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/people',
			array(
				$read + array( 'callback' => array( $this, 'people' ) ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/classes',
			array(
				$read + array( 'callback' => array( $this, 'classes' ) ),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_class' ),
					'permission_callback' => array( $this, 'can_open' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/invitations',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'invite' ),
					'permission_callback' => array( $this, 'can_open' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/invitations/(?P<id>\d+)/resend',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resend' ),
					'permission_callback' => array( $this, 'can_open' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/invitations/(?P<id>\d+)/revoke',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'revoke' ),
					'permission_callback' => array( $this, 'can_open' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/import',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_csv' ),
					'permission_callback' => array( $this, 'can_open' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/content',
			array(
				$read + array( 'callback' => array( $this, 'content' ) ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/branding',
			array(
				$read + array( 'callback' => array( $this, 'branding' ) ),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_branding' ),
					'permission_callback' => array( $this, 'can_open' ),
				),
			)
		);
	}

	/**
	 * Signed-in people who have at least one dashboard section.
	 *
	 * @return bool
	 */
	public function can_open() {
		return is_user_logged_in() && (bool) Front::scope()['nav'];
	}

	/**
	 * Home summary.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function summary( $request ) {
		$scope = Front::scope();
		if ( ! Front::allows( $scope, 'home' ) ) {
			return $this->forbidden();
		}
		return rest_ensure_response(
			FrontSummary::get(
				$scope,
				(int) $request->get_param( 'school' ),
				(string) $request->get_param( 'range' ),
				(string) $request->get_param( 'level' ),
				(int) $request->get_param( 'class' )
			)
		);
	}

	/**
	 * CSV of the current drill. The second click of Export.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export_csv( $request ) {
		$scope = Front::scope();
		if ( ! Front::allows( $scope, 'home' ) ) {
			return $this->forbidden();
		}
		$data = FrontSummary::get( $scope, (int) $request->get_param( 'school' ), (string) $request->get_param( 'range' ), (string) $request->get_param( 'level' ), (int) $request->get_param( 'class' ) );
		$lines = array( $this->csv_row( array_merge( array( __( 'Name', 'ohmylms' ) ), $data['drill']['columns'] ?? array() ) ) );
		foreach ( $data['drill']['rows'] as $row ) {
			$lines[] = $this->csv_row( array_merge( array( $row['name'] ), $row['cells'] ) );
		}
		$response = new \WP_REST_Response( implode( "\n", $lines ) );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="ohmylms-dashboard.csv"' );
		return $response;
	}

	/**
	 * People in the viewer's schools or classes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function people( $request ) {
		$scope = Front::scope();
		if ( ! Front::allows( $scope, 'people' ) ) {
			return $this->forbidden();
		}
		$role = sanitize_key( (string) $request->get_param( 'role' ) );
		$role = in_array( $role, array( 'student', 'teacher', 'guardian' ), true ) ? $role : 'student';
		$map  = array(
			'student'  => 'student',
			'teacher'  => 'teacher',
			'guardian' => 'guardian',
		);
		return rest_ensure_response(
			array(
				'role'    => $role,
				'people'  => $this->member_rows( $scope, $map[ $role ], sanitize_text_field( (string) $request->get_param( 'search' ) ) ),
				'pending' => FrontSummary::pending( $scope, $this->school_ids( $scope, (int) $request->get_param( 'school' ) ) ),
			)
		);
	}

	/**
	 * Classes the viewer may open.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function classes( $request ) {
		$scope = Front::scope();
		if ( ! Front::allows( $scope, 'classes' ) ) {
			return $this->forbidden();
		}
		$summary = FrontSummary::get( $scope, (int) $request->get_param( 'school' ), '7', 'classes', 0 );
		return rest_ensure_response( array( 'classes' => $summary['drill']['rows'] ) );
	}

	/**
	 * Three-step class create. An academic year is filled in when the school has none.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_class( $request ) {
		$scope  = Front::scope();
		$school = FrontSummary::school_in_scope( $scope, (int) $request->get_param( 'school' ) );
		if ( ! $school || ! ( $scope['owner'] || $scope['school_admin'] ) ) {
			return $this->forbidden();
		}
		try {
			$year = Service::ensure_default_year( $school );
			$made = Service::create_class(
				$school,
				array(
					'academic_year_id' => $year,
					'name'             => $request->get_param( 'name' ),
					'grade'            => $request->get_param( 'grade' ),
					'subject'          => $request->get_param( 'subject' ),
				)
			);
			$sent = $this->invite_list( $school, (int) $made['id'], (string) $request->get_param( 'teacher_email' ), 'teacher' );
			$sent += $this->invite_list( $school, (int) $made['id'], (string) $request->get_param( 'student_emails' ), 'student' );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_dashboard_class', $error->getMessage(), array( 'status' => 400 ) );
		}
		return rest_ensure_response(
			array(
				'id'          => (int) $made['id'],
				'invitations' => $sent,
			)
		);
	}

	/**
	 * Email an invitation. Dashboard invitations last 14 days and can be resent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function invite( $request ) {
		$scope  = Front::scope();
		$school = FrontSummary::school_in_scope( $scope, (int) $request->get_param( 'school' ) );
		if ( ! $school || ! ( $scope['owner'] || $scope['school_admin'] ) ) {
			return $this->forbidden();
		}
		$role = sanitize_key( (string) $request->get_param( 'role' ) );
		$role = array(
			'student' => 'student',
			'teacher' => 'teacher',
			'parent'  => 'guardian',
		)[ $role ] ?? '';
		if ( ! $role ) {
			return new \WP_Error( 'ohmylms_dashboard_role', __( 'Choose a student, teacher or parent.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		try {
			$result = Service::invite(
				$school,
				array(
					'role'       => $role,
					'email'      => $request->get_param( 'email' ),
					'class_id'   => (int) $request->get_param( 'class_id' ),
					'send_email' => true,
					'days'       => 14,
				)
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_dashboard_invite', $error->getMessage(), array( 'status' => 400 ) );
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Email a fresh invitation link and retire the previous one.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resend( $request ) {
		$school = $this->writable_school( (int) $request->get_param( 'school' ) );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		try {
			return rest_ensure_response( Service::resend_invite( $school, (int) $request['id'], 14 ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_dashboard_invite', $error->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Revoke a pending invitation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function revoke( $request ) {
		$school = $this->writable_school( (int) $request->get_param( 'school' ) );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		try {
			return rest_ensure_response( Service::revoke_invite( $school, (int) $request['id'] ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_dashboard_invite', $error->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Import students from an uploaded CSV or pasted rows.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_csv( $request ) {
		$school = $this->writable_school( (int) $request->get_param( 'school' ) );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$csv = (string) $request->get_param( 'csv' );
		$files = $request->get_file_params();
		if ( isset( $files['file']['tmp_name'] ) && is_uploaded_file( $files['file']['tmp_name'] ) ) {
			$contents = file_get_contents( $files['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The uploaded CSV is read once, then discarded.
			$csv      = is_string( $contents ) ? $contents : '';
		}
		$rows = $this->parse_people_csv( $csv );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$sent = 0;
		$skipped = array();
		foreach ( $rows as $row ) {
			try {
				Service::invite(
					$school,
					array(
						'role'       => 'student',
						'email'      => $row['email'],
						'class_id'   => $this->class_by_name( $school, $row['class'] ),
						'send_email' => true,
						'days'       => 14,
					)
				);
				++$sent;
			} catch ( \Throwable $error ) {
				$skipped[] = $row['email'] . ': ' . $error->getMessage();
			}
		}
		return rest_ensure_response(
			array(
				'sent'    => $sent,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * Courses, lessons and quizzes the viewer may edit.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function content( $request ) {
		$scope = Front::scope();
		if ( ! Front::allows( $scope, 'content' ) ) {
			return $this->forbidden();
		}
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		$type = in_array( $type, array( 'course', 'lesson', 'quiz' ), true ) ? $type : 'course';
		$post_type = array(
			'course' => OHMYLMS_COURSE_CPT,
			'lesson' => OHMYLMS_LESSON_CPT,
			'quiz'   => OHMYLMS_QUIZ_CPT,
		)[ $type ];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$status = in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'any';
		$query  = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending' ) : $status,
				's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'posts_per_page' => 30,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$edit = get_edit_post_link( $post->ID, 'raw' );
			$items[] = array(
				'id'     => (int) $post->ID,
				'title'  => get_the_title( $post ),
				'status' => (string) $post->post_status,
				'type'   => $type,
				'edit'   => $edit ? $edit : '',
			);
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Design system and mascot, the same options as Settings → Design system.
	 *
	 * @return \WP_REST_Response
	 */
	public function branding() {
		$scope   = Front::scope();
		$mascot  = Mascot::get();
		$presets = array();
		foreach ( DesignSystem::PRESETS as $name => $set ) {
			$values    = DesignSystem::preset_values( $name );
			$presets[] = array(
				'name'          => $name,
				'label'         => (string) $set['label'],
				'colors'        => $values['colors'],
				'button_radius' => $values['button_radius'],
				'density'       => $values['density'],
			);
		}
		$payload = array(
			'owner'   => $scope['owner'],
			'preset'  => DesignSystem::get(),
			'presets' => $presets,
			'font'    => Tokens::font( 'ohmylms_font_family' ),
			'fonts'   => Tokens::font_choices( 'ohmylms_font_family' ),
			'mascot'  => $mascot + array( 'attachment_url' => Mascot::image_url( $mascot ) ),
			'mascots' => Mascot::catalog(),
			'schools' => array(),
		);
		foreach ( $scope['schools'] as $school ) {
			if ( ! $scope['owner'] && 'school_admin' !== $school['role'] && 'owner' !== $school['role'] ) {
				continue;
			}
			$own = SchoolSettings::overrides( $school['id'] );
			$payload['schools'][] = array(
				'id'      => $school['id'],
				'name'    => $school['name'],
				'accent'  => $own['accent'] ?? '',
				'logo_id' => isset( $own['logo'] ) ? (int) $own['logo'] : 0,
				'mascot'  => isset( $own['mascot'] ) ? $own['mascot'] : null,
			);
		}
		return rest_ensure_response( $payload );
	}

	/**
	 * Save site branding when the viewer is the owner, and a school override otherwise.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_branding( $request ) {
		$scope = Front::scope();
		if ( ! Front::allows( $scope, 'settings' ) ) {
			return $this->forbidden();
		}
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		if ( $scope['owner'] && isset( $body['design'] ) ) {
			$errors = DesignSystem::validate( $body['design'] );
			if ( isset( $body['font'] ) && ( ! is_string( $body['font'] ) || ! in_array( $body['font'], Tokens::font_choices( 'ohmylms_font_family' ), true ) ) ) {
				$errors['font'] = __( 'Choose a learner font from the list.', 'ohmylms' );
			}
			if ( isset( $body['mascot'] ) ) {
				$errors = array_merge( $errors, Mascot::validate( $body['mascot'] ) );
			}
			if ( $errors ) {
				return new \WP_Error(
					'ohmylms_invalid_design',
					__( 'Some design settings are not valid.', 'ohmylms' ),
					array(
						'status' => 400,
						'fields' => $errors,
					)
				);
			}
			DesignSystem::save( $body['design'] );
			if ( isset( $body['font'] ) ) {
				update_option( 'ohmylms_font_family', $body['font'] );
			}
			if ( isset( $body['mascot'] ) ) {
				Mascot::save( $body['mascot'] );
			}
		}
		if ( ! empty( $body['school_id'] ) ) {
			$school = $this->writable_school( (int) $body['school_id'] );
			if ( is_wp_error( $school ) ) {
				return $school;
			}
			try {
				SchoolSettings::save( $school, isset( $body['school'] ) && is_array( $body['school'] ) ? $body['school'] : array() );
			} catch ( \Throwable $error ) {
				return new \WP_Error( 'ohmylms_invalid_design', $error->getMessage(), array( 'status' => 400 ) );
			}
		}
		return $this->branding();
	}

	/**
	 * Standard forbidden response for this controller.
	 *
	 * @return \WP_Error
	 */
	private function forbidden() {
		return new \WP_Error( 'ohmylms_dashboard_forbidden', __( 'You cannot open that part of the dashboard.', 'ohmylms' ), array( 'status' => 403 ) );
	}

	/**
	 * A school the viewer may change, or an error.
	 *
	 * @param int $school Requested school.
	 * @return int|\WP_Error
	 */
	private function writable_school( $school ) {
		$scope  = Front::scope();
		$school = FrontSummary::school_in_scope( $scope, $school );
		if ( ! $school || ! ( $scope['owner'] || $scope['school_admin'] ) ) {
			return $this->forbidden();
		}
		return $school;
	}

	/**
	 * School IDs the request is allowed to read.
	 *
	 * @param array $scope  Scope.
	 * @param int   $school Requested school.
	 * @return int[]
	 */
	private function school_ids( array $scope, $school ) {
		$school = FrontSummary::school_in_scope( $scope, $school );
		return $school ? array( $school ) : $scope['school_ids'];
	}

	/**
	 * People rows for one role.
	 *
	 * @param array  $scope  Scope.
	 * @param string $role   Membership or invitation role.
	 * @param string $search Search.
	 * @return array
	 */
	private function member_rows( array $scope, $role, $search ) {
		global $wpdb;
		if ( ! $scope['schema'] || ! $scope['school_ids'] ) {
			return array();
		}
		$ids  = $scope['school_ids'];
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		if ( 'guardian' === $role ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.id,i.email AS name,i.email,'' AS school FROM %i i WHERE i.role='guardian' AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.school_id IN ($in) AND i.email LIKE %s ORDER BY i.id DESC LIMIT 50", array_merge( array( \OhMyLMS\Schools\Schema::table( 'school_invitations' ) ), $ids, array( $like ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name AS name,u.user_email AS email,s.name AS school FROM %i m JOIN {$wpdb->users} u ON u.ID=m.user_id JOIN %i s ON s.id=m.school_id WHERE m.status='active' AND m.role=%s AND m.school_id IN ($in) AND (u.display_name LIKE %s OR u.user_email LIKE %s) ORDER BY u.display_name LIMIT 50", array_merge( array( \OhMyLMS\Schools\Schema::table( 'school_memberships' ), \OhMyLMS\Schools\Schema::table( 'schools' ), $role ), $ids, array( $like, $like ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'     => (int) ( $row['id'] ?? 0 ),
				'name'   => (string) ( $row['name'] ?? '' ),
				'email'  => (string) ( $row['email'] ?? '' ),
				'school' => (string) ( $row['school'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Send one invitation per address in a list.
	 *
	 * @param int    $school School.
	 * @param int    $class  Class.
	 * @param string $list   Emails separated by commas or newlines.
	 * @param string $role   Invitation role.
	 * @return int
	 */
	private function invite_list( $school, $class, $list, $role ) {
		$sent = 0;
		foreach ( preg_split( '/[\s,;]+/', $list ) as $email ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				continue;
			}
			Service::invite(
				$school,
				array(
					'role'       => $role,
					'email'      => $email,
					'class_id'   => $class,
					'send_email' => true,
					'days'       => 14,
				)
			);
			++$sent;
		}
		return $sent;
	}

	/**
	 * Active class ID matching a name in this school.
	 *
	 * @param int    $school School.
	 * @param string $name   Class name.
	 * @return int
	 */
	private function class_by_name( $school, $name ) {
		global $wpdb;
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND name=%s AND status=%s LIMIT 1', \OhMyLMS\Schools\Schema::table( 'classes' ), $school, $name, 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Parse a people CSV into email and class rows.
	 *
	 * @param string $csv CSV text.
	 * @return array|\WP_Error
	 */
	private function parse_people_csv( $csv ) {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory CSV parse.
		fwrite( $stream, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $stream );
		$header = fgetcsv( $stream );
		if ( ! is_array( $header ) ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'ohmylms_dashboard_csv', __( 'The CSV needs a header row.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$map = array();
		foreach ( $header as $index => $label ) {
			$map[ sanitize_key( (string) $label ) ] = $index;
		}
		if ( ! isset( $map['email'] ) ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'ohmylms_dashboard_csv', __( 'The CSV needs an email column.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$rows = array();
		while ( ( $values = fgetcsv( $stream ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$email = sanitize_email( $values[ $map['email'] ] ?? '' );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$rows[] = array(
				'email' => $email,
				'class' => sanitize_text_field( $values[ $map['class'] ?? -1 ] ?? '' ),
			);
			if ( count( $rows ) > 200 ) {
				break;
			}
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! $rows ) {
			return new \WP_Error( 'ohmylms_dashboard_csv', __( 'No email addresses were found.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return $rows;
	}

	/**
	 * One quoted CSV line.
	 *
	 * @param array $cells Cells.
	 * @return string
	 */
	private function csv_row( array $cells ) {
		$out = array();
		foreach ( $cells as $cell ) {
			$out[] = '"' . str_replace( '"', '""', (string) $cell ) . '"';
		}
		return implode( ',', $out );
	}
}
