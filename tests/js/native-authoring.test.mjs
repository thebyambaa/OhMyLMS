import test from 'node:test';
import assert from 'node:assert/strict';
import { createRegistry } from '../../assets/src/extensions/registry.mjs';
import { nativeQuestionTypes } from '../../assets/src/features/learning/nativeAuthoring.mjs';
import { choiceReadyToSave } from '../../assets/src/features/learning/choiceAutosave.mjs';
import {
	rememberQuestion,
	stepQuestionHistory,
} from '../../assets/src/features/learning/questionHistory.mjs';

test( 'native types respect the installed manifest and support declarative and registered extensions', () => {
	const previous = globalThis.window;
	const registry = createRegistry();
	registry.registerQuestionEditor( 'custom-native', {
		label: 'Custom native',
		render: () => null,
	} );
	registry.registerQuestionEditor( 'uninstalled', {
		label: 'Unavailable',
		render: () => null,
	} );
	globalThis.window = {
		ohmylms: { extensions: registry },
		ohmylmsNativeLesson: {
			questionTypes: {
				'single-choice': { label: 'One answer' },
				'custom-options': {
					label: 'Custom options',
					editor: { format: 'ohmylms-options' },
				},
				'custom-native': { label: 'Custom native' },
				'no-editor': { label: 'No editor available' },
			},
		},
	};
	try {
		assert.deepEqual(
			nativeQuestionTypes( [
				[ 'single-choice', 'Choice' ],
				[ 'removed', 'Removed' ],
			] ),
			[
				{ value: 'single-choice', label: 'One answer' },
				{ value: 'custom-options', label: 'Custom options' },
				{ value: 'custom-native', label: 'Custom native' },
			]
		);
		assert.equal(
			choiceReadyToSave( {
				name: 'Custom prompt',
				settings: { type: 'custom-native' },
				questions: [],
			} ),
			true
		);
	} finally {
		globalThis.window = previous;
	}
} );

test( 'newly exposed native formats use their own readiness checks instead of requiring choice options', () => {
	assert.equal(
		choiceReadyToSave( {
			name: 'Information',
			settings: { type: 'statement' },
			questions: [],
		} ),
		true
	);
	assert.equal(
		choiceReadyToSave( {
			name: 'Record an explanation',
			settings: { type: 'audio-response', max_seconds: 60 },
			questions: [],
		} ),
		true
	);
	assert.equal(
		choiceReadyToSave( {
			name: 'Watch and answer',
			settings: { type: 'interactive-video', video_url: '' },
			questions: [],
		} ),
		false
	);
	assert.equal(
		choiceReadyToSave( {
			name: 'Mark words',
			settings: { type: 'hot-text', tokens: [] },
			questions: [],
		} ),
		false
	);
} );

test( 'question undo preserves autosaved identities and recreates deleted options without reusing stale IDs', () => {
	const history = { past: [], future: [] };
	const original = {
		name: 'Before',
		settings: { type: 'single-choice' },
		questions: [
			{ id: 1000, temp: true, answer: 'A' },
			{ id: 1001, temp: true, answer: 'B' },
		],
	};
	rememberQuestion( history, original );
	original.settings.type = 'changed outside history';
	const saved = {
		id: 42,
		name: 'After',
		settings: { type: 'single-choice' },
		questions: [
			{ id: 9, clientKey: 1000, temp: false, answer: 'Changed A' },
		],
	};
	const undone = stepQuestionHistory( history, saved, 'past' );
	assert.equal( undone.name, 'Before' );
	assert.equal( undone.settings.type, 'single-choice' );
	assert.equal( undone.id, 42 );
	assert.equal( undone.questions[ 0 ].id, 9 );
	assert.equal( undone.questions[ 0 ].temp, false );
	assert.equal( undone.questions[ 1 ].temp, true );
	assert.notEqual( undone.questions[ 1 ].id, 1001 );
	const redone = stepQuestionHistory( history, undone, 'future' );
	assert.equal( redone.name, 'After' );
	assert.equal( redone.questions[ 0 ].id, 9 );
	assert.equal( stepQuestionHistory( history, redone, 'future' ), null );
} );
