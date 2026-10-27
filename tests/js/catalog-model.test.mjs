import test from 'node:test';
import assert from 'node:assert/strict';
import {
	addAttachments,
	addSkills,
	attachmentsFor,
	isUuid,
	mergeSaved,
	moveSkill,
	placeSkill,
	removeAttachment,
	removeSkill,
	skillsIn,
	toSavePayload,
	updateAttachment,
	updateSkill,
} from '../../assets/src/features/content-hub/catalogModel.mjs';

const pick = ( id, name, code = '' ) => ( { id, name, code, questions: 3 } );
const base = () =>
	addSkills(
		addSkills(
			[],
			[
				pick( 1, 'Count to 100', 'A.1' ),
				pick( 2, 'Skip count', 'A.2' ),
			],
			10,
			'skill-based'
		),
		[ pick( 3, 'Add within 20', 'B.1' ) ],
		20,
		'skill-based'
	);

test( 'Skills are placed in a chapter in the order added and default to required proficient', () => {
	const skills = base();
	assert.deepEqual(
		skillsIn( skills, 10 ).map( ( skill ) => skill.term_id ),
		[ 1, 2 ]
	);
	assert.deepEqual(
		skillsIn( skills, 20 ).map( ( skill ) => skill.term_id ),
		[ 3 ]
	);
	assert.deepEqual( skillsIn( skills, 0 ), [] );
	assert.ok(
		skills.every(
			( skill ) =>
				skill.target === 'proficient' && skill.required === true
		)
	);
	assert.equal( skills[ 0 ].code, 'A.1' );
} );

test( 'A traditional course adds skills as optional, and a skill is added only once', () => {
	const skills = addSkills( [], [ pick( 1, 'One' ) ], 0, 'traditional' );
	assert.equal( skills[ 0 ].required, false );
	assert.equal(
		addSkills(
			skills,
			[ pick( 1, 'One again' ), pick( 1, 'dup' ) ],
			5,
			'traditional'
		).length,
		1
	);
	assert.equal(
		addSkills( [], [ pick( 7, 'A' ), pick( 7, 'B' ) ], 0, 'skill-based' )
			.length,
		1
	);
} );

test( 'Skills move within their chapter and between chapters without touching the input', () => {
	const skills = base();
	const frozen = JSON.stringify( skills );
	assert.deepEqual(
		skillsIn( moveSkill( skills, 2, -1 ), 10 ).map( ( s ) => s.term_id ),
		[ 2, 1 ]
	);
	assert.equal(
		moveSkill( skills, 1, -1 ),
		skills,
		'the first skill cannot move up'
	);
	assert.equal(
		moveSkill( skills, 3, 1 ),
		skills,
		'the last skill cannot move down'
	);
	assert.equal(
		moveSkill( skills, 99, 1 ),
		skills,
		'an unknown skill is ignored'
	);
	const moved = placeSkill( skills, 1, 20 );
	assert.deepEqual(
		skillsIn( moved, 20 ).map( ( s ) => s.term_id ),
		[ 3, 1 ],
		'it lands at the end of the other chapter'
	);
	assert.deepEqual(
		skillsIn( moved, 10 ).map( ( s ) => s.term_id ),
		[ 2 ]
	);
	assert.equal(
		placeSkill( skills, 1, 10 ),
		skills,
		'placing in the same chapter changes nothing'
	);
	assert.equal( JSON.stringify( skills ), frozen );
} );

test( 'Skill settings update in place', () => {
	const skills = updateSkill( base(), 2, {
		target: 'mastered',
		required: false,
	} );
	assert.deepEqual(
		[ skills[ 1 ].target, skills[ 1 ].required ],
		[ 'mastered', false ]
	);
	assert.equal( skills[ 0 ].target, 'proficient' );
} );

