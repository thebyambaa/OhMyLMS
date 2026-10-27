/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { createSkill } from '../question-bank/api.mjs';
import { searchTargets, loadCatalog } from './api.mjs';
import { loadTree, loadSyllabus } from '../curriculum/api.mjs';
import {
	loadSkillCurriculum,
	skillCurriculumTree,
} from './skillCurriculum.mjs';

const label = ( skill ) =>
	`${ skill.code ? `${ skill.code } · ` : '' }${ skill.name }`;

/**
 * Choose skills from the shared library, or create one on the spot. Skills are shared, so choosing is a
 * reference: nothing is copied, and the same skill can sit in many courses and lessons.
 *
 * `initial` skills start checked. With `courseId`, skills the course already has are left out.
 * @param root0
 * @param root0.title
 * @param root0.confirmLabel
 * @param root0.initial
 * @param root0.courseId
 * @param root0.onConfirm
 * @param root0.onClose
 */
export function SkillPicker( {
	title,
	confirmLabel,
	initial = [],
	courseId = 0,
	onConfirm,
	onClose,
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ page, setPage ] = useState( 1 );
	const [ pages, setPages ] = useState( 1 );
	const [ more, setMore ] = useState( false );
	const [ selected, setSelected ] = useState(
		() => new Map( initial.map( ( skill ) => [ skill.id, skill ] ) )
	);
	const [ draft, setDraft ] = useState( { name: '', code: '' } );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const latest = useRef( 0 );
	const [ curriculum, setCurriculum ] = useState( null );
	const [ excluded, setExcluded ] = useState( [] );
	const [ curriculumError, setCurriculumError ] = useState( '' );

	useEffect( () => {
		let active = true;
		setCurriculum( null );
		setCurriculumError( '' );
		Promise.all( [
			loadSkillCurriculum( loadTree, loadSyllabus ),
			courseId ? loadCatalog( courseId ) : Promise.resolve( null ),
		] )
			.then( ( [ data, catalog ] ) => {
				if ( active ) {
					setExcluded(
						( catalog?.skills || [] ).map( ( row ) => row.term_id )
					);
					setCurriculum( data );
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setCurriculum( { items: [], outlines: [] } );
					setCurriculumError(
						cause.message ||
							__(
								'Could not load curriculum. You can still choose skills from the library.',
								'ohmylms'
							)
					);
				}
			} );
		return () => {
			active = false;
		};
	}, [ courseId ] );

	useEffect( () => {
		const id = ++latest.current;
		const timer = setTimeout( () => {
			searchTargets( { type: 'skill', search, course: courseId } )
				.then( ( data ) => {
					if ( id === latest.current ) {
						setPage( 1 );
						setPages( data.pages || 1 );
						setResults(
							( data.items || [] ).map( ( row ) => ( {
								id: row.id,
								name: row.title,
								code: row.code,
								questions: row.questions,
							} ) )
						);
					}
				} )
				.catch( ( cause ) => {
					if ( id === latest.current ) {
						setResults( [] );
						setError(
							cause.message ||
								__( 'Could not load skills.', 'ohmylms' )
						);
					}
				} );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ search, courseId ] );

	async function loadMore() {
		setMore( true );
		try {
			const data = await searchTargets( {
				type: 'skill',
				search,
				course: courseId,
				page: page + 1,
			} );
			setPage( page + 1 );
			setPages( data.pages || pages );
			setResults( ( current ) => [
				...( current || [] ),
				...( data.items || [] ).map( ( row ) => ( {
					id: row.id,
					name: row.title,
					code: row.code,
					questions: row.questions,
				} ) ),
			] );
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not load skills.', 'ohmylms' )
			);
		}
		setMore( false );
	}

	const toggle = ( skill, checked ) =>
		setSelected( ( current ) => {
			const next = new Map( current );
			if ( checked ) {
				next.set( skill.id, skill );
			} else {
				next.delete( skill.id );
			}
			return next;
		} );

	async function create() {
		if ( ! draft.name.trim() || busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			const skill = await createSkill( {
				name: draft.name.trim(),
				code: draft.code.trim(),
			} );
			const entry = {
				id: skill.id,
				name: skill.name,
				code: skill.code || '',
				questions: 0,
			};
			setResults( ( rows ) => [ entry, ...( rows || [] ) ] );
			toggle( entry, true );
			setDraft( { name: '', code: '' } );
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not create the skill.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}

	// Selected skills that the current search does not show stay visible, so nothing is picked blind.
	const { tree, placed, visible } = skillCurriculumTree(
		curriculum || {},
		search,
		excluded
	);
	const shown = new Set( ( results || [] ).map( ( row ) => row.id ) );
	const hidden = [ ...selected.values() ].filter(
		( skill ) => ! shown.has( skill.id ) && ! visible.has( skill.id )
	);
	const skillCheckbox = ( skill, key = skill.id ) => (
		<CheckboxControl
			key={ key }
			label={ label( skill ) }
			checked={ selected.has( skill.id ) }
			onChange={ ( checked ) => toggle( skill, checked ) }
			__nextHasNoMarginBottom
		/>
	);
	const renderBranch = ( node ) => {
		if ( node.skill ) {
			return skillCheckbox( node.skill, node.key );
		}
		return (
			<details
				key={ `${ search }:${ node.key }` }
				open={ !! search }
				className="ohmylms-skill-picker-branch"
			>
				<summary>{ label( node ) }</summary>
				<div>{ node.children.map( renderBranch ) }</div>
			</details>
		);
	};
	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			className="ohmylms-content-hub-dialog ohmylms-skill-picker"
		>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ curriculumError && (
				<Notice status="warning" isDismissible={ false }>
					{ curriculumError }
				</Notice>
			) }
			<TextControl
				label={ __(
					'Search curriculum, topics and skills',
					'ohmylms'
				) }
				type="search"
				value={ search }
				onChange={ setSearch }
				__nextHasNoMarginBottom
			/>
			<div
				className="ohmylms-skill-picker-list"
				role="group"
				aria-label={ __( 'Skills', 'ohmylms' ) }
			>
				{ ( results === null || curriculum === null ) && <Spinner /> }
				{ tree.map( renderBranch ) }
				{ results !== null &&
					curriculum !== null &&
					! tree.length &&
					! results.length &&
					! hidden.length && (
						<p className="ohmylms-ext-muted">
							{ __(
								'No matching skills. Create one below.',
								'ohmylms'
							) }
						</p>
					) }
				{ hidden.length > 0 && (
					<div className="ohmylms-skill-picker-selected">
						<strong>{ __( 'Selected skills', 'ohmylms' ) }</strong>
						{ hidden.map( ( skill ) => skillCheckbox( skill ) ) }
					</div>
				) }
				{ ( results || [] ).some(
					( skill ) => ! placed.has( skill.id )
				) && (
					<details
						className="ohmylms-skill-picker-branch"
						open={ !! search || ! tree.length }
					>
						<summary>
							{ __( 'Other skills in the library', 'ohmylms' ) }
						</summary>
						<div>
							{ ( results || [] )
								.filter( ( skill ) => ! placed.has( skill.id ) )
								.map( ( skill ) => skillCheckbox( skill ) ) }
							{ page < pages && (
								<Button
									variant="link"
									isBusy={ more }
									disabled={ more }
									onClick={ loadMore }
								>
									{ __( 'Show more skills', 'ohmylms' ) }
								</Button>
							) }
						</div>
					</details>
				) }
			</div>
			<details className="ohmylms-skill-picker-create">
				<summary>{ __( 'Create a new skill', 'ohmylms' ) }</summary>
				<TextControl
					label={ __( 'Name', 'ohmylms' ) }
					value={ draft.name }
					onChange={ ( name ) => setDraft( { ...draft, name } ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Code (optional)', 'ohmylms' ) }
					help={ __(
						'Shown beside the name, like A.1 in a skill catalog.',
						'ohmylms'
					) }
					value={ draft.code }
					onChange={ ( code ) => setDraft( { ...draft, code } ) }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ ! draft.name.trim() || busy }
					onClick={ create }
				>
					{ __( 'Create skill', 'ohmylms' ) }
				</Button>
			</details>
			<div className="ohmylms-content-hub-dialog-actions">
				<span className="ohmylms-ext-muted" aria-live="polite">
					{ sprintf(
						/* translators: %d: number of selected skills. */
						_n(
							'%d skill selected',
							'%d skills selected',
							selected.size,
							'ohmylms'
						),
						selected.size
					) }
				</span>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => onConfirm( [ ...selected.values() ] ) }
				>
					{ confirmLabel }
				</Button>
			</div>
		</Modal>
	);
}
