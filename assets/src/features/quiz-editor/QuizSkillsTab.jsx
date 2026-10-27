/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { SkillPicker } from '../content-hub/SkillPicker';
import { saveSkillMap } from '../question-bank/api.mjs';
import { saveQuizSkills } from './api.mjs';
import {
	promptText,
	questionPrompt,
} from '../question-editor/questionPrompt.mjs';

const skillLabel = ( skill, id ) =>
	skill
		? `${ skill.code ? `${ skill.code } · ` : '' }${ skill.name }`
		: `#${ id }`;

/**
 * Every skill a question's map points at, primary first, without repeats.
 * @param {Object} map Question skill map.
 * @return {Array} Unique skill IDs.
 */
export const questionSkillIds = ( map ) => [
	...new Set(
		Object.values( map || {} )
			.flatMap( ( roles ) => [
				roles.primary,
				...( roles.supporting || [] ),
			] )
			.filter( Boolean )
	),
];

/**
 * Turn the picked skills back into a skill map: the question's current primary skill stays primary
 * when it is still picked, otherwise the first pick becomes primary and the rest support it.
 * @param map
 * @param ids
 * @param part
 */
export function mapFromSelection( map, ids, part = 'p1' ) {
	if ( ! ids.length ) {
		return {};
	}
	const current = map?.[ part ]?.primary;
	const primary = ids.includes( current ) ? current : ids[ 0 ];
	return {
		[ part ]: {
			primary,
			supporting: ids.filter( ( id ) => id !== primary ),
		},
	};
}

const toSkill = ( skills, id ) => {
	const skill = skills.find( ( row ) => row.id === id );
	return { id, name: skill?.name || `#${ id }`, code: skill?.code || '' };
};

/**
 * "Connect skill" button for one question: opens the skill picker and saves the chosen skills.
 * @param root0
 * @param root0.question
 * @param root0.skills
 * @param root0.onChange
 * @param root0.iconOnly
 */
export function ConnectQuestionSkill( {
	question,
	skills,
	onChange,
	iconOnly = false,
} ) {
	const [ open, setOpen ] = useState( false );
	const [ error, setError ] = useState( '' );
	if ( question.temp ) {
		return (
			<Button
				icon="networking"
				variant="tertiary"
				disabled
				label={ __( 'Save the quiz to connect skills', 'ohmylms' ) }
			>
				{ ! iconOnly && __( 'Connect skill', 'ohmylms' ) }
			</Button>
		);
	}
	const part = question.settings?.parts?.[ 0 ]?.id || 'p1';
	const confirm = async ( picked ) => {
		setOpen( false );
		const previous = question.skill_map || {};
		const map = mapFromSelection(
			previous,
			picked.map( ( skill ) => skill.id ),
			part
		);
		onChange( question.id, { skill_map: map } );
		try {
			const saved = await saveSkillMap( question.id, map );
			if ( saved?.skill_map ) {
				onChange( question.id, { skill_map: saved.skill_map } );
			}
			setError( '' );
		} catch ( e ) {
			onChange( question.id, { skill_map: previous } );
			setError(
				e?.message || __( 'Could not connect the skills.', 'ohmylms' )
			);
		}
	};
	return (
		<>
			<Button
				icon="networking"
				variant="tertiary"
				disabled={ !! question.readonly }
				label={ __( 'Connect skill', 'ohmylms' ) }
				onClick={ () => setOpen( true ) }
			>
				{ ! iconOnly && __( 'Connect skill', 'ohmylms' ) }
			</Button>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ open && (
				<SkillPicker
					title={ __( 'Connect skills to this question', 'ohmylms' ) }
					confirmLabel={ __( 'Connect', 'ohmylms' ) }
					initial={ questionSkillIds( question.skill_map ).map(
						( id ) => toSkill( skills, id )
					) }
					onConfirm={ confirm }
					onClose={ () => setOpen( false ) }
				/>
			) }
		</>
	);
}

