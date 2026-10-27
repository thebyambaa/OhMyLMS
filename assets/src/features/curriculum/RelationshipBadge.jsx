/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { relationshipLabels } from './relationships.mjs';

/**
 * Colour is accompanied by a text label and an unsaved state.
 * @param {Object}  props          Badge props.
 * @param {string}  props.relation Relationship code.
 * @param {boolean} props.pending  Unsaved proposal.
 */
export function RelationshipBadge( { relation, pending = false } ) {
	return (
		<span
			className={ `ohmylms-relationship-badge is-${ relation }${ pending ? ' is-pending' : '' }` }
		>
			{ relationshipLabels()[ relation ] || relation }
			{ pending && <strong> · { __( 'Unsaved', 'ohmylms' ) }</strong> }
		</span>
	);
}