test( 'Content attaches to a chapter or to chosen skills, once per course', () => {
	let attachments = addAttachments(
		[],
		[ { id: 501, type: 'lesson', title: 'Counting', status: 'publish' } ],
		{ chapterId: 10, skillIds: [] },
		{ required: false }
	);
	attachments = addAttachments(
		attachments,
		[ { id: 601, type: 'quiz', title: 'Checkpoint', status: 'draft' } ],
		{ chapterId: 10, skillIds: [ 1, 2, 1 ] },
		{ required: true, passPercent: 70 }
	);
	assert.equal( attachments.length, 2 );
	assert.deepEqual( attachments[ 1 ].skill_ids, [ 1, 2 ] );
	assert.equal( attachments[ 1 ].pass_percent, 70 );
	assert.equal(
		attachments[ 0 ].pass_percent,
		null,
		'only quizzes have a pass percentage'
	);
	assert.equal(
		addAttachments(
			attachments,
			[ { id: 501, type: 'lesson', title: 'Counting' } ],
			{ chapterId: 20, skillIds: [] }
		).length,
		2,
		'the same content is not attached twice'
	);
	assert.deepEqual(
		attachmentsFor( attachments, { chapterId: 10 } ).map(
			( item ) => item.content_id
		),
		[ 501 ],
		'chapter content has no skill scope'
	);
	assert.deepEqual(
		attachmentsFor( attachments, { skillId: 1 } ).map(
			( item ) => item.content_id
		),
		[ 601 ]
	);
	assert.deepEqual(
		attachmentsFor( attachments, { skillId: 2 } ).map(
			( item ) => item.content_id
		),
		[ 601 ],
		'content scoped to several skills shows under each'
	);
	assert.deepEqual( attachmentsFor( attachments, { chapterId: 20 } ), [] );
} );

test( 'Removing a skill drops it from content scopes but keeps the content', () => {
	const attachments = addAttachments(
		[],
		[ { id: 601, type: 'quiz', title: 'Q' } ],
		{ chapterId: 10, skillIds: [ 1, 2 ] },
		{ required: true }
	);
	const result = removeSkill( base(), attachments, 1 );
	assert.deepEqual(
		result.skills.map( ( s ) => s.term_id ),
		[ 2, 3 ]
	);
	assert.deepEqual( result.attachments[ 0 ].skill_ids, [ 2 ] );
	const last = removeSkill( result.skills, result.attachments, 2 );
	assert.deepEqual( last.attachments[ 0 ].skill_ids, [] );
	assert.equal( last.attachments.length, 1 );
} );

test( 'Attachments update and remove by identifier', () => {
	const attachments = addAttachments(
		[],
		[
			{ id: 1, type: 'lesson', title: 'L' },
			{ id: 2, type: 'lesson', title: 'M' },
		],
		{ chapterId: 0 },
		{}
	);
	const [ first, second ] = attachments;
	assert.equal(
		updateAttachment( attachments, first.id, { required: true } )[ 0 ]
			.required,
		true
	);
	assert.deepEqual(
		removeAttachment( attachments, first.id ).map( ( item ) => item.id ),
		[ second.id ]
	);
} );

