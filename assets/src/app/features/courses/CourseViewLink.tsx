import { __ } from '@wordpress/i18n';
import type { Course } from './types';

/**
 * WP-admin style "View course" (published) or "Preview" (any other status) link, opened in a new tab.
 * Nothing for a trashed course or when the server gave no address.
 *
 * @param props         Component props.
 * @param props.course  Course with status, permalink and preview link.
 * @param props.compact Short "View" / "Preview" label for a table cell.
 */
export function CourseViewLink( {
	course,
	compact = false,
}: {
	course: Pick< Course, 'status' | 'permalink' | 'preview_url' >;
	compact?: boolean;
} ) {
	if ( 'trash' === course.status ) {
		return null;
	}
	const published = 'publish' === course.status;
	const href = published ? course.permalink : course.preview_url;
	let label: string = __( 'Preview', 'ohmylms' );
	if ( published ) {
		label = compact
			? __( 'View', 'ohmylms' )
			: __( 'View course', 'ohmylms' );
	}
	if ( ! href ) {
		return null;
	}
	return (
		<a
			className={
				compact
					? 'button button-small oml-courses-view'
					: 'button oml-editor-view-link'
			}
			href={ href }
			target="_blank"
			rel="noreferrer"
		>
			{ label }
			<span className="screen-reader-text">
				{ __( '(opens in a new tab)', 'ohmylms' ) }
			</span>
		</a>
	);
}
