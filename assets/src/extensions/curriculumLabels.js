import { __ } from '@wordpress/i18n';
import { relabel } from './labels.mjs';

/**
 * Translate a replacement label. Literal calls keep the strings visible to translation tooling.
 * @param text
 */
function translate( text ) {
	switch ( text ) {
		case 'Curriculum':
			return __( 'Curriculum', 'ohmylms' );
		case 'Curriculum: ':
			return __( 'Curriculum:', 'ohmylms' ) + ' ';
		case 'Show the curriculum as tabs.':
			return __( 'Show the curriculum as tabs.', 'ohmylms' );
		case 'Course bundles':
			return __( 'Course bundles', 'ohmylms' );
		case 'Course bundle':
			return __( 'Course bundle', 'ohmylms' );
		case 'Course bundle: ':
			return __( 'Course bundle:', 'ohmylms' ) + ' ';
		default:
			return text;
	}
}

/**
 * Show "Curriculum" and "Course bundles" wherever the recovered admin app still says category or tag.
 * `wp.hooks` is read from the global, not imported, because wp-i18n already loads it first and the SDK
 * must keep working (without relabeling) on pages that provide only part of `wp`.
 */
export function registerCurriculumLabels() {
	const hooks = typeof window !== 'undefined' && window.wp && window.wp.hooks;
	if ( ! hooks || typeof hooks.addFilter !== 'function' ) {
		return;
	}
	hooks.addFilter(
		'i18n.gettext_ohmylms',
		'ohmylms/curriculum-labels',
		( translation, text ) => relabel( translation, text, translate )
	);
}
