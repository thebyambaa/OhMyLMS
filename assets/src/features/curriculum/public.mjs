/**
 * Public entry of features/curriculum: what other folders of assets/src/features import from it
 * (docs/ENFORCEMENT.md, rule n).
 */
export {
	loadContentSkills,
	loadSyllabus,
	loadTree,
	saveContentSkills,
} from './api.mjs';
export { buildTree } from './model.mjs';
