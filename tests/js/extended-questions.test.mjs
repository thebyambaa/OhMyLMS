import test from 'node:test';
import assert from 'node:assert/strict';
import {
	extendedDefaults,
	extendedPublicView,
} from '../../assets/src/features/question-editor/extendedModel.mjs';
import { questionTypePatch } from '../../assets/src/features/question-editor/questionBlocks.mjs';

test( 'learner configuration never contains extended grading keys or teacher notes', () => {
	for ( const type of [
		'graphing',
		'hot-text',
		'match-table-grid',
		'hotspot',
		'draw',
		'audio-response',
	] ) {
		const settings = {
			...extendedDefaults( type ),
			rubric: 'Teacher only',
			answer: 'Secret',
		};
		const view = extendedPublicView( type, settings );
		for ( const key of [
			'rubric',
			'answer',
			'points',
			'correct',
			'key',
			'zones',
		] ) {
			assert.equal( key in view, false, `${ type }: ${ key }` );
		}
	}
	const labels = extendedPublicView(
		'labeling',
		extendedDefaults( 'labeling' )
	);
	assert.deepEqual( labels.choices, [ 'A', 'B' ] );
	assert.ok( labels.targets.every( ( target ) => ! ( 'answer' in target ) ) );
	const video = extendedPublicView(
		'interactive-video',
		extendedDefaults( 'interactive-video' )
	);
	assert.ok(
		video.checkpoints.every(
			( checkpoint ) => ! ( 'answer' in checkpoint )
		)
	);
	const passage = extendedPublicView(
		'passage',
		extendedDefaults( 'passage' )
	);
	assert.ok(
		passage.parts.every(
			( part ) => ! ( 'answer' in part ) && ! ( 'rubric' in part )
		)
	);
} );

test( 'unscored formats reset marks and extended formats do not create legacy answer rows', () => {
	for ( const type of [ 'poll', 'word-cloud' ] ) {
		const patch = questionTypePatch(
			{ settings: { score: { value: 5 } } },
			type
		);
		assert.equal( patch.settings.score.value, 0 );
		assert.deepEqual( patch.questions, [] );
	}
} );

test( 'a graphing question is only saveable when the server will accept it', async () => {
	const { graphIsValid, extendedIssues } = await import(
		'../../assets/src/features/question-editor/extendedModel.mjs'
	);
	const ok = { mode: 'points', min: -5, max: 5, points: [ { x: 2, y: 3 } ], tolerance: 0.25 };
	assert.equal( graphIsValid( ok ), true );
	assert.deepEqual( extendedIssues( 'graphing', ok ), [] );
	for ( const bad of [
		{ ...ok, points: [ { x: 9, y: 3 } ] },
		{ ...ok, min: 5, max: 5 },
		{ ...ok, min: -101 },
		{ ...ok, max: 101 },
		{ ...ok, tolerance: 2 },
		{ ...ok, tolerance: -0.1 },
		{ ...ok, points: [] },
		{ ...ok, points: [ { x: '', y: 1 } ] },
		{ ...ok, points: Array.from( { length: 51 }, () => ( { x: 1, y: 1 } ) ) },
		{ ...ok, mode: 'line', points: [ { x: 1, y: 1 } ] },
		{ ...ok, mode: 'line', points: [ { x: 1, y: 1 }, { x: 1, y: 1 } ] },
	] ) {
		assert.equal( graphIsValid( bad ), false, JSON.stringify( bad ) );
		assert.deepEqual( extendedIssues( 'graphing', bad ), [ 'graph' ] );
	}
	assert.equal(
		graphIsValid( { ...ok, mode: 'line', points: [ { x: 0, y: 1 }, { x: 1, y: 2 } ] } ),
		true
	);
} );
