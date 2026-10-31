<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 mapping.
/**
 * Versioned figure placements, review gates, and learner projections.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ExamImport;

use OhMyLMS\Assessment\QuestionSnapshot;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\Submission;

defined( 'ABSPATH' ) || exit;

/** Frozen settings own selection and specs; URLs are generated at delivery time. */
final class FigureContent {

	/**
	 * What learners see: the original crop, the accepted reconstruction instead of it (the original
	 * stays the fallback), or the reconstruction with the original beside it.
	 */
	const SELECTIONS = array( 'original', 'jsxgraph', 'both' );

	/** Review states, sources and display choices of a JSXGraph proposal (review drafts only). */
	const PROPOSAL_STATUSES = array( 'proposed', 'accepted', 'rejected' );
	const PROPOSAL_ORIGINS  = array( 'generated', 'authored', 'edited' );
	const PROPOSAL_DISPLAYS = array( 'replace', 'accompany' );

	/**
	 * Validate a bounded placement list, including unapproved extraction candidates.
	 *
	 * @param mixed $figures Placements.
	 * @param array $parts Allowed part IDs.
	 * @param int   $pages Maximum source page.
	 * @return array|\WP_Error Canonical list.
	 */
	public static function validate( $figures, array $parts, $pages = 500 ) {
		if ( ! is_array( $figures ) || count( $figures ) > 20 || array_values( $figures ) !== $figures ) {
			return self::error();
		}
		$clean = array();
		$ids   = array();
		foreach ( $figures as $figure ) {
			if ( ! is_array( $figure ) || ! is_string( $figure['id'] ?? null ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,40}$/D', $figure['id'] ) || isset( $ids[ $figure['id'] ] ) || ! is_int( $figure['page'] ?? null ) || $figure['page'] < 1 || $figure['page'] > $pages || ! FigureSpec::text( $figure['alt'] ?? '' ) ) {
				return self::error();
			}
			$bounds   = PageRenderer::bounds( $figure['bounds'] ?? null );
			$part_ids = $figure['part_ids'] ?? array();
			$asset    = $figure['asset_id'] ?? '';
			if ( is_wp_error( $bounds ) || ! is_array( $part_ids ) || count( $part_ids ) > 50 || count( array_filter( $part_ids, 'is_string' ) ) !== count( $part_ids ) || array_diff( $part_ids, $parts ) || ! is_string( $asset ) || ( '' !== $asset && ! wp_is_uuid( $asset ) ) || ! in_array( $figure['selected'] ?? 'original', self::SELECTIONS, true ) ) {
				return self::error();
			}
			$item = array(
				'id'           => $figure['id'],
				'page'         => $figure['page'],
				'bounds'       => $bounds,
				'part_ids'     => array_values( array_unique( $part_ids ) ),
				'asset_id'     => $asset,
				'alt'          => $figure['alt'] ?? '',
				'not_to_scale' => true === ( $figure['not_to_scale'] ?? false ),
				'selected'     => $figure['selected'] ?? 'original',
				'approved'     => true === ( $figure['approved'] ?? false ),
			);
			if ( isset( $figure['spec'] ) ) {
				$spec = FigureSpec::validate( $figure['spec'] );
				if ( is_wp_error( $spec ) ) {
					return $spec;
				}
				$item['spec'] = $spec;
			}
			if ( 'original' !== $item['selected'] && ! isset( $item['spec'] ) ) {
				return self::error();
			}
			if ( isset( $figure['proposal'] ) ) {
				$proposal = self::proposal( $figure['proposal'] );
				if ( is_wp_error( $proposal ) ) {
					return $proposal;
				}
				$item['proposal'] = $proposal;
			}
			$ids[ $item['id'] ] = true;
			$clean[]            = $item;
		}
		return $clean;
	}

	/**
	 * Validate a JSXGraph proposal: its declarative spec, review status, source and display choice.
	 *
	 * @param mixed $proposal Candidate.
	 * @return array|\WP_Error Canonical proposal.
	 */
	public static function proposal( $proposal ) {
		if ( ! is_array( $proposal ) || array_diff( array_keys( $proposal ), array( 'status', 'origin', 'display', 'spec' ) ) || ! in_array( $proposal['status'] ?? '', self::PROPOSAL_STATUSES, true ) || ! in_array( $proposal['origin'] ?? '', self::PROPOSAL_ORIGINS, true ) || ! in_array( $proposal['display'] ?? 'replace', self::PROPOSAL_DISPLAYS, true ) ) {
			return self::error();
		}
		$spec = FigureSpec::validate( $proposal['spec'] ?? null );
		if ( is_wp_error( $spec ) ) {
			return $spec;
		}
		return array(
			'status'  => $proposal['status'],
			'origin'  => $proposal['origin'],
			'display' => $proposal['display'] ?? 'replace',
			'spec'    => $spec,
		);
	}

	/**
	 * Placements as committed to a question: review-only proposal state is left out.
	 *
	 * @param array $figures Validated placements.
	 * @return array
	 */
	public static function committed( array $figures ) {
		return array_map(
			static function ( $figure ) {
				unset( $figure['proposal'] );
				return $figure;
			},
			$figures
		);
	}

