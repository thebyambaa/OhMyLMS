import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SkillPicker } from './SkillPicker';
import type { SkillInput, SkillSummary } from './api';

export function SkillEditor( {
	item,
	busy,
	error,
	onClose,
	onSave,
}: {
	item: SkillSummary | null;
	busy: boolean;
	error: string | null;
	onClose: () => void;
	onSave: ( value: SkillInput ) => void;
} ) {
	const [ value, setValue ] = useState< SkillInput >(
		item
			? { ...item, prerequisites: [ ...item.prerequisites ] }
			: {
					name: '',
					code: '',
					description: '',
					parent: 0,
					prerequisites: [],
				}
	);
	return (
		<Modal
			title={
				item
					? __( 'Edit skill', 'ohmylms' )
					: __( 'Add skill', 'ohmylms' )
			}
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( ! busy ) {
						onSave( value );
					}
				} }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<fieldset disabled={ busy }>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ value.name }
						required
						maxLength={ 200 }
						onChange={ ( name ) =>
							setValue( ( current ) => ( { ...current, name } ) )
						}
					/>
					<TextControl
						label={ __( 'Code', 'ohmylms' ) }
						value={ value.code }
						maxLength={ 40 }
						onChange={ ( code ) =>
							setValue( ( current ) => ( { ...current, code } ) )
						}
					/>
					<TextareaControl
						label={ __( 'Description', 'ohmylms' ) }
						value={ value.description }
						maxLength={ 10000 }
						onChange={ ( description ) =>
							setValue( ( current ) => ( {
								...current,
								description,
							} ) )
						}
					/>
					<SkillPicker
						label={ __( 'Parent', 'ohmylms' ) }
						selected={ value.parent ? [ value.parent ] : [] }
						multiple={ false }
						exclude={ item?.id ?? null }
						disabled={ busy }
						onChange={ ( ids ) =>
							setValue( ( current ) => ( {
								...current,
								parent: ids[ 0 ] ?? 0,
							} ) )
						}
					/>
					<SkillPicker
						label={ __( 'Prerequisites', 'ohmylms' ) }
						selected={ value.prerequisites }
						multiple
						exclude={ item?.id ?? null }
						disabled={ busy }
						onChange={ ( prerequisites ) =>
							setValue( ( current ) => ( {
								...current,
								prerequisites,
							} ) )
						}
					/>
					<Button
						variant="primary"
						type="submit"
						disabled={ busy || ! value.name.trim() }
						isBusy={ busy }
					>
						{ __( 'Save skill', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ busy }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</fieldset>
			</form>
		</Modal>
	);
}
