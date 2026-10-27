import test from 'node:test';
import assert from 'node:assert/strict';
import {
	ancestorKeys,
	buildOutline,
	chaptersOf,
	containerOf,
	contentKey,
	courseState,
	defaultKey,
	editPath,
	groupKey,
	nodeCounts,
	openFor,
	pathTo,
	resolveKey,
	skillKey,
	stepAmongSiblings,
	syllabusIdFromHash,
	topicLabel,
	topicsOf,
	validateTopic,
	workspacePath,
	outlineDropPosition,
	outlineDropDestination,
	visibleChildren,
	displayCounts,
} from '../../assets/src/features/curriculum/workspace.mjs';
import * as api from '../../assets/src/features/curriculum/api.mjs';

// Names are made up for the tests: they illustrate shapes, not real syllabus definitions.
const skill = ( id, name, code = '' ) => ( {
	term_id: id,
	name,
	code,
	description: '',
} );
const group = ( id, item, name, code, skills = [] ) => ( {
	id,
	item_id: item,
	uuid: `uuid-${ id }`,
	name,
	code,
	description: '',
	chapter_id: 100 + id,
	skills,
} );
// The syllabus (depth 0) holds one root-level chapter and two topics; "1 Number" holds a sub-topic.
const outline = {
	syllabus: { id: 1, name: 'Mathematics' },
	contents: [
		{
			id: 1,
			parent_id: 0,
			depth: 0,
			name: 'Mathematics',
			code: '',
			groups: [
				group( 9, 1, 'Warm-up', '', [ skill( 90, 'Count to ten' ) ] ),
			],
		},
		{
			id: 2,
			parent_id: 1,
			depth: 1,
			name: '1 Number',
			code: '1',
			groups: [
				group( 10, 2, 'Types of number', 'C1.1', [
					skill( 100, 'Prime numbers', 'C1.1.1' ),
					skill( 101, 'Square numbers', 'C1.1.2' ),
				] ),
				group( 11, 2, 'Sets', 'C1.2', [
					skill( 102, 'Set notation', 'C1.2.1' ),
				] ),
			],
		},
		{
			id: 3,
			parent_id: 2,
			depth: 2,
			name: 'Extra',
			code: '',
			groups: [ group( 12, 3, 'Powers', 'C1.3', [] ) ],
		},
		{
			id: 4,
			parent_id: 1,
			depth: 1,
			name: '2 Algebra',
			code: '2',
			groups: [],
		},
	],
	totals: { contents: 3, groups: 4, skills: 4 },
};

test( 'the syllabus in view comes from the hash route', () => {
	assert.equal(
		syllabusIdFromHash( '#/content-hub/curriculum/syllabus/42' ),
		42
	);
	assert.equal(
		syllabusIdFromHash( '#/content-hub/curriculum/syllabus/42?x=1' ),
		42
	);
	assert.equal( syllabusIdFromHash( '#/content-hub/curriculum' ), 0 );
	assert.equal( syllabusIdFromHash( '' ), 0 );
	assert.equal( syllabusIdFromHash( undefined ), 0 );
	assert.equal( workspacePath( 7 ), '/content-hub/curriculum/syllabus/7' );
} );

test( 'the outline is nested: chapters first, then sub-topics, and skills inside chapters', () => {
	const tree = buildOutline( outline );
	assert.equal( tree.root.key, contentKey( 1 ) );
	assert.deepEqual(
		tree.root.children.map( ( node ) => node.key ),
		[ groupKey( 9 ), contentKey( 2 ), contentKey( 4 ) ],
		'under the syllabus: its chapter, then its topics'
	);
	const number = tree.index.get( contentKey( 2 ) );
	assert.deepEqual(
		number.children.map( ( node ) => node.key ),
		[ groupKey( 10 ), groupKey( 11 ), contentKey( 3 ) ]
	);
	assert.deepEqual(
		tree.index.get( groupKey( 10 ) ).children.map( ( node ) => node.key ),
		[ skillKey( 10, 100 ), skillKey( 10, 101 ) ]
	);
	assert.equal(
		tree.index.get( skillKey( 10, 100 ) ).parentKey,
		groupKey( 10 )
	);
	assert.equal(
		tree.index.get( contentKey( 3 ) ).parentKey,
		contentKey( 2 )
	);
	assert.equal( tree.index.get( skillKey( 10, 100 ) ).groupId, 10 );
} );

test( 'an empty or missing outline has no root', () => {
	assert.equal( buildOutline( null ).root, null );
	assert.equal( buildOutline( { contents: [] } ).root, null );
	assert.equal(
		buildOutline( {
			contents: [
				{ id: 1, parent_id: 0, depth: 0, name: 'S', groups: [] },
			],
		} ).root.children.length,
		0
	);
} );

