import { Button, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	generateQuiz,
	linkQuiz,
	readSkillQuizzes,
	searchContent,
	type SkillQuizzes,
} from './workspace';
import { contentRoute, SkillChooser } from './WorkspaceParts';

/** Quizzes a skill owns: connected to it, or drawing their questions from it (`?skill=`). */
export function SkillQuizzesPanel() {
	const [ params, setParams ] = useSearchParams();
	const skill = Number( params.get( 'skill' ) );
	return (
		<section aria-labelledby="oml-ws-quizzes">
			<h3 id="oml-ws-quizzes">
				{ __( 'Skill-owned quizzes', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'Quizzes that belong to a skill: connected to it as a whole, or drawing their questions from its approved questions. Generate a short practice quiz from the question bank, or connect an existing quiz.',
					'ohmylms'
				) }
			</p>
			<SkillChooser
				label={ __( 'Find a skill', 'ohmylms' ) }
				onChoose={ ( found ) => {
					const next = new URLSearchParams( params );
					next.set( 'skill', String( found.id ) );
					setParams( next );
				} }
			/>
			{ Number.isSafeInteger( skill ) && skill > 0 && (
				<OwnedQuizzes key={ skill } skill={ skill } />
			) }
		</section>
	);
}

function styleLabel( style: string ) {
	return (
		{
			quiz: __( 'Quiz', 'ohmylms' ),
			'unit-test': __( 'Unit test', 'ohmylms' ),
			'course-challenge': __( 'Course challenge', 'ohmylms' ),
		}[ style ] ?? style
	);
}

