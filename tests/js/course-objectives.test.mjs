import test from 'node:test';
import assert from 'node:assert/strict';
import {
	defaultSyllabusId,
	filterObjectives,
	objectiveKey,
	objectiveReferences,
} from '../../assets/src/features/courses/objectives.mjs';

test( 'objective search matches full official wording, code and topic', () => {
	const rows = [
		{
			syllabus_id: 1,
			term_id: 4,
			code: 'ALG-02',
			name: 'Equations',
			objective: 'Solve equations with one variable',
			topic: 'Algebra',
		},
		{
			syllabus_id: 2,
			term_id: 4,
			code: 'NUM-01',
			name: 'Numbers',
			objective: 'Compare integers',
			topic: 'Numbers',
		},
	];
	assert.deepEqual( filterObjectives( rows, 'one variable' ), [ rows[ 0 ] ] );
	assert.deepEqual( filterObjectives( rows, 'alg-02' ), [ rows[ 0 ] ] );
	assert.deepEqual( filterObjectives( rows, '', 'Numbers' ), [ rows[ 1 ] ] );
	assert.deepEqual( filterObjectives( rows, 'integers', 'Algebra' ), [] );
	assert.notEqual( objectiveKey( rows[ 0 ] ), objectiveKey( rows[ 1 ] ) );
	assert.deepEqual( objectiveReferences( rows ), [
		{ syllabus_id: 1, term_id: 4 },
		{ syllabus_id: 2, term_id: 4 },
	] );
} );

test( 'the objective picker opens on the edition the course already uses, then the last one chosen', () => {
	const editions = [ { id: 29 }, { id: 3 }, { id: 20 } ];
	const memory = ( value ) => ( {
		getItem: () => value,
		setItem: () => {},
	} );
	assert.equal( defaultSyllabusId( editions, [], memory( '' ) ), '29' );
	assert.equal( defaultSyllabusId( editions, [], memory( '20' ) ), '20' );
	assert.equal(
		defaultSyllabusId(
			editions,
			[ { syllabus_id: 20 }, { syllabus_id: 20 }, { syllabus_id: 3 } ],
			memory( '29' )
		),
		'20',
		'the most used edition wins over the remembered one'
	);
	assert.equal(
		defaultSyllabusId( editions, [], memory( '999' ) ),
		'29',
		'an edition that is no longer offered is ignored'
	);
	assert.equal(
		defaultSyllabusId( editions, [], {
			getItem() {
				throw new Error( 'blocked' );
			},
		} ),
		'29',
		'blocked storage falls back to the first edition'
	);
} );
