<?php
/**
 * Prints the design-language gallery: every shared component and variant, once with the learner tokens
 * (:root, learner branding defaults) and once with the admin Design system tokens (Khan preset).
 *
 * No WordPress needed: the few functions the Ui helpers call are stubbed. Used by
 * tests/browser/ui-gallery.spec.cjs; also handy to open by hand:
 *   php tests/browser/fixtures/ui-gallery.php > gallery.html
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'OHMYLMS_DIR', dirname( __DIR__, 3 ) );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'gallery' );
/**
 * Return an option fallback in the standalone gallery.
 *
 * @param string $key Option name.
 * @param mixed  $default_value Fallback value.
 * @return mixed
 */
function get_option( $key, $default_value = false ) {
	return $default_value;
}
/**
 * Return fixture text without a translation runtime.
 *
 * @param string $text Original text.
 * @return string
 */
function __( $text ) {
	return $text; }
/**
 * Escape a fixture attribute using PHP's native encoder.
 *
 * @param mixed $v Attribute value.
 * @return string
 */
function esc_attr( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES ); }
/**
 * Escape a fixture text node.
 *
 * @param mixed $v Text value.
 * @return string
 */
function esc_html( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES ); }
/**
 * Return a fixed gallery link URL.
 *
 * @param string $v Fixture URL.
 * @return string
 */
function esc_url( $v ) {
	return $v; }
/**
 * Match the native key sanitization used by the helpers.
 *
 * @param string $k Key to sanitize.
 * @return string
 */
