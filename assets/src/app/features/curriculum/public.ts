/**
 * Syllabus comparison operations other features may use: the curriculum feature owns
 * `/syllabus-comparison/*`. `CoursePlacement` and `readTree` stay exported from `index.ts`, which
 * the registry loads eagerly; moving them here would copy them and their dependencies into every
 * chunk that uses them.
 */
export {
	compareSyllabuses,
	removeSkillMapping,
	saveSkillMapping,
	searchSharedSkills,
} from './mappings';
export type { SyllabusComparison } from './mappings';

export { ContentSkillConnections } from './ContentSkillConnections';
export type { ContentKind } from './workspace';
