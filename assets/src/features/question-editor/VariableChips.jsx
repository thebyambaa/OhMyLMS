/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * The names of the variables a question template defines, in order.
 *
 * @param {Object} settings Question settings.
 * @return {string[]} Variable names.
 */
export const variableNames = ( settings ) =>
	( settings?.template?.variables || [] )
		.map( ( variable ) => String( variable?.name || '' ) )
		.filter( Boolean );

/**
 * Buttons that insert a defined variable into a field, so authors need not type the braces.
 *
 * @param {Object}               props        Component properties.
 * @param {string[]}             props.names  Variable names.
 * @param {(name: string)=>void} props.onPick Called with the chosen name.
 * @return {Element|null} Chips, or nothing when there are no variables.
 */
export function VariableChips( { names, onPick } ) {
	if ( ! names.length ) {
		return null;
	}
	return (
		<div
			className="ohmylms-variable-chips"
			role="group"
			aria-label={ __( 'Insert a variable', 'ohmylms' ) }
		>
			<span>{ __( 'Insert:', 'ohmylms' ) }</span>
			{ names.map( ( name ) => (
				<Button
					key={ name }
					variant="tertiary"
					size="small"
					/* translators: %s: variable name. */ label={
						/* translators: %s: item label. */ sprintf(
							__( 'Insert variable %s', 'ohmylms' ),
							name
						)
					}
					onClick={ () => onPick( name ) }
				>
					{ `{{${ name }}}` }
				</Button>
			) ) }
		</div>
	);
}
