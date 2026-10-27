import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { RelationshipBadge } from './RelationshipBadge';

/**
 * Show saved relationships without changing skill or placement identities.
 *
 * @param {Object} props       View properties.
 * @param {Object} props.skill Placed skill and its saved connections.
 * @param {string} props.part  All details, names, or types for spreadsheet columns.
 * @return {Object|null} Connected skill names with source-oriented relationship labels.
 */
export function SkillConnections( { skill, part = 'all' } ) {
	if ( ! skill?.connections?.length ) {
		return null;
	}
	return (
		<span
			className="ohmylms-skill-connections"
			aria-label={ __( 'Connected skills', 'ohmylms' ) }
		>
			{ skill.connections.map( ( mapping, index ) => {
				const outgoing =
					Number( mapping.specific_id ) === Number( skill.term_id );
				const connected = outgoing ? mapping.shared : mapping.specific;
				return (
					<span
						className="ohmylms-skill-connection"
						key={ mapping.id }
						title={ mapping.note || undefined }
					>
						{ part !== 'all' && <span>{ index + 1 }. </span> }
						{ part !== 'types' && (
							<span>
								{ outgoing
									? __( 'Shared skill:', 'ohmylms' )
									: __( 'Source skill:', 'ohmylms' ) }{ ' ' }
								{ connected
									? [ connected.code, connected.name ]
											.filter( Boolean )
											.join( ' · ' )
									: __( 'Unavailable skill', 'ohmylms' ) }
							</span>
						) }
						{ part !== 'names' && (
							<RelationshipBadge relation={ mapping.relation } />
						) }
					</span>
				);
			} ) }
		</span>
	);
}
