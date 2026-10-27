import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dashicon,
	Modal,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { NewQuestionModal } from '../question-bank/NewQuestionModal';
import { createContent } from '../content-hub/api.mjs';
import { useWorkspace } from './context';
import {
	withEditorReturn,
	lessonEditorHref,
} from '../content-hub/editorNavigation.mjs';
import { skillLessonCreator } from './skillLessonCreation.mjs';
import * as api from './api.mjs';
import { editPath } from './workspace.mjs';
import { KindIcon, Tag } from './WorkspaceParts';

/** The name of the extension slot a future module (practice games, for one) fills to add its own section. */
export const RESOURCE_SLOT = 'syllabus.skill.resources';
const STATUS = {
	approved: () => __( 'Approved', 'ohmylms' ),
	draft: () => __( 'Draft', 'ohmylms' ),
	archived: () => __( 'Archived', 'ohmylms' ),
};

/**
 * Find lessons to tag to the skill, or write a new one (a draft) and tag it at once.
 * @param root0
 * @param root0.skill
 * @param root0.tagged
 * @param root0.onChoose
 * @param root0.onCreated
 * @param root0.onClose
 * @param root0.mode
 */
function LessonPicker( { skill, tagged, onChoose, onCreated, onClose, mode } ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ title, setTitle ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ retrying, setRetrying ] = useState( false );
	const creator = useRef( null );
	const latest = useRef( 0 );
	useEffect( () => {
		if ( mode !== 'bank' ) {
			return;
		}
		const id = ++latest.current;
		const timer = setTimeout(
			() => {
				api.lessonTargets( {
					search,
				} )
					.then(
						( rows ) =>
							id === latest.current && setResults( rows || [] )
					)
					.catch( ( cause ) => {
						if ( id !== latest.current ) {
							return;
						}
						setResults( [] );
						setError(
							cause?.message ||
								__( 'Could not search.', 'ohmylms' )
						);
					} );
			},
			search ? 250 : 0
		);
		return () => clearTimeout( timer );
	}, [ search, mode ] );
	async function create( event ) {
		event.preventDefault();
		if ( ! title.trim() || busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			if ( ! creator.current ) {
				creator.current = skillLessonCreator( skill.term_id );
			}
			const made = await creator.current( title );
			onClose();
			onCreated( made );
		} catch ( cause ) {
			setError(
				cause?.message ||
					__( 'Could not create the lesson.', 'ohmylms' )
			);
			setRetrying( true );
			setBusy( false );
		}
	}
	async function choose( row ) {
		if ( busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			if ( ! ( await onChoose( row ) ) ) {
				setError(
					__(
						'The lesson could not be linked to this skill. Try again.',
						'ohmylms'
					)
				);
			}
		} catch ( cause ) {
			setError(
				cause?.message ||
					__(
						'The lesson could not be linked to this skill.',
						'ohmylms'
					)
			);
		} finally {
			setBusy( false );
		}
	}
	const available = ( results || [] ).filter(
		( row ) => ! tagged.has( row.id )
	);
	return (
		<Modal
			title={
				mode === 'create'
					? __( 'Add lesson', 'ohmylms' )
					: __( 'Lesson bank', 'ohmylms' )
			}
			onRequestClose={ () => ! busy && onClose() }
			className="ohmylms-content-hub-dialog"
		>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ mode === 'bank' && (
				<>
					<TextControl
						label={ __( 'Search lessons', 'ohmylms' ) }
						type="search"
						value={ search }
						onChange={ setSearch }
						__nextHasNoMarginBottom
					/>
					<div
						className="ohmylms-skill-picker-list"
						role="group"
						aria-label={ __( 'Lessons', 'ohmylms' ) }
					>
						{ results === null && <Spinner /> }
						{ results !== null && ! available.length && (
							<p className="ohmylms-ext-muted">
								{ __(
									'No additional lessons match your search.',
									'ohmylms'
								) }
							</p>
						) }
						{ available.map( ( row ) => (
							<div key={ row.id } className="ohmylms-ws-pick">
								<span>{ row.title }</span>
								<Button
									variant="secondary"
									size="small"
									disabled={ busy }
									aria-label={ sprintf(
										/* translators: %s: skill or linked content name. */ __(
											'Add %s to this skill',
											'ohmylms'
										),
										row.title
									) }
									onClick={ () => choose( row ) }
								>
									{ __( 'Add', 'ohmylms' ) }
								</Button>
							</div>
						) ) }
					</div>
				</>
			) }
			{ mode === 'create' && (
				<form className="ohmylms-ws-pick-new" onSubmit={ create }>
					<TextControl
						label={ __( 'New lesson title', 'ohmylms' ) }
						placeholder={ __(
							'Enter a title to create a lesson',
							'ohmylms'
						) }
						help={ __(
							'Creates a draft linked to this skill and opens the lesson editor.',
							'ohmylms'
						) }
						value={ title }
						onChange={ setTitle }
						disabled={ busy || retrying }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="primary"
						type="submit"
						isBusy={ busy }
						disabled={ ! title.trim() || busy }
					>
						{ retrying
							? __( 'Retry create and add', 'ohmylms' )
							: __( 'Create and add', 'ohmylms' ) }
					</Button>
				</form>
			) }
			<div className="ohmylms-content-hub-dialog-actions">
				<Button
					variant="tertiary"
					disabled={ busy }
					onClick={ onClose }
				>
					{ __( 'Done', 'ohmylms' ) }
				</Button>
			</div>
		</Modal>
	);
}

