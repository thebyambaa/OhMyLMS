/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import {
	createBlock,
	registerBlockType,
	registerBlockVariation,
} from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import {
	Button,
	ComboboxControl,
	Modal,
	Notice,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import { addSelection, moveSelection } from './model.mjs';
import { installNativeCanvasRecovery } from '../../features/learning/nativeCanvas.mjs';
import { QuestionSourceSwitch } from '../../features/question-editor/QuestionSourceSwitch';
import {
	allowedSources,
	editorDeliveries,
	fromPlayer,
	playerSource,
} from '../../features/question-editor/questionSource.mjs';

const activities = [
	[ 'spin-wheel', __( 'Spin the wheel', 'ohmylms' ), 'deck' ],
	[ 'open-box', __( 'Open the box', 'ohmylms' ), 'deck' ],
	[ 'speaking-cards', __( 'Speaking cards', 'ohmylms' ), 'deck' ],
	[ 'flip-tiles', __( 'Flip tiles', 'ohmylms' ), 'deck' ],
	[ 'find-match', __( 'Find the match', 'ohmylms' ), 'match' ],
	[ 'pair-or-no-pair', __( 'Pair or No Pair', 'ohmylms' ), 'match' ],
	[ 'balloon-pop', __( 'Balloon pop', 'ohmylms' ), 'match' ],
	[ 'gameshow', __( 'Gameshow quiz', 'ohmylms' ), 'choice' ],
	[ 'timed-true-false', __( 'Timed True or false', 'ohmylms' ), 'choice' ],
	[ 'win-or-lose', __( 'Win or lose quiz', 'ohmylms' ), 'choice' ],
	[ 'maze-chase', __( 'Maze chase', 'ohmylms' ), 'choice' ],
	[ 'flying-fruit', __( 'Flying fruit', 'ohmylms' ), 'choice' ],
	[ 'whack-a-mole', __( 'Whack-a-mole', 'ohmylms' ), 'choice' ],
	[ 'airplane', __( 'Airplane', 'ohmylms' ), 'choice' ],
	[ 'speed-sorting', __( 'Speed sorting', 'ohmylms' ), 'sort' ],
	[ 'word-magnets', __( 'Word magnets', 'ohmylms' ), 'magnets' ],
];
const familyHelp = {
	deck: __(
		'Use approved Matching questions for two-sided cards, or Statements for prompts (except Flip tiles, which needs Matching). Random draws do not repeat until the deck is exhausted.',
		'ohmylms'
	),
	match: __(
		'Use Matching questions with distinct prompts and answer labels. Include at least two pairs. Find the match eliminates answers; Pair or No Pair tests real and mismatched pairs; Balloon pop drops the selected label onto the prompt.',
		'ohmylms'
	),
	choice: __(
		'Use Single choice or True/false questions with 2–6 text-labelled choices and one correct answer. Timed True or false requires True/false. Game points, timers and confidence stakes are practice feedback only. Arcade games include stationary keyboard and touch alternatives.',
		'ohmylms'
	),
	sort: __(
		'Use Sort into groups questions with 2–8 groups. Each item appears in a timed round.',
		'ohmylms'
	),
	magnets: __(
		'Use Build from tiles in Words mode. Learners arrange the words freely on a board. Positions are saved; composition is ungraded.',
		'ohmylms'
	),
};
if ( ! window.ohmylmsNativeLesson ) {
	installNativeCanvasRecovery();
}

/**
 * Configure a question player without issuing learner questions in the editor.
 *
 * @param {Object}                props               Block properties.
 * @param {Object}                props.attributes    Saved selection and outcome settings.
 * @param {function(Object):void} props.setAttributes Update block attributes.
 * @param {boolean}               props.isSelected    Whether this block is selected.
 * @param {string}                props.clientId      Block identity in the editor.
 * @param {Object}                props.context       Post type and ID of the document.
 * @return {Element} Bank picker and settings.
 */
export function QuestionPlayerEdit( {
	attributes,
	setAttributes,
	clientId,
	isSelected,
	context = {},
} ) {
	const {
		source,
		questionIds,
		skillIds,
		count,
		graded,
		affectsSkills,
		activityMode = 'standard',
		roundSeconds = 30,
		revealSeconds = 30,
		rememberCount = 2,
		studySeconds = 10,
	} = attributes;
	const { replaceBlocks } = useDispatch( 'core/block-editor' );
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState( [] );
	const [ labels, setLabels ] = useState( {} );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	const ids = source === 'random' ? skillIds : questionIds;
	const key = source === 'random' ? 'skillIds' : 'questionIds';
	const kind = source === 'random' ? 'skills' : 'questions';
	const [ picking, setPicking ] = useState(
		Boolean( isSelected ) && ids.length === 0
	);
	const selection = ids.join( ',' );
	useEffect( () => {
		let active = true;
		const timer = setTimeout( async () => {
			setLoading( true );
			try {
				const base = `/ohmylms/v1/question-player/choices?kind=${ kind }&activityMode=${ activityMode }`;
				const [ found, chosen ] = await Promise.all( [
					apiFetch( {
						path: `${ base }&search=${ encodeURIComponent( search ) }`,
					} ),
					selection
						? apiFetch( {
								path: `${ base }&include=${ selection }`,
							} )
						: [],
				] );
				if ( active ) {
					setItems( found );
					setLabels(
						Object.fromEntries(
							[ ...found, ...chosen ].map( ( item ) => [
								item.id,
								item.title,
							] )
						)
					);
					setError( '' );
				}
			} catch ( failure ) {
				if ( active ) {
					setError( failure.message );
				}
			} finally {
				if ( active ) {
					setLoading( false );
				}
			}
		}, 250 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ kind, search, selection, activityMode ] );
	return (
		<div { ...useBlockProps() }>
			<Placeholder
				icon="welcome-learn-more"
				label={ __( 'OhMyLMS Question Player', 'ohmylms' ) }
			>
				<div style={ { width: '100%' } }>
					<QuestionSourceSwitch
						allowReselect
						source={ playerSource( attributes ) }
						allowed={ allowedSources( {
							postType: context.postType,
							deliveries: editorDeliveries(),
						} ) }
						onChange={ ( next ) => {
							const step = fromPlayer( next );
							if ( step.action === 'question' ) {
								replaceBlocks(
									clientId,
									createBlock(
										'ohmylms/shared-multiple-choice'
									)
								);
							} else {
								setPicking( true );
								setSearch( '' );
								setAttributes( step.attributes );
							}
						} }
					/>
					<p>
						{ __(
							'Use approved, automatically marked bank questions. Students log in to play. Save this post and preview the page to try the player; instructor previews never update student performance.',
							'ohmylms'
						) }
					</p>
					<SelectControl
						label={ __( 'Play mode', 'ohmylms' ) }
						value={ activityMode }
						options={ [
							...activities.map( ( [ value, label ] ) => ( {
								value,
								label,
							} ) ),
							{
								value: 'image-quiz',
								label: __( 'Image quiz', 'ohmylms' ),
							},
							{
								value: 'memory-recall',
								label: __( 'Watch and memorize', 'ohmylms' ),
							},
							{
								value: 'hangman',
								label: __( 'Hangman', 'ohmylms' ),
							},
							{
								value: 'spell-word',
								label: __( 'Spell the word', 'ohmylms' ),
							},
							{
								value: 'standard',
								label: __( 'Standard questions', 'ohmylms' ),
							},
							{
								value: 'flashcards',
								label: __(
									'Flashcards (matching questions)',
									'ohmylms'
								),
							},
							{
								value: 'memory-pairs',
								label: __(
									'Matching pairs (memory game)',
									'ohmylms'
								),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( {
								activityMode: value,
								graded: value === 'standard' && graded,
								affectsSkills:
									value === 'standard' && affectsSkills,
							} )
						}
					/>
					{ activities.some( ( [ id ] ) => id === activityMode ) && (
						<>
							<p>
								{
									familyHelp[
										activities.find(
											( [ id ] ) => id === activityMode
										)[ 2 ]
									]
								}
							</p>
							{ ! [ 'deck', 'magnets' ].includes(
								activities.find(
									( [ id ] ) => id === activityMode
								)[ 2 ]
							) && (
								<RangeControl
									label={ __(
										'Round time (seconds)',
										'ohmylms'
									) }
									min={ 10 }
									max={ 180 }
									value={ roundSeconds }
									onChange={ ( value ) =>
										setAttributes( { roundSeconds: value } )
									}
								/>
							) }
							<p>
								{ __(
									'At most 100 content items per run. Save and preview the page to play.',
									'ohmylms'
								) }
							</p>
						</>
					) }
					{ activityMode === 'image-quiz' && (
						<>
							<p>
								{ __(
									'Choose single-choice questions with a main image, 2–6 answers and one correct choice. Add the image in the question editor and approve the question. Images reveal gradually; buzz to answer. Faster correct answers earn practice points only. Variable questions are not supported.',
									'ohmylms'
								) }
							</p>
							<RangeControl
								label={ __(
									'Image reveal time (seconds)',
									'ohmylms'
								) }
								min={ 10 }
								max={ 120 }
								value={ revealSeconds }
								onChange={ ( value ) =>
									setAttributes( { revealSeconds: value } )
								}
							/>
						</>
					) }
					{ activityMode === 'memory-recall' && (
						<>
							<p>
								{ __(
									'Use 3–24 distinct Matching question fronts as the item pool. A random subset is studied; remaining items are distractors. Recall accuracy is practice feedback only.',
									'ohmylms'
								) }
							</p>
							<RangeControl
								label={ __( 'Number to remember', 'ohmylms' ) }
								min={ 1 }
								max={ 10 }
								value={ rememberCount }
								onChange={ ( value ) =>
									setAttributes( { rememberCount: value } )
								}
							/>
							<RangeControl
								label={ __(
									'Study time (seconds)',
									'ohmylms'
								) }
								min={ 5 }
								max={ 120 }
								value={ studySeconds }
								onChange={ ( value ) =>
									setAttributes( { studySeconds: value } )
								}
							/>
						</>
					) }
					{ activityMode !== 'standard' &&
						activityMode !== 'memory-recall' &&
						activityMode !== 'image-quiz' &&
						! activities.some(
							( [ id ] ) => id === activityMode
						) && (
							<p>
								{ [ 'hangman', 'spell-word' ].includes(
									activityMode
								)
									? __(
											'Choose automatic text answers or Build from tiles questions in Letters mode. Use a clue that does not reveal the answer. Answers must contain letters and be at most 64 characters. Six mistakes end a round. Progress is saved without grades or skill updates.',
											'ohmylms'
										)
									: __(
											'Choose matching questions, up to 24 pairs. Study progress is saved; reveals and game points never affect grades or skill performance.',
											'ohmylms'
										) }
							</p>
						) }
					<p>
						{ source === 'random'
							? sprintf(
									/* translators: %d: number of selected skills. */
									__( 'Selected skills: %d', 'ohmylms' ),
									ids.length
								)
							: sprintf(
									/* translators: %d: number of selected questions. */
									__( 'Selected questions: %d', 'ohmylms' ),
									ids.length
								) }
					</p>
					<Button
						variant="secondary"
						onClick={ () => setPicking( true ) }
					>
						{ __( 'Edit selection', 'ohmylms' ) }
					</Button>
					{ picking && (
						<Modal
							title={
								source === 'random'
									? __( 'From skills', 'ohmylms' )
									: __( 'Pick from bank', 'ohmylms' )
							}
							onRequestClose={ () => setPicking( false ) }
							size="large"
						>
							{ error && (
								<Notice status="error" isDismissible={ false }>
									{ error }
								</Notice>
							) }
							<ComboboxControl
								label={
									source === 'random'
										? __( 'Add a skill', 'ohmylms' )
										: __(
												'Add a question from the bank',
												'ohmylms'
											)
								}
								value={ null }
								options={ items
									.filter(
										( item ) => ! ids.includes( item.id )
									)
									.map( ( item ) => ( {
										value: String( item.id ),
										label: item.title,
									} ) ) }
								onFilterValueChange={ setSearch }
								onChange={ ( value ) =>
									setAttributes( {
										[ key ]: addSelection( ids, value ),
									} )
								}
							/>
							{ loading && <Spinner /> }
							{ ! loading && ! items.length && (
								<p>
									{ __(
										'No eligible items found.',
										'ohmylms'
									) }
								</p>
							) }
							<ol>
								{ ids.map( ( id, index ) => (
									<li key={ id }>
										{ labels[ id ] ||
											sprintf(
												/* translators: %d: saved question or skill ID. */ __(
													'Item %d (unavailable)',
													'ohmylms'
												),
												id
											) }
										{ source === 'selected' && (
											<>
												<Button
													icon="arrow-up-alt2"
													label={ __(
														'Move question up',
														'ohmylms'
													) }
													disabled={ index === 0 }
													onClick={ () =>
														setAttributes( {
															questionIds:
																moveSelection(
																	ids,
																	index,
																	-1
																),
														} )
													}
												/>
												<Button
													icon="arrow-down-alt2"
													label={ __(
														'Move question down',
														'ohmylms'
													) }
													disabled={
														index === ids.length - 1
													}
													onClick={ () =>
														setAttributes( {
															questionIds:
																moveSelection(
																	ids,
																	index,
																	1
																),
														} )
													}
												/>
											</>
										) }
										<Button
											icon="no-alt"
											label={ __(
												'Remove selection',
												'ohmylms'
											) }
											onClick={ () =>
												setAttributes( {
													[ key ]: ids.filter(
														( item ) => item !== id
													),
												} )
											}
										/>
									</li>
								) ) }
							</ol>
							{ source === 'random' && (
								<RangeControl
									label={ __(
										'Maximum questions per play',
										'ohmylms'
									) }
									help={ __(
										'Draws a fresh set without duplicates from the selected skills and their subskills. A smaller pool uses all available questions.',
										'ohmylms'
									) }
									min={ 1 }
									max={ 30 }
									value={ count }
									onChange={ ( value ) =>
										setAttributes( { count: value } )
									}
								/>
							) }
							<Button
								variant="primary"
								onClick={ () => setPicking( false ) }
							>
								{ __( 'Done', 'ohmylms' ) }
							</Button>
						</Modal>
					) }
					<ToggleControl
						label={ __( 'Grade responses', 'ohmylms' ) }
						disabled={ activityMode !== 'standard' }
						checked={ graded }
						help={ __(
							'Show a score and save results for students. Turn off for an ungraded activity.',
							'ohmylms'
						) }
						onChange={ ( value ) =>
							setAttributes( {
								graded: value,
								affectsSkills: value && affectsSkills,
							} )
						}
					/>
					<ToggleControl
						label={ __(
							'Count toward skill performance',
							'ohmylms'
						) }
						checked={ graded && affectsSkills }
						disabled={ ! graded || activityMode !== 'standard' }
						help={ __(
							'When enabled, graded student responses contribute to the questions’ linked skills. Instructor previews never count.',
							'ohmylms'
						) }
						onChange={ ( value ) =>
							setAttributes( { affectsSkills: value } )
						}
					/>
				</div>
			</Placeholder>
		</div>
	);
}

registerBlockType( metadata, {
	edit: QuestionPlayerEdit,
	save: () => null,
	// A practice set of exactly one chosen question is the same thing as a single question block.
	transforms: {
		to: [
			{
				type: 'block',
				blocks: [ 'ohmylms/shared-multiple-choice' ],
				isMatch: ( attributes ) =>
					attributes.source === 'selected' &&
					attributes.questionIds.length === 1,
				transform: ( attributes ) =>
					createBlock( 'ohmylms/shared-multiple-choice', {
						questionId: attributes.questionIds[ 0 ],
						delivery: 'inline',
					} ),
			},
		],
	},
} );

for ( const [ name, title, icon ] of [
	...activities.map( ( [ id, label ] ) => [
		id,
		sprintf(
			/* translators: %s: activity name. */ __( 'OhMyLMS %s', 'ohmylms' ),
			label
		),
		'games',
	] ),
	[ 'image-quiz', __( 'OhMyLMS Image quiz', 'ohmylms' ), 'format-image' ],
	[ 'hangman', __( 'OhMyLMS Hangman', 'ohmylms' ), 'editor-spellcheck' ],
	[
		'spell-word',
		__( 'OhMyLMS Spell the word', 'ohmylms' ),
		'editor-textcolor',
	],
	[
		'memory-recall',
		__( 'OhMyLMS Watch and memorize', 'ohmylms' ),
		'visibility',
	],
	[ 'flashcards', __( 'OhMyLMS Flashcards', 'ohmylms' ), 'index-card' ],
	[ 'memory-pairs', __( 'OhMyLMS Matching Pairs', 'ohmylms' ), 'grid-view' ],
] ) {
	registerBlockVariation( metadata.name, {
		name,
		title,
		icon,
		description: __(
			'Play bank question activities with saved progress.',
			'ohmylms'
		),
		attributes: { activityMode: name, graded: false, affectsSkills: false },
		isActive: [ 'activityMode' ],
		scope: [ 'inserter' ],
	} );
}
