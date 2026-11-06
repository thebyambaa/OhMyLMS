import { __ } from '@wordpress/i18n';
import { Branding } from './Branding';

/**
 * Settings: general note, branding, mascot and the role rules already enforced by the server.
 *
 * @param props        Props.
 * @param props.school Selected school.
 * @param props.pane   general, branding, mascot or roles.
 */
export function SettingsPanel( {
	school,
	pane,
}: {
	school: number;
	pane: string;
} ) {
	if ( 'general' === pane ) {
		return (
			<section>
				<h2>{ __( 'General', 'ohmylms' ) }</h2>
				<p className="oml-dash-note">
					{ __(
						'The school and the period in the top bar apply to Home. WordPress settings, plugins and updates stay in wp-admin.',
						'ohmylms'
					) }
				</p>
			</section>
		);
	}
	if ( 'roles' === pane ) {
		return (
			<section>
				<h2>{ __( 'Roles & permissions', 'ohmylms' ) }</h2>
				<p className="oml-dash-note">
					{ __(
						'A site owner sees every school. A school admin sees only their school. A teacher sees only their classes and the content they can edit. WordPress capabilities and school memberships decide this. There is no separate role table.',
						'ohmylms'
					) }
				</p>
			</section>
		);
	}
	return (
		<Branding
			school={ school }
			focus={ 'mascot' === pane ? 'mascot' : 'branding' }
		/>
	);
}