/**
 * What a skill owns. Lessons are tagged to the skill and questions are mapped to it in the question bank, so
 * both follow the skill into every syllabus and course that uses it. Games (soon) will add a section here
 * through the `syllabus.skill.resources` slot.
 * @param root0
 * @param root0.skill
 * @param root0.syllabus
 * @param root0.returnTo
 */
export function SkillResources( { skill, syllabus, returnTo } ) {
	const w = useWorkspace();
	const [ lessons, setLessons ] = useState( null );
	const [ questions, setQuestions ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ picking, setPicking ] = useState( '' );
	const [ newQuestion, setNewQuestion ] = useState( false );
	const [ newQuiz, setNewQuiz ] = useState( false );
	const [ quizTitle, setQuizTitle ] = useState( '' );
	const quizDraft = useRef( null );
	const [ busy, setBusy ] = useState( false );
	const [ version, setVersion ] = useState( 0 );
	useEffect( () => {
		let current = true;
		setLessons( null );
		setQuestions( null );
		setError( '' );
		api.loadSkill( skill.term_id )
			.then( async ( data ) => {
				const ids = data.lessons || [];
				const rows = ids.length
					? await api.lessonTargets( {
							include: ids,
						} )
					: [];
				if ( current ) {
					setLessons( {
						ids,
						rows,
					} );
				}
			} )
			.catch(
				( cause ) =>
					current &&
					setError(
						cause?.message ||
							__( 'Could not load the lessons.', 'ohmylms' )
					)
			);
		api.skillQuestions( skill.term_id )
			.then( ( data ) => current && setQuestions( data ) )
			.catch(
				( cause ) =>
					current &&
					setError(
						cause?.message ||
							__( 'Could not load the questions.', 'ohmylms' )
					)
			);
		return () => {
			current = false;
		};
	}, [ skill.term_id, version ] );

	/**
	 * Replace the lessons tagged to the skill, then read them again so the list is what is stored.
	 * @param ids
	 */
	async function setTagged( ids ) {
		setBusy( true );
		setError( '' );
		try {
			await api.setSkillLessons( skill.term_id, ids );
			setVersion( ( value ) => value + 1 );
			return true;
		} catch ( cause ) {
			setError(
				cause?.message ||
					__( 'The lessons could not be saved.', 'ohmylms' )
			);
			return false;
		} finally {
			setBusy( false );
		}
	}
	const tagged = new Set( lessons?.ids || [] );
	const total = questions?.total ?? 0;
	const approved = ( questions?.items || [] ).filter(
		( item ) => item.status === 'approved'
	).length;
	const slot = window.ohmylms?.extensions?.renderSlot;
	const quizzes = ( w.courseCatalog.catalog?.attachments || [] ).filter(
		( row ) =>
			row.type === 'quiz' && row.skill_ids?.includes( skill.term_id )
	);
	async function createQuiz( event ) {
		event.preventDefault();
		if ( busy || ! quizTitle.trim() ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			if ( ! quizDraft.current ) {
				quizDraft.current = await createContent(
					'quiz',
					quizTitle.trim()
				);
			}
			const made = quizDraft.current;
			const linked = await w.courseCatalog.attach(
				[
					{
						id: made.id,
						type: 'quiz',
						title: quizTitle.trim(),
						status: 'draft',
					},
				],
				{
					chapterId: 0,
					skillIds: [ skill.term_id ],
				},
				{
					required: false,
				}
			);
			if ( ! linked ) {
				throw new Error(
					__( 'Could not link the quiz. Try again.', 'ohmylms' )
				);
			}
			window.location.hash = withEditorReturn(
				editPath( 'quiz', made.id ),
				returnTo
			);
		} catch ( cause ) {
			setError(
				cause?.message || __( 'Could not create the quiz.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	return (
		<section
			className="ohmylms-ws-owns"
			aria-label={ __( 'What this skill owns', 'ohmylms' ) }
		>
			<h3>{ __( 'What this skill owns', 'ohmylms' ) }</h3>
			<p className="ohmylms-ext-muted">
				{ __(
					'Lessons and questions belong to the skill, so they go wherever the skill goes: into this syllabus’s course and into any other that uses it.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			<div className="ohmylms-ws-owns-block">
				<div className="ohmylms-ws-owns-head">
					<h4>
						<Dashicon icon="book-alt" aria-hidden="true" />
						{ __( 'Lessons', 'ohmylms' ) }{ ' ' }
						<Tag>{ lessons ? lessons.ids.length : '…' }</Tag>
					</h4>
					<div className="ohmylms-ws-resource-actions">
						<Button
							variant="primary"
							size="small"
							disabled={ busy || ! lessons }
							onClick={ () => setPicking( 'create' ) }
						>
							{ __( 'Add lesson', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							size="small"
							disabled={ busy || ! lessons }
							onClick={ () => setPicking( 'bank' ) }
						>
							{ __( 'Lesson bank', 'ohmylms' ) }
						</Button>
					</div>
				</div>
				{ ! lessons && ! error && <Spinner /> }
				{ lessons && ! lessons.rows.length && (
					<p className="ohmylms-ext-muted">
						{ __( 'No lessons teach this skill yet.', 'ohmylms' ) }
					</p>
				) }
				{ lessons && lessons.rows.length > 0 && (
					<ul className="ohmylms-ws-owned">
						{ lessons.rows.map( ( row ) => (
							<li key={ row.id }>
								<KindIcon kind="lesson" />
								<a href={ lessonEditorHref( row, returnTo ) }>
									{ row.title }
								</a>
								<Button
									variant="link"
									isDestructive
									disabled={ busy }
									aria-label={ sprintf(
										/* translators: %s: skill or linked content name. */ __(
											'Take %s off this skill',
											'ohmylms'
										),
										row.title
									) }
									onClick={ () =>
										setTagged(
											lessons.ids.filter(
												( id ) => id !== row.id
											)
										)
									}
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) }
			</div>

			<div className="ohmylms-ws-owns-block">
				<div className="ohmylms-ws-owns-head">
					<h4>
						<Dashicon icon="editor-help" aria-hidden="true" />
						{ __( 'Questions', 'ohmylms' ) }{ ' ' }
						<Tag>{ questions ? total : '…' }</Tag>
					</h4>
					<div className="ohmylms-ws-resource-actions">
						<Button
							variant="primary"
							size="small"
							onClick={ () => setNewQuestion( true ) }
						>
							{ __( 'Add question', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							size="small"
							disabled={ busy || ! w.courseCatalog.catalog }
							onClick={ () => setNewQuiz( true ) }
						>
							{ __( 'New Quiz', 'ohmylms' ) }
						</Button>
					</div>
				</div>
				{ ! questions && ! error && <Spinner /> }
				{ questions && ! total && (
					<p className="ohmylms-ext-muted">
						{ __(
							'Add a question or create a quiz to practise this skill.',
							'ohmylms'
						) }
					</p>
				) }
				{ questions && total > 0 && (
					<>
						<ul className="ohmylms-ws-owned">
							{ questions.items.map( ( item ) => (
								<li key={ item.id }>
									<KindIcon kind="quiz" />
									<span className="ohmylms-ws-owned-title">
										{ item.name ||
											sprintf(
												/* translators: %d: question number. */ __(
													'Question %d',
													'ohmylms'
												),
												item.id
											) }
									</span>
									<Tag
										tone={
											item.status === 'approved'
												? 'ok'
												: ''
										}
									>
										{ (
											STATUS[ item.status ] ||
											( () => item.status )
										)() }
									</Tag>
								</li>
							) ) }
						</ul>
						<p className="ohmylms-ext-muted">
							{ sprintf(
								/* translators: %1$d: number of mapped questions; %2$d: number of approved questions shown. */ _n(
									'%1$d question is mapped to this skill (%2$d of those shown approved).',
									'%1$d questions are mapped to this skill (%2$d of those shown approved).',
									total,
									'ohmylms'
								),
								total,
								approved
							) }
						</p>
					</>
				) }
				{ quizzes.length > 0 && (
					<ul className="ohmylms-ws-owned">
						{ quizzes.map( ( quiz ) => (
							<li key={ quiz.id }>
								<KindIcon kind="quiz" />
								<a
									href={ `#${ withEditorReturn( editPath( 'quiz', quiz.content_id ), returnTo ) }` }
								>
									{ quiz.title }
								</a>
								<Tag>{ __( 'Quiz', 'ohmylms' ) }</Tag>
							</li>
						) ) }
					</ul>
				) }
				<Button
					variant="link"
					className="ohmylms-ws-resource-bank"
					href="#/content-hub/question-bank"
				>
					{ __( 'Question bank', 'ohmylms' ) }
				</Button>
			</div>

			<div className="ohmylms-ws-owns-extra">
				{ slot
					? slot( RESOURCE_SLOT, {
							skill,
							syllabus,
						} )
					: null }
			</div>

			{ picking && (
				<LessonPicker
					mode={ picking }
					skill={ skill }
					tagged={ tagged }
					onClose={ () => setPicking( '' ) }
					onCreated={ ( lesson ) => {
						setVersion( ( value ) => value + 1 );
						window.location.assign(
							lessonEditorHref( lesson, returnTo )
						);
					} }
					onChoose={ ( row ) =>
						setTagged( [
							...new Set( [ ...( lessons?.ids || [] ), row.id ] ),
						] )
					}
				/>
			) }
			{ newQuestion && (
				<NewQuestionModal
					skillId={ skill.term_id }
					onClose={ () => setNewQuestion( false ) }
					onCreated={ () => {
						setNewQuestion( false );
						setVersion( ( value ) => value + 1 );
					} }
				/>
			) }
			{ newQuiz && (
				<Modal
					title={ __( 'New Quiz', 'ohmylms' ) }
					onRequestClose={ () => ! busy && setNewQuiz( false ) }
					className="ohmylms-content-hub-dialog"
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<form onSubmit={ createQuiz }>
						<TextControl
							label={ __( 'Quiz title', 'ohmylms' ) }
							value={ quizTitle }
							onChange={ setQuizTitle }
							disabled={ busy || Boolean( quizDraft.current ) }
							help={ __(
								'Creates a draft linked to this skill and opens the quiz editor.',
								'ohmylms'
							) }
						/>
						<Button
							variant="primary"
							type="submit"
							disabled={ busy || ! quizTitle.trim() }
							isBusy={ busy }
						>
							{ quizDraft.current
								? __( 'Retry linking quiz', 'ohmylms' )
								: __( 'Create quiz', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
		</section>
	);
}
