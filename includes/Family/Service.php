<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Home-learning family links: managed children, private invitations and revocation.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Family;

use OhMyLMS\Bundles\Follows;
use OhMyLMS\Schools\Service as SchoolService;
use OhMyLMS\Student\LearnerCatalog;
use OhMyLMS\Services\EmailVerificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Every call authorizes the signed-in actor afresh. Identifiers from requests select
 * a candidate only. Raw tokens are returned once to the caller and never stored,
 * logged or audited.
 */
final class Service {

	const MAX_CHILDREN = 20;
	const MAX_PENDING  = 20;
	const TTL          = 2 * DAY_IN_SECONDS;
	const MANAGED_META = '_ohmylms_family_managed_by';

	/**
	 * UTC timestamp.
	 *
	 * @return string
	 */
	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Fail with a safe, client-visible message.
	 *
	 * @param mixed  $value Condition that must be truthy.
	 * @param string $message Message for failures.
	 * @param int    $code HTTP-style code.
	 * @throws \RuntimeException When the condition fails.
	 */
	private static function need( $value, $message, $code = 403 ) {
		if ( ! $value ) {
			throw new \RuntimeException( $message, $code ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message escaped by output code.
		}
	}

	/**
	 * Record an audit event; never stores tokens or passwords.
	 *
	 * @param string $action Event name.
	 * @param int    $link_id Link ID.
	 * @param int    $actor_id Actor override, e.g. a signed-out setup flow.
	 */
	private static function audit( $action, $link_id = 0, $actor_id = 0 ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Append-only audit table.
			Schema::table( 'audit' ),
			array(
				'actor_id'   => $actor_id ? $actor_id : get_current_user_id(),
				'action'     => $action,
				'link_id'    => $link_id,
				'created_at' => self::now(),
			)
		);
	}

	/**
	 * Whether a user holds the parent role with a verified email.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function guardian_eligible( $user_id ) {
		$user = get_userdata( (int) $user_id );
		return $user && in_array( 'ohmylms_parent', $user->roles, true ) && EmailVerificationService::is_verified( $user->ID );
	}

	/**
	 * Require the feature and an eligible signed-in guardian.
	 *
	 * @return int Guardian ID.
	 */
	private static function actor_guardian() {
		self::need( Schema::enabled() && is_user_logged_in(), __( 'Family accounts are not available.', 'ohmylms' ) );
		self::need( self::guardian_eligible( get_current_user_id() ), __( 'Use a verified parent account.', 'ohmylms' ) );
		return get_current_user_id();
	}

