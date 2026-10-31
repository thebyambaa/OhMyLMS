import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * The built-in settings-format types edited by `ExtendedEditor`, with their labels and descriptions.
 */
function extendedEditors() {
	return [
		[
			'passage',
			__( 'Passage', 'ohmylms' ),
			__( 'A reading passage with separately scored parts', 'ohmylms' ),
		],
		[
			'graphing',
			__( 'Graphing', 'ohmylms' ),
			__( 'Plot points or a straight line on a grid', 'ohmylms' ),
		],
		[
			'hot-text',
			__( 'Hot text', 'ohmylms' ),
			__( 'Select the right words in a text', 'ohmylms' ),
		],
		[
			'match-table-grid',
			__( 'Match table grid', 'ohmylms' ),
			__( 'Tick the right cells of a table', 'ohmylms' ),
		],
		[
			'labeling',
			__( 'Labeling', 'ohmylms' ),
			__( 'Place labels on pins of an image', 'ohmylms' ),
		],
		[
			'hotspot',
			__( 'Hotspot', 'ohmylms' ),
			__( 'Click the right region of an image', 'ohmylms' ),
		],
		[
			'draw',
			__( 'Draw', 'ohmylms' ),
			__( 'Draw on a canvas for teacher review', 'ohmylms' ),
		],
		[
			'audio-response',
			__( 'Audio response', 'ohmylms' ),
			__( 'Record or upload audio for teacher review', 'ohmylms' ),
		],
		[
			'video-response',
			__( 'Video response', 'ohmylms' ),
			__( 'Record or upload video for teacher review', 'ohmylms' ),
		],
		[
			'poll',
			__( 'Poll', 'ohmylms' ),
			__( 'Unscored choices', 'ohmylms' ),
		],
		[
			'word-cloud',
			__( 'Word cloud', 'ohmylms' ),
			__( 'Unscored words', 'ohmylms' ),
		],
		[
			'discussion-board',
			__( 'Discussion board', 'ohmylms' ),
			__( 'A written response for teacher review', 'ohmylms' ),
		],
		[
			'slide',
			__( 'Slide', 'ohmylms' ),
			__( 'Unscored content between questions', 'ohmylms' ),
		],
		[
			'interactive-video',
			__( 'Interactive video', 'ohmylms' ),
			__( 'A video with timed text checkpoints', 'ohmylms' ),
		],
		[
			'spin-wheel',
			__( 'Spin the wheel', 'ohmylms' ),
			__( 'Unscored cards drawn by a wheel', 'ohmylms' ),
		],
		[
			'gameshow',
			__( 'Gameshow quiz', 'ohmylms' ),
			__( 'Pick the right answer against the clock', 'ohmylms' ),
		],
		[
			'hangman',
			__( 'Hangman', 'ohmylms' ),
			__( 'Guess a word letter by letter', 'ohmylms' ),
		],
		[
			'open-box',
			__( 'Open the box', 'ohmylms' ),
			__( 'Unscored cards behind boxes', 'ohmylms' ),
		],
		[
			'speaking-cards',
			__( 'Speaking cards', 'ohmylms' ),
			__( 'Unscored speaking prompts', 'ohmylms' ),
		],
		[
			'flip-tiles',
			__( 'Flip tiles', 'ohmylms' ),
			__( 'Unscored two-sided tiles', 'ohmylms' ),
		],
		[
			'flashcards',
			__( 'Flashcards', 'ohmylms' ),
			__( 'Unscored two-sided cards', 'ohmylms' ),
		],
		[
			'timed-true-false',
			__( 'Timed True or false', 'ohmylms' ),
			__( 'True or false against the clock', 'ohmylms' ),
		],
		[
			'whack-a-mole',
			__( 'Whack-a-mole', 'ohmylms' ),
			__( 'Hit the right answer as it appears', 'ohmylms' ),
		],
		[
			'flying-fruit',
			__( 'Flying fruit', 'ohmylms' ),
			__( 'Catch the right answer as it flies by', 'ohmylms' ),
		],
	];
}

/**
 * IDs of the built-in question types that have an editor component, in registration order.
 */
export const CORE_QUESTION_EDITOR_IDS = Object.freeze( [
	'numerical',
	'structured',
	'dropdown-blanks',
	'categorize',
	'multi-blank',
	'build-expression',
	'expression',
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
	'text-entry',
	'crossword',
	'wordsearch',
	...extendedEditors().map( ( [ id ] ) => id ),
] );

/**
 * Register the built-in question editors in the public extension registry. An editor that is
 * already registered (an add-on may register its own editor for a built-in type first) is kept.
 *
 * @param {Object} registry   Extension registry (API v1).
 * @param {Object} components Editor components by export name (`NumericalEditor`, …); `BankAnswerFields`
 *                            renders each part of a structured question, `ExtendedEditor` edits the
 *                            extended response formats.
 * @return {string[]} IDs registered by this call.
 */
