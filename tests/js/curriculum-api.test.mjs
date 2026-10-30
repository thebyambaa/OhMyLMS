import test from 'node:test';
import assert from 'node:assert/strict';
import * as api from '../../assets/src/features/curriculum/api.mjs';

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