test( 'a topic whose parent is missing hangs from the syllabus instead of vanishing', () => {
	const odd = {
		contents: [
			{ id: 1, parent_id: 0, depth: 0, name: 'S', groups: [] },
			{ id: 5, parent_id: 99, depth: 1, name: 'Lost', groups: [] },
		],
	};
	assert.deepEqual(
		buildOutline( odd ).root.children.map( ( node ) => node.key ),
		[ contentKey( 5 ) ]
	);
} );

test( 'paths, ancestors and containers', () => {
	const tree = buildOutline( outline );
	assert.deepEqual(
		pathTo( tree.index, skillKey( 10, 101 ) ).map( ( node ) => node.key ),
		[
			contentKey( 1 ),
			contentKey( 2 ),
			groupKey( 10 ),
			skillKey( 10, 101 ),
		]
	);
	assert.deepEqual( ancestorKeys( tree.index, groupKey( 12 ) ), [
		contentKey( 3 ),
		contentKey( 2 ),
		contentKey( 1 ),
	] );
	assert.deepEqual( pathTo( tree.index, 'nope' ), [] );
	assert.equal(
		containerOf( tree, skillKey( 10, 100 ) ).key,
		contentKey( 2 ),
		'a skill is in the topic of its chapter'
	);
	assert.equal( containerOf( tree, groupKey( 9 ) ).key, contentKey( 1 ) );
	assert.equal( containerOf( tree, contentKey( 3 ) ).key, contentKey( 3 ) );
	assert.equal(
		containerOf( tree, 'gone' ).key,
		contentKey( 1 ),
		'an unknown key falls back to the syllabus'
	);
} );

test( 'counts cover everything underneath a node', () => {
	const tree = buildOutline( outline );
	assert.deepEqual( nodeCounts( tree.root ), {
		topics: 3,
		chapters: 4,
		skills: 4,
	} );
	assert.deepEqual( nodeCounts( tree.index.get( contentKey( 2 ) ) ), {
		topics: 1,
		chapters: 3,
		skills: 3,
	} );
	assert.deepEqual( nodeCounts( tree.index.get( groupKey( 11 ) ) ), {
		topics: 0,
		chapters: 0,
		skills: 1,
	} );
	assert.deepEqual( nodeCounts( null ), {
		topics: 0,
		chapters: 0,
		skills: 0,
	} );
	assert.equal( chaptersOf( tree.root ).length, 1 );
	assert.equal( topicsOf( tree.root ).length, 2 );
} );

test( 'selection starts on the syllabus home and survives deletions', () => {
	const tree = buildOutline( outline );
	assert.equal( defaultKey( tree ), contentKey( 1 ) );
	const bare = buildOutline( {
		contents: [
			{ id: 1, parent_id: 0, depth: 0, name: 'S', groups: [] },
			{
				id: 2,
				parent_id: 1,
				depth: 1,
				name: 'T',
				groups: [ group( 5, 2, 'G', '' ) ],
			},
		],
	} );
	assert.equal(
		defaultKey( bare ),
		contentKey( 1 ),
		'nested chapters do not replace the syllabus home'
	);
	assert.equal(
		defaultKey(
			buildOutline( {
				contents: [
					{ id: 1, parent_id: 0, depth: 0, name: 'S', groups: [] },
				],
			} )
		),
		contentKey( 1 )
	);
	assert.equal( defaultKey( { root: null, index: new Map() } ), '' );
	assert.equal( resolveKey( tree, groupKey( 10 ) ), groupKey( 10 ) );
	assert.equal(
		resolveKey( tree, 'g:999', contentKey( 2 ) ),
		contentKey( 2 ),
		'a deleted node falls back to where it was'
	);
	assert.equal(
		resolveKey( tree, 'g:999', 'also gone' ),
		contentKey( 1 ),
		'then to the syllabus'
	);
	assert.deepEqual(
		[ ...openFor( tree, skillKey( 10, 100 ) ) ].sort(),
		[ contentKey( 1 ), contentKey( 2 ), groupKey( 10 ) ].sort()
	);
} );

test( 'moving a node one step stays among siblings of its own kind', () => {
	const tree = buildOutline( outline );
	const sets = tree.index.get( groupKey( 11 ) );
	assert.equal( stepAmongSiblings( tree, sets, -1 ), 0 );
	assert.equal(
		stepAmongSiblings( tree, sets, 1 ),
		null,
		'the last chapter cannot move down, even with a topic after it'
	);
	assert.equal(
		stepAmongSiblings( tree, tree.index.get( groupKey( 10 ) ), -1 ),
		null
	);
	const prime = tree.index.get( skillKey( 10, 100 ) );
	assert.equal( stepAmongSiblings( tree, prime, 1 ), 1 );
	assert.equal( stepAmongSiblings( tree, prime, -1 ), null );
	assert.equal(
		stepAmongSiblings( tree, tree.index.get( contentKey( 4 ) ), -1 ),
		0,
		'topics are ordered among topics'
	);
} );