function OwnedQuizzes( { skill }: { skill: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readSkillQuizzes( skill, signal ),
		[ skill ]
	);
	const request = useRequest( load );
	const [ data, setData ] = useState< SkillQuizzes | null >( null );
	const [ message, setMessage ] = useState( '' );
	const [ title, setTitle ] = useState( '' );
	const [ perSkill, setPerSkill ] = useState( '5' );
	const [ minutes, setMinutes ] = useState( '0' );
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	const operation = useOperation();
	const loadQuizzes = useCallback(
		( signal: AbortSignal ) =>
			null === search
				? Promise.resolve( null )
				: searchContent( 'quiz', search, 1, signal ),
		[ search ]
	);
	const found = useRequest( loadQuizzes );
	const current = data ?? request.data;
	if ( request.loading && ! data ) {
		return <p role="status">{ __( 'Loading quizzes…', 'ohmylms' ) }</p>;
	}
	if ( ! current ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }
			</Notice>
		);
	}
	const draws = Number( perSkill );
	const drawsValid = Number.isInteger( draws ) && draws >= 1 && draws <= 10;
	const limit = Number( minutes );
	const limitValid = Number.isInteger( limit ) && limit >= 0 && limit <= 600;
	function connect( quiz: number, linked: boolean ) {
		void operation.run(
			( signal ) => linkQuiz( skill, quiz, linked, signal ),
			( result ) => {
				setData( result );
				setMessage(
					linked
						? __( 'Quiz connected to the skill.', 'ohmylms' )
						: __( 'Quiz disconnected from the skill.', 'ohmylms' )
				);
			}
		);
	}
	return (
		<>
			<h4>{ current.skill.name }</h4>
			<p>
				{ sprintf(
					/* translators: %d: number of question families. */
					__(
						'%d approved question families can be drawn for this skill.',
						'ohmylms'
					),
					current.available
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<ListTable
				caption={ __( 'Quizzes of this skill', 'ohmylms' ) }
				footer={ false }
				busy={ operation.busy }
				rows={ current.quizzes }
				rowKey={ ( quiz ) => quiz.id }
				emptyMessage={ __(
					'No quiz belongs to this skill yet.',
					'ohmylms'
				) }
				columns={ [
					{
						id: 'title',
						label: __( 'Quiz', 'ohmylms' ),
						primary: true,
						render: ( quiz ) => (
							<Link
								className="row-title"
								to={ contentRoute( 'quiz', quiz.id ) }
							>
								{ quiz.title ||
									sprintf(
										/* translators: %d: ID. */
										__( '#%d', 'ohmylms' ),
										quiz.id
									) }
							</Link>
						),
					},
					{
						id: 'style',
						label: __( 'Style', 'ohmylms' ),
						render: ( quiz ) => styleLabel( quiz.style ),
					},
					{
						id: 'status',
						label: __( 'Status', 'ohmylms' ),
						render: ( quiz ) => quiz.status,
					},
					{
						id: 'owns',
						label: __( 'Belongs by', 'ohmylms' ),
						render: ( quiz ) =>
							[
								quiz.connected && __( 'connection', 'ohmylms' ),
								quiz.drawn > 0 &&
									sprintf(
										/* translators: %d: questions drawn. */
										__( 'draws %d questions', 'ohmylms' ),
										quiz.drawn
									),
							]
								.filter( Boolean )
								.join( ', ' ),
					},
					{
						id: 'skills',
						label: __( 'Skills', 'ohmylms' ),
						className: 'num',
						render: ( quiz ) => quiz.skills,
					},
				] }
				rowActions={ ( quiz ) => [
					quiz.connected &&
						quiz.editable && {
							key: 'disconnect',
							label: __( 'Disconnect', 'ohmylms' ),
							destructive: true,
							disabled: operation.busy,
							onClick: () => connect( quiz.id, false ),
						},
					! quiz.connected &&
						quiz.editable && {
							key: 'connect',
							label: __( 'Connect', 'ohmylms' ),
							disabled: operation.busy,
							onClick: () => connect( quiz.id, true ),
						},
				] }
			/>
			<h5>{ __( 'Connect an existing quiz', 'ohmylms' ) }</h5>
			<form
				role="search"
				aria-label={ __( 'Find a quiz', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSearch( query.trim() );
				} }
			>
				<TextControl
					label={ __( 'Search quizzes', 'ohmylms' ) }
					value={ query }
					maxLength={ 200 }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ found.data && (
				<ul aria-label={ __( 'Quizzes found', 'ohmylms' ) }>
					{ found.data.items
						.filter(
							( quiz ) =>
								! current.quizzes.some(
									( owned ) =>
										owned.id === quiz.id && owned.connected
								)
						)
						.map( ( quiz ) => (
							<li key={ quiz.id }>
								{ quiz.title }{ ' ' }
								<Button
									variant="secondary"
									disabled={
										operation.busy || ! quiz.editable
									}
									onClick={ () => connect( quiz.id, true ) }
									aria-label={ sprintf(
										/* translators: %s: quiz. */
										__( 'Connect %s', 'ohmylms' ),
										quiz.title
									) }
								>
									{ __( 'Connect', 'ohmylms' ) }
								</Button>
							</li>
						) ) }
				</ul>
			) }
			<h5>{ __( 'Generate a practice quiz', 'ohmylms' ) }</h5>
			<form
				noValidate
				aria-label={ __( 'Generate a practice quiz', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( ! drawsValid || ! limitValid ) {
						return;
					}
					void operation.run(
						( signal ) =>
							generateQuiz(
								skill,
								{
									title: title.trim(),
									per_skill: draws,
									time_limit: limit,
								},
								signal
							),
						( result ) => {
							setData( result );
							setTitle( '' );
							setMessage(
								sprintf(
									/* translators: %s: quiz title. */
									__(
										'Draft quiz “%s” created. Review it in the quiz editor before publishing.',
										'ohmylms'
									),
									result.created?.title ?? ''
								)
							);
						}
					);
				} }
			>
				<TextControl
					label={ __( 'Title (optional)', 'ohmylms' ) }
					value={ title }
					maxLength={ 190 }
					onChange={ setTitle }
				/>
				<TextControl
					label={ __( 'Questions to draw', 'ohmylms' ) }
					type="number"
					min={ 1 }
					max={ 10 }
					value={ perSkill }
					help={
						drawsValid
							? undefined
							: __(
									'Draw between 1 and 10 questions.',
									'ohmylms'
								)
					}
					onChange={ setPerSkill }
				/>
				<TextControl
					label={ __(
						'Time limit in minutes (0 for none)',
						'ohmylms'
					) }
					type="number"
					min={ 0 }
					max={ 600 }
					value={ minutes }
					help={
						limitValid
							? undefined
							: __( 'Use 0 to 600 minutes.', 'ohmylms' )
					}
					onChange={ setMinutes }
				/>
				{ drawsValid && draws > current.available && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'This skill does not have enough approved questions for that many draws yet.',
							'ohmylms'
						) }
					</Notice>
				) }
				<Button
					type="submit"
					variant="primary"
					isBusy={ operation.busy }
					disabled={
						operation.busy ||
						! drawsValid ||
						! limitValid ||
						draws > current.available
					}
				>
					{ __( 'Generate draft quiz', 'ohmylms' ) }
				</Button>
			</form>
		</>
	);
}
