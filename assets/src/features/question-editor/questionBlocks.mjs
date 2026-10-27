import {
	EXTENDED_TYPES,
	isExtendedType,
	isUngradedType,
	extendedDefaults,
} from './extendedModel.mjs';
export const QUESTION_BLOCK_PREFIX = 'ohmylms/question-';
import {
	promptText,
	questionPrompt,
	questionPromptPatch,
	statementKey,
} from './questionPrompt.mjs';
import {
	interactiveDefaults,
	interactiveIssues,
	isInteractiveType,
} from './interactiveModel.mjs';
export const QUESTION_BLOCK_TYPES = [
	[ 'single-choice', 'Single choice' ],
	[ 'multiple-choice', 'Multiple select' ],
	[ 'true-false', 'True / false' ],
	[ 'short-text', 'Short answer' ],
	[ 'text-entry', 'Type the answer (automatic)' ],
	[ 'crossword', 'Crossword' ],
	[ 'wordsearch', 'Wordsearch' ],
	[ 'long-text', 'Long answer' ],
	[ 'fill-in-the-blank', 'Fill in the blank' ],
	[ 'statement', 'Statement' ],
	[ 'reorder', 'Reorder' ],
	[ 'matching', 'Matching' ],
	[ 'numerical', 'Numerical' ],
	[ 'structured', 'Structured (multi-part)' ],
	[ 'dropdown-blanks', 'Dropdown in a sentence' ],
	[ 'categorize', 'Sort into groups' ],
	[ 'multi-blank', 'Multi-blank / table' ],
	[ 'build-expression', 'Build from tiles' ],
	[ 'expression', 'Math expression' ],
	[ 'number-line', 'Number line' ],
	[ 'shade-model', 'Shade a model' ],
	[ 'count-blocks', 'Count with blocks' ],
	[ 'set-clock', 'Set the clock' ],
	[ 'make-amount', 'Make an amount' ],
	[ 'fill-level', 'Fill to a level' ],
	[ 'build-chart', 'Build a chart' ],
	[ 'grid-build', 'Build on a grid' ],
	...EXTENDED_TYPES,
];
export const questionBlockName = ( type ) => QUESTION_BLOCK_PREFIX + type;
export const isQuestionBlock = ( block ) =>
	block.name.startsWith( QUESTION_BLOCK_PREFIX );
/**
 * Answer keys, grading settings and options never enter public block content.
 * @param blocks
 */
export function publicQuestionBlocks( blocks ) {
	return blocks
		.filter( ( block ) => ! isQuestionBlock( block ) )
		.map( ( block ) => ( {
			...block,
			innerBlocks: block.innerBlocks
				? publicQuestionBlocks( block.innerBlocks )
				: [],
		} ) );
}

/**
 * Canonical assessment data when an author inserts a question-type block.
 * @param question
 * @param type
 * @param seed
 */
export function questionTypePatch( question, type, seed = Date.now() ) {
	if ( question.settings?.type === type ) {
		return {};
	}
	const settings = {
		type,
		...( question.settings?.template
			? { template: { ...question.settings.template, set: [] } }
			: {} ),
		...( type === 'fill-in-the-blank' ? { blank_mode: 'drag' } : {} ),
		score: question.settings?.score || { enabled: true, value: 1 },
		...Object.fromEntries(
			[ 'required', 'randomize', 'hint', 'explanation', 'question_code' ]
				.filter( ( key ) => question.settings?.[ key ] !== undefined )
				.map( ( key ) => [ key, question.settings[ key ] ] )
		),
	};
	if ( type === 'structured' ) {
		settings.parts = [
			{ id: 'p1', label: 'a', kind: 'written', marks: 1, prompt: '' },
		];
	}
	if ( isInteractiveType( type ) ) {
		Object.assign( settings, interactiveDefaults( type ) );
	}
	if ( isExtendedType( type ) ) {
		Object.assign( settings, extendedDefaults( type ) );
		if ( isUngradedType( type ) ) {
			settings.score = { enabled: true, value: 0 };
		}
	}
	const count = [
		'short-text',
		'long-text',
		'statement',
		'fill-in-the-blank',
	].includes( type )
		? 1
		: 2;
	const questions =
		[ 'numerical', 'structured' ].includes( type ) ||
		isInteractiveType( type ) ||
		isExtendedType( type )
			? []
			: Array.from( { length: count }, ( _, index ) => ( {
					id: seed + index,
					answer:
						type === 'true-false'
							? [ 'True', 'False' ][ index ]
							: '',
					is_correct: [ 'statement', 'fill-in-the-blank' ].includes(
						type
					),
					order_number: index + 1,
					temp: true,
					...( [ 'matching', 'reorder' ].includes( type )
						? {
								matching_data: {
									label: '',
									image_id: '',
									image_url: '',
								},
							}
						: {} ),
				} ) );
	return {
		settings,
		questions,
		...( question.settings?.question_code
			? {
					name:
						type === 'fill-in-the-blank'
							? promptText(
									statementKey( question )
										? questionPrompt( question )
										: question.description
								)
							: `Question ${ question.order_number || 1 }`,
				}
			: {} ),
		...( statementKey( question )
			? questionPromptPatch(
					{ ...question, settings },
					questionPrompt( question )
				)
			: {} ),
	};
}

/**
 * A learner-facing preview model excludes correctness, expected values and teacher-only notes.
 * @param question
 */
export function questionPreviewModel( question ) {
	const type = question.settings?.type || question.type;
	const name = question.name || '';
	const title =
		type === 'fill-in-the-blank'
			? name
					.split( /(\{[^{}<>]+\})/u )
					.filter( Boolean )
					.map( ( text ) =>
						text.startsWith( '{' ) && text.endsWith( '}' )
							? { blank: true }
							: { text }
					)
			: [ { text: name } ];
	return {
		type,
		title,
		body: question.description || '',
		unit: question.settings?.unit || '',
		options: ( question.questions || question.options || [] ).map(
			( option, index ) => ( {
				id: String( option.id ?? index ),
				answer: option.answer || '',
				imageUrl: option.image_url || '',
				match: option.matching_data?.label || '',
			} )
		),
		interactive: isInteractiveType( type ) ? question.settings : null,
		parts: ( Array.isArray( question.settings?.parts )
			? question.settings.parts
			: []
		).map( ( { id, label, prompt, kind, marks } ) => ( {
			id,
			label,
			prompt,
			kind,
			marks,
		} ) ),
	};
}

/**
 * Missing authored content should be explained in preview, rather than rendered as empty controls.
 * @param question
 */
export function questionPreviewIssues( question ) {
	const view = questionPreviewModel( question );
	const issues = [];
	if ( ! question.name?.trim() || question.name === 'Untitled' ) {
		issues.push( 'title' );
	}
	if ( ! QUESTION_BLOCK_TYPES.some( ( [ type ] ) => type === view.type ) ) {
		issues.push( 'type' );
	}
	if (
		[
			'single-choice',
			'multiple-choice',
			'true-false',
			'matching',
			'reorder',
		].includes( view.type )
	) {
		if (
			view.options.length < 2 ||
			view.options.some( ( option ) => ! option.answer.trim() )
		) {
			issues.push( 'answers' );
		}
	}
	if (
		view.type === 'matching' &&
		view.options.some( ( option ) => ! option.match.trim() )
	) {
		issues.push( 'matches' );
	}
	if ( view.type === 'structured' && ! view.parts.length ) {
		issues.push( 'parts' );
	}
	if ( isInteractiveType( view.type ) ) {
		issues.push( ...interactiveIssues( view.type, question.settings ) );
	}
	return issues;
}
