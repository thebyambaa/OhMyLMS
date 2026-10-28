/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { loadSyllabus } from '../curriculum/api.mjs';
import { syllabusSkillRows, skillPickerTree } from './skillPickerTree.mjs';

import { __, sprintf } from '@wordpress/i18n';
import {
	objectiveKey,
	objectiveReferences,
	objectiveRequest,
} from './objectives.mjs';
import { outlineSkillName } from './CourseOutlineTree';

/**
 * Course skills and completion requirements, editable without leaving the outline.
 * @param {Object}                  props          Component properties.
 * @param {Object}                  props.draft    Outline draft.
 * @param {boolean}                 props.busy     Saving state.
 * @param {() => void}              props.onAdd    Open the skill picker.
 * @param {(patch: Object) => void} props.onChange Update the program.
 * @return {Element} Course skill controls.
 */
export function CourseSkillsPanel( { draft, busy, onAdd, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const [ expanded, setExpanded ] = useState( null );
	const [ limit, setLimit ] = useState( 20 );
	const { program } = draft;
	const words = search
		.trim()
		.toLocaleLowerCase()
		.split( /\s+/ )
		.filter( Boolean );
	const rows = program.outcomes.filter( ( row ) => {
		const text =
			`${ draft.skill_codes?.[ row.term_id ] || '' } ${ outlineSkillName( draft.skill_names, row.term_id ) }`.toLocaleLowerCase();
		return words.every( ( word ) => text.includes( word ) );
	} );
	const update = ( id, patch ) =>
		onChange( {
			outcomes: program.outcomes.map( ( row ) =>
				row.term_id === id ? { ...row, ...patch } : row
			),
		} );
	return (
		<section
			className="ohmylms-course-skills"
			aria-label={ __( 'Course skills', 'ohmylms' ) }
		>
			<h2>
				{ __( 'Course skills', 'ohmylms' ) }{ ' ' }
				<small>({ program.outcomes.length })</small>
			</h2>
			<p>
				{ __(
					'Add skills before or after creating units. Connect them to lessons and quizzes here, and choose which skills learners must achieve.',
					'ohmylms'
				) }
			</p>
			<Button variant="primary" disabled={ busy } onClick={ onAdd }>
				{ __( 'Add skills', 'ohmylms' ) }
			</Button>
			<TextControl
				type="search"
				label={ __( 'Search course skills', 'ohmylms' ) }
				value={ search }
				onChange={ ( value ) => {
					setSearch( value );
					setLimit( 20 );
				} }
			/>
			<p role="status" aria-live="polite" aria-atomic="true">
				{ sprintf(
					/* translators: 1: matching skills, 2: total course skills. */
					__( '%1$d of %2$d skills match', 'ohmylms' ),
					rows.length,
					program.outcomes.length
				) }
				{ search && (
					<Button
						variant="link"
						onClick={ () => {
							setSearch( '' );
							setLimit( 20 );
						} }
					>
						{ __( 'Clear search', 'ohmylms' ) }
					</Button>
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %d: skill count. */ __(
						'%d skills required for completion',
						'ohmylms'
					),
					program.outcomes.filter( ( row ) => row.required ).length
				) }
			</p>
			{ ! rows.length && (
				<p>
					{ __(
						'No matching course skills. Choose Add skills to use the library or a syllabus.',
						'ohmylms'
					) }
				</p>
			) }
			{ ( words.length ? rows : rows.slice( 0, limit ) ).map(
				( skill ) => {
					const links = program.items.filter( ( item ) =>
						item.type === 'practice'
							? item.content_id === skill.term_id
							: item.skill_ids?.includes( skill.term_id )
					);
					const teaching = links.filter(
						( item ) => item.type === 'lesson'
					).length;
					const assessment = links.filter( ( item ) =>
						[ 'quiz', 'assignment' ].includes( item.type )
					).length;
					return (
						<div
							className="ohmylms-course-skill-card"
							key={ skill.term_id }
						>
							<strong>
								{ draft.skill_codes?.[ skill.term_id ] }{ ' ' }
								{ outlineSkillName(
									draft.skill_names,
									skill.term_id
								) }
							</strong>
							<p className="ohmylms-outline-hint">
								{ teaching
									? sprintf(
											/* translators: %d: lesson count. */ __(
												'%d teaching links',
												'ohmylms'
											),
											teaching
										)
									: __(
											'No teaching linked',
											'ohmylms'
										) }{ ' ' }
								·{ ' ' }
								{ assessment
									? sprintf(
											/* translators: %d: assessment count. */ __(
												'%d planned assessments',
												'ohmylms'
											),
											assessment
										)
									: __( 'No assessment linked', 'ohmylms' ) }
							</p>
							<CheckboxControl
								label={ __(
									'Required for course completion',
									'ohmylms'
								) }
								checked={ !! skill.required }
								disabled={ busy }
								onChange={ ( required ) =>
									update( skill.term_id, { required } )
								}
							/>
							{ skill.required && (
								<SelectControl
									label={ __(
										'Completion target',
										'ohmylms'
									) }
									value={ skill.target }
									disabled={ busy }
									options={ [
										{
											value: 'proficient',
											label: __(
												'Proficient',
												'ohmylms'
											),
										},
										{
											value: 'mastered',
											label: __( 'Mastered', 'ohmylms' ),
										},
									] }
									onChange={ ( target ) =>
										update( skill.term_id, { target } )
									}
								/>
							) }
							<Button
								variant="link"
								disabled={ busy }
								aria-expanded={ expanded === skill.term_id }
								onClick={ () =>
									setExpanded(
										expanded === skill.term_id
											? null
											: skill.term_id
									)
								}
							>
								{ __(
									'Connect lessons and quizzes',
									'ohmylms'
								) }
							</Button>
							{ expanded === skill.term_id && (
								<fieldset>
									<legend>
										{ __(
											'Content connected to this skill',
											'ohmylms'
										) }
									</legend>
									{ ! program.items.some(
										( item ) => item.type !== 'practice'
									) && (
										<p>
											{ __(
												'Add a lesson or quiz to the outline, then connect it here or in its Skills field.',
												'ohmylms'
											) }
										</p>
									) }
									{ program.items
										.filter(
											( item ) => item.type !== 'practice'
										)
										.map( ( item ) => (
											<CheckboxControl
												key={ item.id }
												label={ `${ item.title || item.name } · ${ item.type === 'lesson' ? __( 'Teaches', 'ohmylms' ) : __( 'Plans to assess', 'ohmylms' ) }` }
												checked={
													item.skill_ids?.includes(
														skill.term_id
													) || false
												}
												disabled={ busy }
												onChange={ ( checked ) =>
													onChange( {
														items: program.items.map(
															( row ) =>
																row.id ===
																item.id
																	? {
																			...row,
																			skill_ids:
																				checked
																					? [
																							...( row.skill_ids ||
																								[] ),
																							skill.term_id,
																						]
																					: (
																							row.skill_ids ||
																							[]
																						).filter(
																							(
																								id
																							) =>
																								id !==
																								skill.term_id
																						),
																		}
																	: row
														),
													} )
												}
											/>
										) ) }
								</fieldset>
							) }
						</div>
					);
				}
			) }
			{ ! words.length && rows.length > limit && (
				<Button
					variant="secondary"
					onClick={ () => setLimit( limit + 20 ) }
				>
					{ __( 'Show more skills', 'ohmylms' ) }
				</Button>
			) }
			<p className="ohmylms-outline-hint">
				{ __(
					'Required skills need enough approved questions for their completion target. Save the course to keep these changes.',
					'ohmylms'
				) }
			</p>
		</section>
	);
}

/**
 * Choose library or syllabus skills from the current outline selection.
 * @param {Object}                   props          Component properties.
 * @param {number}                   props.courseId Course identifier.
 * @param {Object}                   props.draft    Outline draft.
 * @param {() => void}               props.onClose  Close the picker.
 * @param {(rows: Object[]) => void} props.onAdd    Add selected skills to the local draft.
 * @return {Element} Skill picker.
 */
export function OutlineAddSkills( { courseId, draft, onClose, onAdd } ) {
	const [ source, setSource ] = useState( '' );
	const [ data, setData ] = useState( null );
	const [ rows, setRows ] = useState( [] );
	const [ selected, setSelected ] = useState( new Map() );
	const [ search, setSearch ] = useState( '' );
	const [ expanded, setExpanded ] = useState( new Set() );

	const [ loading, setLoading ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ retry, setRetry ] = useState( 0 );
	useEffect( () => {
		let active = true;
		objectiveRequest( courseId )
			.then( ( result ) => {
				if ( active ) {
					setData( result );
					const linked = new Set(
						result.source_ids ||
							result.mappings.map( ( row ) => row.syllabus_id )
					);
					setSource(
						String(
							result.syllabuses.find( ( row ) =>
								linked.has( row.id )
							)?.id || ''
						)
					);
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setError( cause.message );
				}
			} );
		return () => {
			active = false;
		};
	}, [ courseId, retry ] );
	useEffect( () => {
		if ( ! data ) {
			return undefined;
		}
		let active = true;
		setLoading( true );
		setError( '' );
		setRows( [] );
		async function load() {
			const linked = new Set(
				data.source_ids ||
					data.mappings.map( ( row ) => row.syllabus_id )
			);
			const sources = data.syllabuses.filter(
				( row ) => linked.has( row.id ) && String( row.id ) === source
			);
			const outlines = await Promise.all(
				sources.map( ( row ) => loadSyllabus( row.id ) )
			);
			const next = outlines.flatMap( syllabusSkillRows );
			if ( active ) {
				setRows( next );
				setExpanded(
					new Set(
						next
							.map( ( row ) => row.path[ 0 ]?.id )
							.filter( Boolean )
					)
				);
			}
		}
		load()
			.catch( ( cause ) => {
				if ( active ) {
					setError(
						cause.message ||
							__( 'Could not load skills.', 'ohmylms' )
					);
				}
			} )
			.finally( () => active && setLoading( false ) );
		return () => {
			active = false;
		};
	}, [ source, data, retry ] );
	const existing = new Set(
		draft.program.outcomes.map( ( row ) => row.term_id )
	);
	const words = search
		.trim()
		.toLocaleLowerCase()
		.split( /\s+/ )
		.filter( Boolean );
	const available = rows.filter( ( row ) => {
		const text =
			`${ row.code || '' } ${ row.name } ${ row.objective || '' } ${ row.path.map( ( part ) => part.label ).join( ' ' ) }`.toLocaleLowerCase();
		return words.every( ( word ) => text.includes( word ) );
	} );
	const tree = skillPickerTree(
		available.map( ( row ) => ( { ...row, path: row.path.slice( 1 ) } ) )
	);
	const newCount = [ ...selected.keys() ].filter(
		( id ) => ! existing.has( id )
	).length;
	function renderSkill( row ) {
		return (
			<li key={ row.term_id } className="ohmylms-picker-skill">
				<CheckboxControl
					key={ row.term_id }
					label={ `${ row.code || '' } ${ row.name }${ existing.has( row.term_id ) ? ' · ' + __( 'Already in course', 'ohmylms' ) : '' }` }
					checked={ selected.has( row.term_id ) }
					disabled={
						busy ||
						( ! selected.has( row.term_id ) &&
							existing.size + newCount >= 200 &&
							! existing.has( row.term_id ) )
					}
					onChange={ ( checked ) =>
						setSelected( ( current ) => {
							const next = new Map( current );
							if ( checked ) {
								next.set( row.term_id, row );
							} else {
								next.delete( row.term_id );
							}
							return next;
						} )
					}
				/>
			</li>
		);
	}
	function renderBranch( branch ) {
		const open = words.length > 0 || expanded.has( branch.id );
		const count = new Set( branch.rows.map( ( row ) => row.term_id ) ).size;
		return (
			<li key={ branch.id } className="ohmylms-picker-branch">
				<Button
					variant="tertiary"
					className="ohmylms-picker-branch-toggle"
					aria-expanded={ open }
					onClick={ () =>
						setExpanded( ( current ) => {
							const next = new Set( current );
							if ( next.has( branch.id ) ) {
								next.delete( branch.id );
							} else {
								next.add( branch.id );
							}
							return next;
						} )
					}
				>
					<span aria-hidden="true">{ open ? '▾' : '▸' }</span>
					<span>{ branch.label }</span>
					<small>({ count })</small>
				</Button>
				{ open && (
					<ul>
						{ [ ...branch.children.values() ].map( renderBranch ) }
						{ branch.skills.map( renderSkill ) }
					</ul>
				) }
			</li>
		);
	}
	async function add() {
		setBusy( true );
		setError( '' );
		try {
			const references = new Map(
				( data?.mappings || [] ).map( ( row ) => [
					objectiveKey( row ),
					row,
				] )
			);
			for ( const row of selected.values() ) {
				if ( row.syllabus_id ) {
					references.set( objectiveKey( row ), row );
				}
			}
			if ( references.size !== ( data?.mappings.length || 0 ) ) {
				if ( ! data ) {
					throw new Error(
						__(
							'Wait for syllabus references to load, then retry.',
							'ohmylms'
						)
					);
				}
				await objectiveRequest( courseId, {
					method: 'PUT',
					data: {
						mappings: objectiveReferences( [
							...references.values(),
						] ),
						revision: data.revision,
					},
				} );
			}
			onAdd( [ ...selected.values() ] );
		} catch ( cause ) {
			setError( cause.message );
			setBusy( false );
		}
	}
	return (
		<Modal
			title={ __( 'Add skills', 'ohmylms' ) }
			onRequestClose={ busy ? () => {} : onClose }
			shouldCloseOnEsc={ ! busy }
			shouldCloseOnClickOutside={ ! busy }
			className="ohmylms-outline-skill-picker"
		>
			<p>
				{ __(
					'Add skills to this course and the selected lesson or quiz. Syllabus choices also save their alignment references; save the course to keep the course connections.',
					'ohmylms'
				) }
			</p>
			<TextControl
				label={ __( 'Find skills', 'ohmylms' ) }
				value={ search }
				disabled={ busy }
				onChange={ ( value ) => {
					setSearch( value );
				} }
			/>
			<div className="ohmylms-picker-controls">
				<Button
					disabled={ busy || loading }
					onClick={ () =>
						setExpanded(
							new Set(
								rows.flatMap( ( row ) =>
									row.path.map( ( part ) => part.id )
								)
							)
						)
					}
				>
					{ __( 'Expand all', 'ohmylms' ) }
				</Button>
				<Button
					disabled={ busy || loading || words.length > 0 }
					onClick={ () => setExpanded( new Set() ) }
				>
					{ __( 'Collapse all', 'ohmylms' ) }
				</Button>
				<span>
					{ sprintf(
						/* translators: %d: matching unique skill count. */ __(
							'%d skills',
							'ohmylms'
						),
						new Set( available.map( ( row ) => row.term_id ) ).size
					) }
				</span>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }{ ' ' }
					<Button
						variant="link"
						onClick={ () => setRetry( retry + 1 ) }
					>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ loading ? (
				<Spinner />
			) : (
				<div className="ohmylms-outline-skill-results">
					<ul className="ohmylms-picker-tree">
						{ [ ...tree.children.values() ].map( renderBranch ) }
						{ tree.skills.map( renderSkill ) }
					</ul>
					{ ! available.length && (
						<p>
							{ source
								? __(
										'No matching skills in this source.',
										'ohmylms'
									)
								: __(
										'Choose a syllabus in Syllabus alignment before adding skills.',
										'ohmylms'
									) }
						</p>
					) }
				</div>
			) }
			<Button
				variant="primary"
				disabled={
					busy ||
					loading ||
					! selected.size ||
					existing.size + newCount > 200
				}
				isBusy={ busy }
				onClick={ add }
			>
				{ sprintf(
					/* translators: %d: selected skills. */ __(
						'Add selected skills (%d)',
						'ohmylms'
					),
					selected.size
				) }
			</Button>
		</Modal>
	);
}