	/**
	 * Active home-learning children of the signed-in guardian, bounded to 100.
	 *
	 * @return array
	 */
	public static function children() {
		if ( ! Schema::enabled() || ! self::guardian_eligible( get_current_user_id() ) ) {
			return array();
		}
		global $wpdb;
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization read; must not be cached.
			$wpdb->prepare(
				"SELECT l.id AS link_id,l.learner_user_id,u.display_name FROM %i l JOIN {$wpdb->users} u ON u.ID=l.learner_user_id WHERE l.guardian_user_id=%d AND l.scope=%s AND l.status='active' ORDER BY u.display_name LIMIT 100",
				Schema::table( 'links' ),
				get_current_user_id(),
				Schema::SCOPE
			),
			ARRAY_A
		);
	}

	/**
	 * One active child of the signed-in guardian, or null.
	 *
	 * @param int $learner_id Requested learner ID (a candidate, not a grant).
	 * @return array|null
	 */
	public static function child( $learner_id ) {
		foreach ( self::children() as $row ) {
			if ( (int) $row['learner_user_id'] === (int) $learner_id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Create a managed child and an active home link. The parent never sees a password.
	 *
	 * @param string $name Child display name.
	 * @param string $grade Optional grade label.
	 * @return array Child ID and link ID.
	 */
	public static function create_child( $name, $grade = '' ) {
		$guardian = self::actor_guardian();
		$name     = SchoolService::text( $name, 80 );
		self::need( '' !== $name, __( 'Enter the child’s name.', 'ohmylms' ), 400 );
		global $wpdb;
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh quota read.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i l JOIN {$wpdb->usermeta} m ON m.user_id=l.learner_user_id AND m.meta_key=%s AND m.meta_value=%d WHERE l.guardian_user_id=%d AND l.status<>'revoked'", Schema::table( 'links' ), self::MANAGED_META, $guardian, $guardian )
		);
		self::need( self::MAX_CHILDREN > $count, __( 'You have reached the limit of managed children.', 'ohmylms' ), 409 );
		return SchoolService::transaction(
			static function () use ( $guardian, $name, $grade, $wpdb ) {
				$id = wp_insert_user(
					array(
						'user_login'   => 'child-' . strtolower( wp_generate_password( 14, false ) ),
						'user_pass'    => wp_generate_password( 40 ),
						'display_name' => $name,
						'first_name'   => $name,
						'role'         => function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber',
					)
				);
				if ( is_wp_error( $id ) ) {
					throw new \RuntimeException( 'Unable to create this child account.', 400 );
				}
				update_user_meta( $id, self::MANAGED_META, $guardian );
				update_user_meta( $id, '_ohmylms_family_grade', SchoolService::text( $grade, 50 ) );
				$now = self::now();
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic link creation.
					Schema::table( 'links' ),
					array(
						'guardian_user_id' => $guardian,
						'learner_user_id'  => $id,
						'scope'            => Schema::SCOPE,
						'status'           => 'active',
						'approved_by'      => $guardian,
						'approval_method'  => 'managed_create',
						'created_at'       => $now,
						'updated_at'       => $now,
					)
				);
				$link = (int) $wpdb->insert_id;
				self::need( $link, 'Unable to link this child.', 409 );
				self::audit( 'child_created', $link );
				return array(
					'child_id' => (int) $id,
					'link_id'  => $link,
				);
			}
		);
	}

	/**
	 * Insert an invitation and return its one-time raw token.
	 *
	 * @param string $kind link|setup.
	 * @param int    $learner_id Target learner for setup tokens.
	 * @param string $email Recipient email for link tokens.
	 * @return array Invitation ID and raw token.
	 */
	private static function issue( $kind, $learner_id, $email ) {
		global $wpdb;
		$token = bin2hex( random_bytes( 32 ) );
		$ok    = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Invitation write.
			Schema::table( 'invitations' ),
			array(
				'token_hash'      => hash( 'sha256', $token ),
				'kind'            => $kind,
				'issuer_id'       => get_current_user_id(),
				'learner_user_id' => $learner_id,
				'email'           => $email,
				'scope'           => Schema::SCOPE,
				'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
				'created_at'      => self::now(),
			)
		);
		self::need( $ok, __( 'Unable to create this invitation.', 'ohmylms' ), 409 );
		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		);
	}

	/**
	 * Credential-setup link for a child this guardian manages; older setup links are revoked.
	 *
	 * @param int $learner_id Child user ID.
	 * @return array Invitation ID and raw token.
	 */
	public static function issue_setup( $learner_id ) {
		self::actor_guardian();
		return SchoolService::transaction(
			static function () use ( $learner_id ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize replacement and consumption for this account.
				$person = $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID=%d FOR UPDATE', $wpdb->users, $learner_id ) );
				clean_user_cache( $learner_id );
				wp_cache_delete( $learner_id, 'user_meta' );
				self::actor_guardian();
				self::need( $person && self::is_managed( $learner_id ), __( 'You cannot manage this learner.', 'ohmylms' ) );
				$child = self::child( $learner_id );
				$done  = $wpdb->query( $wpdb->prepare( "UPDATE %i SET revoked_at=%s WHERE kind='setup' AND learner_user_id=%d AND consumed_at IS NULL AND revoked_at IS NULL", Schema::table( 'invitations' ), self::now(), $learner_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Revoke superseded links under the account lock.
				self::need( false !== $done, __( 'Unable to replace this setup link.', 'ohmylms' ), 409 );
				$issued = self::issue( 'setup', (int) $learner_id, '' );
				self::audit( 'setup_issued', (int) $child['link_id'] );
				return $issued;
			}
		);
	}

	/**
	 * Private invitation for an existing learner, matched by recipient email at acceptance.
	 *
	 * @param string $email Learner's email.
	 * @return array Invitation ID and raw token.
	 */
	public static function invite_existing( $email ) {
		$guardian = self::actor_guardian();
		$email    = sanitize_email( $email );
		self::need( is_email( $email ), __( 'Enter a valid email address.', 'ohmylms' ), 400 );
		self::need( 0 !== strcasecmp( $email, get_userdata( $guardian )->user_email ), __( 'Use the child’s own email address.', 'ohmylms' ), 400 );
		global $wpdb;
		$pending = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh quota read.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE kind='link' AND issuer_id=%d AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s", Schema::table( 'invitations' ), $guardian, self::now() )
		);
		self::need( self::MAX_PENDING > $pending, __( 'Revoke unused invitations before creating more.', 'ohmylms' ), 409 );
		$issued = self::issue( 'link', 0, $email );
		self::audit( 'invitation_created', 0 );
		return $issued;
	}

	/**
	 * Accept a link invitation as the signed-in learner.
	 *
	 * @param string $token Raw token.
	 * @return array Link ID.
	 */
	public static function accept_link( $token ) {
		self::need( Schema::enabled() && is_user_logged_in(), __( 'Sign in to accept this invitation.', 'ohmylms' ) );
		self::need( is_string( $token ) && preg_match( '/^[a-f0-9]{64}$/', $token ), __( 'Invalid invitation.', 'ohmylms' ), 400 );
		global $wpdb;
		$learner = wp_get_current_user();
		return SchoolService::transaction(
			static function () use ( $wpdb, $token, $learner ) {
				$invite = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token_hash=%s FOR UPDATE', Schema::table( 'invitations' ), hash( 'sha256', $token ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Row lock for single-use acceptance.
				self::need( $invite && 'link' === $invite['kind'] && ! $invite['consumed_at'] && ! $invite['revoked_at'] && $invite['expires_at'] > self::now(), 'This invitation has expired or has already been used.', 400 );
				$guardian = (int) $invite['issuer_id'];
				self::need( $guardian !== $learner->ID && 0 === strcasecmp( $learner->user_email, $invite['email'] ) && EmailVerificationService::is_verified( $learner->ID ), 'This invitation is not for this account.' );
				self::need( self::guardian_eligible( $guardian ), 'The invitation issuer no longer has permission.' );
				$now  = self::now();
				$link = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE guardian_user_id=%d AND learner_user_id=%d AND scope=%s FOR UPDATE', Schema::table( 'links' ), $guardian, $learner->ID, Schema::SCOPE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Row lock.
				if ( $link ) {
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reactivate the unique pair.
						Schema::table( 'links' ),
						array(
							'status'          => 'active',
							'approved_by'     => $learner->ID,
							'approval_method' => 'learner_invitation',
							'revision'        => (int) $link['revision'] + 1,
							'updated_at'      => $now,
							'revoked_at'      => null,
						),
						array( 'id' => $link['id'] )
					);
					$link_id = (int) $link['id'];
				} else {
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic link creation.
						Schema::table( 'links' ),
						array(
							'guardian_user_id' => $guardian,
							'learner_user_id'  => $learner->ID,
							'scope'            => Schema::SCOPE,
							'status'           => 'active',
							'approved_by'      => $learner->ID,
							'approval_method'  => 'learner_invitation',
							'created_at'       => $now,
							'updated_at'       => $now,
						)
					);
					$link_id = (int) $wpdb->insert_id;
				}
				self::need( $link_id, 'Unable to link this account.', 409 );
				$wpdb->update( Schema::table( 'invitations' ), array( 'consumed_at' => $now ), array( 'id' => $invite['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-use consume.
				self::audit( 'invitation_accepted', $link_id );
				return array( 'link_id' => $link_id );
			}
		);
	}

	/**
	 * Accept a credential-setup link without a session and set the child's password.
	 *
	 * @param string $token Raw token.
	 * @param string $password New password, 12-128 characters.
	 * @return array Login name to show once.
	 */
	public static function accept_setup( $token, $password ) {
		self::need( Schema::enabled(), __( 'Family accounts are not available.', 'ohmylms' ) );
		self::need( is_string( $token ) && preg_match( '/^[a-f0-9]{64}$/', $token ), __( 'Invalid invitation.', 'ohmylms' ), 400 );
		self::need( is_string( $password ) && strlen( $password ) >= 12 && strlen( $password ) <= 128, __( 'Choose a password between 12 and 128 characters.', 'ohmylms' ), 400 );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate lookup stays outside the transaction to avoid a stale authorization snapshot.
		$candidate = (int) $wpdb->get_var( $wpdb->prepare( "SELECT learner_user_id FROM %i WHERE token_hash=%s AND kind='setup'", Schema::table( 'invitations' ), hash( 'sha256', $token ) ) );
		return SchoolService::transaction(
			static function () use ( $wpdb, $token, $password, $candidate ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Same account-first lock order as replacement.
				$person = $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID=%d FOR UPDATE', $wpdb->users, $candidate ) );
				$invite = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token_hash=%s FOR UPDATE', Schema::table( 'invitations' ), hash( 'sha256', $token ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Row lock for single-use acceptance.
				self::need( $person && $invite && $candidate === (int) $invite['learner_user_id'] && 'setup' === $invite['kind'] && ! $invite['consumed_at'] && ! $invite['revoked_at'] && $invite['expires_at'] > self::now(), 'This setup link has expired or has already been used.', 400 );
				$learner  = (int) $invite['learner_user_id'];
				$guardian = (int) $invite['issuer_id'];
				clean_user_cache( $learner );
				wp_cache_delete( $learner, 'user_meta' );
				$link = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM %i WHERE guardian_user_id=%d AND learner_user_id=%d AND scope=%s AND status='active'", Schema::table( 'links' ), $guardian, $learner, Schema::SCOPE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization read.
				self::need( $link && self::guardian_eligible( $guardian ) && self::ordinary_learner( $learner ) && (int) get_user_meta( $learner, self::MANAGED_META, true ) === $guardian, 'The invitation issuer no longer has permission.' );
				wp_set_password( $password, $learner );
				$wpdb->update( Schema::table( 'invitations' ), array( 'consumed_at' => self::now() ), array( 'id' => $invite['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-use consume.
				self::audit( 'setup_completed', (int) $link['id'], $learner );
				return array( 'login' => get_userdata( $learner )->user_login );
			}
		);
	}

	/**
	 * Revoke a link; either the guardian or the learner may do so. Takes effect on the next request.
	 *
	 * @param int $link_id Link ID.
	 */
	public static function revoke_link( $link_id ) {
		self::need( Schema::enabled() && is_user_logged_in(), __( 'Family accounts are not available.', 'ohmylms' ) );
		global $wpdb;
		$link = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'links' ), $link_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization read.
		$me   = get_current_user_id();
		self::need( $link && in_array( $me, array( (int) $link['guardian_user_id'], (int) $link['learner_user_id'] ), true ), __( 'You cannot change this link.', 'ohmylms' ) );
		if ( 'revoked' === $link['status'] ) {
			return;
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Revocation.
			Schema::table( 'links' ),
			array(
				'status'     => 'revoked',
				'revision'   => (int) $link['revision'] + 1,
				'updated_at' => self::now(),
				'revoked_at' => self::now(),
			),
			array( 'id' => $link['id'] )
		);
		self::audit( 'link_revoked', (int) $link['id'] );
	}

	/**
	 * Revoke an unused invitation issued by the signed-in guardian.
	 *
	 * @param int $invitation_id Invitation ID.
	 */
	public static function revoke_invitation( $invitation_id ) {
		self::need( Schema::enabled() && is_user_logged_in(), __( 'Family accounts are not available.', 'ohmylms' ) );
		global $wpdb;
		$done = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET revoked_at=%s WHERE id=%d AND issuer_id=%d AND consumed_at IS NULL AND revoked_at IS NULL', Schema::table( 'invitations' ), self::now(), $invitation_id, get_current_user_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Issuer-scoped revoke.
		self::need( $done, __( 'You cannot change this invitation.', 'ohmylms' ) );
		self::audit( 'invitation_revoked', 0 );
	}

	/**
	 * Pending invitations issued by the signed-in guardian; hashes are never returned.
	 *
	 * @return array
	 */
	public static function invitations() {
		if ( ! Schema::enabled() || ! self::guardian_eligible( get_current_user_id() ) ) {
			return array();
		}
		global $wpdb;
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization read.
			$wpdb->prepare( "SELECT id,email,expires_at FROM %i WHERE kind='link' AND issuer_id=%d AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s ORDER BY id DESC LIMIT 50", Schema::table( 'invitations' ), get_current_user_id(), self::now() ),
			ARRAY_A
		);
	}

	/**
	 * Active guardians the signed-in learner is linked to.
	 *
	 * @return array
	 */
	public static function guardians() {
		if ( ! Schema::enabled() || ! is_user_logged_in() ) {
			return array();
		}
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization read.
			$wpdb->prepare( "SELECT l.id AS link_id,l.guardian_user_id,u.display_name FROM %i l JOIN {$wpdb->users} u ON u.ID=l.guardian_user_id WHERE l.learner_user_id=%d AND l.scope=%s AND l.status='active' ORDER BY u.display_name LIMIT 50", Schema::table( 'links' ), get_current_user_id(), Schema::SCOPE ),
			ARRAY_A
		);
		return array_values( array_filter( $rows, static fn( $row ) => self::guardian_eligible( $row['guardian_user_id'] ) ) );
	}

	/**
	 * What a valid link invitation would do for the signed-in recipient, without consuming it.
	 *
	 * @param string $token Raw token.
	 * @return array|null Guardian name, or null for anything not addressed to this account.
	 */
	public static function preview( $token ) {
		if ( ! Schema::enabled() || ! is_user_logged_in() || ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}
		global $wpdb;
		$invite = $wpdb->get_row( $wpdb->prepare( "SELECT issuer_id,email FROM %i WHERE token_hash=%s AND kind='link' AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s", Schema::table( 'invitations' ), hash( 'sha256', $token ), self::now() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read.
		$me     = wp_get_current_user();
		if ( ! $invite || (int) $invite['issuer_id'] === $me->ID || 0 !== strcasecmp( $me->user_email, $invite['email'] ) || ! self::guardian_eligible( $invite['issuer_id'] ) ) {
			return null;
		}
		return array( 'guardian' => get_userdata( (int) $invite['issuer_id'] )->display_name );
	}

	/**
	 * Decline a link invitation addressed to the signed-in account.
	 *
	 * @param string $token Raw token.
	 */
	public static function decline( $token ) {
		self::need( self::preview( $token ), __( 'This invitation is not for this account.', 'ohmylms' ) );
		global $wpdb;
		$wpdb->update( Schema::table( 'invitations' ), array( 'revoked_at' => self::now() ), array( 'token_hash' => hash( 'sha256', $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Recipient decline.
		self::audit( 'invitation_declined', 0 );
	}

	/**
	 * Whether the signed-in guardian manages this child's account (not an invited learner).
	 *
	 * @param int $learner_id Child user ID.
	 * @return bool
	 */
	public static function is_managed( $learner_id ) {
		return self::ordinary_learner( $learner_id ) && null !== self::child( $learner_id ) && (int) get_user_meta( $learner_id, self::MANAGED_META, true ) === get_current_user_id();
	}

	/**
	 * A previously managed account must not become a path into a staff account.
	 *
	 * @param int $learner_id Learner ID.
	 * @return bool
	 */
	private static function ordinary_learner( $learner_id ) {
		$user = get_userdata( $learner_id );
		return $user && ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'edit_users' ) && ! user_can( $user, 'edit_posts' )
			&& ! array_intersect( array( 'administrator', 'ohmylms_school_admin', 'ohmylms_teacher', 'ohmylms_parent' ), $user->roles );
	}

	/**
	 * Current grade label and followed collection IDs for a managed child.
	 *
	 * @param int $learner_id Child user ID.
	 * @return array
	 */
	public static function choices( $learner_id ) {
		if ( ! self::is_managed( $learner_id ) ) {
			return array(
				'grade'    => '',
				'followed' => array(),
			);
		}
		return array(
			'grade'    => (string) get_user_meta( $learner_id, '_ohmylms_family_grade', true ),
			'followed' => array_map( 'intval', array_column( Follows::followed( (int) $learner_id ), 'id' ) ),
		);
	}

	/**
	 * Save a managed child's grade and followed collections. Following lists items on the
	 * child's My learning page only; it grants no access and enrols nobody.
	 *
	 * @param int    $learner_id Child user ID.
	 * @param string $grade Grade label.
	 * @param array  $bundle_ids Chosen collection IDs, restricted to the public catalog.
	 */
	public static function set_choices( $learner_id, $grade, array $bundle_ids ) {
		self::actor_guardian();
		self::need( self::is_managed( $learner_id ), __( 'You cannot manage this learner.', 'ohmylms' ) );
		$allowed = array_map( 'intval', array_column( LearnerCatalog::bundles(), 'id' ) );
		$wanted  = array_values( array_intersect( array_map( 'intval', $bundle_ids ), $allowed ) );
		$current = array_map( 'intval', array_column( Follows::followed( (int) $learner_id ), 'id' ) );
		foreach ( array_diff( $wanted, $current ) as $bundle_id ) {
			$done = Follows::follow( (int) $learner_id, $bundle_id );
			self::need( true === $done, __( 'Unable to save every choice. Remove some collections and try again.', 'ohmylms' ), 409 );
		}
		foreach ( array_diff( $current, $wanted ) as $bundle_id ) {
			Follows::unfollow( (int) $learner_id, $bundle_id );
		}
		update_user_meta( $learner_id, '_ohmylms_family_grade', SchoolService::text( $grade, 50 ) );
		$child = self::child( $learner_id );
		self::audit( 'choices_updated', (int) $child['link_id'] );
	}
}
