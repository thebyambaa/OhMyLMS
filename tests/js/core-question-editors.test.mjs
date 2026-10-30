import test from 'node:test';
import assert from 'node:assert/strict';
import { createRegistry } from '../../assets/src/extensions/registry.mjs';
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
];
const components = () =>
	Object.fromEntries(
		names.map( ( name ) => {
			const component = () => null;
			component.displayName = name;
			return [ name, component ];
		} )
	);

test( 'the fifteen built-in question editors register with their labels', () => {
	const registry = createRegistry();
	const parts = components();
	const registered = registerCoreQuestionEditors( registry, parts );
	assert.deepEqual( registered, [ ...CORE_QUESTION_EDITOR_IDS ] );
	assert.equal( registered.length, 15 );
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
		registry.get( 'question-editor', 'dropdown-blanks' ).label,
		'Dropdown in a sentence'
	);
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

test( 'editors an extension registered first are kept', () => {
	const registry = createRegistry();
	const custom = () => null;
	registry.registerQuestionEditor( 'numerical', {
		label: 'Add-on numerical',
		render: custom,
	} );
	const registered = registerCoreQuestionEditors( registry, components() );
	assert.equal( registered.includes( 'numerical' ), false );
	assert.equal( registered.length, 14 );
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
