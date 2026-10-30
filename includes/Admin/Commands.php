<?php // phpcs:ignore WordPress.Files.FileName -- Retains the established public include path and Composer class mapping.
/**
 * Commands for the WordPress command palette (Ctrl+K / Cmd+K).
 *
 * Every wp-admin screen gets "OhMyLMS: Go to ..." commands for the Content Hub tabs and the settings
 * pages, and "OhMyLMS: New ..." / "Import ..." commands that open the matching screen with its dialog
 * already open (`#/content-hub/exams?action=new`; see assets/src/features/content-hub/useHubAction.js).
 * Only commands the current user may use are sent. Add-ons add their own with the
 * `ohmylms_admin_commands` filter. See docs/COMMAND-PALETTE.md.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

defined( 'ABSPATH' ) || exit;

/** Builds the command list and loads the script that registers it with the palette. */
final class Commands {

	/** Hook the script into every admin screen. */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** Load the registering script with the list the current user may use. */
	public static function enqueue() {
		$commands = self::all();
		if ( ! $commands ) {
			return;
		}
		wp_enqueue_script(
			'ohmylms-commands',
			OHMYLMS_URL . '/assets/js/commands.js',
			array( 'wp-commands', 'wp-data', 'wp-i18n' ),
			(string) filemtime( OHMYLMS_DIR . '/assets/js/commands.js' ),
			true
		);
		wp_add_inline_script(
			'ohmylms-commands',
			'window.ohmylmsCommands = ' . wp_json_encode(
				array(
					'page'     => OHMYLMS_SLUG,
					'commands' => $commands,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Commands the current user may use.
	 *
	 * @return array[] name, label, page (admin page slug), route (a hash route inside that page), keywords
	 *                 and kind (go or action).
	 */
	public static function all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}
		$commands = array();
		foreach ( self::definitions() as $definition ) {
			$capability = $definition['capability'] ?? 'manage_options';
			if ( ! current_user_can( $capability ) ) {
				continue;
			}
			$commands[] = array(
				'name'     => 'ohmylms/' . $definition['name'],
				'label'    => $definition['label'],
				'route'    => $definition['route'],
				'keywords' => $definition['keywords'] ?? array(),
				'kind'     => $definition['kind'] ?? 'go',
			);
		}
		/**
		 * Filters the OhMyLMS command palette commands.
		 *
		 * @param array[] $commands name, label, route, keywords, kind and optionally page. The route is a
		 *                          legacy hash route such as /content-hub/exams?action=new; without a
		 *                          page it opens in the new app when LegacyRoutes maps it.
		 */
		$commands = (array) apply_filters( 'ohmylms_admin_commands', $commands );
		$commands = array_filter(
			$commands,
			static function ( $command ) {
				return is_array( $command )
					&& ! empty( $command['name'] ) && ! empty( $command['label'] )
					&& isset( $command['route'] ) && 0 === strpos( (string) $command['route'], '/' );
			}
		);
		return array_values( array_map( array( __CLASS__, 'with_target' ), $commands ) );
	}

	/**
	 * Point a command at the screen that serves its route: the new app where LegacyRoutes maps the
	 * legacy route, the legacy page otherwise. A command that names its own `page` is left as it is.
	 *
	 * @param array $command Command with a legacy hash route.
	 * @return array Command with `page` (admin page slug) and the route inside that page.
	 */
	private static function with_target( array $command ) {
		if ( ! empty( $command['page'] ) ) {
			$command['page'] = sanitize_key( $command['page'] );
			return $command;
		}
		$target           = LegacyRoutes::target( $command['route'] );
		$command['page']  = $target['page'];
		$command['route'] = $target['route'];
		return $command;
	}

	/**
	 * The built-in commands.
	 *
	 * @return array[]
	 */
	private static function definitions() {
		$go     = array(
			'dashboard'      => array( __( 'Dashboard', 'ohmylms' ), '/dashboard' ),
			'content-hub'    => array( __( 'Content Hub', 'ohmylms' ), '/content-hub' ),
			'courses'        => array( __( 'Courses', 'ohmylms' ), '/content-hub/courses' ),
			'lessons'        => array( __( 'Lessons', 'ohmylms' ), '/content-hub/lessons' ),
			'quizzes'        => array( __( 'Quizzes', 'ohmylms' ), '/content-hub/quizzes' ),
			'exams'          => array( __( 'Exams', 'ohmylms' ), '/content-hub/exams' ),
			'question-bank'  => array( __( 'Question Bank', 'ohmylms' ), '/content-hub/question-bank' ),
			'assignments'    => array( __( 'Assignments', 'ohmylms' ), '/content-hub/assignments' ),
			'skills'         => array( __( 'Skills', 'ohmylms' ), '/content-hub/skills' ),
			'curriculum'     => array( __( 'Curriculum', 'ohmylms' ), '/content-hub/curriculum' ),
			'bundles'        => array( __( 'Course Bundles', 'ohmylms' ), '/content-hub/bundles' ),
			'memberships'    => array( __( 'Membership', 'ohmylms' ), '/memberships' ),
			'account-hub'    => array( __( 'Account Hub', 'ohmylms' ), '/accounthub' ),
			'settings'       => array( __( 'Settings', 'ohmylms' ), '/settings/general-settings' ),
			'settings-pay'   => array( __( 'Payments settings', 'ohmylms' ), '/settings/payments-settings/payments' ),
			'settings-email' => array( __( 'Email settings', 'ohmylms' ), '/settings/emails-settings' ),
			'settings-brand' => array( __( 'Branding settings', 'ohmylms' ), '/settings/branding-settings' ),
			'settings-adv'   => array( __( 'Advanced settings', 'ohmylms' ), '/settings/advanced-settings' ),
			'settings-ai'    => array( __( 'AI Tutor settings', 'ohmylms' ), '/settings/ai-tutor-settings' ),
		);
		$result = array();
		foreach ( $go as $name => $item ) {
			$result[] = array(
				'name'  => 'go-' . $name,
				/* translators: %s: screen name. */
				'label' => sprintf( __( 'OhMyLMS: Go to %s', 'ohmylms' ), $item[0] ),
				'route' => $item[1],
			);
		}
		$actions = array(
			'new-quiz'         => array( __( 'New quiz', 'ohmylms' ), '/content-hub/quizzes?action=new', array( 'create', 'add' ) ),
			'import-quiz'      => array( __( 'Import quiz', 'ohmylms' ), '/content-hub/quizzes?action=import', array( 'upload', 'csv', 'json' ) ),
			'new-exam'         => array( __( 'New exam', 'ohmylms' ), '/content-hub/exams?action=new', array( 'create', 'add', 'test' ) ),
			'import-exam'      => array( __( 'Import exam', 'ohmylms' ), '/content-hub/exams?action=import', array( 'upload', 'pdf', 'csv' ) ),
			'new-question'     => array( __( 'New question', 'ohmylms' ), '/content-hub/question-bank?action=new', array( 'create', 'add', 'bank' ) ),
			'import-questions' => array( __( 'Import questions', 'ohmylms' ), '/content-hub/question-bank?action=import', array( 'upload', 'csv', 'json', 'bank' ) ),
			'new-skill'        => array( __( 'New skill', 'ohmylms' ), '/content-hub/skills?add=1', array( 'create', 'add' ) ),
		);
		foreach ( $actions as $name => $item ) {
			$result[] = array(
				'name'     => $name,
				'label'    => 'OhMyLMS: ' . $item[0],
				'route'    => $item[1],
				'keywords' => $item[2],
				'kind'     => 'action',
			);
		}
		return $result;
	}
}
