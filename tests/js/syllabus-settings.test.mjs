import test from 'node:test';
import assert from 'node:assert/strict';
import {
	saveSyllabusSettings,
	publishSyllabus,
} from '../../assets/src/features/curriculum/api.mjs';

test( 'syllabus profile and publication use dedicated routes', async () => {
	const calls = [];
	const fetch = async ( options ) => {
		calls.push( options );
		return { settings: options.data };
	};
	await saveSyllabusSettings(
		5,
		{ grade: 'Grade 9', categories: [ 'Core', 'Advanced' ] },
		fetch
	);
	await publishSyllabus( 5, fetch );
	assert.deepEqual( calls, [
		{
			path: '/ohmylms/v1/curriculum/items/5/syllabus/settings',
			method: 'PUT',
			data: { grade: 'Grade 9', categories: [ 'Core', 'Advanced' ] },
		},
		{
			path: '/ohmylms/v1/curriculum/items/5/syllabus/publish',
			method: 'POST',
		},
	] );
} );

test( 'syllabus publishing exposes the readiness details so authors can fix them', async () => {
	await assert.rejects(
		publishSyllabus( 5, async () => {
			throw {
				message: 'Not ready',
				data: { errors: [ 'Publish the quiz first.' ] },
			};
		} ),
		( error ) => error.message === 'Publish the quiz first.'
	);
} );
