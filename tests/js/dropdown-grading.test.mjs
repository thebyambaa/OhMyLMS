import test from 'node:test';
import assert from 'node:assert/strict';
import { dropdownAnswers, dropdownSettings, renameDropdownChoice } from '../../assets/src/features/question-editor/dropdownModel.mjs';
import { questionPromptPatch } from '../../assets/src/features/question-editor/questionPrompt.mjs';
import { interactiveIssues } from '../../assets/src/features/question-editor/interactiveModel.mjs';
import { describeExpected } from '../../assets/src/features/quiz-reports/model.mjs';

test('renaming correct choices keeps ticks and upgrades old answers compatibly',()=>{
	const slot = { id:'1', choices:['positive','negative'], answer:'positive' };
	assert.deepEqual(dropdownAnswers(slot),['positive']);
	const patch = renameDropdownChoice(slot,0,'rises');
	assert.deepEqual(patch.answers,['rises']);
	assert.equal(patch.answer,'rises');
	assert.deepEqual(patch.choices,['rises','negative']);
});
test('per-dropdown points sum automatically and remain coherent when markers change',()=>{
	const slot = { id:'1',choices:['a','b','c'],answers:['a','b'],multiple:true,points:3,grading:'equal' };
	const settings = {type:'dropdown-blanks',text:'{1} {2}',slots:[slot,{...slot,id:'2',points:2}],score:{enabled:true,value:1}};
	const upgraded = {...settings,...dropdownSettings(settings,settings.slots)};
	assert.equal(upgraded.score.value,5);
	assert.deepEqual(interactiveIssues('dropdown-blanks',upgraded),[]);
	const patch = questionPromptPatch({settings:upgraded},'<p>Only {1}</p>');
	assert.equal(patch.settings.score.value,3);
	assert.equal(patch.settings.slots[0].grading,'equal');
	assert.deepEqual(patch.settings.slots[0].answers,['a','b']);
	assert.equal(describeExpected({settings:upgraded}),'{1} = a, b, {2} = a, b');
	const randomized = questionPromptPatch({settings:upgraded},'<p>{{x}} and {1} {2}</p>');
	assert.deepEqual(randomized.settings.slots.map(slot=>slot.id),['1','2']);
	assert.equal(randomized.settings.score.value,5);
});
test('no-wrong mode survives author edits and passes validation',()=>{
	const settings = {type:'dropdown-blanks',text:'{1}',slots:[{id:'1',choices:['a','b','c'],answers:['a','b'],multiple:true,points:3,grading:'no-wrong'}]};
	const upgraded = {...settings,...dropdownSettings(settings,settings.slots)};
	assert.deepEqual(interactiveIssues('dropdown-blanks',upgraded),[]);
	const patch = questionPromptPatch({settings:upgraded},'<p>Select {1}</p>');
	assert.equal(patch.settings.slots[0].grading,'no-wrong');
	assert.equal(patch.settings.score.value,3);
	assert.deepEqual(interactiveIssues('dropdown-blanks',{...upgraded,slots:[{...upgraded.slots[0],grading:'unknown'}]}),['choices']);
});
