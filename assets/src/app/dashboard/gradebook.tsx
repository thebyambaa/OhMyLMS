import { __ } from '@wordpress/i18n';
import { boot } from './api';

/** Opens the existing gradebook. The empty “all courses” scope no longer fatals. */
export function Gradebook() {
	return (
		<section>
			<h2>{ __( 'Gradebook', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Course grades, the roster and mastery open in the gradebook.',
					'ohmylms'
				) }
			</p>
			<p>
				<a
					className="oml-dash-btn is-primary"
					href={ boot().links.gradebook }
				>
					{ __( 'Open gradebook', 'ohmylms' ) }
				</a>
			</p>
		</section>
	);
}