	/**
	 * Check the server-side approval gate without affecting legacy nonfigure questions.
	 *
	 * @param array $settings Question settings.
	 * @param bool  $checking_author_review Whether this is the ownership-checked sealing step.
	 * @return true|\WP_Error Approval outcome.
	 */
	public static function publishable( array $settings, $checking_author_review = false ) {
		if ( ! isset( $settings['figure_review'] ) && empty( $settings['figures'] ) ) {
			return true;
		}
		$review  = $settings['figure_review'] ?? array();
		$figures = self::validate( $settings['figures'] ?? array(), array_column( $settings['parts'] ?? array(), 'id' ) );
		if ( is_wp_error( $figures ) || true !== ( $review['complete'] ?? false ) || ( ! empty( $review['required'] ) && ! $figures ) ) {
			return self::error();
		}
		if ( ! $checking_author_review && ! self::attested( $settings ) ) {
			return self::error();
		}
		foreach ( $figures as $figure ) {
			$asset = Figures::asset( $figure['asset_id'] );
			if ( ! $figure['approved'] || '' === trim( $figure['alt'] ) || ! $asset || 'ready' !== $asset['state'] || 'question' !== $asset['document_role'] || is_wp_error( Figures::verified_path( $figure['asset_id'] ) ) || ( 'original' !== $figure['selected'] && empty( $figure['spec'] ) ) ) {
				return self::error();
			}
		}
		return true;
	}

	/**
	 * Seal explicit author review after checking ownership of every original.
	 *
	 * @param array $settings Reviewed settings.
	 * @return array|\WP_Error Settings with a server-generated attestation.
	 */
	public static function seal( array $settings ) {
		$valid = self::publishable( $settings, true );
		if ( is_wp_error( $valid ) || ! current_user_can( 'edit_posts' ) ) {
			return self::error();
		}
		foreach ( $settings['figures'] ?? array() as $figure ) {
			if ( is_wp_error( Figures::author_path( $figure['asset_id'] ) ) ) {
				return self::error();
			}
		}
		$settings['figure_review']['signature'] = self::signature( $settings, true );
		return self::attested( $settings ) ? $settings : self::error();
	}