test( 'labels and edit addresses', () => {
	assert.equal( topicLabel( { code: '1', name: 'Number' } ), '1 · Number' );
	assert.equal( topicLabel( { code: '', name: 'Number' } ), 'Number' );
	assert.equal( editPath( 'lesson', 5 ), '/lesson-edit/5' );
	assert.equal( editPath( 'quiz', 6 ), '/quiz-edit/6' );
	assert.equal( editPath( 'assignment', 7 ), '/assignment-edit/7' );
	assert.equal( editPath( 'bank', 8 ), '' );
} );

test( 'topic numbering follows outline order at every depth without changing stored names or codes', () => {
	const before = JSON.stringify( outline );
	const tree = buildOutline( outline );
	assert.equal(
		topicLabel( tree.index.get( contentKey( 2 ) ).content ),
		'1 · Number'
	);
	assert.equal(
		topicLabel( tree.index.get( contentKey( 3 ) ).content ),
		'1.1 · Extra'
	);
	assert.equal(
		topicLabel( tree.index.get( contentKey( 4 ) ).content ),
		'2 · Algebra'
	);
	const reordered = buildOutline( {
		...outline,
		contents: [
			outline.contents[ 0 ],
			outline.contents[ 3 ],
			outline.contents[ 1 ],
			outline.contents[ 2 ],
		],
	} );
	assert.equal(
		topicLabel( reordered.index.get( contentKey( 2 ) ).content ),
		'2 · Number'
	);
	assert.equal(
		topicLabel( reordered.index.get( contentKey( 3 ) ).content ),
		'2.1 · Extra'
	);
	assert.equal( JSON.stringify( outline ), before );
} );

test( 'drop positions account for removing the source before insertion in either direction', () => {
	const tree = buildOutline( outline );
	assert.equal(
		outlineDropPosition( tree, contentKey( 2 ), contentKey( 4 ), true ),
		1
	);
	assert.equal(
		outlineDropPosition( tree, contentKey( 4 ), contentKey( 2 ), false ),
		0
	);
	assert.equal(
		outlineDropPosition( tree, contentKey( 2 ), contentKey( 4 ), false ),
		null
	);
	assert.equal(
		outlineDropPosition( tree, groupKey( 10 ), groupKey( 11 ), true ),
		1
	);
	assert.equal(
		outlineDropPosition(
			tree,
			skillKey( 10, 100 ),
			skillKey( 10, 101 ),
			true
		),
		1
	);
} );

test( 'outline drops reject cycles, different kinds, moving the syllabus root, and missing nodes', () => {
	const tree = buildOutline( outline );
	assert.equal(
		outlineDropPosition( tree, contentKey( 2 ), contentKey( 3 ) ),
		null
	);
	assert.equal(
		outlineDropPosition( tree, groupKey( 9 ), groupKey( 10 ) ),
		0
	);
	assert.equal(
		outlineDropPosition( tree, groupKey( 9 ), contentKey( 2 ) ),
		null
	);
	assert.equal(
		outlineDropPosition( tree, contentKey( 1 ), contentKey( 2 ) ),
		null
	);
	assert.equal( outlineDropPosition( tree, 'gone', contentKey( 2 ) ), null );
	assert.equal(
		outlineDropPosition( tree, contentKey( 2 ), contentKey( 2 ) ),
		null
	);
} );

test( 'folders can leave their parent, enter another topic, or move to the syllabus root', () => {
	const tree = buildOutline( outline );
	assert.deepEqual(
		outlineDropDestination( tree, contentKey( 3 ), contentKey( 2 ), true ),
		{ parentKey: contentKey( 1 ), position: 1 }
	);
	assert.deepEqual(
		outlineDropDestination(
			tree,
			contentKey( 3 ),
			contentKey( 1 ),
			'inside'
		),
		{ parentKey: contentKey( 1 ), position: 2 }
	);
	assert.deepEqual(
		outlineDropDestination(
			tree,
			contentKey( 3 ),
			contentKey( 4 ),
			'inside'
		),
		{ parentKey: contentKey( 4 ), position: 0 }
	);
	assert.deepEqual(
		outlineDropDestination(
			tree,
			groupKey( 10 ),
			contentKey( 1 ),
			'inside'
		),
		{ parentKey: contentKey( 1 ), position: 1 }
	);
	assert.deepEqual(
		outlineDropDestination( tree, groupKey( 10 ), groupKey( 12 ), true ),
		{ parentKey: contentKey( 3 ), position: 1 }
	);
	assert.equal(
		outlineDropDestination(
			tree,
			contentKey( 2 ),
			contentKey( 3 ),
			'inside'
		),
		null
	);
	assert.equal(
		outlineDropDestination(
			tree,
			contentKey( 3 ),
			groupKey( 10 ),
			'inside'
		),
		null
	);
	assert.equal(
		outlineDropDestination(
			tree,
			skillKey( 10, 100 ),
			contentKey( 1 ),
			'inside'
		),
		null
	);
	assert.equal(
		outlineDropDestination(
			tree,
			skillKey( 10, 100 ),
			skillKey( 9, 90 ),
			true
		),
		null
	);
	assert.equal(
		outlineDropDestination(
			tree,
			contentKey( 4 ),
			contentKey( 1 ),
			'inside'
		),
		null
	);
} );

