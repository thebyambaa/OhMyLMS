import test from 'node:test';
import assert from 'node:assert/strict';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
	questionPreviewModel,
} from '../../assets/src/features/question-editor/questionBlocks.mjs';
import {
	newQuestionCard,
	prepareQuizPayload,
} from '../../assets/src/features/quiz-editor/model.mjs';
import {
	emptyDraft,
	draftToPayload,
} from '../../assets/src/features/question-bank/model.mjs';

for ( const [ type ] of QUESTION_BLOCK_TYPES ) {
	test( `inserting ${ type } initializes its quiz and bank response data`, () => {
		const patch = questionTypePatch( {}, type, 100 );
		const card = newQuestionCard( patch, 200 );
		const payload = prepareQuizPayload( { id: 1 }, [ card ] ).content[ 0 ];
		assert.equal( payload.settings.type, type );
		const preview = questionPreviewModel( card );
		assert.equal( preview.type, type );
		assert.ok( Array.isArray( preview.parts ) );
		assert.equal( payload.id, undefined );
		assert.ok(
			payload.questions.every( ( option ) => option.id === undefined )
		);
		const bank = draftToPayload( emptyDraft( type ) );
		assert.equal( bank.settings.type, type );
		if ( type === 'structured' ) {
			assert.equal( payload.settings.parts.length, 1 );
			assert.equal( bank.settings.parts.length, 1 );
		}
		if ( type === 'matching' ) {
			assert.equal( payload.questions.length, 2 );
			assert.ok(
				payload.questions.every( ( option ) => option.matching_data )
			);
		}
	} );
}