export function registerCoreQuestionEditors( registry, components ) {
	const {
		NumericalEditor,
		StructuredEditor,
		BankAnswerFields,
		DropdownBlanksEditor,
		CategorizeEditor,
		MultiBlankEditor,
		BuildExpressionEditor,
		ExpressionEditor,
		NumberLineEditor,
		ShadeModelEditor,
		CountBlocksEditor,
		SetClockEditor,
		MakeAmountEditor,
		FillLevelEditor,
		BuildChartEditor,
		GridBuildEditor,
		TextEntryEditor,
		CrosswordEditor,
		WordsearchEditor,
		ExtendedEditor,
	} = components;
	const editors = [
		[
			'numerical',
			__( 'Numerical', 'ohmylms' ),
			__( 'A number checked with a tolerance', 'ohmylms' ),
			NumericalEditor,
		],
		[
			'structured',
			__( 'Structured (multi-part)', 'ohmylms' ),
			__( 'A shared stem with separately scored parts', 'ohmylms' ),
			( props ) =>
				createElement( StructuredEditor, {
					...props,
					renderQuestion: BankAnswerFields,
				} ),
		],
		[
			'dropdown-blanks',
			__( 'Dropdown in a sentence', 'ohmylms' ),
			__( 'Choose the right word from a menu in a sentence', 'ohmylms' ),
			DropdownBlanksEditor,
		],
		[
			'categorize',
			__( 'Sort into groups', 'ohmylms' ),
			__( 'Place each item in the group it belongs to', 'ohmylms' ),
			CategorizeEditor,
		],
		[
			'multi-blank',
			__( 'Multi-blank / table', 'ohmylms' ),
			__( 'Several blanks in a sentence or a table', 'ohmylms' ),
			MultiBlankEditor,
		],
		[
			'build-expression',
			__( 'Build from tiles', 'ohmylms' ),
			__( 'Tap tiles into the right order', 'ohmylms' ),
			BuildExpressionEditor,
		],
		[
			'expression',
			__( 'Math expression', 'ohmylms' ),
			__(
				'A typed expression graded by algebraic equivalence',
				'ohmylms'
			),
			ExpressionEditor,
		],
		[
			'number-line',
			__( 'Number line', 'ohmylms' ),
			__( 'Drag a point to the right place on a number line', 'ohmylms' ),
			NumberLineEditor,
		],
		[
			'shade-model',
			__( 'Shade a model', 'ohmylms' ),
			__( 'Shade parts of a bar, grid or circle', 'ohmylms' ),
			ShadeModelEditor,
		],
		[
			'count-blocks',
			__( 'Count with blocks', 'ohmylms' ),
			__( 'Build a number with base-ten blocks', 'ohmylms' ),
			CountBlocksEditor,
		],
		[
			'set-clock',
			__( 'Set the clock', 'ohmylms' ),
			__( 'Set the hands of an analogue clock', 'ohmylms' ),
			SetClockEditor,
		],
		[
			'make-amount',
			__( 'Make an amount', 'ohmylms' ),
			__( 'Pay an amount with bills and coins', 'ohmylms' ),
			MakeAmountEditor,
		],
		[
			'fill-level',
			__( 'Fill to a level', 'ohmylms' ),
			__( 'Fill a jug to a marked level', 'ohmylms' ),
			FillLevelEditor,
		],
		[
			'build-chart',
			__( 'Build a chart', 'ohmylms' ),
			__( 'Drag bars to the right heights', 'ohmylms' ),
			BuildChartEditor,
		],
		[
			'grid-build',
			__( 'Build on a grid', 'ohmylms' ),
			__(
				'Choose squares to meet area and perimeter conditions',
				'ohmylms'
			),
			GridBuildEditor,
		],
		[
			'text-entry',
			__( 'Type the answer (automatic)', 'ohmylms' ),
			__( 'A typed answer checked against accepted answers', 'ohmylms' ),
			TextEntryEditor,
		],
		[
			'crossword',
			__( 'Crossword', 'ohmylms' ),
			__( 'Words and clues laid out as a crossword', 'ohmylms' ),
			CrosswordEditor,
		],
		[
			'wordsearch',
			__( 'Wordsearch', 'ohmylms' ),
			__( 'Find the words hidden in a letter grid', 'ohmylms' ),
			WordsearchEditor,
		],
		...extendedEditors().map( ( [ id, label, description ] ) => [
			id,
			label,
			description,
			( props ) =>
				createElement( ExtendedEditor, { ...props, type: id } ),
		] ),
	];
	const registered = [];
	for ( const [ id, label, description, render ] of editors ) {
		if ( ! registry.get?.( 'question-editor', id ) ) {
			registry.registerQuestionEditor( id, {
				label,
				description,
				render,
			} );
			registered.push( id );
		}
	}
	return registered;
}
