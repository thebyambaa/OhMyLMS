const connectionLabel = ( item ) => {
	if ( teaches( item ) ) {
		return __( 'Teaches', 'ohmylms' );
	}
	return item.type === 'practice'
		? __( 'Practises', 'ohmylms' )
		: __( 'Plans to assess', 'ohmylms' );
};

/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { lessonSkillIds } from './lessonSkills.mjs';
import { outlineSkillName } from './CourseOutlineTree';

const name = ( item ) =>
	item.title || item.name || __( 'Untitled activity', 'ohmylms' );
const teaches = ( item ) => item.type === 'lesson';
const assesses = ( item ) => [ 'quiz', 'assignment' ].includes( item.type );
const covers = ( item, id ) =>
	item.type === 'practice'
		? item.content_id === id
		: item.skill_ids?.includes( id );

/**
 * Course-wide connections computed from the current draft, including unsaved edits.
 * @param {Object}                                    props          Component properties.
 * @param {Object}                                    props.draft    Outline draft.
 * @param {(type: string, id: string|number) => void} props.onSelect Open an outline row.
 * @return {Element} Connection table.
 */
export function CourseSkillCoverage( { draft, onSelect } ) {
	const { program } = draft;
	const links = ( rows, type ) =>
		rows.length ? (
			rows.map( ( row ) => (
				<Button
					key={ row.id }
					variant="link"
					onClick={ () => onSelect( type, row.id ) }
				>
					{ name( row ) }
				</Button>
			) )
		) : (
			<span className="ohmylms-connection-empty">
				{ __( 'Not linked', 'ohmylms' ) }
			</span>
		);
	return (
		<section
			className="ohmylms-skill-connections-panel"
			aria-label={ __( 'Course skill connections', 'ohmylms' ) }
		>
			<h2>
				{ __( 'What teaches and assesses each skill?', 'ohmylms' ) }
			</h2>
			<p>
				{ __(
					'A skill can be taught in several block lessons and assessed in several quizzes. These links describe your plan; open a quiz to check its question mappings. Completion counts each skill once.',
					'ohmylms'
				) }
			</p>
			{ ! program.outcomes.length ? (
				<p>
					{ __(
						'Select a unit and choose Link course skills to get started. Then select a lesson or quiz to connect its skills.',
						'ohmylms'
					) }
				</p>
			) : (
				<div className="ohmylms-connections-table-wrap">
					<table className="ohmylms-connections-table">
						<thead>
							<tr>
								{ [
									__( 'Skill', 'ohmylms' ),
									__( 'Teaching activities', 'ohmylms' ),
									__( 'Practice', 'ohmylms' ),
									__( 'Planned assessments', 'ohmylms' ),
								].map( ( label ) => (
									<th key={ label } scope="col">
										{ label }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ program.outcomes.map( ( skill ) => {
								const items = program.items.filter( ( item ) =>
									covers( item, skill.term_id )
								);
								return (
									<tr key={ skill.term_id }>
										<th scope="row">
											<Button
												variant="link"
												onClick={ () =>
													onSelect(
														'skill',
														skill.term_id
													)
												}
											>
												{ outlineSkillName(
													draft.skill_names,
													skill.term_id
												) }
											</Button>
											<small>
												{ skill.required
													? __(
															'Required for completion',
															'ohmylms'
														)
													: __(
															'Optional for completion',
															'ohmylms'
														) }
											</small>
										</th>
										<td>
											{ links(
												items.filter( teaches ),
												'activity'
											) }
										</td>
										<td>
											{ links(
												items.filter(
													( item ) =>
														item.type === 'practice'
												),
												'activity'
											) }
										</td>
										<td>
											{ links(
												items.filter( assesses ),
												'activity'
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
			) }
		</section>
	);
}

/**
 * Actionable lesson objectives and local activity links.
 * @param {Object}                                                         props          Component properties.
 * @param {Object}                                                         props.draft    Outline draft.
 * @param {Object}                                                         props.lesson   Selected lesson.
 * @param {boolean}                                                        props.busy     Save in progress.
 * @param {(type: string, id: string|number) => void}                      props.onSelect Select an activity or skill.
 * @param {(unitId: number|string, groupId: string, kind: string) => void} props.onAdd    Open an activity picker.
 * @param {(termId: number) => void}                                       props.onUnlink Remove a local link.
 * @return {Element} Lesson connection cards.
 */
export function LessonSkillConnections( {
	draft,
	lesson,
	busy,
	onSelect,
	onAdd,
	onUnlink,
} ) {
	const ids = lessonSkillIds( draft.program, lesson.id );
	const items = draft.program.items.filter(
		( item ) => item.group_id === lesson.id
	);
	return (
		<section
			className="ohmylms-lesson-connections"
			aria-label={ __( 'Lesson skill connections', 'ohmylms' ) }
		>
			<h3>{ __( 'Skills taught in this lesson', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Add activities to teach, practise, and assess these skills. Linking a skill here does not automatically link every activity.',
					'ohmylms'
				) }
			</p>
			{ ! ids.length && (
				<p className="ohmylms-connection-empty">
					{ __(
						'No skills linked yet. Use Link skills to lesson above.',
						'ohmylms'
					) }
				</p>
			) }
			{ ids.map( ( id ) => {
				const connected = items.filter( ( item ) =>
					covers( item, id )
				);
				return (
					<div className="ohmylms-connection-card" key={ id }>
						<Button
							variant="link"
							onClick={ () => onSelect( 'skill', id ) }
						>
							{ outlineSkillName( draft.skill_names, id ) }
						</Button>
						{ connected.map( ( item ) => (
							<p key={ item.id }>
								<span>
									{ connectionLabel( item ) }
									{ ': ' }
								</span>
								<Button
									variant="link"
									onClick={ () =>
										onSelect( 'activity', item.id )
									}
								>
									{ name( item ) }
								</Button>
							</p>
						) ) }
						{ ! connected.some( teaches ) && (
							<p className="ohmylms-connection-empty">
								{ __(
									'No teaching activity linked yet.',
									'ohmylms'
								) }
							</p>
						) }
						{ ! connected.some(
							( item ) =>
								assesses( item ) || item.type === 'practice'
						) && (
							<p className="ohmylms-connection-empty">
								{ __(
									'No practice or assessment linked yet.',
									'ohmylms'
								) }
							</p>
						) }
						<Button
							disabled={ busy }
							variant="tertiary"
							onClick={ () =>
								onAdd(
									lesson.chapter_id,
									lesson.id,
									'practice'
								)
							}
						>
							{ __( 'Add practice', 'ohmylms' ) }
						</Button>
						<Button
							disabled={ busy }
							variant="tertiary"
							onClick={ () => onUnlink( id ) }
						>
							{ __( 'Unlink from this lesson', 'ohmylms' ) }
						</Button>
					</div>
				);
			} ) }
		</section>
	);
}

/**
 * Edit course-local coverage and inspect saved question mappings for a quiz.
 * @param {Object}                                    props          Component properties.
 * @param {Object}                                    props.draft    Outline draft.
 * @param {Object}                                    props.activity Selected activity.
 * @param {boolean}                                   props.busy     Save in progress.
 * @param {(ids: number[]) => void}                   props.onChange Update intended skill IDs.
 * @param {(type: string, id: string|number) => void} props.onSelect Select the containing lesson.
 * @return {Element} Coverage controls.
 */
export function ActivitySkillConnections( {
	draft,
	activity,
	busy,
	onChange,
	onSelect,
} ) {
	const [ quiz, setQuiz ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ retry, setRetry ] = useState( 0 );
	useEffect( () => {
		let active = true;
		setQuiz( null );
		setError( '' );
		if ( activity.type === 'quiz' ) {
			apiFetch( { path: `/ohmylms/v1/quiz/${ activity.content_id }` } )
				.then( ( data ) => active && setQuiz( data ) )
				.catch( ( cause ) => active && setError( cause.message ) );
		}
		return () => {
			active = false;
		};
	}, [ activity.content_id, activity.type, retry ] );
	const selected = activity.skill_ids || [];
	const local = lessonSkillIds( draft.program, activity.group_id );
	const skills = draft.program.outcomes.filter(
		( row ) =>
			! activity.group_id ||
			local.includes( row.term_id ) ||
			selected.includes( row.term_id )
	);
	const counts = new Map();
	for ( const question of quiz?.content || [] ) {
		const primary = new Set(
			Object.values( question.skill_map || {} )
				.map( ( part ) => Number( part.primary ) )
				.filter( Boolean )
		);
		for ( const id of primary ) {
			counts.set( id, ( counts.get( id ) || 0 ) + 1 );
		}
	}
	return (
		<section className="ohmylms-activity-connections">
			<fieldset>
				<legend>
					{ assesses( activity )
						? __( 'Skills this activity should assess', 'ohmylms' )
						: __( 'Skills this activity teaches', 'ohmylms' ) }
				</legend>
				<p>
					{ __(
						'These links apply to this course activity. The shared resource stays reusable in other courses.',
						'ohmylms'
					) }
				</p>
				{ skills.map( ( skill ) => (
					<CheckboxControl
						key={ skill.term_id }
						label={ outlineSkillName(
							draft.skill_names,
							skill.term_id
						) }
						disabled={ busy }
						checked={ selected.includes( skill.term_id ) }
						onChange={ ( checked ) =>
							onChange(
								checked
									? [ ...selected, skill.term_id ]
									: selected.filter(
											( id ) => id !== skill.term_id
										)
							)
						}
					/>
				) ) }
				{ ! skills.length && (
					<p>
						{ __(
							'Select a unit and choose Link course skills, then return here to choose the skills this activity covers.',
							'ohmylms'
						) }
					</p>
				) }
				{ activity.group_id && (
					<Button
						variant="secondary"
						onClick={ () =>
							onSelect( 'lesson', activity.group_id )
						}
					>
						{ __( 'Manage lesson skills', 'ohmylms' ) }
					</Button>
				) }
			</fieldset>
			{ activity.type === 'quiz' && (
				<div className="ohmylms-connection-card">
					<h3>{ __( 'Question coverage', 'ohmylms' ) }</h3>
					<p>
						{ __(
							'Primary question skills provide assessment coverage. Supporting skills and quiz tags do not count as assessed. This checks saved question mappings, not learner mastery.',
							'ohmylms'
						) }
					</p>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					{ ! error && ! quiz && <Spinner /> }
					{ ! error && quiz && (
						<>
							{ ! selected.length && (
								<p>
									{ __(
										'Choose intended skills above to compare them with the questions.',
										'ohmylms'
									) }
								</p>
							) }
							{ selected.map( ( id ) => (
								<p key={ id }>
									<strong>
										{ outlineSkillName(
											draft.skill_names,
											id
										) }
									</strong>
									{ ' — ' }
									{ counts.has( id )
										? sprintf(
												/* translators: %d: question count. */ __(
													'%d questions mapped',
													'ohmylms'
												),
												counts.get( id )
											)
										: __(
												'No primary question mapping',
												'ohmylms'
											) }
								</p>
							) ) }
						</>
					) }
					<Button
						variant="tertiary"
						onClick={ () => setRetry( retry + 1 ) }
					>
						{ __( 'Refresh question coverage', 'ohmylms' ) }
					</Button>
				</div>
			) }
		</section>
	);
}
