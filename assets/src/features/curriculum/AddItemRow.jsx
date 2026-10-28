import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import { useCurriculum } from './context';
import { validateDraft } from './model.mjs';
const MESSAGES = {
	required: () => __( 'Enter a name.', 'ohmylms' ),
	'too-long': () => __( 'That is too long.', 'ohmylms' ),
	invalid: () =>
		__(
			'Start the type with a letter; use lowercase letters, numbers and hyphens.',
			'ohmylms'
		),
};

/**
 * Inline row for adding a root item (parentId 0) or a child. It stays open after each add so a
 * list of siblings can be entered quickly, and reports the outcome in the page's status area.
 * @param root0
 * @param root0.parentId
 * @param root0.parentName
 */
export function AddItemRow( { parentId, parentName } ) {
	const { actions, pending } = useCurriculum();
	const [ name, setName ] = useState( '' );
	const [ touched, setTouched ] = useState( false );
	const errors = validateDraft( {
		name,
		item_type: 'custom',
	} );
	const nameId = `ohmylms-cur-add-name-${ parentId }`;
	const label = parentId
		? sprintf(
				/* translators: %s: curriculum item name. */ __(
					'Name of the new item under %s',
					'ohmylms'
				),
				parentName
			)
		: __( 'Name of the new top-level item', 'ohmylms' );
	async function submit( event ) {
		event?.preventDefault();
		setTouched( true );
		if ( Object.keys( errors ).length || pending ) {
			return;
		}
		const ok = await actions.create( parentId, {
			name,
			item_type: 'custom',
		} );
		if ( ok ) {
			setName( '' );
			setTouched( false );
			document.getElementById( nameId )?.focus();
		}
	}
	return (
		<form className="ohmylms-cur-add" onSubmit={ submit } noValidate>
			<TextControl
				id={ nameId }
				label={ label }
				value={ name }
				onChange={ setName }
				help={
					touched && errors.name
						? MESSAGES[ errors.name ]()
						: undefined
				}
				__nextHasNoMarginBottom
			/>

			<div className="ohmylms-cur-add-actions">
				<Button
					variant="primary"
					type="submit"
					isBusy={ pending }
					disabled={ pending }
				>
					{ parentId
						? __( 'Add child', 'ohmylms' )
						: __( 'Add top-level item', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => actions.cancelAdd() }
				>
					{ __( 'Done', 'ohmylms' ) }
				</Button>
			</div>
		</form>
	);
}
