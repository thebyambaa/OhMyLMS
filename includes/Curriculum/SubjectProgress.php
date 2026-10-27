<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Read-only subject profile section.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Bundles\Progress;

defined( 'ABSPATH' ) || exit;

/** Shared mastery and direct syllabus progress, without transferred credit. */
final class SubjectProgress {

	/**
	 * Prevent duplicate profile rendering.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/** Register dashboard integration and an optional shortcode. */
	public static function init() {
		add_shortcode( 'ohmylms_subject_skills', array( __CLASS__, 'section' ) );
		foreach ( array( 'ohmylms_lms_student_dashboard_sections', 'ohmylms_lms_student_profile_after_dashboard_content' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'print_once' ), 6 );
		}
	}

	/** Print only for the current account, once per request. */
	public static function print_once() {
		if ( ! self::$printed ) {
			self::$printed = true;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- section() escapes each value for its HTML context.
			echo self::section();
		}
	}

	/**
	 * Read only published, explicitly selected subject syllabuses.
	 *
	 * @param int    $student Learner ID from a trusted current-account caller.
	 * @param string $subject Profile subject, grade or level.
	 * @return array
	 */
	public static function progress( $student, $subject ) {
		$result = array(
			'syllabuses' => array(),
			'shared'     => array(),
		);
		if ( ! $student || ! Schema::ready() || ! \OhMyLMS\Assessment\Schema::ready() ) {
			return $result;
		}
		$all_ids = array();
		foreach ( ( (array) get_option( Comparison::OPTION, array() ) )[ $subject ] ?? array() as $id ) {
			$course = SyllabusCourse::course_id( $id );
			if ( ! $course || 'publish' !== get_post_status( $course ) ) {
				continue;
			}
			$rows = Comparison::objectives( $id );
			if ( is_wp_error( $rows ) ) {
				continue;
			}
			$rows                   = Comparison::attach_mappings( $rows );
			$all_ids                = array_merge( $all_ids, array_column( $rows, 'term_id' ) );
			$result['syllabuses'][] = array(
				'syllabus' => Items::describe( Items::get( $id ) ),
				'coverage' => Comparison::coverage( $rows ),
				'items'    => $rows,
			);
		}
		$progress = Progress::for_objectives( $student, array_values( array_unique( $all_ids ) ) );
		foreach ( $result['syllabuses'] as &$syllabus ) {
			$syllabus['audit'] = array(
				'unassessed'   => array(),
				'developing'   => array(),
				'unmapped'     => array(),
				'related_only' => array(),
			);
			foreach ( $syllabus['items'] as &$row ) {
				$row['direct'] = $progress['direct'][ $row['term_id'] ] ?? null;
				$level         = $row['direct']['level'] ?? 'not-started';
				if ( 'not-started' === $level || \OhMyLMS\Performance\Levels::is_developing( $level ) ) {
					$syllabus['audit'][ 'not-started' === $level ? 'unassessed' : 'developing' ][] = $row['term_id'];
				}
				if ( ! metadata_exists( 'term', $row['term_id'], Comparison::SUBJECT_META ) ) {
					if ( ! $row['maps_to'] ) {
						$syllabus['audit']['unmapped'][] = $row['term_id'];
					} elseif ( ! in_array( 'equivalent', array_column( $row['maps_to'], 'relation' ), true ) ) {
						$syllabus['audit']['related_only'][] = $row['term_id'];
					}
				}
			}
			unset( $row );
		}
		unset( $syllabus );
		foreach ( $progress['shared'] as $shared ) {
			$id = $shared['shared']['term_id'];
			if ( metadata_exists( 'term', $id, Comparison::SUBJECT_META ) ) {
				$shared['topic']    = (string) get_term_meta( $id, Comparison::TOPIC_META, true );
				$result['shared'][] = $shared;
			}
		}
		$shown = array_column( array_column( $result['shared'], 'shared' ), 'term_id' );
		foreach ( $progress['direct'] as $id => $entry ) {
			if ( metadata_exists( 'term', $id, Comparison::SUBJECT_META ) && ! in_array( $id, $shown, true ) ) {
				$result['shared'][] = array(
					'shared'         => array(
						'term_id' => $id,
						'name'    => $entry['name'],
						'code'    => $entry['code'],
					),
					'level'          => $entry['level'],
					'level_label'    => $entry['level_label'],
					'evidence_count' => $entry['evidence_count'],
					'topic'          => (string) get_term_meta( $id, Comparison::TOPIC_META, true ),
					'sources'        => array(),
				);
			}
		}
		foreach ( $result['shared'] as &$shared ) {
			$shared['content'] = SharedContent::published( $shared['shared']['term_id'] );
		}
		unset( $shared );
		return $result;
	}

	/**
	 * Read configured subjects independently for the current learner.
	 *
	 * @param int $student Trusted learner ID.
	 * @return array
	 */
	public static function all_progress( $student ) {
		$subjects = array();
		foreach ( array_keys( (array) get_option( Comparison::OPTION, array() ) ) as $subject ) {
			$subjects[ $subject ] = self::progress( $student, $subject );
		}
		return $subjects;
	}

	/**
	 * Escaped Subject profile HTML for the current account.
	 *
	 * @return string
	 */
	public static function section() {
		if ( ! is_user_logged_in() || ! get_option( Comparison::OPTION ) ) {
			return '';
		}
		$subjects = self::all_progress( get_current_user_id() );
		ob_start();
		?>
		<?php
		foreach ( $subjects as $subject => $data ) {
			?>
			<section class="ohmylms-subject-skills">
			<h2><?php echo esc_html( $subject ); ?></h2>
			<p><?php esc_html_e( 'Shared mastery pools equivalent evidence once per answer. Direct syllabus progress and mapping coverage are separate. Related links add no evidence. Course completion follows its own rules.', 'ohmylms' ); ?></p>
			<h3><?php esc_html_e( 'Shared mastery', 'ohmylms' ); ?></h3>
			<?php if ( ! $data['shared'] ) { ?>
				<p><?php esc_html_e( 'No shared shared skill mappings are available yet.', 'ohmylms' ); ?></p>
			<?php } ?>
			<?php foreach ( $data['shared'] as $skill ) { ?>
				<details>
					<summary><?php echo esc_html( implode( ' · ', array_filter( array( $skill['topic'], $skill['shared']['name'], $skill['level_label'] ) ) ) ); ?></summary>
					<p><?php echo esc_html( sprintf( /* translators: %d: evidence answer count. */ __( 'Evidence: %d answers', 'ohmylms' ), $skill['evidence_count'] ) ); ?></p>
					<ul>
						<?php foreach ( $skill['content'] as $resource ) { ?>
							<li><a href="<?php echo esc_url( $resource['url'] ); ?>"><?php echo esc_html( $resource['title'] ); ?></a> · <?php echo esc_html( $resource['kind'] ); ?></li>
						<?php } ?>
						<?php foreach ( $skill['sources'] as $source ) { ?>
							<li><?php echo esc_html( implode( ' · ', array_filter( array( $source['name'], $source['code'], $source['relation'], $source['level_label'], $source['note'] ) ) ) ); ?></li>
						<?php } ?>
					</ul>
				</details>
			<?php } ?>
			<h3><?php esc_html_e( 'Syllabus views', 'ohmylms' ); ?></h3>
			<?php if ( ! $data['syllabuses'] ) { ?>
				<p><?php esc_html_e( 'Subject syllabuses have not been published yet.', 'ohmylms' ); ?></p>
			<?php } ?>
			<?php foreach ( $data['syllabuses'] as $syllabus ) { ?>
				<details>
					<summary><?php echo esc_html( $syllabus['syllabus']['name'] . ' ' . $syllabus['syllabus']['version'] ); ?></summary>
					<p><?php echo esc_html( sprintf( /* translators: 1: equivalent objectives, 2: related objectives, 3: unmapped objectives. */ __( 'Mapping coverage: %1$d equivalent, %2$d related only, %3$d unmapped.', 'ohmylms' ), $syllabus['coverage']['equivalent'], $syllabus['coverage']['related_only'], $syllabus['coverage']['unmapped'] ) ); ?></p>
					<p><?php echo esc_html( sprintf( /* translators: 1: unassessed objectives, 2: developing objectives. */ __( 'Study plan audit: %1$d unassessed objectives, %2$d developing objectives. Unassessed is not failed mastery.', 'ohmylms' ), count( $syllabus['audit']['unassessed'] ), count( $syllabus['audit']['developing'] ) ) ); ?></p>
					<ul>
						<?php foreach ( $syllabus['items'] as $row ) { ?>
							<li><details>
								<summary><?php echo esc_html( $row['code'] . ' ' . $row['name'] . ' · ' . ( $row['direct']['level_label'] ?? __( 'Not assessed', 'ohmylms' ) ) ); ?></summary>
								<p><?php echo esc_html( $row['objective'] ); ?></p>
								<p><?php echo esc_html( sprintf( /* translators: %d: direct evidence answer count. */ __( 'Direct evidence: %d answers', 'ohmylms' ), $row['direct']['evidence_count'] ?? 0 ) ); ?></p>
								<?php if ( ! $row['maps_to'] && ! metadata_exists( 'term', $row['term_id'], Comparison::SUBJECT_META ) ) { ?>
									<p><?php esc_html_e( 'Unmapped objective', 'ohmylms' ); ?></p>
								<?php } ?>
								<?php foreach ( $row['maps_to'] as $mapping ) { ?>
									<p><?php echo esc_html( implode( ' · ', array_filter( array( $mapping['shared']['name'] ?? '', $mapping['relation'], $mapping['note'] ) ) ) ); ?></p>
								<?php } ?>
							</details></li>
						<?php } ?>
					</ul>
				</details>
			<?php } ?>
		</section><?php } ?>
		<?php
		return ob_get_clean();
	}
}
