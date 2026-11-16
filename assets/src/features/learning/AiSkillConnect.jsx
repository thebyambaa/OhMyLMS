import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	loadBankQuestion,
	saveQuestionAttributes,
} from '../question-bank/public.mjs';
import {
	defaultChoice,
	loadCurricula,
	suggestSkills,
	withPrimarySkill,
} from './aiSkillConnect.mjs';
import { SkillField } from './SkillField';

/** @typedef {import('./aiSkillConnect.mjs').SuggestionRow} SuggestionRow */
/** @typedef {import('./aiSkillConnect.mjs').Curriculum} Curriculum */

/**
 * Message of a REST failure.
 *
 * @param {unknown} error Failure.
 * @return {string} Text to show.
 */
const failure = ( error ) =>
	error && typeof error === 'object' && 'message' in error
		? String( error.message )
		: __( 'Something went wrong. Try again.', 'ohmylms' );

/**
 * Why a row has no suggestion to accept.
 *
 * @param {SuggestionRow} row Suggestion.
 * @return {string} Explanation.
 */
function rowNote( row ) {
	if ( 'unavailable' === row.status ) {
		return __( 'You cannot edit this question.', 'ohmylms' );
	}
	if ( 'has-skill' === row.status ) {
		return __( 'Already has a skill.', 'ohmylms' );
	}
	return row.reason || __( 'No skill in this curriculum fits.', 'ohmylms' );
}

/**
 * "Connect skills with AI": AI suggests the skill each question of the quiz assesses, from a
 * curriculum the author chooses; the author reviews every suggestion, can change or skip it, and
 * only accepted ones are saved, each through the question's normal update.
 *
 * @param {Object}         props             Properties.
 * @param {() => number[]} props.questionIds Questions placed in the quiz, read when the dialog opens.
 * @return {React.ReactElement} Button and dialog.
 */
