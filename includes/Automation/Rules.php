<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Automation rules: events, conditions, actions and validation.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Automation;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The allow-list of "when <event> then <action>" rules. Rules are data only: every event,
 * condition and action is one of the fixed entries below and every parameter is validated, so a
 * rule cannot run code, call URLs or write arbitrary data. Events are the triggers the legacy
 * Mail Mint connector offered (`Automation\Triggers\OhMyLmsTriggers`) plus course completion and
 * plan purchase; actions cover sending an email, enrolling in a course, adding to a cohort or a
 * membership plan and sending a coupon code. See docs/api/automations.md.
 *
 * @since 1.0.0
 */
final class Rules {

	const MAX_NAME    = 190;
	const MAX_ACTIONS = 5;
	const MAX_IDS     = 50;
	const MAX_SUBJECT = 200;
	const MAX_BODY    = 5000;
	const STATUSES    = array( 'active', 'paused' );
	const MERGE_TAGS  = array( '{first_name}', '{last_name}', '{display_name}', '{course_title}', '{site_name}', '{site_url}', '{course_url}', '{coupon_code}' );

	/**
	 * Events with the conditions each accepts.
	 *
	 * @return array<string, array{label: string, conditions: string[]}>
	 */
	public static function events() {
		return array(
			'course_purchased'     => array(
				'label'      => __( 'A course is purchased', 'ohmylms' ),
				'conditions' => array( 'course_ids' ),
			),
			'membership_purchased' => array(
				'label'      => __( 'A membership plan is purchased', 'ohmylms' ),
				'conditions' => array( 'membership_ids' ),
			),
			'course_enrolled'      => array(
				'label'      => __( 'A learner is enrolled in a course', 'ohmylms' ),
				'conditions' => array( 'course_ids' ),
			),
			'enrolment_cancelled'  => array(
				'label'      => __( 'A course enrolment is cancelled', 'ohmylms' ),
				'conditions' => array( 'course_ids' ),
			),
			'lesson_completed'     => array(
				'label'      => __( 'A lesson is completed', 'ohmylms' ),
				'conditions' => array( 'course_ids', 'lesson_ids' ),
			),
			'course_progress'      => array(
				'label'      => __( 'Course progress reaches a rate', 'ohmylms' ),
				'conditions' => array( 'course_ids', 'operator', 'rate' ),
			),
			'course_completed'     => array(
				'label'      => __( 'A course is completed', 'ohmylms' ),
				'conditions' => array( 'course_ids' ),
			),
			'quiz_submitted'       => array(
				'label'      => __( 'A quiz is submitted', 'ohmylms' ),
				'conditions' => array( 'course_ids', 'quiz_ids' ),
			),
			'assignment_submitted' => array(
				'label'      => __( 'An assignment is submitted', 'ohmylms' ),
				'conditions' => array( 'course_ids', 'assignment_ids' ),
			),
			'assignment_reviewed'  => array(
				'label'      => __( 'An assignment is graded', 'ohmylms' ),
				'conditions' => array( 'course_ids', 'assignment_ids', 'result' ),
			),
		);
	}

	/**
	 * Actions and their parameters.
	 *
	 * @return array<string, array{label: string, params: string[]}>
	 */
	public static function actions() {
		return array(
			'send_email'       => array(
				'label'  => __( 'Send an email', 'ohmylms' ),
				'params' => array( 'to', 'subject', 'body' ),
			),
			'enrol_course'     => array(
				'label'  => __( 'Enrol in a course', 'ohmylms' ),
				'params' => array( 'course_id' ),
			),
			'add_to_cohort'    => array(
				'label'  => __( 'Add to a cohort', 'ohmylms' ),
				'params' => array( 'cohort_id' ),
			),
			'grant_membership' => array(
				'label'  => __( 'Give a membership plan', 'ohmylms' ),
				'params' => array( 'membership_id' ),
			),
			'send_coupon'      => array(
				'label'  => __( 'Send a coupon code', 'ohmylms' ),
				'params' => array( 'coupon_id', 'subject', 'body' ),
			),
		);
	}

