import { createQuizEditor } from './QuizEditor';
import { createQuestionList } from './QuestionList';
import { createQuizSettings } from './QuizSettings';
import { createQuizSettingsFields } from './QuizSettingsFields';
export { useQuizEditor } from './useQuizEditor';
export { QuizQuestionCards } from './QuizQuestionCards';
export const quizEditorComponents = {
	QuizEditor: createQuizEditor,
	QuestionList: createQuestionList,
	QuizSettings: createQuizSettings,
	QuizSettingsFields: createQuizSettingsFields,
};