export function AiSkillConnect( { questionIds } ) {
	const [ open, setOpen ] = useState( false );
	const [ curricula, setCurricula ] = useState(
		/** @type {Curriculum[]|null} */ ( null )
	);
	const [ itemId, setItemId ] = useState( 0 );
	const [ includeMapped, setIncludeMapped ] = useState( false );
	const [ phase, setPhase ] = useState(
		/** @type {'choose'|'running'|'review'|'saving'|'done'} */ ( 'choose' )
	);
	const [ progress, setProgress ] = useState( [ 0, 0 ] );
	const [ rows, setRows ] = useState( /** @type {SuggestionRow[]} */ ( [] ) );
	const [ choices, setChoices ] = useState(
		/** @type {Record<number, number>} */ ( {} )
	);
	const [ names, setNames ] = useState(
		/** @type {Record<number, {name?: string, code?: string}>} */ ( {} )
	);
	const [ error, setError ] = useState( '' );
	const [ summary, setSummary ] = useState( '' );
	const [ ids, setIds ] = useState( /** @type {number[]} */ ( [] ) );

	useEffect( () => {
		if ( ! open || curricula ) {
			return undefined;
		}
		let active = true;
		loadCurricula()
			.then( ( list ) => {
				if ( active ) {
					setCurricula( list );
					setItemId( list[ 0 ]?.id || 0 );
				}
			} )
			.catch( ( cause ) => active && setError( failure( cause ) ) );
		return () => {
			active = false;
		};
	}, [ open, curricula ] );

	const start = () => {
		setOpen( true );
		setPhase( 'choose' );
		setRows( [] );
		setChoices( {} );
		setError( '' );
		setSummary( '' );
		setIds( questionIds() );
	};

	const find = async () => {
		setPhase( 'running' );
		setError( '' );
		setProgress( [ 0, ids.length ] );
		try {
			const found = await suggestSkills(
				ids,
				itemId,
				includeMapped,
				( done, total ) => setProgress( [ done, total ] )
			);
			setRows( found );
			setChoices(
				Object.fromEntries(
					found.map( ( row ) => [
						row.question,
						defaultChoice( row ),
					] )
				)
			);
			/** @type {Record<number, {name?: string}>} */
			const known = {};
			for ( const row of found ) {
				for ( const skill of [ row.skill, row.current ] ) {
					if ( skill ) {
						known[ skill.id ] = { name: skill.name };
					}
				}
			}
			setNames( known );
			setPhase( 'review' );
		} catch ( cause ) {
			setError( failure( cause ) );
			setPhase( 'choose' );
		}
	};

	const save = async () => {
		setPhase( 'saving' );
		setError( '' );
		const chosen = rows.filter( ( row ) => choices[ row.question ] > 0 );
		let saved = 0;
		/** @type {string[]} */
		const failed = [];
		for ( const row of chosen ) {
			try {
				const current = await loadBankQuestion( row.question );
				await saveQuestionAttributes( row.question, {
					skills: withPrimarySkill(
						current.skill_map,
						choices[ row.question ]
					),
					base_modified: current.modified,
				} );
				++saved;
			} catch ( cause ) {
				failed.push(
					`${ row.title || row.question }: ${ failure( cause ) }`
				);
			}
		}
		setSummary(
			sprintf(
				/* translators: %d: number of questions. */
				_n(
					'Saved the skill of %d question.',
					'Saved the skills of %d questions.',
					saved,
					'ohmylms'
				),
				saved
			)
		);
		setError( failed.join( '\n' ) );
		setPhase( 'done' );
	};

	const accepted = Object.values( choices ).filter( Boolean ).length;
	const suggested = rows.filter( ( row ) => 'suggested' === row.status );

	return (
		<>
			<Button variant="secondary" onClick={ start }>
				{ __( 'Connect skills with AI', 'ohmylms' ) }
			</Button>
			{ open && (
				<Modal
					title={ __( 'Connect skills with AI', 'ohmylms' ) }
					size="large"
					onRequestClose={ () =>
						'running' !== phase &&
						'saving' !== phase &&
						setOpen( false )
					}
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					{ 'choose' === phase && (
						<>
							<p>
								{ sprintf(
									/* translators: %d: number of questions. */
									_n(
										'AI reads the %d question in this quiz and suggests the one skill it assesses, from the curriculum you choose. You review every suggestion; nothing is saved until you accept.',
										'AI reads the %d questions in this quiz and suggests the one skill each assesses, from the curriculum you choose. You review every suggestion; nothing is saved until you accept.',
										ids.length,
										'ohmylms'
									),
									ids.length
								) }
							</p>
							{ ! curricula && ! error && <Spinner /> }
							{ curricula && (
								<SelectControl
									__nextHasNoMarginBottom
									label={ __( 'Curriculum', 'ohmylms' ) }
									value={ String( itemId ) }
									options={ [
										...curricula.map( ( item ) => ( {
											value: String( item.id ),
											label: `${ item.depth ? '— ' : '' }${
												item.name
											} (${ sprintf(
												/* translators: %d: number of skills. */
												_n(
													'%d skill',
													'%d skills',
													item.skills,
													'ohmylms'
												),
												item.skills
											) })`,
										} ) ),
										{
											value: '0',
											label: __(
												'All skills in the library',
												'ohmylms'
											),
										},
									] }
									onChange={ ( next ) =>
										setItemId( Number( next ) )
									}
								/>
							) }
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __(
									'Also suggest for questions that already have a skill',
									'ohmylms'
								) }
								checked={ includeMapped }
								onChange={ setIncludeMapped }
							/>
							<p className="ohmylms-ai-skill-actions">
								<Button
									variant="primary"
									disabled={ ! curricula || ! ids.length }
									onClick={ find }
								>
									{ __( 'Suggest skills', 'ohmylms' ) }
								</Button>
							</p>
						</>
					) }
					{ 'running' === phase && (
						<p role="status">
							<Spinner />
							{ sprintf(
								/* translators: 1: questions read, 2: all questions. */
								__(
									'Reading questions… %1$d of %2$d',
									'ohmylms'
								),
								progress[ 0 ],
								progress[ 1 ]
							) }
						</p>
					) }
					{ ( 'review' === phase || 'saving' === phase ) && (
						<>
							<p>
								{ sprintf(
									/* translators: 1: number of suggestions, 2: number of questions. */
									_n(
										'%1$d suggestion for %2$d questions. Untick a suggestion to skip it, or change the skill.',
										'%1$d suggestions for %2$d questions. Untick a suggestion to skip it, or change the skill.',
										suggested.length,
										'ohmylms'
									),
									suggested.length,
									rows.length
								) }
							</p>
							<table className="ohmylms-ai-skill-table widefat striped">
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Use', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Question', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Skill', 'ohmylms' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ rows.map( ( row ) => {
										const choice =
											choices[ row.question ] || 0;
										const offered =
											choice ||
											row.skill?.id ||
											row.current?.id ||
											0;
										const editable =
											'unavailable' !== row.status;
										return (
											<tr key={ row.question }>
												<td>
													<CheckboxControl
														__nextHasNoMarginBottom
														aria-label={ __(
															'Use this skill',
															'ohmylms'
														) }
														checked={ choice > 0 }
														disabled={
															! editable ||
															! offered ||
															'saving' === phase
														}
														onChange={ ( on ) =>
															setChoices( {
																...choices,
																[ row.question ]:
																	on
																		? offered
																		: 0,
															} )
														}
													/>
												</td>
												<td>
													{ row.title ||
														sprintf(
															/* translators: %d: question ID. */
															__(
																'Question %d',
																'ohmylms'
															),
															row.question
														) }
													{ row.current && (
														<p className="ohmylms-ai-skill-note">
															{ sprintf(
																/* translators: %s: skill name. */
																__(
																	'Now: %s',
																	'ohmylms'
																),
																row.current.name
															) }
														</p>
													) }
												</td>
												<td>
													{ editable && (
														<SkillField
															single
															label={
																row.skill
																	? sprintf(
																			/* translators: %d: confidence percentage. */
																			__(
																				'Suggested (%d%% sure)',
																				'ohmylms'
																			),
																			Math.round(
																				row.confidence *
																					100
																			)
																		)
																	: __(
																			'Skill',
																			'ohmylms'
																		)
															}
															value={
																offered
																	? [
																			offered,
																		]
																	: []
															}
															names={ names }
															disabled={
																'saving' ===
																phase
															}
															empty={ rowNote(
																row
															) }
															onChange={ (
																next,
																picked
															) => {
																setNames( {
																	...names,
																	...Object.fromEntries(
																		picked.map(
																			(
																				skill
																			) => [
																				skill.id,
																				skill,
																			]
																		)
																	),
																} );
																setChoices( {
																	...choices,
																	[ row.question ]:
																		next[ 0 ] ||
																		0,
																} );
															} }
														/>
													) }
													{ row.skill &&
														row.reason && (
															<p className="ohmylms-ai-skill-note">
																{ row.reason }
															</p>
														) }
													{ ! editable && (
														<p className="ohmylms-ai-skill-note">
															{ rowNote( row ) }
														</p>
													) }
												</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
							<p className="ohmylms-ai-skill-actions">
								<Button
									variant="primary"
									disabled={
										! accepted || 'saving' === phase
									}
									isBusy={ 'saving' === phase }
									onClick={ save }
								>
									{ sprintf(
										/* translators: %d: number of questions. */
										_n(
											'Save %d skill',
											'Save %d skills',
											accepted,
											'ohmylms'
										),
										accepted
									) }
								</Button>
								<Button
									variant="tertiary"
									disabled={ 'saving' === phase }
									onClick={ () => setOpen( false ) }
								>
									{ __( 'Cancel', 'ohmylms' ) }
								</Button>
							</p>
						</>
					) }
					{ 'done' === phase && (
						<>
							<Notice status="success" isDismissible={ false }>
								{ summary }
							</Notice>
							<p className="ohmylms-ai-skill-actions">
								<Button
									variant="primary"
									onClick={ () => setOpen( false ) }
								>
									{ __( 'Close', 'ohmylms' ) }
								</Button>
							</p>
						</>
					) }
				</Modal>
			) }
		</>
	);
}
