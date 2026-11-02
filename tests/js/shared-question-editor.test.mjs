import test from 'node:test';
import assert from 'node:assert/strict';
import {
	draftToPayload,
	emptyDraft,
} from '../../assets/src/features/question-bank/model.mjs';

test( 'standalone question options preserve Required in the saved payload', () => {
	const draft = emptyDraft();
	draft.settings.required = true;
	assert.equal( draftToPayload( draft ).settings.required, true );
} );
