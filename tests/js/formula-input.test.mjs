import test from 'node:test';
import assert from 'node:assert/strict';
import {
	insertVariable,
	listToNumbersOrFormulas,
	numberOrFormula,
	toleranceOrFormula,
} from '../../assets/src/features/question-editor/formulaInput.mjs';

const record = ( make ) => {
	const seen = [];
	const props = make( ( value ) => seen.push( value ) );
	return { props, seen };
};

test( 'a number field keeps numbers as numbers and formulas as text', () => {
	const { props, seen } = record( ( on ) => numberOrFormula( 4, on ) );
	assert.equal( props.value, 4 );
	props.onChange( '12' );
	props.onChange( '{{a*b}}' );
	props.onChange( '3.' );
	props.onChange( '' );
	props.onChange( 'abc' );
	assert.deepEqual( seen, [ 12, '{{a*b}}', '3.', undefined ] );
} );

test( 'a tolerance is never negative and may be a formula', () => {
	const { props, seen } = record( ( on ) => toleranceOrFormula( undefined, on ) );
	assert.equal( props.value, 0 );
	props.onChange( '-2' );
	props.onChange( '0.5' );
	props.onChange( '{{c}}' );
	props.onChange( '' );
	assert.deepEqual( seen, [ 0, 0.5, '{{c}}', 0 ] );
} );

test( 'accepted answers keep numbers and complete formulas, including commas inside a formula', () => {
	assert.deepEqual( listToNumbersOrFormulas( '4, {{a*b}}, x, 2.5' ), [ 4, '{{a*b}}', 2.5 ] );
	assert.deepEqual( listToNumbersOrFormulas( '{{max(a,b)}}, 7' ), [ '{{max(a,b)}}', 7 ] );
	assert.equal( listToNumbersOrFormulas( '{{a' ), undefined );
	assert.equal( listToNumbersOrFormulas( '' ), undefined );
} );

test( 'inserting a variable replaces an empty field or a number and extends a formula', () => {
	assert.equal( insertVariable( undefined, 'a' ), '{{a}}' );
	assert.equal( insertVariable( 5, 'a' ), '{{a}}' );
	assert.equal( insertVariable( '{{a}}', 'b' ), '{{a}}{{b}}' );
} );

test( 'a variable is inserted at the end of the last paragraph of a statement', async () => {
	const { insertIntoStatement } = await import(
		'../../assets/src/features/question-editor/formulaInput.mjs'
	);
	assert.equal( insertIntoStatement( '', 'a' ), '<p>{{a}}</p>' );
	assert.equal( insertIntoStatement( '<p>What is</p>', 'a' ), '<p>What is {{a}}</p>' );
	assert.equal( insertIntoStatement( 'plain', 'b' ), 'plain {{b}}' );
} );

test( 'the lesson panel inserts the question block for every type, and the block previews types it cannot edit', async () => {
	const fs = await import( 'node:fs' );
	const panel = fs.readFileSync( 'assets/src/features/learning/NativeLessonSettings.jsx', 'utf8' );
	assert.ok( ! panel.includes( 'core/shortcode' ), 'the panel still inserts shortcode text' );
	assert.ok( panel.includes( "createBlock( 'ohmylms/shared-multiple-choice'" ) );
	const block = fs.readFileSync( 'assets/src/features/learning/LessonChoiceBlock.jsx', 'utf8' );
	assert.ok( block.includes( 'ohmylms-inline-check-note' ) && block.includes( 'readOnly' ) );
} );
