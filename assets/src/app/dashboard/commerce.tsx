import { __ } from '@wordpress/i18n';
import { boot } from './api';

/** Commerce stays in wp-admin for the site owner. */
export function Commerce() {
	return (
		<section>
			<h2>{ __( 'Commerce', 'ohmylms' ) }</h2>
			<p>
				<a href={ boot().links.commerce }>
					{ __( 'Open commerce in wp-admin', 'ohmylms' ) }
				</a>
			</p>
		</section>
	);
}
