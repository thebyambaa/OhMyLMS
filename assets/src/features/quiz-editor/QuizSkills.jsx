import { createElement, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { SkillMapEditor } from '../question-bank/SkillMapEditor';
import { listSkills, saveSkillMap } from '../question-bank/api.mjs';

/**
 * The skill library, loaded once per editor.
 */
export function useSkillList() {
	const [ skills, setSkills ] = useState( [] );
	useEffect( () => {
		let active = true;
		listSkills()
			.then( ( data ) => active && setSkills( data.skills || [] ) )
			.catch( () => {} );
		return () => {
			active = false;
		};
	}, [] );
	return skills;
}

/**
 * Skill IDs a question's map points at, primary first.
 * @param map
 */
const mapSkillIds = ( map ) =>
	Object.values( map || {} ).flatMap( ( roles ) => [
		roles.primary,
		...( roles.supporting || [] ),
	] );

/**
 * Skills assessed by the open question. Saved straight away, like the Question Bank does,
 * so the skill link does not wait for the quiz to be saved.
 * @param root0
 * @param root0.question
 * @param root0.skills
 * @param root0.onChange
 */
export function QuestionSkills( { question, skills, onChange } ) {
	const [ status, setStatus ] = useState( '' );
	useEffect( () => setStatus( '' ), [ question?.id ] );
	if ( ! question ) {
		return null;
	}
	if ( question.temp ) {
		return (
			<section className="ohmylms-question-skills">
				<h3>{ __( 'Skills assessed', 'ohmylms' ) }</h3>
				<p>
					{ __(
						'Save the quiz to choose the skills this question assesses.',
						'ohmylms'
					) }
				</p>
			</section>
		);
	}
	const change = async ( map ) => {
		const previous = question.skill_map || {};
		onChange( question.id, { skill_map: map } );
		setStatus( __( 'Saving…', 'ohmylms' ) );
		try {
			const saved = await saveSkillMap( question.id, map );
			if ( saved?.skill_map ) {
				onChange( question.id, { skill_map: saved.skill_map } );
			}
			setStatus( __( 'Saved', 'ohmylms' ) );
		} catch ( e ) {
			onChange( question.id, { skill_map: previous } );
			setStatus(
				e?.message || __( 'Could not save the skills.', 'ohmylms' )
			);
		}
	};
	return (
		<section className="ohmylms-question-skills">
			<h3>
				{ __( 'Skills assessed', 'ohmylms' ) }{ ' ' }
				<span role="status" className="ohmylms-question-autosave">
					{ status }
				</span>
			</h3>
			<SkillMapEditor
				skills={ skills }
				value={ question.skill_map || {} }
				parts={ ( question.settings?.parts || [ { id: 'p1' } ] ).map(
					( part ) => part.id
				) }
				disabled={ !! question.readonly }
				onChange={ change }
			/>
		</section>
	);
}

/**
 * Skills the quiz covers, taken from its questions.
 * @param root0
 * @param root0.questions
 * @param root0.skills
 * @param root0.selected
 * @param root0.onSelect
 */
export function QuizSkillsSummary( {
	questions,
	skills,
	selected = null,
	onSelect = () => {},
} ) {
	const names = new Map(
		skills.map( ( skill ) => [
			skill.id,
			`${ skill.code ? `${ skill.code } · ` : '' }${ skill.name }`,
		] )
	);
	const counts = new Map();
	for ( const question of questions ) {
		for ( const id of new Set( mapSkillIds( question.skill_map ) ) ) {
			if ( id ) {
				counts.set( id, ( counts.get( id ) || 0 ) + 1 );
			}
		}
	}
	const unmapped = questions.filter(
		( question ) =>
			! question.temp &&
			! mapSkillIds( question.skill_map ).some( Boolean )
	).length;
	if ( ! counts.size && ! unmapped ) {
		return null;
	}
	return (
		<div
			className="ohmylms-quiz-skills"
			aria-label={ __( 'Skills covered', 'ohmylms' ) }
		>
			<strong>
				{ sprintf(
					/* translators: %d: number of skills. */ _n(
						'%d skill covered',
						'%d skills covered',
						counts.size,
						'ohmylms'
					),
					counts.size
				) }
			</strong>
			{ [ ...counts ].map( ( [ id, count ] ) => (
				<button
					type="button"
					key={ id }
					className={ `ohmylms-quiz-skill-chip${ selected === id ? ' is-selected' : '' }` }
					aria-pressed={ selected === id }
					onClick={ () => onSelect( selected === id ? null : id ) }
				>
					{ names.get( id ) || `#${ id }` }
					{ count > 1 ? ` ×${ count }` : '' }
				</button>
			) ) }
			{ unmapped > 0 && (
				<button
					type="button"
					className={ `ohmylms-quiz-skill-chip is-missing${ selected === 'none' ? ' is-selected' : '' }` }
					aria-pressed={ selected === 'none' }
					onClick={ () =>
						onSelect( selected === 'none' ? null : 'none' )
					}
				>
					{ sprintf(
						/* translators: %d: number of questions. */ _n(
							'%d question has no skill',
							'%d questions have no skill',
							unmapped,
							'ohmylms'
						),
						unmapped
					) }
				</button>
			) }
		</div>
	);
}

/**
 * Does the question belong under the chosen skill chip? `none` means no skill at all.
 * @param question
 * @param selected
 */
export function matchesSkillFilter( question, selected ) {
	if ( null === selected ) {
		return true;
	}
	const ids = mapSkillIds( question.skill_map ).filter( Boolean );
	return 'none' === selected ? ! ids.length : ids.includes( selected );
}
