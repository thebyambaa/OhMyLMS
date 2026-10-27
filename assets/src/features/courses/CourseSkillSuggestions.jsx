/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { acceptedSkill, suggestionsFor } from './provenance.mjs';

/**
 * Ask a skill source which skills a lesson (or a unit's activities outside lessons) teaches, then add
 * the ones the author accepts. Library skills are reused; new skills join the library as pending
 * review, so the course can use them in its draft but not publish until they are approved.
 *
 * @param {Object}                     props          Component properties.
 * @param {number}                     props.courseId Course ID.
 * @param {Object}                     props.place    unitId and groupId (lesson UUID, or empty for the unit).
 * @param {boolean}                    props.saved    Whether the place exists in the saved outline.
 * @param {string}                     props.subject  The course's usual subject.
 * @param {boolean}                    props.busy     A save is running.
 * @param {(skills: Object[]) => void} props.onAdd    Link accepted skills (termId, name, meta) into the outline.
 */
export function CourseSkillSuggestions( {
	courseId,
	place,
	saved,
	subject: courseSubject,
	busy,
	onAdd,
} ) {
	const [ data, setData ] = useState( null );
	const [ subject, setSubject ] = useState( courseSubject || '' );
	const [ rows, setRows ] = useState( [] );
	const [ edits, setEdits ] = useState( {} );
	const [ working, setWorking ] = useState( false );
	const [ error, setError ] = useState( '' );
	const path = `/ohmylms/v1/courses/${ courseId }/structure/skill-suggestions`;
	const { unitId, groupId } = place;
	useEffect( () => {
		let active = true;
		apiFetch( { path } )
			.then( ( result ) => {
				if ( active ) {
					setData( result );
					setRows(
						suggestionsFor( result.items, { unitId, groupId } )
					);
				}
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ path, unitId, groupId ] );
	const source = data?.sources.find(
		( row ) => 'course-content' === row.id && row.available
	);
	async function suggest() {
		setWorking( true );
		setError( '' );
		try {
			const result = await apiFetch( {
				path,
				method: 'POST',
				data: {
					unit_id: place.unitId,
					group_id: place.groupId || '',
					subject,
					source: source.id,
				},
			} );
			setRows( suggestionsFor( result.suggestions, place ) );
			if ( ! result.suggestions.length ) {
				setError(
					__(
						'The source found no skills here. Add readings or activity names, then try again.',
						'ohmylms'
					)
				);
			}
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setWorking( false );
		}
	}
	async function review( ids, action ) {
		setWorking( true );
		setError( '' );
		try {
			const result = await apiFetch( {
				path: `${ path }/review`,
				method: 'POST',
				data: {
					ids,
					action,
					overrides: Object.fromEntries(
						ids
							.filter( ( id ) => edits[ id ]?.name )
							.map( ( id ) => [ id, { name: edits[ id ].name } ] )
					),
				},
			} );
			const failed = Object.values( result ).filter(
				( row ) => row.error
			);
			if ( failed.length ) {
				setError( failed.map( ( row ) => row.error ).join( ' ' ) );
			}
			if ( 'accept' === action ) {
				onAdd(
					Object.values( result )
						.map( ( row ) => acceptedSkill( row, edits[ row.id ] ) )
						.filter( Boolean )
				);
			}
			const done = Object.values( result )
				.filter( ( row ) => ! row.error )
				.map( ( row ) => row.id );
			setRows( rows.filter( ( row ) => ! done.includes( row.id ) ) );
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setWorking( false );
		}
	}
	const subjects = data?.subjects || [];
	return (
		<section
			className="ohmylms-skill-suggestions"
			aria-label={ __( 'Suggest skills with AI', 'ohmylms' ) }
		>
			<h3>{ __( 'Suggest skills with AI', 'ohmylms' ) }</h3>
			<p className="ohmylms-outline-hint">
				{ __(
					'AI reads this lesson and proposes the skills it teaches, reusing library skills where they fit. New skills wait for library approval before the course can be published.',
					'ohmylms'
				) }
			</p>
			{ ! data && ! error && <Spinner /> }
			{ data && ! source && (
				<p className="ohmylms-outline-hint">
					{ __(
						'Connect an AI provider under AI settings to suggest skills.',
						'ohmylms'
					) }
				</p>
			) }
			{ data && source && ! saved && (
				<p className="ohmylms-outline-hint">
					{ __(
						'Save the outline draft first so the AI can read this lesson.',
						'ohmylms'
					) }
				</p>
			) }
			{ data && source && saved && (
				<div className="ohmylms-skill-suggestions-start">
					<SelectControl
						label={ __( 'Subject', 'ohmylms' ) }
						value={ subject }
						options={ [
							{
								value: '',
								label: __( 'Default profile', 'ohmylms' ),
							},
							...[
								...new Set(
									[ courseSubject, ...subjects ].filter(
										Boolean
									)
								),
							].map( ( value ) => ( { value, label: value } ) ),
						] }
						onChange={ setSubject }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						disabled={ working || busy }
						isBusy={ working && ! rows.length }
						onClick={ suggest }
					>
						{ rows.length
							? __( 'Suggest again', 'ohmylms' )
							: __( 'Suggest skills', 'ohmylms' ) }
					</Button>
				</div>
			) }
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ rows.length > 0 && (
				<ul className="ohmylms-skill-suggestion-list">
					{ rows.map( ( row ) => (
						<li key={ row.id }>
							<div className="ohmylms-skill-suggestion-head">
								<span
									className={ `ohmylms-outline-badge${
										row.shared ? '' : ' is-new'
									}` }
								>
									{ row.shared
										? __( 'Library skill', 'ohmylms' )
										: __( 'New skill', 'ohmylms' ) }
								</span>
								<span className="ohmylms-outline-meta">
									{ sprintf(
										/* translators: %d: confidence percentage. */
										__( '%d%% confident', 'ohmylms' ),
										Math.round( row.confidence * 100 )
									) }
								</span>
							</div>
							{ row.shared ? (
								<strong>{ row.shared.name }</strong>
							) : (
								<TextControl
									label={ __( 'Skill name', 'ohmylms' ) }
									value={
										edits[ row.id ]?.name ??
										row.proposal?.name ??
										''
									}
									onChange={ ( name ) =>
										setEdits( {
											...edits,
											[ row.id ]: { name },
										} )
									}
									__nextHasNoMarginBottom
								/>
							) }
							{ ! row.shared && row.proposal?.description && (
								<p className="ohmylms-outline-hint">
									{ row.proposal.description }
								</p>
							) }
							{ row.reason && (
								<p className="ohmylms-outline-hint">
									{ row.reason }
								</p>
							) }
							<div className="ohmylms-skill-suggestion-actions">
								<Button
									variant="secondary"
									disabled={ working || busy }
									onClick={ () =>
										review( [ row.id ], 'accept' )
									}
								>
									{ __( 'Add to lesson', 'ohmylms' ) }
								</Button>
								<Button
									variant="tertiary"
									disabled={ working || busy }
									onClick={ () =>
										review( [ row.id ], 'reject' )
									}
								>
									{ __( 'Dismiss', 'ohmylms' ) }
								</Button>
							</div>
						</li>
					) ) }
				</ul>
			) }
			{ rows.length > 1 && (
				<Button
					variant="primary"
					disabled={ working || busy }
					onClick={ () =>
						review(
							rows.map( ( row ) => row.id ),
							'accept'
						)
					}
				>
					{ __( 'Add all', 'ohmylms' ) }
				</Button>
			) }
		</section>
	);
}