function sanitize_key( $k ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
/**
 * The gallery loads the component CSS directly.
 *
 * @return bool
 */
function wp_style_is() {
	return true; }
/** No-op stylesheet queue for the standalone gallery. */
function wp_enqueue_style() {}
/** Ignore the behavior queue; browser specs load the authored script explicitly. */
function wp_enqueue_script() {}
/**
 * Resolve a fixture asset URL without WordPress.
 *
 * @param string $path Relative asset path.
 * @return string
 */
function plugins_url( $path ) {
	return 'https://example.test/' . $path;
}
/**
 * Generate fixture-local IDs.
 *
 * @param string $prefix ID prefix.
 * @return string
 */
function wp_unique_id( $prefix ) {
	static $next = 0;
	return $prefix . ( ++$next );
}
require OHMYLMS_DIR . '/vendor/autoload.php';

use OhMyLMS\Design\DesignSystem;
use OhMyLMS\Design\Tokens;
use OhMyLMS\Design\Ui;

/**
 * One gallery section's markup.
 *
 * @param string $context Unique ID prefix for this token context.
 * @return string
 */
function oml_gallery_sections( $context = 'learner' ) {
	$buttons = '';
	foreach ( array( 'secondary', 'primary', 'tertiary', 'danger' ) as $variant ) {
		foreach ( array( 'sm', 'md', 'lg' ) as $size ) {
			$buttons .= Ui::button(
				$variant . ' ' . $size,
				array(
					'variant' => $variant,
					'size'    => $size,
				)
			) . ' ';
		}
	}
	$buttons .= Ui::button(
		'disabled',
		array(
			'variant'  => 'primary',
			'disabled' => true,
		)
	) . ' ';
	$buttons .= Ui::button(
		'link',
		array(
			'variant' => 'primary',
			'href'    => '#',
		)
	);

	$badges = Ui::badge( 'neutral' );
	foreach ( array( 'primary', 'success', 'warning', 'danger', 'info' ) as $tone ) {
		$badges .= ' ' . Ui::badge( $tone, array( 'tone' => $tone ) );
	}

	$progress = '';
	foreach ( array( 0, 35, 100 ) as $value ) {
		$progress .= '<p>' . Ui::progress( $value, $value . ' percent' ) . '</p>';
	}
	$progress .= '<p>' . Ui::progress( 70, 'Course progress', array( 'tone' => 'success' ) ) . '</p>';

	$cards = '<div class="gallery-row">'
		. Ui::card( '<h3 class="oml-card-title">Card</h3><p>Surface, border, radius and shadow.</p>' )
		. Ui::card( '<h3 class="oml-card-title">Flat</h3><p>No shadow.</p>', array( 'flat' => true ) )
		. Ui::card( '<h3 class="oml-card-title">Interactive</h3><p>Lifts on hover.</p>', array( 'interactive' => true ) )
		. '</div>';

	$type = '';
	foreach ( array( 'xs', 'sm', 'md', 'lg', 'xl', '2xl' ) as $size ) {
		$type .= '<div style="font-size:var(--oml-font-size-' . $size . ')">Font size ' . $size . '</div>';
	}

	$swatches = '';
	foreach ( array( 'primary', 'secondary', 'success', 'warning', 'danger', 'info', 'heading', 'text', 'muted', 'border', 'surface', 'subtle', 'background' ) as $name ) {
		$swatches .= '<span class="gallery-swatch"><i style="background:var(--oml-' . $name . ')"></i>' . $name . '</span>';
	}

	$controls = '<div class="gallery-controls"><div class="gallery-row">';
	$options  = array(
		array(
			'value' => 'math',
			'label' => 'Mathematics',
		),
		array(
			'value' => 'science',
			'label' => 'Science',
		),
		array(
			'value'    => 'archived',
			'label'    => 'Archived',
			'disabled' => true,
		),
	);
	foreach ( array( 'sm', 'md', 'lg' ) as $size ) {
		$controls .= Ui::input(
			array(
				'id'          => $context . '-input-' . $size,
				'label'       => 'Input ' . $size,
				'size'        => $size,
				'placeholder' => 'Your name',
			)
		);
	}
	$controls .= '</div><div class="gallery-row">';
	foreach ( array( 'sm', 'md', 'lg' ) as $size ) {
		$controls .= Ui::select(
			array(
				'id'      => $context . '-select-' . $size,
				'label'   => 'Select ' . $size,
				'size'    => $size,
				'options' => $options,
				'value'   => 'math',
			)
		);
	}
	$controls .= '</div><div class="gallery-row">'
		. Ui::input(
			array(
				'id'       => $context . '-email',
				'label'    => 'Email address',
				'type'     => 'email',
				'required' => true,
				'value'    => 'not-an-email',
				'help'     => 'Use your school email.',
				'error'    => 'Enter a valid email address.',
			)
		)
		. Ui::input(
			array(
				'id'       => $context . '-disabled',
				'label'    => 'Disabled',
				'value'    => 'Unavailable',
				'disabled' => true,
			)
		)
		. Ui::input(
			array(
				'id'       => $context . '-readonly',
				'label'    => 'Read only',
				'value'    => 'Student 1024',
				'readonly' => true,
			)
		)
		. '</div><div class="gallery-row">'
		. Ui::select(
			array(
				'id'          => $context . '-required',
				'label'       => 'Required subject',
				'required'    => true,
				'placeholder' => 'Choose a subject',
				'options'     => $options,
			)
		)
		. Ui::select(
			array(
				'id'       => $context . '-select-disabled',
				'label'    => 'Disabled select',
				'disabled' => true,
				'value'    => 'math',
				'options'  => $options,
			)
		)
		. Ui::select(
			array(
				'id'       => $context . '-multiple',
				'label'    => 'Multiple subjects',
				'multiple' => true,
				'value'    => array( 'math', 'science' ),
				'options'  => $options,
			)
		)
		. '</div></div>';

	$sections = array(
		'buttons'  => $buttons,
		'badges'   => $badges,
		'progress' => $progress,
		'cards'    => $cards,
		'type'     => $type,
		'colors'   => $swatches,
		'controls' => $controls,
	);
	$alerts   = '<div style="display:grid;gap:var(--oml-space-3)">';
	foreach ( array( 'info', 'success', 'warning', 'danger' ) as $tone ) {
		$alerts .= Ui::alert(
			'Your course progress is saved. You can continue from any device.',
			array(
				'tone'  => $tone,
				'title' => ucfirst( $tone ),
			)
		);
	}
	$alerts                 .= Ui::alert( 'A message without a title.', array( 'role' => 'status' ) );
	$alerts                 .= Ui::alert(
		'<script>alert("unsafe")</script>',
		array(
			'tone'  => 'bogus',
			'title' => '<em>Plain text</em>',
			'attrs' => array(
				'data-test' => 'escaped-alert',
				'onclick'   => 'alert(1)',
				'style'     => 'display:none',
			),
		)
	);
	$sections['alerts']      = $alerts . '</div>';
	$sections['tabs']        = Ui::tabs(
		array(
			array(
				'id'           => 'overview',
				'label'        => 'Overview',
				'content_html' => '<p>Course overview.</p>',
			),
			array(
				'id'           => 'locked',
				'label'        => 'Locked',
				'disabled'     => true,
				'content_html' => '<p>Unavailable content.</p>',
			),
			array(
				'id'           => 'lessons',
				'label'        => 'Lessons',
				'content_html' => '<p>Course lessons.</p>',
			),
		),
		array( 'label' => 'Course details' )
	);
	$sections['table']       = Ui::table(
		array( 'Course', 'Status', 'Progress' ),
		array( array( 'Mathematics', 'Published', '60%' ), array( 'Science', 'Draft', '0%' ) ),
		array(
			'caption' => 'Courses',
			'striped' => true,
		)
	);
	$sections['empty-state'] = Ui::empty_state(
		'No lessons yet',
		array(
			'description'  => 'Create a lesson to begin.',
			'actions_html' => Ui::button( 'Create lesson', array( 'variant' => 'primary' ) ),
		)
	);
	$sections['modal']       = Ui::button( 'Open dialog', array( 'attrs' => array( 'data-oml-dialog-open' => $context . '-modal' ) ) )
		. Ui::modal(
			'<p>Review the course settings.</p><label>Course name <input id="' . esc_attr( $context . '-modal-input' ) . '" class="oml-control oml-input" name="title"></label>',
			array(
				'id'    => $context . '-modal',
				'title' => 'Course settings',
			)
		);
	$html                    = '';
	foreach ( $sections as $name => $markup ) {
		$html .= '<section data-gallery="' . $name . '"><h2>' . ucfirst( $name ) . '</h2>' . $markup . '</section>';
	}
	return $html;
}

$learner_vars = ':root{' . Tokens::frontend_oml_vars() . '}';
$admin_vars   = str_replace( 'body.' . DesignSystem::BODY_CLASS, '.gallery-admin', DesignSystem::css() );

echo '<!doctype html><html><head><meta charset="utf-8"><title>OhMyLMS design language</title><style>'
	. file_get_contents( OHMYLMS_DIR . '/assets/css/oml-ui.css' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.Security.EscapeOutput.OutputNotEscaped -- Local component CSS is intentionally rendered as stylesheet text.
	. $learner_vars . $admin_vars // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- These token generators emit validated CSS declarations.
	. '*,*::before,*::after{animation:none!important;transition:none!important}'
	. 'body{font-family:Arial,Helvetica,sans-serif;font-size:14px;margin:0}'
	. '.gallery-context{background:var(--oml-background);color:var(--oml-text);padding:24px;width:720px}'
	. '.gallery-context h1{color:var(--oml-heading);font-size:20px;margin:0 0 16px}'
	. 'section{margin-bottom:24px;padding-bottom:8px}section h2{color:var(--oml-heading);font-size:13px;margin:0 0 8px;text-transform:uppercase}'
	. '.gallery-row{display:flex;gap:16px}.gallery-row>*{flex:1}'
	. '.gallery-controls{display:grid;gap:16px}'
	// An integral section height preserves existing admin screenshot crop positions.
	. 'section[data-gallery="controls"]{min-height:440px}'
	. '.gallery-swatch{display:inline-flex;align-items:center;gap:6px;margin:0 12px 8px 0}.gallery-swatch i{border:1px solid var(--oml-border);display:inline-block;height:20px;width:20px}'
	. '</style></head><body>';
foreach ( array(
	'learner' => 'Learner (:root)',
	'admin'   => 'Admin (Khan preset)',
) as $context => $context_title ) {
	echo '<main class="gallery-context' . ( 'admin' === $context ? ' gallery-admin' : '' ) . '" data-context="' . esc_attr( $context ) . '"><h1>' . esc_html( $context_title ) . '</h1>' . oml_gallery_sections( $context ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sections combine escaped Ui helpers and fixed fixture markup.
}
echo '</body></html>';
