import {
	Button,
	Modal,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import type { Fields } from './model';

export function FieldsDialog( {
	title,
	initial,
	busy,
	error,
	shared,
	onSave,
	onClose,
}: {
	title: string;
	initial: Fields;
	busy: boolean;
	error: string;
	shared?: boolean;
	onSave: ( fields: Fields ) => Promise< boolean >;
	onClose: () => void;
} ) {
	const original = {
		name: initial.name,
		code: initial.code,
		description: initial.description,
	};
	const [ fields, setFields ] = useState( original );
	const [ discard, setDiscard ] = useState( false );
	const dirty = JSON.stringify( fields ) !== JSON.stringify( original );
	const guard = useUnsavedChangesGuard( dirty );
	function close() {
		if ( ! busy ) {
			if ( dirty ) {
				setDiscard( true );
			} else {
				onClose();
			}
		}
	}
	return (
		<Modal title={ title } onRequestClose={ close }>
			{ shared && (
				<p>
					{ __(
						'Editing this skill changes its library record everywhere it is used.',
						'ohmylms'
					) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void onSave( fields ).then( ( saved ) => {
						if ( saved ) {
							onClose();
						}
					} );
				} }
			>
				<fieldset disabled={ busy }>
					<legend>{ __( 'Details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ fields.name }
						maxLength={ 190 }
						onChange={ ( name ) =>
							setFields( { ...fields, name } )
						}
					/>
					<TextControl
						label={ __( 'Code', 'ohmylms' ) }
						value={ fields.code }
						maxLength={ shared ? 40 : 60 }
						onChange={ ( code ) =>
							setFields( { ...fields, code } )
						}
					/>
					<TextareaControl
						label={ __( 'Notes', 'ohmylms' ) }
						value={ fields.description }
						maxLength={ 2000 }
						onChange={ ( description ) =>
							setFields( { ...fields, description } )
						}
					/>
					<Button
						variant="primary"
						type="submit"
						disabled={ ! fields.name.trim() }
						isBusy={ busy }
					>
						{ __( 'Save details', 'ohmylms' ) }
					</Button>
				</fieldset>
			</form>
			{ discard && (
				<div role="alert">
					<p>{ __( 'Discard your unsaved details?', 'ohmylms' ) }</p>
					<Button onClick={ () => setDiscard( false ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ onClose }>
						{ __( 'Discard details', 'ohmylms' ) }
					</Button>
				</div>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Leave with unsaved details?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.leave }>
						{ __( 'Discard and leave', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</Modal>
	);
}
