<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner dashboard presentation; all learning rules remain in their existing services.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\{Evidence, Recommendations};

defined( 'ABSPATH' ) || exit;

/** Learner dashboard presentation; all learning rules remain in their existing services. */
final class Dashboard {
	const THEMES = array( 'meadow', 'ocean', 'sunset' );
	/**
	 * Rendered.
	 *
	 * @var mixed
	 */
	public static $rendered = false;

	/**
	 * Init for Dashboard.
	 */
	public static function init() {
		add_action(
			'wp',
			static function () {
				if ( is_ohmylms_dashboard() ) {
					remove_action( 'ohmylms_lms_student_profile_before_dashboard_content', 'ohmylms_lms_student_profile_name' ); }
			}
		);
		add_action(
			'rest_api_init',
			static function () {
				register_rest_route(
					'ohmylms/v1',
					'/student/dashboard-theme',
					array(
						'methods'             => 'PUT',
						'permission_callback' => static function () {
							return is_user_logged_in(); },
						'callback'            => static function ( $request ) {
							$theme = $request->get_param( 'theme' );
							if ( ! is_string( $theme ) || ! in_array( $theme, self::THEMES, true ) ) {
								return new \WP_Error( 'dashboard_theme', __( 'Choose an available theme.', 'ohmylms' ), array( 'status' => 400 ) );
							}
							update_user_meta( get_current_user_id(), '_ohmylms_dashboard_theme', $theme );
							return rest_ensure_response( array( 'theme' => $theme ) );
						},
					)
				);
			}
		);
		// Load before the page head: shortcode templates render after wp_head.
		add_action(
			'wp_enqueue_scripts',
			static function () {
				if ( ! is_user_logged_in() ) {
					return; }
				global $post;
				$shortcode_page = $post && ( has_shortcode( $post->post_content, 'ohmylms_dashboard' ) || has_shortcode( $post->post_content, 'ohmylms_my_profile' ) );
				if ( ! $shortcode_page && ! is_ohmylms_profile() && ! is_ohmylms_dashboard() && ! is_ohmylms_profile_shortcode() ) {
					return; }
				self::assets();
			}
		);
	}

	/**
	 * Assets for Dashboard.
	 */
	public static function assets() {
		wp_enqueue_style( 'ohmylms-student-dashboard', plugins_url( 'assets/css/student-dashboard.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/student-dashboard.css' ) );
		wp_enqueue_script( 'ohmylms-student-dashboard', plugins_url( 'assets/js/student-dashboard.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/student-dashboard.js' ), true );
	}

	/**
	 * Data for Dashboard.
	 *
	 * @param mixed $user User.
	 */
	public static function data( $user ) {
		global $wpdb;
		$theme     = get_user_meta( $user, '_ohmylms_dashboard_theme', true );
		$zone_name = get_user_meta( $user, '_ohmylms_learning_timezone', true );
		$zone      = \OhMyLMS\Engagement\Streak::timezone( $zone_name ) ? new \DateTimeZone( $zone_name ) : wp_timezone();
		if ( \OhMyLMS\Engagement\StreakSettings::enabled() && \OhMyLMS\Engagement\StreakSchema::ready() ) {
			$saved_zone = $wpdb->get_var( $wpdb->prepare( 'SELECT timezone FROM %i WHERE user_id=%d', $wpdb->prefix . 'ohmylms_streak_state', $user ) );
			if ( \OhMyLMS\Engagement\Streak::timezone( $saved_zone ) ) {
				$zone = new \DateTimeZone( $saved_zone ); }
		}
		$now              = new \DateTimeImmutable( 'now', $zone );
		$start            = $now->modify( '-' . ( (int) $now->format( 'N' ) - 1 ) . ' days' )->setTime( 0, 0 );
		$end              = $start->modify( '+7 days' );
		$data             = array(
			'theme'           => in_array( $theme, self::THEMES, true ) ? $theme : 'meadow',
			'skills'          => array(),
			'recommendations' => array(),
			'recent'          => array(),
			'weekly'          => array(
				'answers' => 0,
				'skills'  => 0,
				'days'    => 0,
			),
			'practice'        => Frontend::enabled(),
			'week_label'      => wp_date( 'M j', $start->getTimestamp(), $zone ) . ' – ' . wp_date( 'M j', $now->getTimestamp(), $zone ),
		);
		$data['timezone'] = $zone->getName();
		if ( ! $data['practice'] ) {
			return $data; }
		Evidence::process( 50 );
		$data['skills']          = Evidence::summary( $user );
		$data['recommendations'] = Recommendations::for_student( $user, 6 );
		$recent                  = $data['skills'];
		usort(
			$recent,
			static function ( $a, $b ) {
				return strcmp( $b['last_evidence_at'] ?? '', $a['last_evidence_at'] ?? '' );
			}
		);
		$data['recent'] = array_slice( $recent, 0, 6 );
		$utc            = new \DateTimeZone( 'UTC' );
		$rows           = $wpdb->get_results( $wpdb->prepare( 'SELECT i.response,i.answered_at,s.term_id FROM %i i JOIN %i s ON s.id=i.session_id WHERE s.student_id=%d AND s.mode=\'skill\' AND i.answered_at >= %s AND i.answered_at < %s', Schema::table( 'practice_items' ), Schema::table( 'practice_sessions' ), $user, $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) ), ARRAY_A );
		$days           = array();
		$skills         = array();
		foreach ( $rows as $row ) {
			if ( ! \OhMyLMS\Engagement\StreakCalendar::meaningful( json_decode( $row['response'], true ) ) ) {
				continue; }
			++$data['weekly']['answers'];
			$skills[ (int) $row['term_id'] ] = true;
			$date                            = new \DateTimeImmutable( $row['answered_at'], $utc );
			$days[ $date->setTimezone( $zone )->format( 'Y-m-d' ) ] = true;
		}
		$data['weekly']['skills'] = count( $skills );
		$data['weekly']['days']   = count( $days );
		return $data;
	}
}