test( 'The save payload sends skills in order and omits identifiers the server has not issued', () => {
	const uuid = '11111111-1111-4111-8111-111111111111';
	const catalog = {
		mode: 'skill-based',
		skills: updateSkill( base(), 3, { chapter_id: 0 } ),
		attachments: [
			{
				id: uuid,
				type: 'lesson',
				content_id: 5,
				chapter_id: 10,
				skill_ids: [],
				required: false,
				pass_percent: null,
			},
			{
				id: 'tmp-9',
				type: 'quiz',
				content_id: 6,
				chapter_id: 0,
				skill_ids: [ 1 ],
				required: true,
				pass_percent: 65,
			},
			{
				id: 'tmp-10',
				type: 'quiz',
				content_id: 7,
				chapter_id: 0,
				skill_ids: [],
				required: false,
				pass_percent: null,
			},
		],
	};
	const payload = toSavePayload( catalog );
	assert.equal( payload.mode, 'skill-based' );
	assert.deepEqual(
		payload.outcomes.map( ( o ) => [ o.term_id, o.chapter_id ] ),
		[
			[ 1, 10 ],
			[ 2, 10 ],
			[ 3, 0 ],
		]
	);
	assert.deepEqual( Object.keys( payload.outcomes[ 0 ] ).sort(), [
		'chapter_id',
		'required',
		'target',
		'term_id',
	] );
	assert.equal( payload.attachments[ 0 ].id, uuid );
	assert.ok(
		! ( 'id' in payload.attachments[ 1 ] ) &&
			! ( 'id' in payload.attachments[ 2 ] )
	);
	assert.ok(
		! ( 'pass_percent' in payload.attachments[ 0 ] ) &&
			payload.attachments[ 1 ].pass_percent === 65
	);
	assert.equal(
		payload.attachments[ 2 ].pass_percent,
		80,
		'a quiz without a percentage gets the default'
	);
	assert.ok( isUuid( uuid ) && ! isUuid( 'tmp-1' ) && ! isUuid( undefined ) );
} );

test( 'After saving, new content takes its server identifier and local edits are kept', () => {
	const uuid = '22222222-2222-4222-8222-222222222222';
	const current = {
		mode: 'blended',
		skills: [ { term_id: 1, name: 'Local edit' } ],
		chapters: [],
		course: { title: 'Old' },
		published_version: 1,
		unpublished_changes: false,
		attachments: [
			{ id: 'tmp-4', content_id: 9, title: 'Typed locally' },
			{ id: uuid, content_id: 10, title: 'Existing' },
		],
	};
	const saved = {
		course: { title: 'Grade 2' },
		published_version: 2,
		unpublished_changes: true,
		attachments: [
			{ id: '33333333-3333-4333-8333-333333333333', content_id: 9 },
			{ id: uuid, content_id: 10 },
		],
	};
	const merged = mergeSaved( current, saved );
	assert.equal(
		merged.attachments[ 0 ].id,
		'33333333-3333-4333-8333-333333333333'
	);
	assert.equal( merged.attachments[ 0 ].title, 'Typed locally' );
	assert.equal( merged.attachments[ 1 ].id, uuid );
	assert.equal( merged.skills[ 0 ].name, 'Local edit' );
	assert.equal( merged.mode, 'blended' );
	assert.deepEqual(
		[
			merged.course.title,
			merged.published_version,
			merged.unpublished_changes,
		],
		[ 'Grade 2', 2, true ]
	);
} );

test( 'Lesson group membership survives a save and is cleared when a row changes unit', () => {
	const group = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
	const uuid = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
	const skills = updateSkill( base(), 1, { group_id: group } );
	const attachments = [
		{
			id: uuid,
			type: 'lesson',
			content_id: 7,
			chapter_id: 10,
			group_id: group,
			skill_ids: [],
			required: false,
		},
	];
	const payload = toSavePayload( { mode: 'blended', skills, attachments } );
	assert.equal( payload.outcomes[ 0 ].group_id, group );
	assert.ok( ! ( 'group_id' in payload.outcomes[ 1 ] ) );
	assert.equal( payload.attachments[ 0 ].group_id, group );

	const moved = placeSkill( skills, 1, 20 );
	assert.equal( moved.find( ( s ) => s.term_id === 1 ).group_id, '' );
	const same = updateSkill( skills, 1, { target: 'mastered' } );
	assert.equal( same.find( ( s ) => s.term_id === 1 ).group_id, group );
	const moveItem = updateAttachment( attachments, uuid, { chapter_id: 20 } );
	assert.equal( moveItem[ 0 ].group_id, '' );
	assert.ok(
		! (
			'group_id' in
			toSavePayload( { mode: 'blended', skills, attachments: moveItem } )
				.attachments[ 0 ]
		)
	);
} );
