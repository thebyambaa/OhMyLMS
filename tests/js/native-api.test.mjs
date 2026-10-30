import test from 'node:test';
import assert from 'node:assert/strict';
import { queryString } from '../../assets/src/features/rest.mjs';
import {
	previewQuestion,
	publishExam,
	saveExamInstructions,
	searchChapterContents,
} from '../../assets/src/features/learning/api.mjs';

test( 'query strings leave out empty values and encode the rest', () => {
	assert.equal( queryString(), '' );
	assert.equal( queryString( { a: '', b: null, c: undefined } ), '' );
	assert.equal(
		queryString( { search: 'a b', page: 2, skill: 0, flag: false } ),
		'?search=a+b&page=2&skill=0&flag=false'
	);
} );

test( 'native editor requests keep their routes and payloads', async () => {
	const calls = [];
	const fetch = ( options ) => {
		calls.push( options );
		return Promise.resolve( {} );
	};
	const signal = new AbortController().signal;
	await saveExamInstructions( 7, 'Read carefully.', fetch );
	await publishExam( 7, true, fetch );
	await searchChapterContents( 3, 'a&b', fetch );
	await previewQuestion( { question: { type: 'x' }, seed: 5 }, signal, fetch );
	assert.deepEqual( calls, [
		{
			path: '/ohmylms/v1/exams/7/settings',
			method: 'POST',
			data: { instructions: 'Read carefully.' },
		},
		{
			path: '/ohmylms/v1/exams/7/publish',
			method: 'POST',
			data: { confirm_overlap: true },
		},
		{ path: '/ohmylms/v1/chapters/3/search-contents?term=a%26b' },
		{
			path: '/ohmylms/v1/question-preview',
			method: 'POST',
			signal,
			data: { question: { type: 'x' }, seed: 5 },
		},
	] );
} );

test( 'a REST failure reaches the caller unchanged', async () => {
	const failure = { code: 'exam_overlap', data: { warnings: [ 'w' ] } };
	await assert.rejects(
		publishExam( 7, false, () => Promise.reject( failure ) ),
		( error ) => error === failure
	);
} );
