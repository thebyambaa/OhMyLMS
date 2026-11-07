import { Notice } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { blockEditorUrl } from './editUrl';

/**
 * `#/content-hub/lessons/:id` (and the old `tab=lessons&id=` address) open the lesson in the WordPress block
 * editor instead of a page of their own.
 */
export default function LessonRedirect() {
	const { id } = useParams();
	const lesson = id && /^[1-9]\d*$/.test( id ) ? Number( id ) : null;
	useEffect( () => {
		if ( null !== lesson ) {
			window.location.replace( blockEditorUrl( lesson ) );
		}
	}, [ lesson ] );
	if ( null === lesson ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This lesson link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/content-hub/lessons">
					{ __( 'Back to lessons', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	return (
		<p role="status">{ __( 'Opening the block editor…', 'ohmylms' ) }</p>
	);
}
