import test from 'node:test';
import assert from 'node:assert/strict';
import {
	VARIABLE_LETTERS,
	defaultTemplate,
	hasTemplate,
	nextVariableName,
	parseLines,
	parseValues,
	setPathSuggestions,
	templateIssues,
	variableDefaults,
} from '../../assets/src/features/question-editor/templateModel.mjs';
import { interactiveIssues } from '../../assets/src/features/question-editor/interactiveModel.mjs';
import { validateDraft, emptyDraft } from '../../assets/src/features/question-bank/model.mjs';

test( 'a question has a template once it has variables', () => {
	assert.equal( hasTemplate( {} ), false );
	assert.equal( hasTemplate( { template: { variables: [] } } ), false );
	assert.equal( hasTemplate( { template: defaultTemplate() } ), true );
} );

test( 'variable letters skip e and never repeat', () => {
	assert.ok( ! VARIABLE_LETTERS.includes( 'e' ) );
	assert.equal( VARIABLE_LETTERS.length, 25 );
	assert.equal( nextVariableName( defaultTemplate().variables ), 'c' );
	assert.equal( nextVariableName( [ { name: 'a' }, { name: 'b' }, { name: 'c' }, { name: 'd' } ] ), 'f' );
	assert.equal( nextVariableName( VARIABLE_LETTERS.map( ( name ) => ( { name } ) ) ), '' );
} );

test( 'each kind of variable starts with usable limits', () => {
	assert.deepEqual( variableDefaults( 'int', 'a' ), { name: 'a', type: 'int', min: 2, max: 9 } );
	assert.equal( variableDefaults( 'decimal', 'x' ).places, 1 );
	assert.deepEqual( variableDefaults( 'choice', 'w' ).values, [ 'apples', 'pears' ] );
	assert.equal( variableDefaults( 'expr', 'c' ).expr, '' );
	assert.equal( variableDefaults( 'nonsense', 'z' ).type, 'int' );
} );

test( 'lists and conditions are parsed from typed text', () => {
	assert.deepEqual( parseValues( 'apples, 3 , ,2.5, pears,' ), [ 'apples', 3, 2.5, 'pears' ] );
	assert.deepEqual( parseLines( ' a>b \n\n gcd(a,b)=1\n' ), [ 'a>b', 'gcd(a,b)=1' ] );
} );

test( 'authors see the setting names that matter for each question type', () => {
	assert.deepEqual( setPathSuggestions( 'numerical' ), [ 'answer' ] );
	assert.deepEqual( setPathSuggestions( 'set-clock' ), [ 'hour', 'minute' ] );
	assert.ok( setPathSuggestions( 'multi-blank' ).includes( 'blanks.a.answer' ) );
	assert.deepEqual( setPathSuggestions( 'single-choice' ), [] );
} );

test( 'quick checks catch bad letters, ranges and empty formulas', () => {
	assert.deepEqual( templateIssues( defaultTemplate() ), [] );
	assert.ok( templateIssues( { variables: [ { name: 'ab', type: 'int', min: 1, max: 2 } ] } ).includes( 'names' ) );
	assert.ok( templateIssues( { variables: [ { name: 'e', type: 'int', min: 1, max: 2 } ] } ).includes( 'names' ) );
	assert.ok( templateIssues( { variables: [ { name: 'a', type: 'int', min: 1, max: 2 }, { name: 'a', type: 'int', min: 1, max: 2 } ] } ).includes( 'names' ) );
	assert.ok( templateIssues( { variables: [ { name: 'a', type: 'int', min: 5, max: 2 } ] } ).includes( 'range' ) );
	assert.ok( templateIssues( { variables: [ { name: 'a', type: 'expr', expr: ' ' } ] } ).includes( 'values' ) );
	assert.ok( templateIssues( { variables: [ { name: 'a', type: 'int', min: 1, max: 2 } ], set: [ { path: 'answer', expr: '' } ] } ).includes( 'set' ) );
} );

test( 'a template skips the static checks that only make sense for fixed values', () => {
	const template = defaultTemplate();
	assert.deepEqual( interactiveIssues( 'number-line', { min: 0, max: 10, step: 1, target: 99, template } ), [] );
	assert.ok( interactiveIssues( 'number-line', { min: 0, max: 10, step: 1, target: 99 } ).includes( 'target' ) );
	const draft = emptyDraft( 'numerical' );
	draft.name = 'What is {{a}} x {{b}}?';
	assert.ok( validateDraft( draft ).includes( 'numerical-answer' ) );
	draft.settings = { ...draft.settings, template };
	assert.ok( ! validateDraft( draft ).includes( 'numerical-answer' ) );
} );