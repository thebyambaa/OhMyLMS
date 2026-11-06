import { __ } from '@wordpress/i18n';
import { boot } from './api';

/** Reports stay on the existing screens. Home exports the drill you are viewing. */
export function Reports() {
	return (
		<section>
			<h2>{ __( 'Reports', 'ohmylms' ) }</h2>
			<p className="oml-dash-note">
				{ __(
					'School and quiz reports stay on their existing screens. Home → Export downloads the drill you are viewing.',
					'ohmylms'
				) }
			</p>
			<p>
				<a href={ boot().links.reports }>
					{ __( 'Open quiz reports', 'ohmylms' ) }
				</a>
			</p>
		</section>
	);
}
