import test from 'node:test';
import assert from 'node:assert/strict';
import {
	gradeBand,
	isActive,
	overridesFor,
	percent,
	progress,
	proposes,
	skillSummary,
	waitsForProposal,
	weightPercent,
} from '../../assets/src/features/curriculum/aiAnalysis.mjs';

test( 'a run is advanced only while queued or running, and its progress is bounded', () => {
	assert.equal( isActive( { status: 'queued' } ), true );
	assert.equal( isActive( { status: 'running' } ), true );
	assert.equal( isActive( { status: 'done' } ), false );
	assert.equal( isActive( null ), false );
	assert.equal( progress( { processed: 4, total: 9 } ), 44 );
	assert.equal( progress( { processed: 12, total: 9 } ), 100 );
	assert.equal( progress( { processed: 0, total: 0 } ), 0 );
} );

test( 'grade bands and confidence read naturally', () => {
	assert.equal( gradeBand( 7, 7 ), 'Grade 7' );
	assert.equal( gradeBand( 7, 9 ), 'Grades 7–9' );
	assert.equal( gradeBand( 12, 13 ), 'Grades 12–beyond grade 12' );
	assert.equal( gradeBand( 13, 13 ), 'Beyond grade 12' );
	assert.equal( gradeBand( 0, 5 ), '' );
	const cefr = [ 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' ].map(
		( label, index ) => ( {
			value: index + 1,
			label,
		} )
	);
	assert.equal( gradeBand( 2, 2, cefr ), 'A2' );
	assert.equal( gradeBand( 2, 4, cefr ), 'A2–B2' );
	assert.equal( gradeBand( 5, 9, cefr ), 'C1–C2' );
	assert.equal(
		skillSummary( { domain: 'reading', grade_from: 1, grade_to: 3 }, cefr ),
		'reading · A1–B1'
	);
	assert.equal( percent( 0.856 ), 86 );
	assert.equal( percent( 4 ), 100 );
} );

test( 'each kind of suggestion says what it proposes', () => {
	assert.equal(
		proposes( { kind: 'new_skill', proposal: { name: 'Area' } } ),
		'Area'
	);
	assert.equal(
		proposes( {
			kind: 'placement',
			placement: { domain: 'algebra', grade_from: 7, grade_to: 8 },
		} ),
		'algebra · Grades 7–8'
	);
	assert.equal(
		proposes( { kind: 'mapping', shared: { name: 'Linear equations' } } ),
		'Linear equations'
	);
	assert.equal(
		proposes( {
			kind: 'mapping',
			shared: null,
			proposal: { name: 'Area', status: 'pending' },
		} ),
		'Area'
	);
} );

test( 'link weights and linked skills read as percentages and short summaries', () => {
	assert.equal( weightPercent( 0.6 ), 60 );
	assert.equal( weightPercent( 7 ), 100 );
	assert.equal( weightPercent( 0 ), 5 );
	assert.equal(
		skillSummary( { domain: 'algebra', grade_from: 7, grade_to: 8 } ),
		'algebra · Grades 7–8'
	);
	assert.equal( skillSummary( { domain: '', grade_from: 0 } ), '' );
	assert.equal( skillSummary( null ), '' );
} );

test( 'a mapping to an unaccepted proposal waits, and only edited suggestions send overrides', () => {
	assert.equal(
		waitsForProposal( {
			kind: 'mapping',
			shared: null,
			proposal: { status: 'pending' },
		} ),
		true
	);
	assert.equal(
		waitsForProposal( {
			kind: 'mapping',
			shared: { id: 1 },
			proposal: null,
		} ),
		false
	);
	assert.deepEqual(
		overridesFor( { 3: { relation: 'related' }, 4: {} }, [ 3, 4, 5 ] ),
		{ 3: { relation: 'related' } }
	);
} );
