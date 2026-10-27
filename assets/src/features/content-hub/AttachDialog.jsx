import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { createContent, searchTargets } from './api.mjs';
import { ImportButton } from './ImportButton.jsx';
import { DEFAULT_PASS_PERCENT } from './catalogModel.mjs';
const typeOptions = () => [
	{
		label: __( 'Lesson', 'ohmylms' ),
		value: 'lesson',
	},
	{
		label: __( 'Quiz', 'ohmylms' ),
		value: 'quiz',
	},
	{
		label: __( 'Assignment', 'ohmylms' ),
		value: 'assignment',
	},
];

/**
 * Attach lessons, quizzes or assignments to a whole chapter or to chosen skills. Content is reusable, so
 * picking existing content is a reference, and new content can be created right here and attached at once.
 * @param root0
 * @param root0.courseId
 * @param root0.chapters
 * @param root0.skills
 * @param root0.scope
 * @param root0.onConfirm
 * @param root0.onClose
 */
export function AttachDialog( {
	courseId,
	chapters,
	skills,
	scope,
	onConfirm,
	onClose,
} ) {
	const [ type, setType ] = useState( 'lesson' );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ picked, setPicked ] = useState( () => new Map() );
	const [ target, setTarget ] = useState(
		scope.skillIds.length ? 'skills' : 'chapter'
	);
	const [ skillIds, setSkillIds ] = useState( scope.skillIds );
	const [ required, setRequired ] = useState( false );
	const [ passPercent, setPassPercent ] = useState( DEFAULT_PASS_PERCENT );
	const [ name, setName ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const latest = useRef( 0 );
	const chapter = chapters.find( ( row ) => row.id === scope.chapterId );
	useEffect( () => {
		const id = ++latest.current;
		setResults( null );
		const timer = setTimeout( () => {
			searchTargets( {
				type,
				search,
				course: courseId,
			} )
				.then( ( data ) => {
					if ( id === latest.current ) {
						setResults( data.items || [] );
					}
				} )
				.catch( ( cause ) => {
					if ( id === latest.current ) {
						setResults( [] );
						setError(
							cause.message ||
								__( 'Could not load content.', 'ohmylms' )
						);
					}
				} );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ type, search, courseId ] );
	const key = ( row ) => `${ row.type }:${ row.id }`;
	const choose = ( row, checked ) =>
		setPicked( ( current ) => {
			const next = new Map( current );
			if ( checked ) {
				next.set( key( row ), row );
			} else {
				next.delete( key( row ) );
			}
			return next;
		} );
	async function create() {
		if ( ! name.trim() || busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			const made = await createContent( type, name.trim() );
			const row = {
				id: made.id,
				type,
				title: name.trim(),
				status: 'draft',
			};
			setResults( ( rows ) => [ row, ...( rows || [] ) ] );
			choose( row, true );
			setName( '' );
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not create it.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	const rows = [ ...picked.values() ].filter( ( row ) => row.type === type );
	const shown = new Set( ( results || [] ).map( ( row ) => row.id ) );
	const list = [
		...rows.filter( ( row ) => ! shown.has( row.id ) ),
		...( results || [] ).map( ( row ) => ( {
			...row,
			type,
		} ) ),
	];
	const chosen = [ ...picked.values() ];
	const valid =
		chosen.length > 0 && ( target === 'chapter' || skillIds.length > 0 );
	const scopeLabel = chapter
		? sprintf(
				/* translators: %s: lesson or chapter title. */ __(
					'The whole chapter “%s”',
					'ohmylms'
				),
				chapter.name
			)
		: __( 'The whole course (no chapter)', 'ohmylms' );
	const hasQuiz = chosen.some( ( row ) => row.type === 'quiz' );
	return (
		<Modal
			title={ __( 'Attach content', 'ohmylms' ) }
			onRequestClose={ onClose }
			className="ohmylms-content-hub-dialog ohmylms-attach-dialog"
		>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<SelectControl
				label={ __( 'Type', 'ohmylms' ) }
				value={ type }
				options={ typeOptions() }
				onChange={ setType }
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'Search existing content', 'ohmylms' ) }
				type="search"
				value={ search }
				onChange={ setSearch }
				__nextHasNoMarginBottom
			/>
			<div
				className="ohmylms-skill-picker-list"
				role="group"
				aria-label={ __( 'Content', 'ohmylms' ) }
			>
				{ results === null && <Spinner /> }
				{ results !== null && ! list.length && (
					<p className="ohmylms-ext-muted">
						{ __( 'Nothing matches. Create it below.', 'ohmylms' ) }
					</p>
				) }
				{ list.map( ( row ) => (
					<CheckboxControl
						key={ key( row ) }
						label={ `${ row.title }${ row.status && row.status !== 'publish' ? ` (${ row.status })` : '' }` }
						checked={ picked.has( key( row ) ) }
						onChange={ ( checked ) => choose( row, checked ) }
						__nextHasNoMarginBottom
					/>
				) ) }
			</div>
			<details className="ohmylms-skill-picker-create">
				<summary>{ __( 'Create new and attach', 'ohmylms' ) }</summary>
				<TextControl
					label={ __( 'Title', 'ohmylms' ) }
					value={ name }
					onChange={ setName }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ ! name.trim() || busy }
					onClick={ create }
				>
					{ __( 'Create draft', 'ohmylms' ) }
				</Button>
				{ type === 'quiz' && (
					<ImportButton
						target="quiz"
						targets={ [ 'quiz' ] }
						onQuiz={ ( quiz ) => {
							const row = {
								id: quiz.id,
								type: 'quiz',
								title: quiz.name,
								status: 'draft',
							};
							setResults( ( current ) => [
								row,
								...( current || [] ),
							] );
							choose( row, true );
						} }
					/>
				) }
			</details>
			<RadioControl
				label={ __( 'Attach to', 'ohmylms' ) }
				selected={ target }
				options={ [
					{
						label: scopeLabel,
						value: 'chapter',
					},
					{
						label: __( 'Selected skills', 'ohmylms' ),
						value: 'skills',
					},
				] }
				onChange={ setTarget }
			/>
			{ target === 'skills' && (
				<div
					className="ohmylms-skill-picker-list"
					role="group"
					aria-label={ __( 'Skills in this course', 'ohmylms' ) }
				>
					{ ! skills.length && (
						<p className="ohmylms-ext-muted">
							{ __(
								'Add skills to this course first.',
								'ohmylms'
							) }
						</p>
					) }
					{ skills.map( ( skill ) => (
						<CheckboxControl
							key={ skill.term_id }
							label={ `${ skill.code ? `${ skill.code } · ` : '' }${ skill.name }` }
							checked={ skillIds.includes( skill.term_id ) }
							onChange={ ( checked ) =>
								setSkillIds( ( current ) =>
									checked
										? [ ...current, skill.term_id ]
										: current.filter(
												( id ) => id !== skill.term_id
											)
								)
							}
							__nextHasNoMarginBottom
						/>
					) ) }
				</div>
			) }
			<CheckboxControl
				label={ __(
					'Learners must complete this to finish the course',
					'ohmylms'
				) }
				checked={ required }
				onChange={ setRequired }
				__nextHasNoMarginBottom
			/>
			{ hasQuiz && (
				<TextControl
					label={ __( 'Quiz pass percentage', 'ohmylms' ) }
					type="number"
					min={ 0 }
					max={ 100 }
					value={ passPercent }
					onChange={ ( value ) =>
						setPassPercent(
							Math.max( 0, Math.min( 100, Number( value ) || 0 ) )
						)
					}
					__nextHasNoMarginBottom
				/>
			) }
			<div className="ohmylms-content-hub-dialog-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					disabled={ ! valid }
					onClick={ () =>
						onConfirm( {
							picks: chosen,
							scope: {
								chapterId: scope.chapterId,
								skillIds: target === 'skills' ? skillIds : [],
							},
							options: {
								required,
								passPercent,
							},
						} )
					}
				>
					{ sprintf(
						/* translators: %d: number of attached learning activities. */ __(
							'Attach %d',
							'ohmylms'
						),
						chosen.length
					) }
				</Button>
			</div>
		</Modal>
	);
}
