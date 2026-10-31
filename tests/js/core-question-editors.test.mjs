import test from 'node:test';
import assert from 'node:assert/strict';
import { createRegistry } from '../../assets/src/app/extensions/registry.mjs';
import {
	CORE_QUESTION_EDITOR_IDS,
	registerCoreQuestionEditors,
} from '../../assets/src/features/question-editor/registerCoreEditors.js';

const names = [
	'NumericalEditor',
	'StructuredEditor',
	'BankAnswerFields',
	'DropdownBlanksEditor',
	'CategorizeEditor',
	'MultiBlankEditor',
	'BuildExpressionEditor',
	'ExpressionEditor',
	'NumberLineEditor',
	'ShadeModelEditor',
	'CountBlocksEditor',
	'SetClockEditor',
	'MakeAmountEditor',
	'FillLevelEditor',
	'BuildChartEditor',
	'GridBuildEditor',
	'TextEntryEditor',
	'CrosswordEditor',
	'WordsearchEditor',
	'ExtendedEditor',
];
const components = () =>
	Object.fromEntries(
		names.map( ( name ) => {
			const component = () => null;
			component.displayName = name;
			return [ name, component ];
		} )
	);

// Every built-in `ohmylms-settings` type the server registers (QuestionTypes and ExtendedQuestions).
const SETTINGS_TYPES = [
	'numerical',
	'structured',
	'crossword',
	'wordsearch',
	'text-entry',
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
	'passage',
	'graphing',
	'hot-text',
	'match-table-grid',
	'labeling',
	'hotspot',
	'draw',
	'audio-response',
	'video-response',
	'poll',
	'word-cloud',
	'discussion-board',
	'slide',
	'interactive-video',
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
];

test( 'every built-in settings type has an editor, registered with its label', () => {
	const registry = createRegistry();
	const parts = components();
	const registered = registerCoreQuestionEditors( registry, parts );
	assert.deepEqual( registered, [ ...CORE_QUESTION_EDITOR_IDS ] );
	assert.deepEqual( [ ...registered ].sort(), [ ...SETTINGS_TYPES ].sort() );
	assert.deepEqual(
		registry
			.list( 'question-editor' )
			.map( ( entry ) => entry.id )
			.sort(),
		[ ...CORE_QUESTION_EDITOR_IDS ].sort()
	);
	const numerical = registry.get( 'question-editor', 'numerical' );
	assert.equal( numerical.label, 'Numerical' );
	assert.equal( numerical.description, 'A number checked with a tolerance' );
	assert.equal( numerical.render, parts.NumericalEditor );
	assert.equal(
		registry.get( 'question-editor', 'grid-build' ).render,
		parts.GridBuildEditor
	);
	assert.equal(
		registry.get( 'question-editor', 'crossword' ).render,
		parts.CrosswordEditor
	);
	assert.equal(
		registry.get( 'question-editor', 'dropdown-blanks' ).label,
		'Dropdown in a sentence'
	);
	assert.equal( registry.get( 'question-editor', 'slide' ).label, 'Slide' );
} );

test( 'structured parts render with BankAnswerFields', () => {
	const registry = createRegistry();
	const parts = components();
	registerCoreQuestionEditors( registry, parts );
	const element = registry
		.get( 'question-editor', 'structured' )
		.render( { value: { type: 'structured' }, onChange() {} } );
	assert.equal( element.type, parts.StructuredEditor );
	assert.equal( element.props.renderQuestion, parts.BankAnswerFields );
	assert.deepEqual( element.props.value, { type: 'structured' } );
} );

test( 'extended formats render the extended editor with their type', () => {
	const registry = createRegistry();
	const parts = components();
	registerCoreQuestionEditors( registry, parts );
	for ( const type of [ 'passage', 'hotspot', 'slide', 'flying-fruit' ] ) {
		const element = registry
			.get( 'question-editor', type )
			.render( { value: { type }, onChange() {} } );
		assert.equal( element.type, parts.ExtendedEditor );
		assert.equal( element.props.type, type );
		assert.deepEqual( element.props.value, { type } );
	}
} );

test( 'editors an extension registered first are kept', () => {
	const registry = createRegistry();
	const custom = () => null;
	registry.registerQuestionEditor( 'numerical', {
		label: 'Add-on numerical',
		render: custom,
	} );
	const registered = registerCoreQuestionEditors( registry, components() );
	assert.equal( registered.includes( 'numerical' ), false );
	assert.equal( registered.length, CORE_QUESTION_EDITOR_IDS.length - 1 );
	assert.equal(
		registry.get( 'question-editor', 'numerical' ).render,
		custom
	);
	assert.deepEqual(
		registerCoreQuestionEditors( registry, components() ),
		[]
	);
} );

test( 'a registry without get registers every editor', () => {
	const ids = [];
	registerCoreQuestionEditors(
		{ registerQuestionEditor: ( id ) => ids.push( id ) },
		components()
	);
	assert.deepEqual( ids, [ ...CORE_QUESTION_EDITOR_IDS ] );
} );
