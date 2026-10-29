import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCurriculum } from './context';
import { DeleteItem } from './DeleteItem';
import { LinkedContent } from './LinkedContent';
import { SubjectCourseOrder } from './SubjectCourseOrder';
import { SkillMappings } from './SkillMappings';
import { SyllabusSummary } from './SyllabusSummary';
import * as api from './api.mjs';
import {
	draftFrom,
	isDirty,
	moveTargets,
	pathNames,
	payloadFrom,
	validateDraft,
} from './model.mjs';
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
 * Expanded panel under a row: details, position, linked content, skill mappings and deletion.
 * @param root0
 * @param root0.id
 * @param root0.item
 */
export function ItemPanel( { id, item } ) {
	const c = useCurriculum();
	const [ draft, setDraft ] = useState( () => draftFrom( item ) );
	const [ touched, setTouched ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ parent, setParent ] = useState( String( item.parent_id || 0 ) );
	const [ detail, setDetail ] = useState( null );
	const [ detailError, setDetailError ] = useState( '' );
	const errors = validateDraft( draft );
	const dirty = isDirty( draft, item );
	// The syllabus switch saves at once. It shows the new state while the save runs, then follows the
	// saved item again (which also puts it back if the server refused, e.g. the syllabus still has groups).
	const [ syllabusFlag, setSyllabusFlag ] = useState( null );
	async function toggleSyllabus( value ) {
		setSyllabusFlag( value );
		await c.actions.update( item, {
			is_syllabus: value,
		} );
		setSyllabusFlag( null );
	}
	useEffect( () => {
		document.getElementById( `ohmylms-cur-name-${ item.id }` )?.focus();
	}, [ item.id ] );
	// Re-sync only when the server copy changes and nothing is being typed.
	useEffect( () => {
		if ( ! dirty ) {
			setDraft( draftFrom( item ) );
		}
		setParent( String( item.parent_id || 0 ) );
	}, [ item.updated_at, item.parent_id, item, dirty ] );
	useEffect( () => {
		let current = true;
		api.loadItem( item.id )
			.then(
				( response ) =>
					current && ( setDetail( response ), setDetailError( '' ) )
			)
			.catch(
				( error ) =>
					current &&
					setDetailError(
						error?.message ||
							__( 'Could not load linked content.', 'ohmylms' )
					)
			);
		return () => {
			current = false;
		};
	}, [
		item.id,
		item.links?.course,
		item.links?.skill,
		item.links?.bank,
		item.links?.quiz,
	] );
	const set = ( key ) => ( value ) => {
		setSaved( false );
		setDraft( ( previous ) => ( {
			...previous,
			[ key ]: value,
		} ) );
	};
	async function save( event ) {
		event.preventDefault();
		setTouched( true );
		if ( Object.keys( errors ).length || c.pending ) {
			return;
		}
		const ok = await c.actions.update( item, payloadFrom( draft ) );
		setSaved( Boolean( ok ) );
	}
	const targets = moveTargets( c.items, item.id, c.maxDepth );
	const path = pathNames( c.items, item.id );
	return (
		<section
			id={ id }
			className="ohmylms-cur-panel"
			aria-label={ sprintf(
				/* translators: %s: curriculum item name. */ __(
					'Editing %s',
					'ohmylms'
				),
				item.name
			) }
		>
			<p className="ohmylms-ext-muted ohmylms-cur-path">
				{ path.length ? `${ path.join( ' › ' ) } › ` : '' }
				<strong>{ item.name }</strong>
			</p>
			<div className="ohmylms-cur-syllabus-toggle">
				<CheckboxControl
					label={ __( 'This item is a syllabus', 'ohmylms' ) }
					checked={ syllabusFlag ?? Boolean( item.is_syllabus ) }
					disabled={ c.pending }
					onChange={ toggleSyllabus }
					help={ __(
						'Any item can be a syllabus. A syllabus is also a course: its folders are folders and its skills are the course’s skills. It is edited in its own editor, opened by its name. This saves at once.',
						'ohmylms'
					) }
					__nextHasNoMarginBottom
				/>
			</div>
			<form onSubmit={ save } noValidate className="ohmylms-cur-details">
				<TextControl
					id={ `ohmylms-cur-name-${ item.id }` }
					label={ __( 'Name', 'ohmylms' ) }
					value={ draft.name }
					onChange={ set( 'name' ) }
					help={
						touched && errors.name
							? MESSAGES[ errors.name ]()
							: undefined
					}
					__nextHasNoMarginBottom
				/>

				<TextControl
					label={ __( 'Syllabus code (optional)', 'ohmylms' ) }
					value={ draft.code }
					onChange={ set( 'code' ) }
					help={
						touched && errors.code
							? MESSAGES[ errors.code ]()
							: undefined
					}
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Version (optional)', 'ohmylms' ) }
					value={ draft.version }
					onChange={ set( 'version' ) }
					help={
						touched && errors.version
							? MESSAGES[ errors.version ]()
							: __(
									'For example the years a syllabus applies.',
									'ohmylms'
								)
					}
					__nextHasNoMarginBottom
				/>
				<TextareaControl
					label={ __( 'Description (optional)', 'ohmylms' ) }
					value={ draft.description }
					onChange={ set( 'description' ) }
					help={
						touched && errors.description
							? MESSAGES[ errors.description ]()
							: undefined
					}
					__nextHasNoMarginBottom
				/>
				<div className="ohmylms-cur-save">
					<Button
						variant="primary"
						type="submit"
						isBusy={ c.pending }
						disabled={ c.pending || ! dirty }
					>
						{ __( 'Save changes', 'ohmylms' ) }
					</Button>
					{ dirty && (
						<span className="ohmylms-cur-dirty">
							{ __( 'Unsaved changes', 'ohmylms' ) }
						</span>
					) }
					{ saved && ! dirty && (
						<span className="ohmylms-cur-saved" role="status">
							{ __( 'Saved', 'ohmylms' ) }
						</span>
					) }
				</div>
			</form>

			<div className="ohmylms-cur-move">
				<SelectControl
					label={ __( 'Move to', 'ohmylms' ) }
					value={ parent }
					onChange={ setParent }
					options={ [
						{
							value: '0',
							label: __( 'Top level', 'ohmylms' ),
						},
						...targets.map( ( target ) => ( {
							value: String( target.id ),
							label: `${ '— '.repeat( target.depth ) }${ target.name }`,
						} ) ),
					] }
					help={ __(
						'Moves the item and everything under it to the end of that list. Use ↑ and ↓ on the row to reorder.',
						'ohmylms'
					) }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					disabled={
						c.pending ||
						Number( parent ) === ( item.parent_id || 0 )
					}
					onClick={ () => c.actions.move( item, Number( parent ) ) }
				>
					{ __( 'Move', 'ohmylms' ) }
				</Button>
			</div>

			{ item.is_syllabus && <SyllabusSummary item={ item } /> }
			{ item.item_type === 'subject' && (
				<SubjectCourseOrder item={ item } />
			) }

			{ ! detail && ! detailError && <Spinner /> }
			{ detailError && <p role="alert">{ detailError }</p> }
			{ detail && (
				<>
					<LinkedContent
						item={ item }
						detail={ detail }
						onChange={ setDetail }
					/>
					<SkillMappings
						item={ item }
						detail={ detail }
						onChange={ setDetail }
					/>
					<DeleteItem
						item={ item }
						dependents={ detail.dependents }
					/>
				</>
			) }
		</section>
	);
}
