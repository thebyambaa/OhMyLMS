/**
 * Question formats grouped by what the learner does. Shared by the type chooser
 * dialog and the in-block type menu so both list formats the same way.
 */
export const QUESTION_TYPE_GROUPS = [
	[
		'Basic',
		[
			'single-choice',
			'multiple-choice',
			'true-false',
			'short-text',
			'text-entry',
			'long-text',
			'fill-in-the-blank',
			'statement',
			'poll',
			'word-cloud',
		],
	],
	[
		'Interactive and responses',
		[
			'reorder',
			'build-expression',
			'crossword',
			'wordsearch',
			'matching',
			'dropdown-blanks',
			'categorize',
			'multi-blank',
			'hot-text',
			'match-table-grid',
			'audio-response',
			'video-response',
			'discussion-board',
		],
	],
	[
		'Activities and games',
		[
			'spin-wheel',
			'gameshow',
			'hangman',
			'open-box',
			'speaking-cards',
			'flip-tiles',
			'flashcards',
			'timed-true-false',
			'whack-a-mole',
			'flying-fruit',
		],
	],
	[ 'Mathematics', [ 'numerical', 'structured', 'expression', 'graphing' ] ],
	[
		'Visual learning',
		[
			'number-line',
			'shade-model',
			'count-blocks',
			'set-clock',
			'make-amount',
			'fill-level',
			'build-chart',
			'grid-build',
			'labeling',
			'hotspot',
			'draw',
		],
	],
	[ 'Content and multimedia', [ 'passage', 'interactive-video' ] ],
];

/**
 * Split option objects ({ value, label }) into the groups above. Formats that
 * no group lists, such as add-on types, fall into a final "More" group.
 * @param options
 */
export function groupQuestionTypes( options ) {
	const known = new Set(
		QUESTION_TYPE_GROUPS.flatMap( ( [ , ids ] ) => ids )
	);
	const groups = QUESTION_TYPE_GROUPS.map( ( [ heading, ids ] ) => [
		heading,
		ids
			.map( ( id ) => options.find( ( option ) => option.value === id ) )
			.filter( Boolean ),
	] );
	groups.push( [
		'More',
		options.filter( ( option ) => ! known.has( option.value ) ),
	] );
	return groups.filter( ( [ , items ] ) => items.length );
}
