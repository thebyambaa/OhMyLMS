import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { saveItem, type CurriculumItem, type ItemInput } from './api';

const FALLBACK_TYPES = [ 'subject', 'syllabus', 'grade', 'unit', 'topic' ];

function label( type: string ) {
	return type.charAt( 0 ).toUpperCase() + type.slice( 1 );
}

function inputOf( item: CurriculumItem | null ): ItemInput {
	return {
		name: item?.name ?? '',
		item_type: item?.item_type ?? 'topic',
		description: item?.description ?? '',
		code: item?.code ?? '',
		version: item?.version ?? '',
		is_syllabus: item?.is_syllabus ?? false,
		icon: item?.icon ?? '',
	};
}

/**
 * Add an item under a parent (or at the top), or edit one. The item is saved when the form is.
 *
 * @param props            Component props.
 * @param props.item       The item to edit, or null to add one.
 * @param props.parent     Parent of a new item, 0 for the top level.
 * @param props.parentName Name of the parent, shown above a new item.
 * @param props.types      Item types the site suggests.
 * @param props.onClose    Close without saving.
 * @param props.onSaved    Called with the saved item.
 */
export function ItemDialog( {
	item,
	parent,
	parentName,
	types,
	onClose,
	onSaved,
}: {
	item: CurriculumItem | null;
	parent: number;
	parentName: string;
	types: readonly string[];
	onClose: () => void;
	onSaved: ( saved: CurriculumItem ) => void;
} ) {
	const [ input, setInput ] = useState< ItemInput >( () => inputOf( item ) );
	const operation = useOperation();
	const choices = Array.from(
		new Set( [
			...( types.length ? types : FALLBACK_TYPES ),
			input.item_type,
		] )
	);
	const set = ( patch: Partial< ItemInput > ) =>
		setInput( ( current ) => ( { ...current, ...patch } ) );
	function submit() {
		void operation.run(
			( signal ) => saveItem( input, item, parent, signal ),
			onSaved
		);
	}
	return (
		<Modal
			title={
				item
					? __( 'Edit curriculum item', 'ohmylms' )
					: __( 'Add curriculum item', 'ohmylms' )
			}
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ false }
		>
			<form
				className="oml-cw-form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				{ ! item && (
					<p>
						{ parent
							? sprintf(
									/* translators: %s: name of the parent item. */
									__( 'Inside %s', 'ohmylms' ),
									parentName
								)
							: __( 'At the top level.', 'ohmylms' ) }
					</p>
				) }
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				<TextControl
					label={ __( 'Name', 'ohmylms' ) }
					value={ input.name }
					maxLength={ 190 }
					required
					onChange={ ( name ) => set( { name } ) }
				/>
				<SelectControl
					label={ __( 'Kind', 'ohmylms' ) }
					value={ input.item_type }
					options={ choices.map( ( type ) => ( {
						value: type,
						label: label( type ),
					} ) ) }
					onChange={ ( itemType ) =>
						set( {
							item_type: itemType,
							is_syllabus:
								'syllabus' === itemType
									? true
									: input.is_syllabus,
						} )
					}
				/>
				<CheckboxControl
					label={ __(
						'This is a syllabus: it holds skill groups and skills',
						'ohmylms'
					) }
					checked={ input.is_syllabus }
					onChange={ ( isSyllabus ) =>
						set( { is_syllabus: isSyllabus } )
					}
				/>
				<TextControl
					label={ __( 'Code', 'ohmylms' ) }
					value={ input.code }
					onChange={ ( code ) => set( { code } ) }
				/>
				<TextControl
					label={ __( 'Version', 'ohmylms' ) }
					value={ input.version }
					onChange={ ( version ) => set( { version } ) }
				/>
				<TextareaControl
					label={ __( 'Description', 'ohmylms' ) }
					value={ input.description }
					rows={ 3 }
					onChange={ ( description ) => set( { description } ) }
				/>
				<div className="oml-cw-actions">
					<Button
						variant="primary"
						type="submit"
						isBusy={ operation.busy }
						disabled={ operation.busy || ! input.name.trim() }
					>
						{ item
							? __( 'Save', 'ohmylms' )
							: __( 'Add item', 'ohmylms' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}
