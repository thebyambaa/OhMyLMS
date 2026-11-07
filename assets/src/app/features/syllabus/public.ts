/**
 * What other features may use: the course-from-syllabus workflow. Load it lazily into the
 * `admin-app-syllabus` chunk so it does not grow the importing feature's chunk.
 */
export { CourseFromSyllabusDialog } from './CourseFromSyllabusDialog';
/** The syllabus outline and its commands, for screens that edit skill groups in place. */
export { command as syllabusCommand, readSyllabus } from './api';
export type { Content, Group, Skill, SyllabusOutline } from './model';
