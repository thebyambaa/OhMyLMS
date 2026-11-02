import { __ } from '@wordpress/i18n';
import type { Course } from './types';

/**
 * WP-admin style "View course" (published) or "Preview" (any other status) link, opened in a new tab.
 * Nothing for a trashed course or when the server gave no address.
 *
 * @param props        Component props.
 * @param props.course Course with status, permalink and preview link.
 */
export function CourseViewLink( {
	course,
}: {
	course: Pick< Course, 'status' | 'permalink' | 'preview_url' >;
} ) {
	if ( 'trash' === course.status ) {
		return null;
	}
	const published = 'publish' === course.status;
	const href = published ? course.permalink : course.preview_url;
	if ( ! href ) {
		return null;
	}
	return (
		<a
			className="button oml-editor-view-link"
			href={ href }
			target="_blank"
			rel="noreferrer"
		>
			{ published
				? __( 'View course', 'ohmylms' )
				: __( 'Preview', 'ohmylms' ) }
			<span className="screen-reader-text">
				{ __( '(opens in a new tab)', 'ohmylms' ) }
			</span>
		</a>
	);
}