	/**
	 * Post type each id condition refers to.
	 */
	const ID_CONDITIONS = array(
		'course_ids'     => 'ohmylms-course',
		'membership_ids' => 'ohmylms-membership',
		'lesson_ids'     => 'ohmylms-lesson',
		'quiz_ids'       => 'ohmylms-quiz',
		'assignment_ids' => 'ohmylms-assignment',
	);

	/**
	 * Options for the rule editor.
	 *
	 * @return array
	 */
	public static function options() {
		$events = array();
		foreach ( self::events() as $id => $event ) {
			$events[] = array_merge( array( 'id' => $id ), $event );
		}
		$actions = array();
		foreach ( self::actions() as $id => $action ) {
			$actions[] = array_merge( array( 'id' => $id ), $action );
		}
		return array(
			'events'      => $events,
			'actions'     => $actions,
			'operators'   => array( 'gte', 'lte', 'eq' ),
			'results'     => array( 'pass', 'fail' ),
			'recipients'  => array( 'learner', 'admin' ),
			'merge_tags'  => self::MERGE_TAGS,
			'max_actions' => self::MAX_ACTIONS,
			'max_ids'     => self::MAX_IDS,
			'max_subject' => self::MAX_SUBJECT,
			'max_body'    => self::MAX_BODY,
		);
	}

	/**
	 * Whether a value is a list (JSON array).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	/**
	 * Whether a value is an object (JSON object or empty).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_map( $value ) {
		return is_array( $value ) && ( ! $value || ! self::is_list( $value ) );
	}

	/**
	 * Whether an id is a post of a type (optionally published).
	 *
	 * @param mixed  $id        Id.
	 * @param string $post_type Post type.
	 * @param bool   $published Require publish status.
	 * @return bool
	 */
	private static function post_of( $id, $post_type, $published = false ) {
		return is_int( $id ) && $id > 0 && get_post_type( $id ) === $post_type && ( ! $published || 'publish' === get_post_status( $id ) );
	}

