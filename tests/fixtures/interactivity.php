<?php
/**
 * Local browser fixture: the plugin's real templates, decorators and Interactivity modules with the
 * installed WordPress Interactivity runtime, mocked data, no database or wp-load.php.
 *
 * php -S 127.0.0.1:8108 tests/fixtures/interactivity.php, then open /fixture:
 *   /fixture                    quiz player (?types=, ?layout=, ?timer=) with a disclosure and the reward toast
 *   /fixture?view=practice      SmartScore practice (needs the ohmylms-smartscore add-on, see OHMYLMS_SMARTSCORE_DIR)
 *   /fixture?view=catalog|account|auth|reviews|carousel|checkout   learner pages (tests/fixtures/interactivity/learner.php)
 * Files are served at their real URLs (/wp-content/plugins/<plugin>/assets/..., /wp-includes/js/dist/script-modules/...),
 * so the modules' relative imports resolve as they do on a site. No jQuery is served.
 *
 * @package OhMyLMS\Tests
 */

if ( 'cli-server' !== PHP_SAPI ) {
	exit;
}
define( 'OHMYLMS_DIR', dirname( __DIR__, 2 ) );
define( 'OHMYLMS_PATH', OHMYLMS_DIR );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'test' );
define( 'ABSPATH', dirname( OHMYLMS_DIR, 3 ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'OHMYLMS_SS_DIR', rtrim( getenv( 'OHMYLMS_SMARTSCORE_DIR' ) ? getenv( 'OHMYLMS_SMARTSCORE_DIR' ) : dirname( OHMYLMS_DIR ) . '/ohmylms-smartscore', '/\\' ) . '/' );

$fixture_route  = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$fixture_plugin = '/wp-content/plugins/' . basename( OHMYLMS_DIR ) . '/';
$fixture_static = array(
	$fixture_plugin . 'assets/'           => OHMYLMS_DIR . '/assets/',
	'/wp-includes/js/dist/script-modules/' => ABSPATH . 'wp-includes/js/dist/script-modules/',
	'/smartscore/'                         => OHMYLMS_SS_DIR . 'assets/',
);
foreach ( $fixture_static as $prefix => $base ) {
	if ( 0 !== strpos( $fixture_route, $prefix ) ) {
		continue;
	}
	$root = realpath( $base );
	$file = $root ? realpath( $base . substr( rawurldecode( $fixture_route ), strlen( $prefix ) ) ) : false;
	$type = array(
		'js'    => 'text/javascript',
		'mjs'   => 'text/javascript',
		'css'   => 'text/css',
		'svg'   => 'image/svg+xml',
		'json'  => 'application/json',
		'png'   => 'image/png',
		'woff2' => 'font/woff2',
	)[ strtolower( pathinfo( (string) $file, PATHINFO_EXTENSION ) ) ] ?? null;
	if ( ! $file || ! $type || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_file( $file ) ) {
		http_response_code( 404 );
		exit;
	}
	header( 'Content-Type: ' . $type );
	readfile( $file );
	exit;
}
if ( '/favicon.ico' === $fixture_route ) {
	http_response_code( 204 );
	exit;
}
if ( '/fixture' !== $fixture_route ) {
	http_response_code( 404 );
	exit;
}
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	echo 'Fixture submission received';
	exit;
}

require __DIR__ . '/interactivity/wordpress.php';

