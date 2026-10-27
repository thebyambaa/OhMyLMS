import test from 'node:test';
import assert from 'node:assert/strict';
import {
	prepareCoursePayload,
	orderedChapters,
	completedCourseSteps,
	mergeSavedCourse,
} from '../../assets/src/features/courses/model.mjs';
import { saveCourseWithChapters } from '../../assets/src/features/courses/api.mjs';
import { courseEditPath } from '../../assets/src/features/courses/model.mjs';
test( 'course editing uses the syllabus workspace only for a linked syllabus', () => {
	assert.equal(
		courseEditPath( { id: 5, syllabus_id: 12 } ),
		'/content-hub/curriculum/syllabus/12'
	);
	assert.equal(
		courseEditPath( { id: 5, syllabus_id: '12' } ),
		'/content-hub/curriculum/syllabus/12'
	);
	for ( const syllabus_id of [ undefined, null, 0, -1, 'invalid', 1.5 ] )
		assert.equal(
			courseEditPath( { id: 5, syllabus_id } ),
			'/course-edit/5'
		);
} );
test( 'course payload preserves extension fields and does not mutate publication state', () => {
	const course = Object.freeze( {
		id: 5,
		status: 'draft',
		description: '',
		settings: { extension: 'keep' },
	} );
	const payload = prepareCoursePayload( course, {
		status: 'future',
		date: { date: '2030-01-02 10:00:00' },
	} );
	assert.equal( course.status, 'draft' );
	assert.equal( payload.description, '' );
	assert.equal( payload.settings.extension, 'keep' );
	assert.equal( payload.status, 'future' );
	const chapters = {
		allIds: [ 2, 1 ],
		byId: { 1: { id: 1, content: [ 7 ] }, 2: { id: 2, content: [ 8 ] } },
	};
	const ordered = orderedChapters( chapters );
	ordered[ 0 ].content.push( 9 );
	assert.deepEqual(
		ordered.map( ( chapter ) => chapter.id ),
		[ 2, 1 ]
	);
	assert.deepEqual( chapters.byId[ 2 ].content, [ 8 ] );
	assert.deepEqual(
		completedCourseSteps(
			{
				...course,
				name: 'Title',
				description: 'Body',
				image_src: 'image',
			},
			chapters,
			true
		),
		[ 0, 1 ]
	);
} );
test( 'course save awaits chapters and surfaces a partial save without declaring success', async () => {
	let finish;
	let settled = false;
	const waiting = new Promise( ( resolve ) => {
		finish = resolve;
	} );
	const saving = saveCourseWithChapters(
		5,
		{ name: 'New' },
		[],
		async ( options ) =>
			options.path.endsWith( '/chapters' )
				? waiting
				: { id: 5, name: 'New' }
	).then( ( value ) => {
		settled = true;
		return value;
	} );
	await Promise.resolve();
	await Promise.resolve();
	assert.equal( settled, false );
	finish( { status: 'success' } );
	assert.equal( ( await saving ).id, 5 );
	await assert.rejects(
		saveCourseWithChapters( 5, { name: 'New' }, [], async ( options ) => {
			if ( options.path.endsWith( '/chapters' ) )
				throw Error( 'Network failure' );
			return { id: 5 };
		} ),
		( error ) =>
			error.partialCourse.id === 5 &&
			error.message.includes( 'chapters could not be saved' )
	);
} );
test( 'a failed course request does not write chapter data', async () => {
	const calls = [];
	await assert.rejects(
		saveCourseWithChapters( 5, {}, [], async ( options ) => {
			calls.push( options.path );
			throw Error( 'Forbidden' );
		} ),
		/Forbidden/
	);
	assert.deepEqual( calls, [ '/ohmylms/v1/courses/5' ] );
} );
test( 'save response keeps newer editor changes while accepting server publication state', () => {
	const before = { name: 'First name', status: 'draft', description: 'Text' };
	const merged = mergeSavedCourse( { ...before, status: 'publish' }, before, {
		...before,
		name: 'Second name',
		description: '',
	} );
	assert.equal( merged.name, 'Second name' );
	assert.equal( merged.description, '' );
	assert.equal( merged.status, 'publish' );
} );