	/**
	 * Validate a full rule (create) or a partial update merged over `$current`.
	 *
	 * @param mixed      $body    JSON body.
	 * @param array|null $current Stored rule (prepared shape) for updates.
	 * @return array|WP_Error `{name, status, event, conditions, actions}` normalised.
	 */
	public static function validate( $body, $current = null ) {
		if ( ! self::is_map( $body ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Send the rule as an object.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$allowed = array( 'name', 'status', 'event', 'conditions', 'actions' );
		$unknown = array_diff( array_keys( $body ), $allowed );
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_automation_unknown_field',
				__( 'The request contains unknown fields.', 'ohmylms' ),
				array(
					'status' => 400,
					'params' => array_fill_keys( $unknown, __( 'Unknown field.', 'ohmylms' ) ),
				)
			);
		}
		$rule = array_merge(
			array(
				'name'       => null,
				'status'     => 'paused',
				'event'      => null,
				'conditions' => array(),
				'actions'    => array(),
			),
			$current ? array_intersect_key( $current, array_flip( $allowed ) ) : array(),
			$body
		);
		if ( $current && isset( $body['event'] ) && $body['event'] !== $current['event'] && ! array_key_exists( 'conditions', $body ) ) {
			$rule['conditions'] = array();
		}

		$errors = array();
		$name   = is_string( $rule['name'] ) ? trim( sanitize_text_field( $rule['name'] ) ) : '';
		if ( '' === $name || mb_strlen( $name ) > self::MAX_NAME ) {
			/* translators: %d: maximum length. */
			$errors['name'] = sprintf( __( 'Enter a name of 1 to %d characters.', 'ohmylms' ), self::MAX_NAME );
		}
		if ( ! in_array( $rule['status'], self::STATUSES, true ) ) {
			$errors['status'] = __( 'Use active or paused.', 'ohmylms' );
		}
		$events = self::events();
		if ( ! is_string( $rule['event'] ) || ! isset( $events[ $rule['event'] ] ) ) {
			$errors['event'] = __( 'Choose an event from the list.', 'ohmylms' );
		}

		$conditions = array();
		if ( ! self::is_map( $rule['conditions'] ) ) {
			$errors['conditions'] = __( 'Conditions must be an object.', 'ohmylms' );
		} elseif ( ! isset( $errors['event'] ) ) {
			$supported = $events[ $rule['event'] ]['conditions'];
			foreach ( $rule['conditions'] as $key => $value ) {
				$field = 'conditions.' . $key;
				if ( ! in_array( $key, $supported, true ) ) {
					$errors[ $field ] = __( 'This event does not support this condition.', 'ohmylms' );
				} elseif ( isset( self::ID_CONDITIONS[ $key ] ) ) {
					if ( ! self::is_list( $value ) || count( $value ) > self::MAX_IDS || count( array_unique( $value, SORT_REGULAR ) ) !== count( $value ) ) {
						/* translators: %d: maximum number of items. */
						$errors[ $field ] = sprintf( __( 'Choose up to %d different items.', 'ohmylms' ), self::MAX_IDS );
						continue;
					}
					foreach ( $value as $id ) {
						if ( ! self::post_of( $id, self::ID_CONDITIONS[ $key ] ) ) {
							$errors[ $field ] = __( 'One of the chosen items does not exist.', 'ohmylms' );
							continue 2;
						}
					}
					if ( $value ) {
						$conditions[ $key ] = $value;
					}
				} elseif ( 'operator' === $key ) {
					if ( ! in_array( $value, array( 'gte', 'lte', 'eq' ), true ) ) {
						$errors[ $field ] = __( 'Use gte, lte or eq.', 'ohmylms' );
					} else {
						$conditions[ $key ] = $value;
					}
				} elseif ( 'rate' === $key ) {
					if ( ! is_int( $value ) || $value < 0 || $value > 100 ) {
						$errors[ $field ] = __( 'Enter a whole percentage from 0 to 100.', 'ohmylms' );
					} else {
						$conditions[ $key ] = $value;
					}
				} elseif ( 'result' === $key ) {
					if ( ! in_array( $value, array( 'pass', 'fail' ), true ) ) {
						$errors[ $field ] = __( 'Use pass or fail.', 'ohmylms' );
					} else {
						$conditions[ $key ] = $value;
					}
				}
			}
			if ( 'course_progress' === $rule['event'] && ! isset( $errors['conditions.rate'] ) && ! isset( $conditions['rate'] ) ) {
				$errors['conditions.rate'] = __( 'Enter the progress rate.', 'ohmylms' );
			}
			if ( isset( $conditions['rate'] ) && ! isset( $conditions['operator'] ) ) {
				$conditions['operator'] = 'gte';
			}
		}

		$actions = array();
		if ( ! self::is_list( $rule['actions'] ) || ! $rule['actions'] || count( $rule['actions'] ) > self::MAX_ACTIONS ) {
			/* translators: %d: maximum number of actions. */
			$errors['actions'] = sprintf( __( 'Add 1 to %d actions.', 'ohmylms' ), self::MAX_ACTIONS );
		} else {
			foreach ( $rule['actions'] as $index => $action ) {
				$result = self::validate_action( $action, 'actions.' . $index );
				if ( is_array( $result ) && isset( $result['errors'] ) ) {
					$errors = array_merge( $errors, $result['errors'] );
				} else {
					$actions[] = $result;
				}
			}
		}

		if ( $errors ) {
			return new WP_Error(
				'ohmylms_automation_invalid',
				__( 'Some rule fields are not valid.', 'ohmylms' ),
				array(
					'status' => 422,
					'params' => $errors,
				)
			);
		}
		return array(
			'name'       => $name,
			'status'     => $rule['status'],
			'event'      => $rule['event'],
			'conditions' => $conditions,
			'actions'    => $actions,
		);
	}

