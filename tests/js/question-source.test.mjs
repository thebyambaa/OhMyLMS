import test from 'node:test';
import assert from 'node:assert/strict';
import {
	allowedSources,
	editorDeliveries,
	fromPlayer,
	fromQuestionBlock,
	playerSource,
} from '../../assets/src/features/question-editor/questionSource.mjs';

test( 'a quiz, a lesson and a page offer write, bank and skills', () => {
	assert.deepEqual( allowedSources( { postType: 'ohmylms-quiz' } ), [
		'write',
		'bank',
		'skills',
	] );
	assert.deepEqual( allowedSources( { postType: 'ohmylms-lesson' } ), [
		'write',
		'bank',
		'skills',
	] );
} );

test( 'an exam offers no skills because it does not allow practice', () => {
	assert.deepEqual(
		allowedSources( { postType: 'ohmylms-quiz', deliveries: [ 'quiz' ] } ),
		[ 'write', 'bank' ]
	);
} );

test( 'the bank question editor only writes', () => {
	assert.deepEqual( allowedSources( { postType: 'ohmylms-question' } ), [
		'write',
	] );
} );

test( 'picking from the bank in a quiz opens the picker; elsewhere it becomes a practice set', () => {
	assert.deepEqual(
		fromQuestionBlock( 'bank', { postType: 'ohmylms-quiz' } ),
		{ action: 'pick' }
	);
	const lesson = fromQuestionBlock( 'bank', { postType: 'ohmylms-lesson' } );
	assert.equal( lesson.action, 'player' );
	assert.equal( lesson.attributes.source, 'selected' );
	assert.deepEqual( lesson.attributes.questionIds, [] );
} );

test( 'skills always make a random practice set; writing changes nothing', () => {
	const skills = fromQuestionBlock( 'skills', { postType: 'ohmylms-quiz' } );
	assert.equal( skills.action, 'player' );
	assert.equal( skills.attributes.source, 'random' );
	assert.deepEqual( fromQuestionBlock( 'write' ), { action: 'none' } );
} );

test( 'the player switches between bank and skills, or goes back to a question', () => {
	assert.deepEqual( fromPlayer( 'skills' ), {
		action: 'set',
		attributes: { source: 'random' },
	} );
	assert.deepEqual( fromPlayer( 'bank' ), {
		action: 'set',
		attributes: { source: 'selected' },
	} );
	assert.deepEqual( fromPlayer( 'write' ), { action: 'question' } );
	assert.equal( playerSource( { source: 'random' } ), 'skills' );
	assert.equal( playerSource( { source: 'selected' } ), 'bank' );
} );

test( 'the editor reads the deliveries its kind allows, or all of them', () => {
	globalThis.window = {
		ohmylmsNativeLesson: { profile: { deliveries: [ 'quiz' ] } },
	};
	assert.deepEqual( editorDeliveries(), [ 'quiz' ] );
	globalThis.window = {};
	assert.deepEqual( editorDeliveries(), [ 'quiz', 'inline', 'practice' ] );
} );
