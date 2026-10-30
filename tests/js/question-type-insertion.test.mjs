import test from 'node:test';
import assert from 'node:assert/strict';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
	questionPreviewModel,
} from '../../assets/src/features/question-editor/questionBlocks.mjs';
import {
	emptyDraft,
	draftToPayload,
} from '../../assets/src/features/question-bank/model.mjs';

for ( const [ type ] of QUESTION_BLOCK_TYPES ) {
	test( `inserting ${ type } initializes its preview and bank response data`, () => {
		const patch = questionTypePatch( {}, type, 100 );
		assert.equal( patch.settings.type, type );
		const preview = questionPreviewModel( patch );
		assert.equal( preview.type, type );
		assert.ok( Array.isArray( preview.parts ) );
		const bank = draftToPayload( emptyDraft( type ) );
		assert.equal( bank.settings.type, type );
		if ( type === 'structured' ) {
			assert.equal( bank.settings.parts.length, 1 );
		}
	} );
}
