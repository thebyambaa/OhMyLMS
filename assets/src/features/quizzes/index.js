// Compatibility facade for the original combined quiz feature.
import { quizEditorComponents } from '../quiz-editor';
import { questionEditorComponents } from '../question-editor';
export const quizComponents = {
	...quizEditorComponents,
	...questionEditorComponents,
};
