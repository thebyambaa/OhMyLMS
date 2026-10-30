<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Gamification registry: the single source for sections, events, limits, defaults and schema.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement;

defined( 'ABSPATH' ) || exit;

/**
 * Describes everything configurable under `ohmylms/v1/gamification`.
 *
 * Sections are `points`, `levels`, `badges`, `leaderboard` and `rewards`. Each section is one
 * option; levels and badges also have a collection option. Point rules are a fixed allow-list
 * of events (never free text), badge criteria use the metrics in {@see self::metrics()}.
 *
 * @since 1.0.0
 */
final class GamificationRegistry {

	/**
	 * Largest number of points one award, rule or adjustment may carry.
	 */
	const MAX_POINTS = 100000;

	/**
	 * Largest value a badge criterion may compare with.
	 */
	const MAX_CRITERION_VALUE = 1000000;

	/**
	 * Largest number of levels.
	 */
	const MAX_LEVELS = 50;

	/**
	 * Largest number of custom badges.
	 */
	const MAX_BADGES = 100;

	/**
	 * Largest number of criteria on one badge.
	 */
	const MAX_CRITERIA = 10;

	/**
	 * Section ids in display order.
	 *
	 * @return string[]
	 */
	public static function sections() {
		return array( 'points', 'levels', 'badges', 'leaderboard', 'rewards' );
	}

	/**
	 * Whether a section exists.
	 *
	 * @param string $section Section id.
	 * @return bool
	 */
	public static function has_section( $section ) {
		return in_array( $section, self::sections(), true );
	}

	/**
	 * Option that stores a section.
	 *
	 * @param string $section Section id.
	 * @return string
	 */
	public static function option( $section ) {
		$options = array(
			'points'      => 'ohmylms_point_settings',
			'levels'      => 'ohmylms_level_settings',
			'badges'      => 'ohmylms_badge_settings',
			'leaderboard' => 'ohmylms_leaderboard_settings',
			'rewards'     => 'ohmylms_reward_settings',
		);

		return isset( $options[ $section ] ) ? $options[ $section ] : '';
	}

