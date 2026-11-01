import {
	Button,
	Modal,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { CategoryStyles, type Styles } from './CategoryStyles';
import { HEX_COLOR, type SyllabusOutline } from './model';

type SourceFields = {
	name: string;
	code: string;
	version: string;
	description: string;
	subject: string;
	grade: string;
	language: string;
	categories: string;
};
const PROFILE = [
	'subject',
	'grade',
	'language',
	'categories',
	'category_styles',
];

function categoryList( value: string ) {
	return value
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean );
}

/**
 * The styles of the listed categories, keyed in category order with lowercase colours, as
 * `SyllabusSettings` stores them.
 *
 * @param styles     Styles by category.
 * @param categories Categories in order.
 */
function stylesFor( styles: Styles, categories: string[] ): Styles {
	return Object.fromEntries(
		categories.flatMap( ( category ) => {
			const style = styles[ category ];
			return style
				? [
						[
							category,
							{
								icon: style.icon,
								color: style.color.toLowerCase(),
							},
						],
					]
				: [];
		} )
	);
}

/**
 * The syllabus's source context (name, code, edition and notes) and its profile (subject, grade,
 * language, skill categories and their icon/colour styles). Only changed fields are sent; the profile
 * is stored with the syllabus course, so it is read-only until that course exists.
 *
 * @param props         Dialog properties.
 * @param props.data    Current outline.
 * @param props.busy    Whether a command is running.
 * @param props.error   Last command error.
 * @param props.onSave  Send the changed fields; resolves true when saved.
 * @param props.onClose Close the dialog.
 */
export function SourceDialog( {
	data,
	busy,
	error,
	onSave,
	onClose,
}: {
	data: SyllabusOutline;
	busy: boolean;
	error: string;
	onSave: ( fields: Record< string, unknown > ) => Promise< boolean >;
	onClose: () => void;
} ) {
	const original: SourceFields = {
		name: data.syllabus.name,
		code: data.syllabus.code,
		version: data.syllabus.version,
		description: data.syllabus.description,
		subject: data.settings.subject,
		grade: data.settings.grade,
		language: data.settings.language,
		categories: data.settings.categories.join( '\n' ),
	};
	const [ fields, setFields ] = useState( original );
	const [ styles, setStyles ] = useState< Styles >(
		data.settings.category_styles
	);
	const [ discard, setDiscard ] = useState( false );
	const categories = categoryList( fields.categories );
	const edits: Record< string, unknown > = Object.fromEntries(
		( Object.keys( original ) as ( keyof SourceFields )[] )
			.filter( ( key ) =>
				'categories' === key
					? categories.join( '\n' ) !== original.categories
					: fields[ key ].trim() !== original[ key ].trim()
			)
			.map( ( key ) => [
				key,
				'categories' === key ? categories : fields[ key ].trim(),
			] )
	);
	const kept = stylesFor( styles, categories );
	if (
		JSON.stringify( kept ) !==
		JSON.stringify( stylesFor( data.settings.category_styles, categories ) )
	) {
		edits.category_styles = kept;
	}
	const changed = Object.keys( edits );
	const badColour = Object.values( kept ).some(
		( style ) => ! HEX_COLOR.test( style.color )
	);
	const guard = useUnsavedChangesGuard( changed.length > 0 );
	const profile = !! data.course;
	const field = ( key: keyof SourceFields ) => ( value: string ) =>
		setFields( { ...fields, [ key ]: value } );
	function close() {
		if ( ! busy ) {
			if ( changed.length ) {
				setDiscard( true );
			} else {
				onClose();
			}
		}
	}
	return (
		<Modal
			title={ __( 'Source and profile', 'ohmylms' ) }
			onRequestClose={ close }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void onSave( edits ).then( ( saved ) => {
						if ( saved ) {
							onClose();
						}
					} );
				} }
			>
				<fieldset disabled={ busy }>
					<legend>{ __( 'Source', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ fields.name }
						maxLength={ 190 }
						onChange={ field( 'name' ) }
					/>
					<TextControl
						label={ __( 'Syllabus code', 'ohmylms' ) }
						value={ fields.code }
						maxLength={ 60 }
						onChange={ field( 'code' ) }
					/>
					<TextControl
						label={ __( 'Version or edition', 'ohmylms' ) }
						value={ fields.version }
						maxLength={ 60 }
						help={ __(
							'The source edition this outline follows, for example the exam year.',
							'ohmylms'
						) }
						onChange={ field( 'version' ) }
					/>
					<TextareaControl
						label={ __( 'Description', 'ohmylms' ) }
						value={ fields.description }
						maxLength={ 2000 }
						onChange={ field( 'description' ) }
					/>
				</fieldset>
				<fieldset disabled={ busy || ! profile }>
					<legend>{ __( 'Profile', 'ohmylms' ) }</legend>
					{ ! profile && (
						<p>
							{ __(
								'The profile is stored with the syllabus course. Create that course to edit it.',
								'ohmylms'
							) }
						</p>
					) }
					<TextControl
						label={ __( 'Subject', 'ohmylms' ) }
						value={ fields.subject }
						maxLength={ 100 }
						onChange={ field( 'subject' ) }
					/>
					<TextControl
						label={ __( 'Grade or level', 'ohmylms' ) }
						value={ fields.grade }
						maxLength={ 100 }
						onChange={ field( 'grade' ) }
					/>
					<TextControl
						label={ __( 'Language', 'ohmylms' ) }
						value={ fields.language }
						maxLength={ 100 }
						onChange={ field( 'language' ) }
					/>
					<TextareaControl
						label={ __( 'Skill categories', 'ohmylms' ) }
						value={ fields.categories }
						help={ __(
							'One per line, at most 50. A category still used by a skill cannot be removed.',
							'ohmylms'
						) }
						onChange={ field( 'categories' ) }
					/>
					<CategoryStyles
						categories={ categories }
						styles={ styles }
						onChange={ setStyles }
					/>
				</fieldset>
				{ ( data.roadmap_sources.length > 0 ||
					'roadmap' === data.syllabus.item_type ) && (
					<section aria-label={ __( 'Roadmap sources', 'ohmylms' ) }>
						<h3>{ __( 'Roadmap sources', 'ohmylms' ) }</h3>
						<ul>
							{ data.roadmap_sources.map( ( source ) => (
								<li key={ source.id }>
									{ source.name } { source.version }
								</li>
							) ) }
						</ul>
						<p>
							<Link to="/content-hub/curriculum/compare">
								{ __(
									'Change roadmap sources in Compare',
									'ohmylms'
								) }
							</Link>
						</p>
					</section>
				) }
				<Button
					variant="primary"
					type="submit"
					disabled={
						busy ||
						! changed.length ||
						! fields.name.trim() ||
						badColour ||
						( ! profile &&
							changed.some( ( key ) => PROFILE.includes( key ) ) )
					}
					isBusy={ busy }
				>
					{ __( 'Save source and profile', 'ohmylms' ) }
				</Button>
			</form>
			{ discard && (
				<div role="alert">
					<p>{ __( 'Discard your unsaved changes?', 'ohmylms' ) }</p>
					<Button onClick={ () => setDiscard( false ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ onClose }>
						{ __( 'Discard changes', 'ohmylms' ) }
					</Button>
				</div>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Leave with unsaved changes?', 'ohmylms' ) }
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
