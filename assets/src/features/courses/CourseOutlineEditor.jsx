/** @jsx createElement */
import {
	createElement,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { CourseStructurePublish } from './CourseStructurePublish';
import { ActivityCreateForm } from './ActivityCreateForm';
import { CourseReadiness } from './CourseReadiness';
import { CourseOutlineTree, outlineSkillName } from './CourseOutlineTree';
import { SkillProvenance } from './SkillProvenance';
import { lessonSkillIds, linkLessonSkill } from './lessonSkills.mjs';
import {
	CourseSkillCoverage,
	ActivitySkillConnections,
} from './CourseSkillConnections';
import {
	addOutlinePractice,
	dropOutlineActivity,
	dropOutlineLesson,
	dropOutlineUnit,
	missingOutlineSkills,
	moveOutlineRow,
	orderedOutlineItems,
	outlineStatus,
	practicePlacement,
	removeOutlineSkills,
} from './outline.mjs';

const uuid = () => {
	const bytes = window.crypto.getRandomValues( new Uint8Array( 16 ) );
	bytes[ 6 ] = 64 + ( bytes[ 6 ] % 16 );
	bytes[ 8 ] = 128 + ( bytes[ 8 ] % 64 );
	const hex = Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
	return [
		hex.slice( 0, 8 ),
		hex.slice( 8, 12 ),
		hex.slice( 12, 16 ),
		hex.slice( 16, 20 ),
		hex.slice( 20 ),
	].join( '-' );
};
const same = ( left, right ) => String( left || 0 ) === String( right || 0 );
const special = ( item ) =>
	[ 'unit-test', 'course-challenge' ].includes( item.style );
const key = ( value ) =>
	JSON.stringify( { units: value.units, program: value.program } );

/**
 * Unified course, unit, lesson and activity authoring with one saved revision.
 *
 * @param {Object}                     root0               Component properties.
 * @param {number}                     root0.courseId      Course ID.
 * @param {Object}                     root0.editor        Course metadata editor.
 * @param {(dirty: boolean) => void}   root0.onDirtyChange Track unsaved outline edits in navigation.
 * @param {(revision: string) => void} root0.onSaved       Refresh related course coverage after a save.
 * @param {Element}                    root0.courseInfo    Course information and media fields.
 * @param {Element}                    root0.alignment     Syllabus objective mappings.
 */
export function CourseOutlineEditor( {
	courseId,
	editor,
	onDirtyChange,
	onSaved,
	courseInfo,
	alignment,
} ) {
	const [ draft, setDraft ] = useState( null );
	const [ baseline, setBaseline ] = useState( '' );
	const [ selected, setSelected ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ picker, setPicker ] = useState( null );
	const [ pickerBusy, setPickerBusy ] = useState( false );
	const [ kind, setKind ] = useState( 'lesson' );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ retry, setRetry ] = useState( 0 );
	const [ resourcePage, setResourcePage ] = useState( 1 );
	const [ resourcePages, setResourcePages ] = useState( 1 );
	const [ discard, setDiscard ] = useState( false );
	const [ tab, setTab ] = useState( 'outline' );
	const [ readiness, setReadiness ] = useState( null );
	const dirty = draft && key( draft ) !== baseline;
	const adopt = useCallback( ( data ) => {
		const next = {
			...data,
			units: data.units.filter( ( unit ) => unit.id > 0 ),
		};
		setDraft( next );
		setBaseline( key( next ) );
	}, [] );
	useEffect( () => {
		onDirtyChange( !! dirty );
	}, [ dirty, onDirtyChange ] );
	useEffect( () => () => onDirtyChange( false ), [ onDirtyChange ] );
	useEffect( () => {
		let active = true;
		setDraft( null );
		setError( '' );
		apiFetch( { path: `/ohmylms/v1/courses/${ courseId }/structure` } )
			.then( ( data ) => {
				if ( active ) {
					adopt( data );
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
	}, [ courseId, retry, adopt ] );
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );
	useEffect( () => {
		if ( ! picker || picker.create ) {
			return undefined;
		}
		const linked = draft.program.outcomes
			.filter(
				( row ) =>
					( ! picker.groupId ||
						lessonSkillIds(
							draft.program,
							picker.groupId
						).includes( row.term_id ) ) &&
					( draft.skill_names?.[ row.term_id ] || '' )
						.toLowerCase()
						.includes( search.toLowerCase() )
			)
			.map( ( row ) => ( {
				id: row.term_id,
				title: outlineSkillName( draft.skill_names, row.term_id ),
				type: 'practice',
				style: 'practice',
			} ) );
		if ( kind === 'practice' && ! search.trim() ) {
			setResults( linked );
			setResourcePages( 1 );
			return undefined;
		}
		let active = true;
		const timer = setTimeout( () => {
			apiFetch( {
				path: `/ohmylms/v1/courses/${ courseId }/structure/resources?type=${
					kind === 'practice' ? 'skill' : kind
				}&search=${ encodeURIComponent( search ) }&page=${ resourcePage }`,
			} )
				.then( ( data ) => {
					if ( ! active ) {
						return;
					}
					// Practice is the skill itself: library skills can be linked and practised in one step.
					setResults(
						kind === 'practice'
							? [
									...( resourcePage === 1 ? linked : [] ),
									...data.items
										.filter(
											( row ) =>
												! linked.some(
													( own ) => own.id === row.id
												)
										)
										.map( ( row ) => ( {
											...row,
											type: 'practice',
											style: 'practice',
										} ) ),
								]
							: data.items
					);
					setResourcePages( data.pages || 1 );
				} )
				.catch( ( cause ) => {
					if ( active ) {
						setError( cause.message );
					}
				} );
		}, 200 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [
		picker,
		kind,
		search,
		resourcePage,
		courseId,
		draft?.program,
		draft?.skill_names,
	] );
	function change( patch ) {
		setDraft( { ...draft, ...patch } );
		setNotice( '' );
	}
	function program( patch ) {
		change( { program: { ...draft.program, ...patch } } );
	}
	function select( type, id ) {
		setSelected( { type, id } );
		setError( '' );
	}
	async function save() {
		if ( busy || editor.saving ) {
			return false;
		}
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			if (
				editor.dirty &&
				! ( await editor.save( undefined, undefined, undefined, {
					silent: true,
				} ) )
			) {
				throw new Error(
					__(
						'Save the course information before saving the outline.',
						'ohmylms'
					)
				);
			}
			const result = await apiFetch( {
				path: `/ohmylms/v1/courses/${ courseId }/structure`,
				method: 'PUT',
				data: {
					revision: draft.revision,
					units: draft.units,
					program: {
						...draft.program,
						items: orderedOutlineItems(
							draft.program,
							draft.units
						),
					},
				},
			} );
			adopt( result );
			setSelected( null );
			await editor.refreshStructure();
			onSaved?.( result.revision );
			setNotice( __( 'Outline draft saved.', 'ohmylms' ) );
			return true;
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not save the outline.', 'ohmylms' )
			);
			return false;
		} finally {
			setBusy( false );
		}
	}
	function addUnit() {
		const id = uuid();
		change( {
			units: [
				...draft.units,
				{
					id,
					title: __( 'New unit', 'ohmylms' ),
					description: '',
					number: '',
				},
			],
		} );
		select( 'unit', id );
	}
	function openPicker( unitId, groupId = '', initialKind = '' ) {
		const checkpoint = [ 'unit-test', 'course-challenge' ].includes(
			initialKind
		);
		setResults( [] );
		setSearch( '' );
		setResourcePage( 1 );
		let initial = 'lesson';
		if ( checkpoint ) {
			initial = 'quiz';
		} else if ( [ 'quiz', 'practice' ].includes( initialKind ) ) {
			initial = initialKind;
		}
		setKind( initial );
		setPicker( {
			unitId,
			groupId,
			initialKind,
			style: checkpoint ? initialKind : '',
			lesson: 'lesson' === initialKind,
			create:
				!! initialKind && initialKind !== 'practice' && ! checkpoint,
			requestId: uuid(),
		} );
	}
	function addPractice( resource ) {
		const id = uuid();
		const next = addOutlinePractice( draft.program, {
			termId: resource.id,
			unitId: picker.unitId,
			groupId: picker.groupId,
			id,
		} );
		if ( ! next ) {
			setError(
				__(
					'This lesson already has practice for this skill.',
					'ohmylms'
				)
			);
			return;
		}
		change( {
			skill_names: {
				...draft.skill_names,
				[ resource.id ]: resource.title,
			},
			skill_codes: {
				...draft.skill_codes,
				...( resource.code ? { [ resource.id ]: resource.code } : {} ),
			},
			program: {
				...next,
				items: next.items.map( ( item ) =>
					item.id === id ? { ...item, name: resource.title } : item
				),
			},
		} );
		setPicker( null );
		select( 'activity', id );
	}
	function move( type, id, target ) {
		if ( 'unit' === type ) {
			change( {
				units: dropOutlineUnit( draft.units, id, target.beforeId ),
			} );
		} else if ( 'lesson' === type ) {
			program( dropOutlineLesson( draft.program, id, target ) );
		} else {
			program( dropOutlineActivity( draft.program, id, target ) );
		}
	}
	function updateSkillMeta( termId, patch ) {
		setDraft( ( current ) => ( {
			...current,
			skill_meta: {
				...current.skill_meta,
				[ termId ]: { ...current.skill_meta?.[ termId ], ...patch },
			},
		} ) );
	}
	function removeSkills( ids ) {
		program( removeOutlineSkills( draft.program, ids ) );
		setSelected( null );
	}
	function addActivity( resource ) {
		const isChallenge = resource.style === 'course-challenge';
		const id = uuid();
		change( {
			revision: resource.revision || draft.revision,
			program: {
				...draft.program,
				items: [
					...draft.program.items,
					{
						id,
						content_id: resource.id,
						type: resource.type,
						style: resource.style,
						edit_url: resource.edit_url,
						name: resource.title,
						status: resource.status,
						chapter_id: isChallenge ? 0 : picker.unitId,
						...( picker.groupId && ! special( resource )
							? { group_id: picker.groupId }
							: {} ),
						required: resource.type !== 'practice',
						...( resource.type === 'quiz'
							? { pass_percent: 80 }
							: {} ),
					},
				],
			},
		} );
		setPicker( null );
		select( 'activity', id );
	}
	function linkSkill( resource ) {
		const existing = draft.program.outcomes.find(
			( row ) => row.term_id === resource.id
		);
		if ( ! existing && draft.program.outcomes.length >= 200 ) {
			setError(
				__( 'A course can select at most 200 skills.', 'ohmylms' )
			);
			return;
		}
		change( {
			skill_names: {
				...draft.skill_names,
				[ resource.id ]: resource.title,
			},
			skill_codes: {
				...draft.skill_codes,
				...( resource.code ? { [ resource.id ]: resource.code } : {} ),
			},
			program: linkLessonSkill(
				draft.program,
				resource.id,
				picker.unitId,
				picker.groupId
			),
		} );
		setPicker( null );
	}
	async function removeUnit( unit ) {
		if ( typeof unit.id === 'string' ) {
			change( {
				units: draft.units.filter( ( row ) => row.id !== unit.id ),
			} );
			setSelected( null );
			return;
		}
		if ( dirty ) {
			setError(
				__(
					'Save the outline before removing an existing unit.',
					'ohmylms'
				)
			);
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			await apiFetch( {
				path: `/ohmylms/v1/chapters/${ unit.id }`,
				method: 'DELETE',
				data: { revision: draft.revision },
			} );
			setSelected( null );
			setRetry( retry + 1 );
			await editor.refreshStructure();
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setBusy( false );
		}
	}
	if ( ! draft ) {
		return (
			<section className="ohmylms-outline-editor">
				{ error ? (
					<Notice status="error" isDismissible={ false }>
						{ error }
						<Button onClick={ () => setRetry( retry + 1 ) }>
							{ __( 'Reload outline', 'ohmylms' ) }
						</Button>
					</Notice>
				) : (
					<Spinner />
				) }
			</section>
		);
	}
	const unit =
		selected?.type === 'unit'
			? draft.units.find( ( row ) => row.id === selected.id )
			: null;
	const activity =
		selected?.type === 'activity'
			? draft.program.items.find( ( row ) => row.id === selected.id )
			: null;
	const skill =
		selected?.type === 'skill'
			? draft.program.outcomes.find(
					( row ) => row.term_id === selected.id
				)
			: null;
	const skillPractice = skill
		? draft.program.items.find(
				( item ) =>
					item.type === 'practice' &&
					item.content_id === skill.term_id
			)
		: null;
	const rowsIn = ( unitId ) =>
		draft.program.items.filter( ( item ) =>
			same( item.chapter_id, unitId )
		);
	const missing = missingOutlineSkills( draft.program, draft.skill_names );
	const status = outlineStatus( {
		saving: busy || editor.saving,
		dirty: dirty || editor.dirty,
		unpublished: draft.unpublished_changes,
	} );
	const statusText = {
		saving: __( 'Saving…', 'ohmylms' ),
		unsaved: __( 'Unsaved changes', 'ohmylms' ),
		unpublished: __(
			'Draft saved · not yet published to learners',
			'ohmylms'
		),
		published: __( 'Published · learners see this outline', 'ohmylms' ),
	}[ status ];
	const tabs = [
		[ 'outline', __( 'Outline', 'ohmylms' ) ],
		[ 'skills', __( 'Skill connections', 'ohmylms' ) ],
		[
			'readiness',
			__( 'Readiness', 'ohmylms' ),
			readiness ? readiness.blocked : undefined,
		],
		[ 'alignment', __( 'Syllabus alignment', 'ohmylms' ) ],
		[ 'info', __( 'Course info', 'ohmylms' ) ],
	];
	const tabKey = ( event ) => {
		const index = tabs.findIndex( ( [ value ] ) => value === tab );
		const target = {
			ArrowRight: ( index + 1 ) % tabs.length,
			ArrowLeft: ( index + tabs.length - 1 ) % tabs.length,
			Home: 0,
			End: tabs.length - 1,
		}[ event.key ];
		if ( target === undefined ) {
			return;
		}
		event.preventDefault();
		setTab( tabs[ target ][ 0 ] );
		document
			.getElementById( `ohmylms-outline-tab-${ tabs[ target ][ 0 ] }` )
			?.focus();
	};
	const visible = ! picker
		? []
		: results.filter( ( resource ) => {
				if ( picker.skills ) {
					return ! lessonSkillIds(
						draft.program,
						picker.groupId
					).includes( resource.id );
				}
				if ( picker.style && resource.style !== picker.style ) {
					return false;
				}
				if ( kind === 'practice' ) {
					return (
						practicePlacement(
							draft.program,
							resource.id,
							picker
						) !== 'placed'
					);
				}
				return ! draft.program.items.some(
					( row ) =>
						row.type === resource.type &&
						row.content_id === resource.id
				);
			} );
	const moveButtons = ( rows, row, update ) => (
		<span className="ohmylms-outline-move">
			<Button
				disabled={ busy || rows[ 0 ]?.id === row.id }
				onClick={ () => update( moveOutlineRow( rows, row.id, -1 ) ) }
			>
				{ __( 'Move up', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ busy || rows.at( -1 )?.id === row.id }
				onClick={ () => update( moveOutlineRow( rows, row.id, 1 ) ) }
			>
				{ __( 'Move down', 'ohmylms' ) }
			</Button>
		</span>
	);
	return (
		<section
			className="ohmylms-outline-editor"
			aria-label={ __( 'Course outline editor', 'ohmylms' ) }
			aria-busy={ busy }
		>
			{ discard && (
				<Modal
					title={ __( 'Discard outline changes?', 'ohmylms' ) }
					onRequestClose={ () => setDiscard( false ) }
				>
					<Button onClick={ () => setDiscard( false ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button
						isDestructive
						onClick={ () => {
							setDiscard( false );
							setSelected( null );
							setRetry( retry + 1 );
						} }
					>
						{ __( 'Discard and reload', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			<header className="ohmylms-outline-heading">
				<div className="ohmylms-outline-title">
					<h1>{ __( 'Course outline', 'ohmylms' ) }</h1>
					<p className={ `ohmylms-outline-status is-${ status }` }>
						<span
							className="ohmylms-outline-status-dot"
							aria-hidden="true"
						/>
						{ statusText }
						{ readiness && ! readiness.ready && (
							<Button
								variant="link"
								onClick={ () => setTab( 'readiness' ) }
							>
								{ sprintf(
									/* translators: %d: number of publishing blockers. */ _n(
										'%d publishing blocker',
										'%d publishing blockers',
										readiness.blocked,
										'ohmylms'
									),
									readiness.blocked
								) }
							</Button>
						) }
					</p>
				</div>
				<div className="ohmylms-outline-actions">
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ () =>
							dirty ? setDiscard( true ) : setRetry( retry + 1 )
						}
					>
						{ dirty
							? __( 'Discard changes', 'ohmylms' )
							: __( 'Reload saved outline', 'ohmylms' ) }
					</Button>
					<Button
						variant={ dirty ? 'primary' : 'secondary' }
						disabled={
							busy || ! ( dirty || editor.dirty ) || editor.saving
						}
						onClick={ save }
						isBusy={ busy }
					>
						{ __( 'Save outline draft', 'ohmylms' ) }
					</Button>
					<CourseStructurePublish
						primary={ ! dirty && 'unpublished' === status }
						onPublished={ ( revision ) =>
							setDraft( ( current ) => ( {
								...current,
								revision,
								unpublished_changes: false,
							} ) )
						}
						onNotice={ setNotice }
						onError={ setError }
						courseId={ courseId }
						editor={ {
							...editor,
							dirty: dirty || editor.dirty,
							saving: busy || editor.saving,
							save,
						} }
					/>
				</div>
			</header>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
					<Button
						onClick={ () => {
							if ( ! dirty ) {
								setSelected( null );
								setRetry( retry + 1 );
							} else {
								setDiscard( true );
							}
						} }
					>
						{ __( 'Reload outline', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ missing.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of missing skills. */ _n(
							'This outline links %d skill that no longer exists in the skill library. Remove it before saving.',
							'This outline links %d skills that no longer exist in the skill library. Remove them before saving.',
							missing.length,
							'ohmylms'
						),
						missing.length
					) }
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () => removeSkills( missing ) }
					>
						{ __( 'Remove missing skills', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			<div
				className="ohmylms-outline-tabs"
				role="tablist"
				aria-label={ __( 'Course content sections', 'ohmylms' ) }
			>
				{ tabs.map( ( [ value, label, count ] ) => (
					<button
						key={ value }
						type="button"
						role="tab"
						id={ `ohmylms-outline-tab-${ value }` }
						aria-controls={ `ohmylms-outline-panel-${ value }` }
						aria-selected={ tab === value }
						tabIndex={ tab === value ? 0 : -1 }
						className={ `ohmylms-outline-tab${
							tab === value ? ' is-active' : ''
						}` }
						onClick={ () => setTab( value ) }
						onKeyDown={ tabKey }
					>
						{ label }
						{ count !== undefined && (
							<span
								className={ `ohmylms-outline-tab-count${
									count ? ' is-warning' : ''
								}` }
							>
								{ count || '✓' }
							</span>
						) }
					</button>
				) ) }
			</div>
			<div
				role="tabpanel"
				id="ohmylms-outline-panel-skills"
				aria-labelledby="ohmylms-outline-tab-skills"
				hidden={ tab !== 'skills' }
			>
				<CourseSkillCoverage
					draft={ draft }
					onSelect={ ( type, id ) => {
						setTab( 'outline' );
						select( type, id );
					} }
				/>
			</div>
			<div
				role="tabpanel"
				id="ohmylms-outline-panel-readiness"
				aria-labelledby="ohmylms-outline-tab-readiness"
				hidden={ tab !== 'readiness' }
			>
				<CourseReadiness
					courseId={ courseId }
					draft={ draft }
					dirty={ !! dirty }
					onSummary={ setReadiness }
					onRemoveMissing={ () => removeSkills( missing ) }
					onSelect={ ( type, id ) => {
						setTab( 'outline' );
						select( type, id );
						window.requestAnimationFrame( () => {
							const inspector = document.querySelector(
								'.ohmylms-outline-inspector'
							);
							inspector?.scrollIntoView( {
								block: 'start',
								behavior: 'smooth',
							} );
							inspector?.focus();
						} );
					} }
				/>
			</div>
			<div
				role="tabpanel"
				id="ohmylms-outline-panel-alignment"
				aria-labelledby="ohmylms-outline-tab-alignment"
				hidden={ tab !== 'alignment' }
			>
				{ alignment }
			</div>
			<div
				role="tabpanel"
				id="ohmylms-outline-panel-info"
				aria-labelledby="ohmylms-outline-tab-info"
				className="ohmylms-outline-course-info"
				hidden={ tab !== 'info' }
			>
				{ courseInfo }
			</div>
			<div
				role="tabpanel"
				id="ohmylms-outline-panel-outline"
				aria-labelledby="ohmylms-outline-tab-outline"
				hidden={ tab !== 'outline' }
			>
				<div className="ohmylms-outline-columns">
					<CourseOutlineTree
						draft={ draft }
						selected={ selected }
						busy={ busy }
						onSelect={ select }
						onAddUnit={ addUnit }
						onAdd={ openPicker }
						onMove={ move }
					/>
					<div className="ohmylms-outline-inspector" tabIndex={ -1 }>
						{ ! unit && ! activity && ! skill && (
							<div>
								<h2>
									{ __( 'Course completion', 'ohmylms' ) }
								</h2>
								<p>
									{ __(
										'Select an activity in the outline to require its completion. Required skills also need enough approved questions for their target.',
										'ohmylms'
									) }
								</p>
								{ draft.program.outcomes.length > 0 && (
									<fieldset>
										<legend>
											{ __(
												'Skills required for completion',
												'ohmylms'
											) }
										</legend>
										{ draft.program.outcomes.map(
											( outcome ) => (
												<CheckboxControl
													key={ outcome.term_id }
													label={ outlineSkillName(
														draft.skill_names,
														outcome.term_id
													) }
													checked={
														!! outcome.required
													}
													disabled={ busy }
													onChange={ ( required ) =>
														program( {
															outcomes:
																draft.program.outcomes.map(
																	( row ) =>
																		row.term_id ===
																		outcome.term_id
																			? {
																					...row,
																					required,
																				}
																			: row
																),
														} )
													}
												/>
											)
										) }
									</fieldset>
								) }
								{ draft.units.length === 0 ? (
									<Button
										variant="primary"
										onClick={ addUnit }
									>
										{ __(
											'Add your first unit',
											'ohmylms'
										) }
									</Button>
								) : (
									<p className="ohmylms-outline-hint">
										{ __(
											'Each unit holds lessons, practice, quizzes and assignments. Select anything in the outline to edit it.',
											'ohmylms'
										) }
									</p>
								) }
							</div>
						) }
						{ skill && (
							<div>
								<h2>{ __( 'Skill', 'ohmylms' ) }</h2>
								<p>
									<strong>
										{ outlineSkillName(
											draft.skill_names,
											skill.term_id
										) }
									</strong>
								</p>
								{ ! draft.skill_names?.[ skill.term_id ] && (
									<Notice
										status="warning"
										isDismissible={ false }
									>
										{ __(
											'This skill no longer exists in the skill library. Remove it from the course before saving.',
											'ohmylms'
										) }
									</Notice>
								) }
								{ draft.skill_names?.[ skill.term_id ] && (
									<SkillProvenance
										termId={ skill.term_id }
										meta={
											draft.skill_meta?.[ skill.term_id ]
										}
										canReview={ !! draft.can_review_skills }
										onChange={ ( patch ) =>
											updateSkillMeta(
												skill.term_id,
												patch
											)
										}
									/>
								) }
								<CheckboxControl
									label={ __(
										'Required for completion',
										'ohmylms'
									) }
									checked={ !! skill.required }
									disabled={ busy }
									onChange={ ( required ) =>
										program( {
											outcomes:
												draft.program.outcomes.map(
													( row ) =>
														row.term_id ===
														skill.term_id
															? {
																	...row,
																	required,
																}
															: row
												),
										} )
									}
								/>
								{ ! skillPractice &&
									draft.skill_names?.[ skill.term_id ] && (
										<Button
											variant="secondary"
											disabled={ busy }
											onClick={ () => {
												const id = uuid();
												const next = addOutlinePractice(
													draft.program,
													{
														termId: skill.term_id,
														unitId:
															skill.chapter_id ||
															0,
														groupId:
															skill.group_id ||
															'',
														id,
													}
												);
												if ( next ) {
													program( next );
													select( 'activity', id );
												}
											} }
										>
											{ __(
												'Add practice for this skill',
												'ohmylms'
											) }
										</Button>
									) }
								<Button
									isDestructive
									disabled={ busy }
									onClick={ () =>
										removeSkills( [ skill.term_id ] )
									}
								>
									{ __(
										'Remove skill from course',
										'ohmylms'
									) }
								</Button>
							</div>
						) }
						{ unit && (
							<div>
								<h2>{ __( 'Unit', 'ohmylms' ) }</h2>
								<TextControl
									label={ __( 'Unit title', 'ohmylms' ) }
									value={ unit.title }
									disabled={ busy }
									onChange={ ( title ) =>
										change( {
											units: draft.units.map( ( row ) =>
												row.id === unit.id
													? { ...row, title }
													: row
											),
										} )
									}
								/>
								<TextControl
									label={ __( 'Unit number', 'ohmylms' ) }
									value={ unit.number || '' }
									disabled={ busy }
									onChange={ ( number ) =>
										change( {
											units: draft.units.map( ( row ) =>
												row.id === unit.id
													? { ...row, number }
													: row
											),
										} )
									}
								/>
								<TextareaControl
									label={ __(
										'Unit description',
										'ohmylms'
									) }
									value={ unit.description || '' }
									disabled={ busy }
									onChange={ ( description ) =>
										change( {
											units: draft.units.map( ( row ) =>
												row.id === unit.id
													? { ...row, description }
													: row
											),
										} )
									}
								/>
								{ moveButtons( draft.units, unit, ( units ) =>
									change( { units } )
								) }
								<Button
									variant="secondary"
									disabled={ busy }
									onClick={ () =>
										openPicker( unit.id, '', 'lesson' )
									}
								>
									{ __( 'Add lesson', 'ohmylms' ) }
								</Button>
								<Button
									disabled={ busy }
									onClick={ () => openPicker( unit.id ) }
								>
									{ __( 'Add activity to unit', 'ohmylms' ) }
								</Button>
								<Button
									isDestructive
									disabled={
										busy ||
										draft.program.items.some( ( row ) =>
											same( row.chapter_id, unit.id )
										) ||
										draft.program.outcomes.some( ( row ) =>
											same( row.chapter_id, unit.id )
										)
									}
									onClick={ () => removeUnit( unit ) }
								>
									{ __( 'Remove empty unit', 'ohmylms' ) }
								</Button>
							</div>
						) }
						{ activity && (
							<div>
								<h2>{ __( 'Activity', 'ohmylms' ) }</h2>
								{ activity.type !== 'practice' &&
									activity.status &&
									activity.status !== 'publish' && (
										<Notice
											status="warning"
											isDismissible={ false }
										>
											{ __(
												'This resource is a draft. Finish and publish it in its content editor before publishing the outline.',
												'ohmylms'
											) }
										</Notice>
									) }
								{ activity.type === 'practice' ? (
									<>
										<p>
											{ sprintf(
												/* translators: %s: practice skill name. */ __(
													'Practices skill: %s',
													'ohmylms'
												),
												draft.skill_names?.[
													activity.content_id
												] || activity.name
											) }
										</p>
										{ draft.skill_names?.[
											activity.content_id
										] && (
											<SkillProvenance
												termId={ activity.content_id }
												meta={
													draft.skill_meta?.[
														activity.content_id
													]
												}
												canReview={
													!! draft.can_review_skills
												}
												onChange={ ( patch ) =>
													updateSkillMeta(
														activity.content_id,
														patch
													)
												}
											/>
										) }
									</>
								) : (
									<ActivitySkillConnections
										draft={ draft }
										activity={ activity }
										busy={ busy }
										onSelect={ select }
										onChange={ ( skillIds ) =>
											program( {
												items: draft.program.items.map(
													( item ) =>
														item.id === activity.id
															? {
																	...item,
																	skill_ids:
																		skillIds,
																}
															: item
												),
											} )
										}
									/>
								) }
								<TextControl
									label={ __(
										'Activity name in this course',
										'ohmylms'
									) }
									value={ activity.title ?? activity.name }
									disabled={ busy }
									onChange={ ( title ) =>
										program( {
											items: draft.program.items.map(
												( row ) =>
													row.id === activity.id
														? { ...row, title }
														: row
											),
										} )
									}
								/>
								<SelectControl
									label={ __(
										'Unit for activity',
										'ohmylms'
									) }
									value={ String( activity.chapter_id || 0 ) }
									disabled={
										busy ||
										activity.style === 'course-challenge'
									}
									options={ [
										{
											label: __(
												'Course level',
												'ohmylms'
											),
											value: '0',
										},
										...draft.units.map( ( row ) => ( {
											label: row.title,
											value: String( row.id ),
										} ) ),
									] }
									onChange={ ( value ) => {
										const target = draft.units.find(
											( row ) => same( row.id, value )
										);
										program( {
											items: draft.program.items.map(
												( row ) =>
													row.id === activity.id
														? {
																...row,
																chapter_id:
																	target
																		? target.id
																		: 0,
																group_id: '',
															}
														: row
											),
										} );
									} }
								/>
								{ special( activity ) ? (
									<p>
										{ activity.style === 'unit-test'
											? __(
													'Unit tests appear at the end of a unit, outside lessons.',
													'ohmylms'
												)
											: __(
													'The course challenge appears after all units.',
													'ohmylms'
												) }
									</p>
								) : (
									moveButtons(
										rowsIn(
											activity.chapter_id,
											activity.group_id || ''
										).filter( ( row ) => ! special( row ) ),
										activity,
										( next ) =>
											program( {
												items: [
													...draft.program.items.filter(
														( row ) =>
															! next.some(
																( entry ) =>
																	entry.id ===
																	row.id
															)
													),
													...next,
												],
											} )
									)
								) }
								<CheckboxControl
									label={ __(
										'Required for completion',
										'ohmylms'
									) }
									checked={ !! activity.required }
									disabled={
										busy || activity.type === 'practice'
									}
									onChange={ ( required ) =>
										program( {
											items: draft.program.items.map(
												( row ) =>
													row.id === activity.id
														? { ...row, required }
														: row
											),
										} )
									}
								/>
								{ activity.edit_url && (
									<Button
										variant="secondary"
										href={ activity.edit_url }
										target="_blank"
										rel="noreferrer"
									>
										{ __(
											'Edit resource content',
											'ohmylms'
										) }
									</Button>
								) }
								<Button
									isDestructive
									disabled={ busy }
									onClick={ () => {
										program( {
											items: draft.program.items.filter(
												( row ) =>
													row.id !== activity.id
											),
										} );
										setSelected( null );
									} }
								>
									{ __(
										'Remove activity placement',
										'ohmylms'
									) }
								</Button>
							</div>
						) }
					</div>
				</div>
			</div>
			{ picker && (
				<Modal
					title={
						{
							'unit-test': __( 'Add unit test', 'ohmylms' ),
							'course-challenge': __(
								'Add course challenge',
								'ohmylms'
							),
						}[ picker.style ] ||
						( picker.skills &&
							__( 'Link skills to lesson', 'ohmylms' ) ) ||
						( picker.lesson
							? __( 'Add lesson', 'ohmylms' )
							: __( 'Add activity', 'ohmylms' ) )
					}
					onRequestClose={
						pickerBusy ? () => {} : () => setPicker( null )
					}
				>
					{ ! picker.skills && (
						<div className="ohmylms-outline-activity-actions">
							<Button
								variant={
									picker.create ? 'secondary' : 'primary'
								}
								disabled={ pickerBusy }
								onClick={ () =>
									setPicker( { ...picker, create: false } )
								}
							>
								{ picker.lesson
									? __( 'Choose existing lesson', 'ohmylms' )
									: __( 'Use existing activity', 'ohmylms' ) }
							</Button>
							<Button
								variant={
									picker.create ? 'primary' : 'secondary'
								}
								disabled={ pickerBusy }
								onClick={ () =>
									setPicker( { ...picker, create: true } )
								}
							>
								{ picker.lesson
									? __( 'Create new lesson', 'ohmylms' )
									: __( 'Create new activity', 'ohmylms' ) }
							</Button>
						</div>
					) }
					{ picker.create ? (
						<ActivityCreateForm
							courseId={ courseId }
							revision={ draft.revision }
							requestId={ picker.requestId }
							initialKind={
								[ 'video', 'reading', 'quiz' ].includes(
									picker.initialKind
								)
									? picker.initialKind
									: 'reading'
							}
							onBusy={ setPickerBusy }
							onCreated={ addActivity }
							lessonOnly={ !! picker.lesson }
							style={ picker.style }
						/>
					) : (
						<>
							{ picker.style && (
								<p>
									{ 'unit-test' === picker.style
										? __(
												'Choose a quiz whose style is Unit test. It appears at the end of the unit and checks every skill in it.',
												'ohmylms'
											)
										: __(
												'Choose a quiz whose style is Course challenge. It appears after all units and checks skills across the course.',
												'ohmylms'
											) }{ ' ' }
									{ __(
										'Or choose Create new activity to make a draft with this style.',
										'ohmylms'
									) }
								</p>
							) }
							{ ! picker.skills &&
								! picker.style &&
								! picker.lesson && (
									<SelectControl
										label={ __(
											'Activity type',
											'ohmylms'
										) }
										value={ kind }
										options={ [
											{
												value: 'lesson',
												label: __(
													'Reading, video or audio',
													'ohmylms'
												),
											},
											{
												value: 'quiz',
												label: __(
													'Quiz, unit test or course challenge',
													'ohmylms'
												),
											},
											{
												value: 'assignment',
												label: __(
													'Assignment',
													'ohmylms'
												),
											},
											{
												value: 'practice',
												label: __(
													'Practice a skill',
													'ohmylms'
												),
											},
										] }
										onChange={ ( value ) => {
											setKind( value );
											setResourcePage( 1 );
											setResults( [] );
										} }
									/>
								) }
							{ picker.skills && (
								<p>
									{ __(
										'Choose a skill from the library. The same skill can be linked to several lessons. New course skills begin optional for completion.',
										'ohmylms'
									) }
								</p>
							) }
							<TextControl
								label={
									( picker.skills &&
										__( 'Find a skill', 'ohmylms' ) ) ||
									( picker.style &&
										__( 'Find a quiz', 'ohmylms' ) ) ||
									( picker.lesson &&
										__( 'Find a lesson', 'ohmylms' ) ) ||
									__( 'Find an activity', 'ohmylms' )
								}
								value={ search }
								onChange={ ( value ) => {
									setSearch( value );
									setResourcePage( 1 );
								} }
							/>
							{ kind === 'practice' && ! picker.skills && (
								<p>
									{ results.length || search.trim()
										? __(
												'Practice is the skill itself. Skills linked here are listed first; search to link and practise any library skill.',
												'ohmylms'
											)
										: __(
												'No skills are linked here yet. Search the skill library to link a skill and add its practice in one step.',
												'ohmylms'
											) }
								</p>
							) }
							<ul className="ohmylms-outline-resources">
								{ visible.map( ( resource ) => {
									const elsewhere =
										kind === 'practice' &&
										! picker.skills &&
										'elsewhere' ===
											practicePlacement(
												draft.program,
												resource.id,
												picker
											);
									return (
										<li key={ resource.id }>
											<span>
												{ resource.code && (
													<span className="ohmylms-outline-code">
														{ resource.code }
													</span>
												) }
												{ resource.title }
												{ elsewhere && (
													<small>
														{ __(
															'Linked to another lesson',
															'ohmylms'
														) }
													</small>
												) }
											</span>
											<Button
												variant="secondary"
												disabled={ elsewhere }
												label={ sprintf(
													/* translators: %s: resource title. */ __(
														'Add %s',
														'ohmylms'
													),
													resource.title
												) }
												showTooltip={ false }
												onClick={ () => {
													if ( picker.skills ) {
														linkSkill( resource );
													} else if (
														kind === 'practice'
													) {
														addPractice( resource );
													} else {
														addActivity( resource );
													}
												} }
											>
												{ __( 'Add', 'ohmylms' ) }
											</Button>
										</li>
									);
								} ) }
								{ picker.style && ! visible.length && (
									<li>
										{ __(
											'No matching quizzes on this page.',
											'ohmylms'
										) }
									</li>
								) }
							</ul>
							<Button
								disabled={ resourcePage <= 1 }
								onClick={ () =>
									setResourcePage( resourcePage - 1 )
								}
							>
								{ __( 'Previous', 'ohmylms' ) }
							</Button>
							<Button
								disabled={ resourcePage >= resourcePages }
								onClick={ () =>
									setResourcePage( resourcePage + 1 )
								}
							>
								{ __( 'Next', 'ohmylms' ) }
							</Button>
							{ ! picker.lesson &&
								draft.create_urls?.[ kind ] && (
									<Button
										href={ draft.create_urls[ kind ] }
										target="_blank"
										rel="noreferrer"
									>
										{ __(
											'Create a new resource in its editor',
											'ohmylms'
										) }
									</Button>
								) }
						</>
					) }
				</Modal>
			) }
		</section>
	);
}
