import test from 'node:test';
import assert from 'node:assert/strict';
import { loadSkillCurriculum, skillCurriculumTree } from '../../assets/src/features/content-hub/skillCurriculum.mjs';

const data = {
	items: [
		{ id: 1, parent_id: 0, name: 'Mathematics', position: 0 },
		{ id: 2, parent_id: 1, name: 'Year 8', is_syllabus: true, position: 0 },
		{ id: 3, parent_id: 2, name: 'Algebra', item_type: 'topic', position: 0 },
	],
	outlines: [{ contents: [{ id: 3, groups: [{ id: 10, item_id: 3, name: 'Equations', skills: [
		{ term_id: 41, name: 'Linear equations', code: 'A.1' },
		{ term_id: 42, name: 'Quadratic equations', code: 'A.2' },
	] }] }] }],
};
const leaves = ( nodes ) => nodes.flatMap( ( node ) => node.skill ? [node.skill] : leaves(node.children) );

test('curriculum parents, syllabus topics and skills retain their canonical IDs', () => {
	const { tree, placed } = skillCurriculumTree(data);
	assert.equal(tree[0].name, 'Mathematics');
	assert.equal(tree[0].children[0].name, 'Year 8');
	assert.equal(tree[0].children[0].children[0].name, 'Algebra');
	assert.deepEqual(leaves(tree).map(skill => skill.id), [41,42]);
	assert.deepEqual([...placed], [41,42]);
});

test('search keeps ancestors and allows searching topics, syllabuses and skill codes', () => {
	assert.deepEqual(leaves(skillCurriculumTree(data, 'A.2').tree).map(skill=>skill.id), [42]);
	assert.deepEqual([...skillCurriculumTree(data, 'A.2').visible], [42]);
	assert.equal(leaves(skillCurriculumTree(data, 'algebra').tree).length, 2);
	assert.equal(leaves(skillCurriculumTree(data, 'year 8').tree).length, 2);
	assert.deepEqual(skillCurriculumTree(data, 'missing').tree, []);
	assert.deepEqual(leaves(skillCurriculumTree(data, '', [41]).tree).map(skill=>skill.id), [42]);
});

test('overlapping syllabus outlines do not duplicate topic groups and corrupt parents stay bounded', () => {
	assert.equal(leaves(skillCurriculumTree({...data,outlines:[...data.outlines,...data.outlines]}).tree).length,2);
	const corrupt = {...data,items:[{...data.items[2],parent_id:3}]};
	assert.equal(leaves(skillCurriculumTree(corrupt).tree).length,2);
});

test('syllabus requests are limited to four and failures are reported', async () => {
	let active=0; let max=0; let requests=0;
	const items=Array.from({length:9},(_,i)=>({id:i+1,is_syllabus:true}));
	const loaded=await loadSkillCurriculum(async()=>({items}),async(id)=>{
		active++; max=Math.max(max,active); requests++;
		await new Promise(resolve=>setTimeout(resolve,2));
		active--; return {syllabus:{id},contents:[]};
	});
	assert.equal(requests,9); assert.equal(max,4); assert.equal(loaded.outlines.length,9);
	await assert.rejects(loadSkillCurriculum(async()=>({items}),async()=>{throw new Error('Unavailable');}),/Unavailable/);
});