test( 'all syllabuses expose skill groups while preserving placements and cross-group moves', () => {
	const tree = buildOutline( {
		contents: [
			{ id: 1, name: 'S', depth: 0, groups: [] },
			{
				id: 2,
				parent_id: 1,
				name: 'T',
				depth: 1,
				groups: [
					group( 5, 2, 'Core', '', [ skill( 50, 'A' ) ] ),
					group( 6, 2, 'Extended', '', [ skill( 60, 'B' ) ] ),
					group( 7, 2, 'Applications', '', [ skill( 70, 'C' ) ] ),
				],
			},
		],
	} );
	const children = visibleChildren( tree.index.get( contentKey( 2 ) ), {
		categories: [ 'Core', 'Extended' ],
	} );
	assert.deepEqual(
		children.map( ( child ) => child.kind ),
		[ 'group', 'group', 'group' ]
	);
	assert.deepEqual( displayCounts( tree.root ), {
		topics: 1,
		chapters: 3,
		skills: 3,
	} );
	assert.equal(
		tree.index.get( skillKey( 5, 50 ) ).groupId,
		5,
		'stored placement stays intact'
	);
	assert.equal(
		outlineDropPosition( tree, skillKey( 5, 50 ), skillKey( 6, 60 ), true ),
		1
	);
} );

test( 'topic fields are checked like the server does', () => {
	assert.deepEqual(
		validateTopic( {
			name: 'Number',
			code: '1',
			version: '2028',
			description: 'x',
		} ),
		{}
	);
	assert.deepEqual( validateTopic( { name: '  ' } ), { name: 'required' } );
	assert.equal(
		validateTopic( { name: 'x'.repeat( 191 ) } ).name,
		'too-long'
	);
	assert.equal(
		validateTopic( { name: 'x', code: 'c'.repeat( 61 ) } ).code,
		'too-long'
	);
	assert.equal(
		validateTopic( { name: 'x', version: 'v'.repeat( 61 ) } ).version,
		'too-long'
	);
	assert.equal(
		validateTopic( { name: 'x', description: 'd'.repeat( 2001 ) } )
			.description,
		'too-long'
	);
	assert.deepEqual(
		validateTopic( { name: 'x', code: '' } ),
		{},
		'an empty code is fine'
	);
} );

test( 'the course a syllabus is has a state in words', () => {
	assert.equal( courseState( null ), 'none' );
	assert.equal(
		courseState( { status: 'draft', published: false } ),
		'draft'
	);
	assert.equal(
		courseState( { status: 'draft', published: true } ),
		'published-draft'
	);
	assert.equal(
		courseState( { status: 'publish', published: true } ),
		'live'
	);
} );

test( 'API calls for the course and for what a skill owns use the documented routes', async () => {
	const calls = [];
	const fetch = ( args ) => {
		calls.push( args );
		return Promise.resolve( {} );
	};
	await api.ensureCourse( 5, fetch );
	await api.setCourseSkills(
		5,
		[ { term_id: 9, required: true, target: 'mastered' } ],
		fetch
	);
	await api.loadSkill( 9, fetch );
	await api.setSkillLessons( 9, [ 3, 4 ], fetch );
	await api.lessonTargets( { search: 'frac' }, fetch );
	await api.lessonTargets( { include: [ 3, 4 ] }, fetch );
	await api.skillQuestions( 9, 5, fetch );
	assert.deepEqual( calls, [
		{
			path: '/ohmylms/v1/curriculum/items/5/syllabus/course',
			method: 'POST',
		},
		{
			path: '/ohmylms/v1/curriculum/items/5/syllabus/course/skills',
			method: 'PUT',
			data: {
				skills: [ { term_id: 9, required: true, target: 'mastered' } ],
			},
		},
		{ path: '/ohmylms/v1/skills/9' },
		{
			path: '/ohmylms/v1/skills/9/lessons',
			method: 'PUT',
			data: { lesson_ids: [ 3, 4 ] },
		},
		{ path: '/ohmylms/v1/skills/link-targets?type=lesson&search=frac' },
		{ path: '/ohmylms/v1/skills/link-targets?type=lesson&include=3%2C4' },
		{ path: '/ohmylms/v1/question-bank?skill=9&per_page=5' },
	] );
} );
