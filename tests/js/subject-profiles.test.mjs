import test from 'node:test';
import assert from 'node:assert/strict';
import {
	domainId,
	formFromJson,
	formFromProfile,
	profileFromForm,
	profileJson,
} from '../../assets/src/features/curriculum/subjectProfiles.mjs';

test( 'domain ids are built from names', () => {
	assert.equal( domainId( 'Functions and graphs' ), 'functions-and-graphs' );
	assert.equal( domainId( 'Électricité' ), 'electricite' );
	assert.equal( domainId( '2D shapes' ), 'd-shapes' );
	assert.equal( domainId( 'Уншлага' ), '' );
	assert.equal( domainId( 'a'.repeat( 60 ) ).length, 40 );
} );

test( 'editor values round-trip a profile', () => {
	const profile = {
		key: 'english-language',
		label: 'English language',
		language: 'en',
		domains: [ { id: 'reading', label: 'Reading' } ],
		levels: [ 'A1', 'A2' ],
		ai: { role: 'analyst', context: '', level_hints: 'CEFR' },
		builtin: false,
	};
	const form = formFromProfile( profile );
	assert.equal( form.levels, 'A1\nA2' );
	assert.deepEqual( profileFromForm( form ), {
		key: 'english-language',
		label: 'English language',
		language: 'en',
		domains: [ { id: 'reading', label: 'Reading' } ],
		levels: [ 'A1', 'A2' ],
		ai: { role: 'analyst', context: '', level_hints: 'CEFR' },
	} );
} );

test( 'a missing profile starts an empty form', () => {
	assert.equal( formFromProfile( null ).key, '' );
	assert.equal( formFromProfile().levels, '' );
} );

test( 'saving trims text, fills blank ids and drops empty rows', () => {
	const form = {
		...formFromProfile(),
		label: ' Physics ',
		domains: [
			{ id: '', label: 'Waves and optics' },
			{ id: '', label: ' ' },
		],
		levels: 'Grade 9\n\n Grade 10 \r\n',
	};
	const saved = profileFromForm( form );
	assert.equal( 'key' in saved, false );
	assert.equal( saved.label, 'Physics' );
	assert.deepEqual( saved.domains, [
		{ id: 'waves-and-optics', label: 'Waves and optics' },
	] );
	assert.deepEqual( saved.levels, [ 'Grade 9', 'Grade 10' ] );
} );

test( 'profiles export and import as JSON', () => {
	const form = formFromProfile( {
		key: 'chemistry',
		label: 'Chemistry',
		domains: [ { id: 'organic', label: 'Organic' } ],
		levels: [ 'Grade 10', 'Grade 11' ],
		ai: { role: 'chemistry analyst' },
	} );
	const json = profileJson( form );
	assert.equal( JSON.parse( json ).key, undefined );
	const fresh = formFromJson( json );
	assert.equal( fresh.key, '' );
	assert.equal( fresh.label, 'Chemistry' );
	const imported = formFromJson( json, {
		key: 'science',
		label: 'Science',
	} );
	assert.equal( imported.key, 'science' );
	assert.equal( imported.label, 'Science' );
	assert.equal( imported.levels, 'Grade 10\nGrade 11' );
	assert.equal( imported.role, 'chemistry analyst' );
	assert.equal(
		formFromJson( '{"label":"X","levels":[{"label":"L1"}]}' ).levels,
		'L1'
	);
	assert.equal( formFromJson( 'not json' ), null );
	assert.equal( formFromJson( '{"label":"X"}' ), null );
	assert.equal(
		formFromJson( '{"label":"X","levels":[],"domains":"a"}' ),
		null
	);
} );