// Plugin functions the templates call; the real ones live in includes/Utility and need the database.
function ohmylms_get_template( $name, $args = array() ) {
	if ( is_array( $args ) ) {
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Mirrors ohmylms_get_template().
	}
	ob_start();
	include OHMYLMS_DIR . '/templates/' . $name;
	echo apply_filters( 'ohmylms_template_html', ob_get_clean(), $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates escape their own fields.
}
function ohmylms_get_template_part( $slug, $name = '', $atts = null ) {
	$part = $slug . ( $name ? '-' . $name : '' );
	if ( isset( $GLOBALS['fixture_parts'][ $part ] ) ) {
		call_user_func( $GLOBALS['fixture_parts'][ $part ], $atts );
		return;
	}
	if ( is_array( $atts ) ) {
		extract( $atts ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Mirrors ohmylms_get_template_part().
	}
	include OHMYLMS_DIR . '/templates/' . $slug . ( $name ? '-' . $name : '' ) . '.php';
}
function ohmylms_render_slot( $name, $context = array() ) {
	echo '<div data-test-slot="' . esc_attr( $name ) . '"></div>';
}
function ohmylms_enqueue_interactivity_module( $id ) {
	\OhMyLMS\Extensions\Interactivity::enqueue( $id );
}
function ohmylms_format_duration( $duration ) {
	return '';
}
function ohmylms_get_course_by_content_id( $id ) {
	return 2;
}
function ohmylms_get_next_content_permalink( $id ) {
	return '/next-lesson';
}
function ohmylms_get_question( $id ) {
	return new class() {
		public function get_image_url() {
			return '';
		}
		public function get_video_id() {
			return 0;
		}
	};
}
function ohmylms_get_quiz( $id ) {
	return new class() {
		public function get_name() {
			return 'Fixture quiz';
		}
		public function get_timer() {
			return (float) ( $_GET['timer'] ?? 0 );
		}
		public function get_quiz_attempt( $student ) {
			return array(
				'id'         => 11,
				'start_date' => gmdate( 'Y-m-d H:i:s' ),
			);
		}
		public function get_settings() {
			return array(
				'layout'               => $_GET['layout'] ?? 'one_question_per_page',
				'question_in_one_page' => 2,
				'short_text_limit'     => 5,
			);
		}
		public function get_questions() {
			$questions = array();
			foreach ( explode( ',', $_GET['types'] ?? 'single-choice,short-text,custom-type' ) as $index => $type ) {
				$id          = 101 + $index;
				$questions[] = array(
					'id'        => $id,
					'name'      => 'Question ' . $id,
					'settings'  => array(
						'type'     => $type,
						'required' => true,
					),
					'questions' => array(
						array(
							'id'            => $id * 10,
							'question_id'   => $id,
							'answer'        => 'A',
							'matching_data' => array( 'label' => 'First' ),
						),
						array(
							'id'            => $id * 10 + 1,
							'question_id'   => $id,
							'answer'        => 'B',
							'matching_data' => array( 'label' => 'Second' ),
						),
					),
				);
			}
			return $questions;
		}
	};
}

$GLOBALS['fixture_options'] = array();
add_filter(
	'ohmylms_course_tabs',
	static function () {
		return array(
			'description' => array(
				'title'    => 'Description',
				'callback' => static function () {
					echo 'Description panel';
				},
			),
			'content'     => array(
				'title'    => 'Course content',
				'callback' => static function () {
					echo 'Content panel';
				},
			),
			'addon'       => array(
				'title'    => 'Add-on tab',
				'callback' => static function () {
					echo 'Extension panel';
				},
			),
		);
	}
);
\OhMyLMS\Extensions\Interactivity::init();
\OhMyLMS\Extensions\QuestionTypes::register_defaults();
// Stands in for an add-on question type (the ohmylms-custom-question plugin is not part of this repository).
\OhMyLMS\Extensions\Registry::register(
	'question',
	'custom-type',
	array(
		'label'    => 'Custom',
		'validate' => '__return_true',
		'grade'    => '__return_true',
		'render'   => static function ( $question, $attempt ) {
			printf(
				'<div class="fixture-custom-question"><input type="text" name="attempt[%1$d][quiz_question][%2$d]" aria-label="%3$s"></div>',
				(int) $attempt['id'],
				(int) $question['id'],
				esc_attr( 'Answer to ' . $question['name'] )
			);
		},
	)
);

$fixture_view   = (string) ( $_GET['view'] ?? '' );
$fixture_extra  = array();
$fixture_config = array();
ob_start();
if ( 'practice' === $fixture_view ) {
	if ( ! is_file( OHMYLMS_SS_DIR . 'templates/practice.php' ) ) {
		http_response_code( 501 );
		exit( 'The SmartScore add-on (ohmylms-smartscore) is not installed; set OHMYLMS_SMARTSCORE_DIR.' );
	}
	$fixture_config['ohmylms/smartscore'] = array(
		'root'  => '/api',
		'nonce' => 'fixture',
		'i18n'  => array(
			'error'       => 'Request failed',
			'correctIs'   => 'Correct answer',
			'explanation' => 'Explanation',
			'keepGoing'   => 'Keep practising',
			'gotIt'       => 'Got it',
			'mastered'    => 'Mastered',
			'completed'   => 'Complete',
			'reached'     => 'Reached %d',
			'incorrect'   => 'Incorrect',
			'praise'      => array( 'Correct' ),
		),
	);
	$fixture_extra[] = '/smartscore/interactivity.js';
	$quiz_id         = 10;
	$data            = array(
		'quiz'  => 10,
		'title' => 'Practice fixture',
		'state' => array(
			'score'     => 0,
			'zone'      => 'learning',
			'zoneLabel' => '',
			'code'      => 'A',
		),
		'next'  => '/next-lesson',
	);
	include OHMYLMS_SS_DIR . 'templates/practice.php';
	if ( ! empty( $_GET['multiple'] ) ) {
		$quiz_id      = 20;
		$data['quiz'] = 20;
		include OHMYLMS_SS_DIR . 'templates/practice.php';
	}
	foreach ( array( 'ohmylms/quiz', 'ohmylms/questions' ) as $fixture_module ) {
		wp_enqueue_script_module( $fixture_module );
	}
} elseif ( '' !== $fixture_view ) {
	require __DIR__ . '/interactivity/learner.php';
	if ( ! fixture_learner_view( $fixture_view ) ) {
		ob_end_clean();
		http_response_code( 404 );
		exit( 'Unknown view.' );
	}
} else {
	// The quiz timeout posts here; the spec intercepts `**/expiry`.
	$GLOBALS['fixture_ajax_url'] = '/expiry';
	ohmylms_get_template( 'single-lesson/quiz-form.php', array() );
	echo \OhMyLMS\Extensions\Interactivity::disclosures( '<div class="ohmylms-accordion-item"><div class="ohmylms-accordion-head">Disclosure</div><div class="ohmylms-accordion-body">Details</div></div>', 'fixture.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixture markup.
	ohmylms_get_template( 'global/ohmylms-celebration.php', array() );
	foreach ( array( 'ohmylms/gamification', 'ohmylms/tabs', 'ohmylms/curriculum' ) as $fixture_module ) {
		wp_enqueue_script_module( $fixture_module );
	}
}
do_action( 'wp_footer' );
$fixture_body = ob_get_clean();

// Every ohmylms/* module is mapped (stripe imports ohmylms/checkout, question-player ohmylms/questions).
$fixture_imports = array(
	'@wordpress/interactivity'        => '/wp-includes/js/dist/script-modules/interactivity/index.js',
	'@wordpress/interactivity-router' => '/wp-includes/js/dist/script-modules/interactivity-router/index.js',
	'@wordpress/a11y'                 => '/wp-includes/js/dist/script-modules/a11y/index.js',
);
foreach ( glob( OHMYLMS_DIR . '/assets/interactivity/*.js' ) as $fixture_file ) {
	$fixture_name                                = basename( $fixture_file, '.js' );
	$fixture_imports[ 'ohmylms/' . ( 'sdk' === $fixture_name ? 'interactivity' : $fixture_name ) ] = plugins_url( 'assets/interactivity/' . $fixture_name . '.js' );
}
$fixture_data = wp_interactivity()->filter_script_module_interactivity_data( array() );
if ( $fixture_config ) {
	$fixture_data['config'] = array_merge( $fixture_data['config'] ?? array(), $fixture_config );
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>OhMyLMS fixture</title>
<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'assets/interactivity/frontend.css' ) ); ?>">
<?php foreach ( $GLOBALS['fixture_styles'] ?? array() as $fixture_style ) { ?>
<link rel="stylesheet" href="<?php echo esc_url( plugins_url( $fixture_style ) ); ?>">
<?php } ?>
<style id="ohmylms-tokens"><?php echo \OhMyLMS\Design\Tokens::frontend_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated CSS. ?></style>
<style>[hidden]{display:none!important}.ohmylms-one-question-per-page .ohmylms-quiz-box:not(.active),.ohmylms-question-group:not(.active){display:none}.required-question,.ohmylms-quiz-alert,.ohmylms-quiz-timeup-text{display:none}</style>
<script type="importmap"><?php echo wp_json_encode( array( 'imports' => $fixture_imports ), JSON_UNESCAPED_SLASHES ); ?></script>
<?php if ( $fixture_data ) { ?>
<script type="application/json" id="wp-script-module-data-@wordpress/interactivity"><?php echo wp_json_encode( $fixture_data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ); ?></script>
<?php } ?>
<script type="application/json" id="wp-script-module-data-ohmylms/interactivity"><?php echo wp_json_encode( apply_filters( 'script_module_data_ohmylms/interactivity', array() ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ); ?></script>
</head><body class="ohmylms-page">
<?php echo $fixture_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered templates. ?>
<?php foreach ( array_keys( $GLOBALS['fixture_modules'] ) as $fixture_module ) { ?>
<script type="module" src="<?php echo esc_url( $fixture_imports[ $fixture_module ] ?? '' ); ?>"></script>
<?php } ?>
<?php foreach ( $fixture_extra as $fixture_src ) { ?>
<script type="module" src="<?php echo esc_url( $fixture_src ); ?>"></script>
<?php } ?>
</body></html>