/**
 * The quiz editor's Skills tab: the skills the quiz as a whole covers, and the skills of every question.
 * @param root0
 * @param root0.editor
 * @param root0.skills
 */
export function QuizSkillsTab( { editor, skills } ) {
	const [ open, setOpen ] = useState( false );
	const [ error, setError ] = useState( '' );
	const quizId = editor.quiz?.id;
	const quizSkillIds = editor.quiz?.skill_ids || [];
	const byId = new Map( skills.map( ( skill ) => [ skill.id, skill ] ) );

	const connect = async ( picked ) => {
		setOpen( false );
		const previous = quizSkillIds;
		const ids = picked.map( ( skill ) => skill.id );
		editor.updateField( 'skill_ids', ids );
		try {
			const saved = await saveQuizSkills( quizId, ids );
			editor.updateField( 'skill_ids', saved.skill_ids || ids );
			setError( '' );
		} catch ( e ) {
			editor.updateField( 'skill_ids', previous );
			setError(
				e?.message || __( 'Could not connect the skills.', 'ohmylms' )
			);
		}
	};

	return (
		<section className="ohmylms-quiz-skills-tab">
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div className="ohmylms-quiz-skills-block">
				<header>
					<h3>{ __( 'Quiz skills', 'ohmylms' ) }</h3>
					<Button
						variant="secondary"
						icon="networking"
						disabled={ ! quizId }
						onClick={ () => setOpen( true ) }
					>
						{ __( 'Connect skill', 'ohmylms' ) }
					</Button>
				</header>
				<p className="ohmylms-ext-muted">
					{ __(
						'The skills this quiz is meant to cover as a whole. Each question also has its own skills below.',
						'ohmylms'
					) }
				</p>
				<div className="ohmylms-quiz-skills">
					{ quizSkillIds.length ? (
						quizSkillIds.map( ( id ) => (
							<span
								key={ id }
								className="ohmylms-quiz-skill-chip"
							>
								{ skillLabel( byId.get( id ), id ) }
							</span>
						) )
					) : (
						<span className="ohmylms-ext-muted">
							{ __( 'No skills connected yet.', 'ohmylms' ) }
						</span>
					) }
				</div>
			</div>
			<div className="ohmylms-quiz-skills-block">
				<header>
					<h3>{ __( 'Question skills', 'ohmylms' ) }</h3>
				</header>
				{ ! editor.questions.length && (
					<p className="ohmylms-ext-muted">
						{ __( 'This quiz has no questions yet.', 'ohmylms' ) }
					</p>
				) }
				{ editor.questions.map( ( question, index ) => {
					const ids = questionSkillIds( question.skill_map );
					return (
						<div
							key={ question.id }
							className="ohmylms-quiz-skills-question"
						>
							<strong>{ sprintf( '%02d', index + 1 ) }</strong>
							<span className="ohmylms-quiz-skills-prompt">
								{ promptText( questionPrompt( question ) ) ||
									__( 'Untitled question', 'ohmylms' ) }
							</span>
							<span className="ohmylms-quiz-skills">
								{ ids.length ? (
									ids.map( ( id ) => (
										<span
											key={ id }
											className="ohmylms-quiz-skill-chip"
										>
											{ skillLabel( byId.get( id ), id ) }
										</span>
									) )
								) : (
									<span className="ohmylms-quiz-skill-chip is-missing">
										{ __( 'No skill', 'ohmylms' ) }
									</span>
								) }
							</span>
							<ConnectQuestionSkill
								question={ question }
								skills={ skills }
								onChange={ editor.patchQuestion }
							/>
						</div>
					);
				} ) }
			</div>
			{ open && (
				<SkillPicker
					title={ __( 'Connect skills to this quiz', 'ohmylms' ) }
					confirmLabel={ __( 'Connect', 'ohmylms' ) }
					initial={ quizSkillIds.map( ( id ) =>
						toSkill( skills, id )
					) }
					onConfirm={ connect }
					onClose={ () => setOpen( false ) }
				/>
			) }
		</section>
	);
}