	/**
	 * Point events: the only things that can award points.
	 *
	 * `threshold` is a percentage (0-100) the learner must reach, or null when the event has none.
	 *
	 * @return array<string,array>
	 */
	public static function events() {
		return array(
			'course_completion_rate'  => array(
				'label'       => __( 'Course completion', 'ohmylms' ),
				'description' => __( 'Award points when a learner reaches the completion percentage of a course.', 'ohmylms' ),
				'points'      => 10,
				'threshold'   => 100,
			),
			'lesson_complete'         => array(
				'label'       => __( 'Lesson completion', 'ohmylms' ),
				'description' => __( 'Award points when a learner finishes a lesson.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => null,
			),
			'quiz_passing_mark'       => array(
				'label'       => __( 'Quiz achievement', 'ohmylms' ),
				'description' => __( 'Award points when a learner scores at or above the mark on a quiz.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => 30,
			),
			'assignment_passing_mark' => array(
				'label'       => __( 'Assignment achievement', 'ohmylms' ),
				'description' => __( 'Award points when a learner reaches the passing score on an assignment.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => 30,
			),
			'submit_quiz'             => array(
				'label'       => __( 'Quiz submission', 'ohmylms' ),
				'description' => __( 'Award points whenever a quiz is submitted, whatever the score.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => null,
			),
			'submit_assignment'       => array(
				'label'       => __( 'Assignment submission', 'ohmylms' ),
				'description' => __( 'Award points when a learner submits an assignment.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => null,
			),
			'comment_on_course'       => array(
				'label'       => __( 'First comment on a course', 'ohmylms' ),
				'description' => __( 'Award points when a learner comments on a course.', 'ohmylms' ),
				'points'      => 2,
				'threshold'   => null,
			),
			'user_registration'       => array(
				'label'       => __( 'User registration', 'ohmylms' ),
				'description' => __( 'Award points when a new user registers.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => null,
			),
			'course_enrollment'       => array(
				'label'       => __( 'Course enrollment', 'ohmylms' ),
				'description' => __( 'Award points when a learner enrolls in a course.', 'ohmylms' ),
				'points'      => 5,
				'threshold'   => null,
			),
			'purchase'                => array(
				'label'       => __( 'Purchase', 'ohmylms' ),
				'description' => __( 'Award points when a learner pays for an order.', 'ohmylms' ),
				'points'      => 10,
				'threshold'   => null,
			),
			'community_post_create'   => array(
				'label'       => __( 'Community post', 'ohmylms' ),
				'description' => __( 'Award points when a learner creates a community post.', 'ohmylms' ),
				'points'      => 8,
				'threshold'   => null,
			),
			'community_post_comment'  => array(
				'label'       => __( 'Community comment', 'ohmylms' ),
				'description' => __( 'Award points when a learner comments on a community post.', 'ohmylms' ),
				'points'      => 3,
				'threshold'   => null,
			),
			'community_reaction'      => array(
				'label'       => __( 'Community reaction', 'ohmylms' ),
				'description' => __( 'Award points when a learner reacts to community content.', 'ohmylms' ),
				'points'      => 1,
				'threshold'   => null,
			),
		);
	}

	/**
	 * Metrics a badge criterion may measure (allow-list).
	 *
	 * @return array<string,string> Metric => label.
	 */
	public static function metrics() {
		return array(
			'points'            => __( 'Bonus points', 'ohmylms' ),
			'completed_lesson'  => __( 'Completed lessons', 'ohmylms' ),
			'completed_courses' => __( 'Completed courses', 'ohmylms' ),
		);
	}

	/**
	 * Comparison operators of a badge criterion (allow-list).
	 *
	 * @return string[]
	 */
	public static function operators() {
		return array( '>=', '>', '<=', '<', '==', '!=' );
	}

	/**
	 * Leaderboard ranking modes.
	 *
	 * @return array<string,string> Mode => label.
	 */
	public static function leaderboard_modes() {
		return array(
			'completion_rate' => __( 'Course completion rate', 'ohmylms' ),
			'highest_quiz'    => __( 'Highest quiz score', 'ohmylms' ),
			'fastest_time'    => __( 'Fastest completion time', 'ohmylms' ),
		);
	}

	/**
	 * Default value of a section.
	 *
	 * @param string $section Section id.
	 * @return array
	 */
	public static function defaults( $section ) {
		switch ( $section ) {
			case 'points':
				$rules = array();
				foreach ( self::events() as $event => $definition ) {
					$rules[] = array(
						'event'     => $event,
						'enabled'   => false,
						'points'    => $definition['points'],
						'threshold' => $definition['threshold'],
						'email'     => array(
							'enabled' => false,
							'subject' => '',
							'body'    => '',
						),
					);
				}
				return array(
					'enabled' => false,
					'rules'   => $rules,
				);
			case 'leaderboard':
				return array(
					'enabled'   => false,
					'mode'      => 'completion_rate',
					'threshold' => 0,
					'limit'     => 10,
				);
			case 'rewards':
				return array(
					'enabled'              => false,
					'purchase_with_points' => false,
				);
			case 'levels':
			case 'badges':
				return array( 'enabled' => false );
		}

		return array();
	}

	/**
	 * Schema of one section, in JSON Schema draft 4 as understood by core.
	 *
	 * Properties marked `readonly` are returned for display and ignored on input.
	 *
	 * @param string $section Section id.
	 * @return array
	 */
	public static function schema( $section ) {
		$enabled = array(
			'description' => __( 'Whether this part of gamification is switched on.', 'ohmylms' ),
			'type'        => 'boolean',
		);
		$schemas = array(
			'points'      => array(
				'enabled' => $enabled,
				'rules'   => array(
					'description' => __( 'Point rules. Only listed events are changed; unknown events are rejected.', 'ohmylms' ),
					'type'        => 'array',
					'maxItems'    => count( self::events() ),
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'event' ),
						'properties'           => array(
							'event'          => array(
								'type' => 'string',
								'enum' => array_keys( self::events() ),
							),
							'label'          => array(
								'type'     => 'string',
								'readonly' => true,
							),
							'description'    => array(
								'type'     => 'string',
								'readonly' => true,
							),
							'enabled'        => array( 'type' => 'boolean' ),
							'points'         => array(
								'type'    => 'integer',
								'minimum' => 0,
								'maximum' => self::MAX_POINTS,
							),
							'threshold'      => array(
								'description' => __( 'Minimum percentage (0-100) for events that have one, otherwise null.', 'ohmylms' ),
								'type'        => array( 'integer', 'null' ),
								'minimum'     => 0,
								'maximum'     => 100,
							),
							'supports_threshold' => array(
								'type'     => 'boolean',
								'readonly' => true,
							),
							'email'          => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'enabled' => array( 'type' => 'boolean' ),
									'subject' => array(
										'type'      => 'string',
										'maxLength' => 150,
									),
									'body'    => array(
										'type'      => 'string',
										'maxLength' => 5000,
									),
								),
							),
						),
					),
				),
			),
			'levels'      => array( 'enabled' => $enabled ),
			'badges'      => array( 'enabled' => $enabled ),
			'leaderboard' => array(
				'enabled'   => $enabled,
				'mode'      => array(
					'description' => __( 'How learners are ranked.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( self::leaderboard_modes() ),
				),
				'threshold' => array(
					'description' => __( 'Minimum percentage a learner needs to be listed; 0 lists everyone.', 'ohmylms' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 100,
				),
				'limit'     => array(
					'description' => __( 'Number of learners shown.', 'ohmylms' ),
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 100,
				),
			),
			'rewards'     => array(
				'enabled'              => $enabled,
				'purchase_with_points' => array(
					'description' => __( 'Let learners pay for eligible courses with bonus points.', 'ohmylms' ),
					'type'        => 'boolean',
				),
			),
		);

		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'gamification-' . $section,
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => isset( $schemas[ $section ] ) ? $schemas[ $section ] : array(),
		);
	}

	/**
	 * Schema of a badge criterion.
	 *
	 * @return array
	 */
	public static function criterion_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'metric', 'operator', 'value' ),
			'properties'           => array(
				'metric'   => array(
					'type' => 'string',
					'enum' => array_keys( self::metrics() ),
				),
				'operator' => array(
					'type' => 'string',
					'enum' => self::operators(),
				),
				'value'    => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => self::MAX_CRITERION_VALUE,
				),
			),
		);
	}

	/**
	 * Merge a points rule with its catalogue entry for display.
	 *
	 * @param array $rule Stored rule.
	 * @return array
	 */
	public static function describe_rule( array $rule ) {
		$events = self::events();
		$event  = $rule['event'];

		$rule['label']              = $events[ $event ]['label'];
		$rule['description']        = $events[ $event ]['description'];
		$rule['supports_threshold'] = null !== $events[ $event ]['threshold'];

		return $rule;
	}

	/**
	 * Build a slug from a name.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	public static function slugify( $name ) {
		$slug = sanitize_title( remove_accents( (string) $name ) );
		$slug = substr( $slug, 0, 40 );

		return trim( $slug, '-' );
	}
}