	/**
	 * Bind review to exact placements, selection, specs and part identities.
	 *
	 * The JSON is encoded with a fixed float precision: `serialize_precision` differs between PHP
	 * configurations (web server and CLI), and an attestation must verify under both.
	 *
	 * @param array $settings  Frozen settings.
	 * @param bool  $create    Initialize a persistent review key on explicit author approval.
	 * @param int   $precision `serialize_precision` to encode with; -1 (shortest exact) by default.
	 * @return string Server attestation, never sent to learners.
	 */
	private static function signature( array $settings, $create = false, $precision = -1 ) {
		$key = get_option( '_ohmylms_figure_review_key', '' );
		if ( ! $key && $create ) {
			add_option( '_ohmylms_figure_review_key', wp_generate_password( 64, true, true ), '', false );
			$key = get_option( '_ohmylms_figure_review_key', '' );
		}
		if ( ! is_string( $key ) || strlen( $key ) < 32 ) {
			return '';
		}
		$before = ini_get( 'serialize_precision' );
		ini_set( 'serialize_precision', (string) $precision ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Restored below; makes the attestation independent of the PHP configuration.
		$json = wp_json_encode( array( $settings['figures'] ?? array(), ! empty( $settings['figure_review']['complete'] ), ! empty( $settings['figure_review']['required'] ), array_column( $settings['parts'] ?? array(), 'id' ) ) );
		ini_set( 'serialize_precision', (string) $before ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Restore the configured precision.
		return hash_hmac( 'sha256', (string) $json, $key );
	}

	/**
	 * Verify provenance without reading unrelated asset files on every image request.
	 *
	 * @param array $settings Frozen settings.
	 * @return bool Exact reviewed content.
	 */
	private static function attested( array $settings ) {
		$signature = $settings['figure_review']['signature'] ?? '';
		if ( ! is_string( $signature ) || 64 !== strlen( $signature ) ) {
			return false;
		}
		// Attestations sealed before the precision was fixed used the server's setting (often 17).
		foreach ( array( -1, 17 ) as $precision ) {
			$expected = self::signature( $settings, false, $precision );
			if ( 64 === strlen( $expected ) && hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render safe frozen figures into the shared stem or a part prompt.
	 *
	 * @param QuestionSnapshot $snapshot Frozen question.
	 * @param string           $scope Delivery scope.
	 * @param string           $part Part ID, empty for the shared stem.
	 * @return string Escaped figure markup.
	 */
	public static function html( QuestionSnapshot $snapshot, $scope = '', $part = '' ) {
		$figures = $snapshot->get_settings()['figures'] ?? array();
		if ( ! $figures ) {
			return '';
		}
		self::enqueue();
		$html = '';
		foreach ( $figures as $figure ) {
			if ( empty( $figure['approved'] ) || ( '' === $part ? ! empty( $figure['part_ids'] ) : ! in_array( $part, $figure['part_ids'] ?? array(), true ) ) ) {
				continue;
			}
			$url      = add_query_arg(
				array(
					'ohmylms_figure'  => $figure['asset_id'],
					'figure_version'  => $snapshot->get_version_id(),
					'figure_question' => $snapshot->get_id(),
					'figure_scope'    => (string) $scope,
				),
				home_url( '/' )
			);
			$selected = $figure['selected'] ?? 'original';
			$spec     = 'original' !== $selected ? ( $figure['spec'] ?? null ) : null;
			$asset    = Figures::asset( $figure['asset_id'] );
			$width    = isset( $asset['width_mm'] ) ? '--ohmylms-figure-width:' . (float) $asset['width_mm'] . 'mm' : '';
			$display  = $spec && 'both' === $selected ? ' data-figure-display="accompany"' : '';
			$html    .= '<figure class="ohmylms-import-figure" style="' . esc_attr( $width ) . '" data-figure-spec="' . esc_attr( $spec ? wp_json_encode( $spec ) : '' ) . '"' . $display . '><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $figure['alt'] ) . '">';
			if ( ! empty( $figure['not_to_scale'] ) ) {
				$html .= '<figcaption>' . esc_html__( 'Not to scale', 'ohmylms' ) . '</figcaption>';
			}
			$html .= '</figure>';
		}
		return $html;
	}

	/** Enqueue one trusted figure runtime, with original images available on failure. */
	public static function enqueue() {
		\OhMyLMS\Assessment\Interactive::register_jsxgraph();
		wp_enqueue_style( 'ohmylms-figures', plugins_url( 'assets/css/import-figures.css', OHMYLMS_FILE ), array( 'ohmylms-jsxgraph' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/import-figures.css' ) );
		wp_enqueue_script( 'ohmylms-figures', plugins_url( 'assets/js/import-figures.js', OHMYLMS_FILE ), array( 'ohmylms-jsxgraph' ), (string) filemtime( OHMYLMS_DIR . '/assets/js/import-figures.js' ), true );
	}

	/**
	 * Authorize an exact frozen asset against the active viewer and delivery.
	 *
	 * @param string $uuid Asset UUID.
	 * @param int    $version_id Frozen version.
	 * @param string $scope Attempt ID or author-preview scope.
	 * @param int    $question_id Editable question for author previews.
	 * @return string|\WP_Error Protected path.
	 */
	public static function path( $uuid, $version_id, $scope, $question_id = 0 ) {
		if ( ! is_user_logged_in() ) {
			return self::error();
		}
		$snapshot = $version_id ? VersionPublisher::snapshot( $version_id ) : null;
		if ( ! $snapshot ) {
			if ( $question_id && AccessPolicy::can_use_question( $question_id ) ) {
				$current = VersionPublisher::content( $question_id )['settings'] ?? array();
				foreach ( self::attested( $current ) ? ( $current['figures'] ?? array() ) : array() as $figure ) {
					if ( ( $figure['asset_id'] ?? '' ) === $uuid && ! empty( $figure['approved'] ) ) {
						return Figures::verified_path( $uuid );
					}
				}
			}
			return Figures::author_path( $uuid );
		}
		if ( ! self::attested( $snapshot->get_settings() ) ) {
			return self::error();
		}
		$member = false;
		foreach ( $snapshot->get_settings()['figures'] ?? array() as $figure ) {
			if ( ( $figure['asset_id'] ?? '' ) === $uuid && ! empty( $figure['approved'] ) ) {
				$member = true;
			}
		}
		if ( ! $member || ! is_user_logged_in() ) {
			return self::error();
		}
		if ( AccessPolicy::can_use_question( $snapshot->get_id() ) ) {
			return Figures::verified_path( $uuid );
		}
		if ( ! ctype_digit( (string) $scope ) || (int) $scope < 1 ) {
			return self::error();
		}
		global $wpdb;
		$attempt = $wpdb->get_row( $wpdb->prepare( 'SELECT id,quiz_id FROM %i WHERE id=%d AND student_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts', (int) $scope, get_current_user_id() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live delivery authorization must not be cached.
		$member  = $attempt ? $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE attempt_id=%d AND version_id=%d', Schema::table( 'attempt_items' ), $attempt['id'], $version_id ) ) : false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact immutable attempt membership.
		$asset   = Figures::asset( $uuid );
		return $member && $asset && 'question' === $asset['document_role'] && ! is_wp_error( Submission::access( $attempt['quiz_id'], get_current_user_id() ) ) ? Figures::verified_path( $uuid ) : self::error();
	}

	/**
	 * Safe review failure.
	 *
	 * @return \WP_Error Error.
	 */
	private static function error() {
		return new \WP_Error( 'exam_figure_review', __( 'Confirm figure completeness and approve every required figure before publishing.', 'ohmylms' ), array( 'status' => 409 ) );
	}
}
