/**
 * What other features may use from the question bank. Everything else in this folder is private.
 * The hosted draft editor is loaded lazily from `./draft/public`.
 */
export type { QuestionDraftEditorProps } from './QuestionDraftEditor';
export { listBanks, listQuestions, listQuestionTypes, PER_PAGE } from './api';
export { saveFile } from './exchange';
export { DIFFICULTIES, difficultyLabel, MAX_NAME, typeLabel } from './model';
export { SkillPicker } from './SkillPicker';
export type {
	BankRef,
	QuestionCreateInput,
	QuestionListQuery,
	QuestionSummary,
	QuestionType,
	SkillRef,
} from './types';

/**
 * Route of a question in the question bank editor.
 *
 * @param id Question id.
 * @return In-app path.
 */
export function questionEditorPath( id: number ): string {
	return `/content-hub/question-bank/${ id }`;
}
