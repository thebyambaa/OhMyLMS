import test from 'node:test';
import assert from 'node:assert/strict';
import { promptSnapshots, quickEditStatus } from '../../assets/src/features/quiz-editor/quickEditStatus.mjs';

const question = ( id, text ) => ({ id, title: 'Question', description: text, settings: {type:'short-answer',question_code:'test'} });
test('saving and failing another question leaves a saved prompt status unchanged', () => {
	const one=question(1,'One'); const two=question(2,'Two');
	const saved=promptSnapshots([one,two]);
	const edited=question(2,'Changed'); const submitted=promptSnapshots([one,edited]);
	assert.equal(quickEditStatus(one,saved,submitted,true,null),'saved');
	assert.equal(quickEditStatus(one,saved,submitted,false,'Conflict'),'saved');
	assert.equal(quickEditStatus(edited,saved,submitted,true,null),'saving');
	assert.equal(quickEditStatus(edited,saved,submitted,false,'Conflict'),'error');
});
test('a prompt is saved only after the accepted version matches the current edit', () => {
	const original=question(1,'Original'); const sent=question(1,'Submitted'); const newer=question(1,'Typed while saving');
	const saved=promptSnapshots([original]); const submitted=promptSnapshots([sent]);
	assert.equal(quickEditStatus(sent,saved,submitted,false,null),'pending');
	assert.equal(quickEditStatus(newer,saved,submitted,true,null),'pending');
	const accepted=promptSnapshots([sent]);
	assert.equal(quickEditStatus(sent,accepted,submitted,false,null),'saved');
	assert.equal(quickEditStatus(newer,accepted,submitted,false,null),'pending');
	assert.equal(quickEditStatus(original,saved,submitted,false,null),'saved');
});
