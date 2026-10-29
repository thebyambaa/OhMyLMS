import test from 'node:test';
import assert from 'node:assert/strict';
import {
	syllabusSkillRows,
	skillPickerTree,
} from '../../assets/src/features/courses/skillPickerTree.mjs';

const outline = {
	syllabus: { id: 1 },
	contents: [
		{ id: 1, name: 'Mathematics', depth: 0, groups: [] },
		{
			id: 2,
			parent_id: 1,
			name: 'Number',
			depth: 1,
			groups: [
				{
					id: 20,
					name: 'Core',
					skills: [
						{ term_id: 23, code: 'C1.1', name: 'Types of number' },
						{ term_id: 24, code: 'C1.2', name: 'Sets' },
					],
				},
			],
		},
		{
			id: 3,
			parent_id: 2,
			name: 'Extension',
			depth: 2,
			groups: [
				{
					id: 21,
					name: 'Extended',
					skills: [ { term_id: 25, name: 'Surds' } ],
				},
			],
		},
	],
};

test( 'picker preserves Curriculum order and nested topic paths', () => {
	const rows = syllabusSkillRows( outline );
	assert.deepEqual(
		rows.map( ( row ) => row.term_id ),
		[ 23, 24, 25 ]
	);
	assert.deepEqual(
		rows[ 2 ].path.map( ( part ) => part.label ),
		[ 'Mathematics', '1 · Number', '1.1 · Extension', 'Extended' ]
	);
	assert.equal( rows[ 0 ].syllabus_id, 1 );
} );

test( 'filtered skills retain their ancestors and source identities', () => {
	const rows = syllabusSkillRows( outline ).filter(
		( row ) => row.term_id === 25
	);
	const tree = skillPickerTree( rows );
	let branch = tree;
	for ( const part of rows[ 0 ].path ) {
		assert.equal( branch.children.size, 1 );
		branch = branch.children.get( part.id );
		assert.equal( branch.rows.length, 1 );
	}
	assert.equal( branch.skills[ 0 ].term_id, 25 );
	const other = syllabusSkillRows( { ...outline, syllabus: { id: 99 } } );
	assert.equal( skillPickerTree( [ ...rows, ...other ] ).children.size, 2 );
} );
