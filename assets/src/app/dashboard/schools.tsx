import { __ } from '@wordpress/i18n';
import { boot } from './api';

/** Schools the owner can see. School admins only have their own school in the picker. */
export function Schools() {
	const schools = boot().schools;
	return (
		<section>
			<h2>{ __( 'Schools', 'ohmylms' ) }</h2>
			{ schools.length ? (
				<ul>
					{ schools.map( ( school ) => (
						<li key={ school.id }>{ school.name }</li>
					) ) }
				</ul>
			) : (
				<p className="oml-dash-note">
					{ __( 'No schools yet.', 'ohmylms' ) }
				</p>
			) }
		</section>
	);
}
