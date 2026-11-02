<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for issued certificates.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Certificates\Download;
use OhMyLMS\Certificates\Issuer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Issued certificates under `ohmylms/v1/issued-certificates` (docs/api/certificates.md).
 *
 * Administration routes require {@see Issuer::CAPABILITY}. `GET /issued-certificates/verify/{code}` is
 * public and returns only what the certificate itself shows.
 */
class IssuedCertificatesController extends RestController {

	/**
	 * Learners returned by one learner search.
	 */
	const MAX_LEARNERS = 20;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'issued-certificates';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the issued certificate.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
					'args'                => array(
						'user_id'        => array(
							'description' => __( 'Learner who receives the certificate.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
						'course_id'      => array(
							'description' => __( 'Course the certificate is for.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
						'certificate_id' => array(
							'description' => __( 'Template; 0 or omitted for the course template.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 0,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/learners',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_learners' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
					'args'                => array(
						'search' => array(
							'description' => __( 'Name, e-mail or user name.', 'ohmylms' ),
							'type'        => 'string',
							'maxLength'   => 100,
							'default'     => '',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/verify/(?P<code>[A-Za-z0-9-]{1,40})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'verify' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item,
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
				),
			)
		);
		foreach ( array( 'revoke', 'reissue' ) as $action ) {
			register_rest_route(
				$this->namespace,
				$item . '/' . $action,
				array(
					'args' => array( 'id' => $id ),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action . '_item' ),
						'permission_callback' => array( $this, 'manage_permissions_check' ),
					),
				)
			);
		}
	}

	/**
	 * Administration permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function manage_permissions_check( $request ) {
		if ( ! current_user_can( Issuer::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_issued_certificate_forbidden', __( 'Sorry, you are not allowed to manage issued certificates.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * List issued certificates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = (int) $request['page'];
		$per_page = (int) $request['per_page'];
		$result   = Issuer::query(
			array(
				'search'      => (string) $request['search'],
				'status'      => (string) $request['status'],
				'source'      => (string) $request['source'],
				'course'      => (int) $request['course'],
				'certificate' => (int) $request['certificate'],
				'user'        => (int) $request['user'],
				'orderby'     => (string) $request['orderby'],
				'order'       => (string) $request['order'],
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
		$pages    = (int) ceil( $result['total'] / $per_page );
		if ( $page > 1 && $page > $pages ) {
			return new WP_Error( 'ohmylms_issued_certificate_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$templates = $this->template_names( array_column( $result['items'], 'certificate_id' ) );
		$response  = rest_ensure_response(
			array_map(
				function ( $record ) use ( $templates ) {
					return $this->view( $record, $templates );
				},
				$result['items']
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Read one issued certificate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$record = Issuer::get( (int) $request['id'] );
		if ( ! $record ) {
			return $this->not_found();
		}

		return $this->respond( $record );
	}

	/**
	 * Issue a certificate by hand.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$known = $this->reject_unknown_fields( $request, array( 'user_id', 'course_id', 'certificate_id' ) );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$record = Issuer::issue( (int) $request['user_id'], (int) $request['course_id'], (int) $request['certificate_id'] );

		return is_wp_error( $record ) ? $record : $this->respond( $record, 201 );
	}

	/**
	 * Revoke a certificate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_item( $request ) {
		$known = $this->reject_unknown_fields( $request, array() );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$record = Issuer::revoke( (int) $request['id'] );

		return is_wp_error( $record ) ? $record : $this->respond( $record );
	}

	/**
	 * Re-issue a certificate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reissue_item( $request ) {
		$known = $this->reject_unknown_fields( $request, array() );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$record = Issuer::reissue( (int) $request['id'] );

		return is_wp_error( $record ) ? $record : $this->respond( $record, 201 );
	}

	/**
	 * Search learners for the manual issue form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_learners( $request ) {
		$search = trim( (string) $request['search'] );
		$args   = array(
			'number'      => self::MAX_LEARNERS,
			'orderby'     => 'display_name',
			'order'       => 'ASC',
			'fields'      => array( 'ID', 'display_name', 'user_email' ),
			'count_total' => false,
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}
		$users    = get_users( $args );
		$response = rest_ensure_response(
			array_map(
				static function ( $user ) {
					return array(
						'id'    => (int) $user->ID,
						'name'  => Issuer::student_name( (int) $user->ID ),
						'email' => (string) $user->user_email,
					);
				},
				$users
			)
		);
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Public verification of a code.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify( $request ) {
		$record = Issuer::find_by_code( (string) $request['code'] );
		if ( Download::throttled( ! $record ) ) {
			return new WP_Error( 'ohmylms_certificate_verify_throttled', __( 'Too many codes were checked from your network. Try again in a few minutes.', 'ohmylms' ), array( 'status' => 429 ) );
		}
		if ( ! $record ) {
			return new WP_Error( 'ohmylms_certificate_code_not_found', __( 'No certificate has this code.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$data             = Download::public_view( $record );
		$data['valid']    = 'issued' === $record['status'];
		$data['page_url'] = Download::verify_url( $record['code'] );
		$response         = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'X-Robots-Tag', 'noindex' );

		return $response;
	}

	/**
	 * Collection arguments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$id = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'minimum'     => 0,
				'default'     => 0,
			);
		};

		return array(
			'page'        => array(
				'description' => __( 'Current page of the collection.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
			),
			'per_page'    => array(
				'description' => __( 'Maximum number of items per page.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 100,
				'default'     => 20,
			),
			'search'      => array(
				'description' => __( 'Code, learner name or e-mail, or course title.', 'ohmylms' ),
				'type'        => 'string',
				'maxLength'   => 100,
				'default'     => '',
			),
			'status'      => array(
				'type'    => 'string',
				'enum'    => array( 'all', 'issued', 'revoked' ),
				'default' => 'all',
			),
			'source'      => array(
				'type'    => 'string',
				'enum'    => array( '', 'completion', 'manual', 'reissue' ),
				'default' => '',
			),
			'course'      => $id( __( 'Only certificates of this course.', 'ohmylms' ) ),
			'certificate' => $id( __( 'Only certificates issued from this template.', 'ohmylms' ) ),
			'user'        => $id( __( 'Only certificates of this learner.', 'ohmylms' ) ),
			'orderby'     => array(
				'type'    => 'string',
				'enum'    => array( 'issued_at', 'id', 'student_name', 'course_title' ),
				'default' => 'issued_at',
			),
			'order'       => array(
				'type'    => 'string',
				'enum'    => array( 'asc', 'desc' ),
				'default' => 'desc',
			),
		);
	}

	/**
	 * Response for one record.
	 *
	 * @param array $record Issued certificate.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private function respond( array $record, $status = 200 ) {
		$response = rest_ensure_response( $this->view( $record, $this->template_names( array( $record['certificate_id'] ) ) ) );
		$response->set_status( $status );
		$response->header( 'Cache-Control', 'private, no-store' );
		if ( 201 === $status ) {
			$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $record['id'] ) ) );
		}

		return $response;
	}

	/**
	 * Admin view of a record.
	 *
	 * @param array             $record    Issued certificate.
	 * @param array<int,string> $templates Template names by ID.
	 * @return array
	 */
	private function view( array $record, array $templates ) {
		$user = get_userdata( $record['user_id'] );
		$data = array(
			'id'           => $record['id'],
			'code'         => $record['code'],
			'status'       => $record['status'],
			'source'       => $record['source'],
			'student_name' => $record['student_name'],
			'learner'      => array(
				'id'     => $record['user_id'],
				'exists' => (bool) $user,
				'email'  => $user ? (string) $user->user_email : '',
			),
			'course'       => array(
				'id'     => $record['course_id'],
				'title'  => $record['course_title'],
				'exists' => OHMYLMS_COURSE_CPT === get_post_type( $record['course_id'] ),
			),
			'template'     => array(
				'id'   => $record['certificate_id'],
				'name' => $templates[ $record['certificate_id'] ] ?? '',
			),
			'completed_at' => $this->iso( $record['completed_at'] ),
			'issued_at'    => $this->iso( $record['issued_at'] ),
			'revoked_at'   => $this->iso( $record['revoked_at'] ),
			'issued_by'    => $record['issued_by'],
			'revoked_by'   => $record['revoked_by'],
			'replaces'     => $record['replaces'],
			'links'        => array(
				'pdf'    => 'issued' === $record['status'] ? Download::url( $record['code'], 'pdf' ) : '',
				'print'  => 'issued' === $record['status'] ? Download::url( $record['code'], 'print' ) : '',
				'verify' => Download::verify_url( $record['code'] ),
			),
		);

		/**
		 * Filters one issued certificate in the REST API.
		 *
		 * @param array $data   Response data.
		 * @param array $record The issued certificate.
		 */
		return apply_filters( 'ohmylms_rest_prepare_issued_certificate', $data, $record );
	}

	/**
	 * Template names by ID (one query).
	 *
	 * @param int[] $ids Template IDs.
	 * @return array<int,string>
	 */
	private function template_names( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, false );
		$names = array();
		foreach ( $ids as $id ) {
			if ( OHMYLMS_CERTIFICATE_CPT === get_post_type( $id ) ) {
				$names[ $id ] = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
			}
		}

		return $names;
	}

	/**
	 * Refuse body fields the route does not accept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string[]        $allowed Accepted fields.
	 * @return true|WP_Error
	 */
	private function reject_unknown_fields( WP_REST_Request $request, array $allowed ) {
		$json    = $request->get_json_params();
		$body    = array_merge( (array) $request->get_body_params(), is_array( $json ) ? $json : array() );
		$unknown = array_values( array_diff( array_map( 'strval', array_keys( $body ) ), $allowed ) );

		return $unknown ? new WP_Error(
			'ohmylms_issued_certificate_unknown_field',
			__( 'Unknown field(s) in the request.', 'ohmylms' ),
			array(
				'status' => 400,
				'fields' => $unknown,
			)
		) : true;
	}

	/**
	 * Not found error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'ohmylms_issued_certificate_not_found', __( 'Issued certificate not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * RFC 3339 UTC date or null.
	 *
	 * @param string|null $gmt MySQL GMT date.
	 * @return string|null
	 */
	private function iso( $gmt ) {
		return $gmt ? gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $gmt . ' UTC' ) ) : null;
	}
}
