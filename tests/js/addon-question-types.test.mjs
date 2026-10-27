import test from 'node:test';
import assert from 'node:assert/strict';
import { isSettingsAddonType } from '../../assets/src/features/question-editor/extendedModel.mjs';
import { canLeaveQuestion } from '../../assets/src/features/quiz-editor/model.mjs';

const manifest = {
	'graded-reader': {
		label: 'Graded reader',
		editor: { format: 'ohmylms-settings' },
	},
	'options-addon': {
		label: 'Options add-on',
		editor: { format: 'ohmylms-options' },
	},
	passage: { label: 'Passage', editor: { format: 'ohmylms-settings' } },
};
// The choice-question validator must never run for settings-format add-on types.
const choiceValidator = () => ( { isValid: false } );

test( 'settings-format add-on types are recognised from the manifest', () => {
	assert.equal( isSettingsAddonType( 'graded-reader', manifest ), true );
	assert.equal( isSettingsAddonType( 'options-addon', manifest ), false );
	assert.equal( isSettingsAddonType( 'single-choice', manifest ), false );
	assert.equal( isSettingsAddonType( 'graded-reader', undefined ), false );
} );

test( 'built-in extended types are not treated as add-on types', () => {
	assert.equal( isSettingsAddonType( 'passage', manifest ), false );
} );

test( 'a titled add-on question can leave the quiz editor without options', () => {
	globalThis.window = { ohmylmsExtensionManifest: { question: manifest } };
	try {
		const question = {
			name: 'Reader',
			settings: { type: 'graded-reader' },
			questions: [],
		};
		assert.equal( canLeaveQuestion( question, choiceValidator ), true );
		assert.equal(
			canLeaveQuestion( { ...question, name: ' ' }, choiceValidator ),
			false
		);
		assert.equal(
			canLeaveQuestion(
				{ ...question, settings: { type: 'options-addon' } },
				choiceValidator
			),
			false
		);
	} finally {
		delete globalThis.window;
	}
} );

import {
	extendedDefaults,
	extendedPublicView,
} from '../../assets/src/features/question-editor/extendedModel.mjs';

test( 'video response allows camera and screen recording by default', () => {
	assert.deepEqual( extendedDefaults( 'video-response' ).sources, [
		'camera',
		'screen',
	] );
	assert.deepEqual(
		extendedPublicView( 'video-response', {
			max_seconds: 30,
			sources: [ 'screen' ],
			secret: 'x',
		} ),
		{ max_seconds: 30, sources: [ 'screen' ] }
	);
	assert.equal( extendedDefaults( 'audio-response' ).sources, undefined );
} );
