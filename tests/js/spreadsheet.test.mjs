import test from 'node:test';
import assert from 'node:assert/strict';
import {
	syllabusSheetRows,
	editSheetCell,
	sheetChanges,
	pastedSheetRows,
	saveSheetChanges,
} from '../../assets/src/features/curriculum/spreadsheet.mjs';

const outline = {
	syllabus: { id: 1 },
	contents: [
		{ id: 1, parent_id: 0, name: 'Math', groups: [] },
		{
			id: 2,
			parent_id: 1,
			name: 'Algebra',
			code: 'A',
			groups: [
				{
					id: 10,
					name: 'Equations',
					skills: [
						{ term_id: 100, name: 'Solve' },
						{ term_id: 101, name: 'Form' },
					],
				},
			],
		},
		{
			id: 3,
			parent_id: 2,
			name: 'Advanced',
			code: 'B',
			groups: [
				{
					id: 11,
					name: 'Practice',
					skills: [ { term_id: 100, name: 'Solve' } ],
				},
			],
		},
		{ id: 4, parent_id: 1, name: 'Empty unit', code: '', groups: [] },
	],
};

test( 'spreadsheet conversion preserves nested paths, empty units, shared IDs and distinct placements', () => {
	const rows = syllabusSheetRows( outline );
	assert.equal( rows.length, 4 );
	assert.equal( rows[ 2 ].unit, 'Algebra > Advanced' );
	assert.equal( rows[ 3 ].contentId, 4 );
	assert.equal( rows[ 3 ].topic, '' );
	assert.equal( rows[ 0 ].termId, rows[ 2 ].termId );
	assert.notEqual( rows[ 0 ].groupId, rows[ 2 ].groupId );
	assert.notEqual( rows[ 0 ].key, rows[ 2 ].key );
} );

test( 'shared edits create one identity-based update and ancestor renames preserve hierarchy', () => {
	const baseline = syllabusSheetRows( outline );
	const skillEdit = editSheetCell( baseline, 0, 'topic', 'Solve equations' );
	assert.equal( skillEdit[ 2 ].topic, 'Solve equations' );
	assert.deepEqual( sheetChanges( baseline, skillEdit ).changes, [
		{
			kind: 'skill',
			id: 100,
			fields: { name: 'Solve equations' },
			previous: { name: 'Solve' },
		},
	] );
	const folderEdit = editSheetCell( baseline, 0, 'unit', 'Expressions' );
	assert.equal( folderEdit[ 2 ].unit, 'Expressions > Advanced' );
	assert.deepEqual( sheetChanges( baseline, folderEdit ).changes, [
		{
			kind: 'item',
			id: 2,
			fields: { name: 'Expressions' },
			previous: { name: 'Algebra' },
		},
	] );
} );

test( 'pasted rows inherit group context and use the existing unit code', () => {
	const rows = pastedSheetRows(
		'№\tUnit\tChapter\tTopic\n1\tAlgebra\tEquations\tFirst\n\t\t\tSecond'
	);
	assert.equal( rows[ 1 ].unit, 'Algebra' );
	assert.equal( rows[ 1 ].chapter, 'Equations' );
	const baseline = syllabusSheetRows( outline );
	const plan = sheetChanges( baseline, [ ...baseline, ...rows ] );
	assert.equal( plan.additions.length, 2 );
	assert.equal( plan.additions[ 0 ].content_code, 'A' );
} );

test( 'stale values reject a save before writes and retry acknowledgements follow successful operations only', async () => {
	const baseline = syllabusSheetRows( outline );
	const plan = sheetChanges(
		baseline,
		editSheetCell( baseline, 0, 'topic', 'Revised' )
	);
	let writes = 0;
	const api = {
		loadSyllabus: async () => structuredClone( outline ),
		updateSkill: async () => {
			writes++;
		},
	};
	const acknowledgements = [];
	await saveSheetChanges( {
		id: 1,
		plan,
		api,
		onSaved: ( change ) => acknowledgements.push( change.id ),
	} );
	assert.equal( writes, 1 );
	assert.deepEqual( acknowledgements, [ 100 ] );
	api.loadSyllabus = async () => {
		const changed = structuredClone( outline );
		changed.contents[ 1 ].groups[ 0 ].skills[ 0 ].name =
			'Changed elsewhere';
		return changed;
	};
	await assert.rejects(
		() => saveSheetChanges( { id: 1, plan, api, onSaved: () => {} } ),
		/changed in another editor/
	);
	assert.equal( writes, 1 );
} );
