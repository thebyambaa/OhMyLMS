import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';

/**
 * Catch-all route.
 *
 * @param props        Component props.
 * @param props.homeTo Path of the page to offer as a way back.
 */
export function NotFound( { homeTo }: { homeTo: string } ) {
	return (
		<>
			<h2>{ __( 'Page not found', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'This page does not exist or you cannot open it.',
					'ohmylms'
				) }
			</p>
			<p>
				<Link to={ homeTo }>
					{ __( 'Go to the start page', 'ohmylms' ) }
				</Link>
			</p>
		</>
	);
}
