import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import * as workspace from '../../assets/src/features/curriculum/workspace.mjs';
import { siblingMove } from '../../assets/src/features/curriculum/model.mjs';
import { categoryAppearance } from '../../assets/src/features/curriculum/categoryAppearance.mjs';

const source = fs
	.readFileSync(
		new URL(
			'../../assets/src/features/curriculum/workspaceActions.js',
			import.meta.url
		),
		'utf8'
	)
	.replace( /import[\s\S]*?from ['"][^'"]+['"];\s*/g, '' )
	.replace( 'export function', 'function' );
function fixture( groups, failGroup = false, contents = [] ) {
	const tree = workspace.buildOutline( {
		contents: [
			{ id: 1, name: 'S', depth: 0, groups: [] },
			{ id: 2, parent_id: 1, name: 'T', depth: 1, groups },
			...contents,
		],
	} );
	const calls = [],
		selected = [];
	const api = Object.fromEntries(
		[
			'addGroup',
			'addSkill',
			'moveSkill',
			'moveItem',
			'moveGroup',
			'updateSkill',
			'deleteItem',
		].map( ( method ) => [
			method,
			async ( ...args ) => {
				calls.push( [ method, ...args ] );
				return method === 'addGroup'
					? failGroup
						? false
						: { group_id: 99 }
					: {};
			},
		] )
	);
	const scope = {
		api,
		...workspace,
		siblingMove,
		categoryAppearance,
		__: ( s ) => s,
		_n: ( s ) => s,
		sprintf: ( s ) => s,
	};
	const createActions = new Function(
		...Object.keys( scope ),
		source + '; return createActions;'
	)( ...Object.values( scope ) );
	const actions = createActions( {
		syllabusId: 1,
		tree,
		items: [],
		select: ( key ) => selected.push( key ),
		ws: {
			outline: { settings: { categories: [ 'Core', 'Extended' ] } },
			run: ( operation ) => operation(),
		},
	} );
	return { actions, calls, selected };
}

test( 'moving a nested topic out sends the new parent and position to the item API', async () => {
	const { actions, calls } = fixture( [], false, [
		{ id: 3, parent_id: 2, name: 'Nested', depth: 2, groups: [] },
	] );
	assert.equal( await actions.reorderOutline( 'c:3', 'c:2', true ), true );
	assert.deepEqual( calls, [ [ 'moveItem', 3, 1, 1 ] ] );
} );

test( 'moving a skill group out preserves the group and uses the destination topic', async () => {
	const { actions, calls } = fixture( [
		{ id: 5, name: 'Group', skills: [] },
	] );
	assert.equal(
		await actions.reorderOutline( 'g:5', 'c:1', 'inside' ),
		true
	);
	assert.deepEqual( calls, [ [ 'moveGroup', 1, 5, 1, 0 ] ] );
} );

test( 'deleting a syllabus explicitly confirms deletion of its whole structure', async () => {
	const { actions, calls } = fixture( [] );
	assert.equal( await actions.deleteSyllabus(), true );
	assert.deepEqual( calls, [
		[ 'deleteItem', 1, { children: 'delete', confirm: true } ],
	] );
} );

test( 'adding a direct skill creates its storage container without adding it to a category chapter', async () => {
	const { actions, calls } = fixture( [
		{ id: 5, name: 'Core', skills: [] },
	] );
	await actions.addSkillInTopic( 2, { name: 'New skill' } );
	assert.deepEqual( calls, [
		[ 'addGroup', 1, { name: 'Skills', item_id: 2 } ],
		[ 'addSkill', 1, 99, { name: 'New skill' } ],
	] );
} );
test( 'existing direct-skill containers are reused, and failed creation never adds a skill', async () => {
	const ready = fixture( [ { id: 8, name: 'Skills', skills: [] } ] );
	await ready.actions.addSkillInTopic( 2, { name: 'A' } );
	assert.deepEqual( ready.calls, [ [ 'addSkill', 1, 8, { name: 'A' } ] ] );
	const failed = fixture( [], true );
	assert.equal(
		await failed.actions.addSkillInTopic( 2, { name: 'A' } ),
		false
	);
	assert.equal( failed.calls.length, 1 );
} );
test( 'flat-list reordering preserves inherited category and follows the moved skill', async () => {
	const { actions, calls, selected } = fixture( [
		{ id: 5, name: 'Core', skills: [ { term_id: 50, name: 'A' } ] },
		{ id: 6, name: 'Extended', skills: [ { term_id: 60, name: 'B' } ] },
	] );
	await actions.reorderOutline( 's:5:50', 's:6:60', true );
	assert.deepEqual( calls, [
		[ 'updateSkill', 1, 50, { category: 'Core' } ],
		[ 'moveSkill', 1, 5, 50, 6, 1 ],
	] );
	assert.deepEqual( selected, [ 's:6:50' ] );
} );

test( 'reordering from the topic pane keeps the topic selected across stored groups', async () => {
	const { actions, calls, selected } = fixture( [
		{ id: 5, name: 'Core', skills: [ { term_id: 50, name: 'A' } ] },
		{ id: 6, name: 'Extended', skills: [ { term_id: 60, name: 'B' } ] },
	] );
	await actions.reorderOutline( 's:5:50', 's:6:60', true, {
		selectMoved: false,
	} );
	assert.deepEqual( calls.at( -1 ), [ 'moveSkill', 1, 5, 50, 6, 1 ] );
	assert.deepEqual( selected, [] );
} );