	/**
	 * Validate one action.
	 *
	 * @param mixed  $action Action.
	 * @param string $prefix Error key prefix.
	 * @return array Normalised action, or `['errors' => [...]]`.
	 */
	private static function validate_action( $action, $prefix ) {
		$types = self::actions();
		if ( ! self::is_map( $action ) || ! isset( $action['type'] ) || ! is_string( $action['type'] ) || ! isset( $types[ $action['type'] ] ) ) {
			return array( 'errors' => array( $prefix . '.type' => __( 'Choose an action from the list.', 'ohmylms' ) ) );
		}
		$type    = $action['type'];
		$params  = $types[ $type ]['params'];
		$errors  = array();
		$unknown = array_diff( array_keys( $action ), array_merge( array( 'type' ), $params ) );
		foreach ( $unknown as $key ) {
			$errors[ $prefix . '.' . $key ] = __( 'Unknown field.', 'ohmylms' );
		}
		$clean = array( 'type' => $type );
		foreach ( $params as $param ) {
			$value = $action[ $param ] ?? null;
			$key   = $prefix . '.' . $param;
			switch ( $param ) {
				case 'to':
					if ( ! in_array( $value, array( 'learner', 'admin' ), true ) ) {
						$errors[ $key ] = __( 'Send to the learner or the site administrator.', 'ohmylms' );
					} else {
						$clean[ $param ] = $value;
					}
					break;
				case 'subject':
					$text = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
					if ( '' === $text || mb_strlen( $text ) > self::MAX_SUBJECT ) {
						/* translators: %d: maximum length. */
						$errors[ $key ] = sprintf( __( 'Enter a subject of 1 to %d characters.', 'ohmylms' ), self::MAX_SUBJECT );
					} else {
						$clean[ $param ] = $text;
					}
					break;
				case 'body':
					$text = is_string( $value ) ? trim( sanitize_textarea_field( $value ) ) : '';
					if ( '' === $text || mb_strlen( $text ) > self::MAX_BODY ) {
						/* translators: %d: maximum length. */
						$errors[ $key ] = sprintf( __( 'Enter a message of 1 to %d characters.', 'ohmylms' ), self::MAX_BODY );
					} elseif ( 'send_coupon' === $type && false === strpos( $text, '{coupon_code}' ) ) {
						$errors[ $key ] = __( 'Include {coupon_code} in the message.', 'ohmylms' );
					} else {
						$clean[ $param ] = $text;
					}
					break;
				case 'course_id':
					if ( ! self::post_of( $value, 'ohmylms-course', true ) ) {
						$errors[ $key ] = __( 'Choose a published course.', 'ohmylms' );
					} else {
						$clean[ $param ] = $value;
					}
					break;
				case 'membership_id':
					if ( ! self::post_of( $value, 'ohmylms-membership', true ) ) {
						$errors[ $key ] = __( 'Choose a published membership plan.', 'ohmylms' );
					} else {
						$clean[ $param ] = $value;
					}
					break;
				case 'coupon_id':
					if ( ! self::post_of( $value, 'ohmylms_coupon', true ) ) {
						$errors[ $key ] = __( 'Choose a published coupon.', 'ohmylms' );
					} else {
						$clean[ $param ] = $value;
					}
					break;
				case 'cohort_id':
					$cohort = is_int( $value ) ? \OhMyLMS\Cohorts\Cohorts::row( $value ) : null;
					if ( ! $cohort || 'archived' === $cohort['status'] ) {
						$errors[ $key ] = __( 'Choose an active cohort.', 'ohmylms' );
					} else {
						$clean[ $param ] = $value;
					}
					break;
			}
		}
		return $errors ? array( 'errors' => $errors ) : $clean;
	}

	/**
	 * Whether a rule's conditions match an event context.
	 *
	 * @param array $conditions Normalised conditions.
	 * @param array $context    `{user_id, course_id, membership_id, content_id, rate, result}`.
	 * @return bool
	 */
	public static function matches( array $conditions, array $context ) {
		$map = array(
			'course_ids'     => 'course_id',
			'membership_ids' => 'membership_id',
			'lesson_ids'     => 'content_id',
			'quiz_ids'       => 'content_id',
			'assignment_ids' => 'content_id',
		);
		foreach ( $map as $condition => $field ) {
			if ( ! empty( $conditions[ $condition ] ) && ! in_array( (int) ( $context[ $field ] ?? 0 ), array_map( 'intval', $conditions[ $condition ] ), true ) ) {
				return false;
			}
		}
		if ( isset( $conditions['rate'] ) ) {
			$rate = (float) ( $context['rate'] ?? -1 );
			$goal = (float) $conditions['rate'];
			$op   = $conditions['operator'] ?? 'gte';
			if ( ( 'gte' === $op && $rate < $goal ) || ( 'lte' === $op && $rate > $goal ) || ( 'eq' === $op && abs( $rate - $goal ) > 0.0001 ) ) {
				return false;
			}
		}
		if ( isset( $conditions['result'] ) && ( $context['result'] ?? '' ) !== $conditions['result'] ) {
			return false;
		}
		return true;
	}
}
