import test from 'node:test';
import assert from 'node:assert/strict';
import {
	hotTextFromAuthoring,
	hotTextNormalize,
	hotTextScore,
	hotTextToAuthoring,
} from '../../assets/src/features/question-editor/hotTextModel.mjs';

test( 'legacy token lists migrate to a passage on read', () => {
	const s = hotTextNormalize( { tokens: [ { id: 't1', text: '2' }, { id: 't2', text: '3' } ], correct: [ 't1' ] } );
	assert.equal( s.passage, '[[t1]] [[t2]]' );
	assert.equal( s.scoring, 'partial' );
	assert.equal( hotTextToAuthoring( s ), '[[2]] [[3]]' );
} );

test( 'editing keeps ids and correct marks stable', () => {
	const prev = { passage: 'A [[t1]] and [[t2]] end.', tokens: [ { id: 't1', text: 'cat' }, { id: 't2', text: 'dgo' } ], correct: [ 't2' ] };
	const typo = hotTextFromAuthoring( 'Now a [[cat]] and [[dog]] end. [[new one]]', prev );
	assert.deepEqual( typo.tokens.map( ( t ) => t.id ), [ 't1', 't2', 't3' ] );
	assert.deepEqual( typo.correct, [ 't2' ] );
	assert.equal( typo.passage, 'Now a [[t1]] and [[t2]] end. [[t3]]' );
	const removed = hotTextFromAuthoring( 'Only [[dgo]]', prev );
	assert.deepEqual( removed.tokens, [ { id: 't2', text: 'dgo' } ] );
	assert.deepEqual( removed.correct, [ 't2' ] );
} );

test( 'partial credit floors at zero; all-or-nothing is exact', () => {
	assert.equal( hotTextScore( [ 'a' ], [ 'a', 'b' ] ), 0.5 );
	assert.equal( hotTextScore( [ 'a', 'x' ], [ 'a', 'b' ] ), 0 );
	assert.equal( hotTextScore( [ 'x' ], [ 'a', 'b' ] ), 0 );
	assert.equal( hotTextScore( [ 'a', 'b' ], [ 'a', 'b' ] ), 1 );
	assert.equal( hotTextScore( [ 'a' ], [ 'a', 'b' ], 'all' ), 0 );
} );
