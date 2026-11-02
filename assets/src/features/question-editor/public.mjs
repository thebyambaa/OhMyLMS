/**
 * Public entry of features/question-editor: the question type models other folders of
 * assets/src/features import (docs/ENFORCEMENT.md, rule n).
 *
 * Only .mjs modules: question-bank/model.mjs, paper/paperTypes.mjs and learning/choiceAutosave.mjs
 * import this file and run in Node tests, and MultipleChoiceBlock and QuestionLivePreview import
 * question-bank and math, which import these models. The three components other folders use
 * (MultipleChoiceBlock, QuestionLivePreview, QuestionMediaUpload) are imported directly and listed
 * in tools/architecture-rules/frontend-allowlist.json.
 */
export { dropdownAnswers } from './dropdownModel.mjs';
export {
	EXTENDED_TYPES,
	extendedDefaults,
	extendedIssues,
	isExtendedType,
	isUngradedType,
} from './extendedModel.mjs';
export { parseInlineBlankPrompt } from './inlineBlanks.mjs';
export {
	INTERACTIVE_TYPES,
	interactiveDefaults,
	interactiveIssues,
	isInteractiveType,
	markerIds,
} from './interactiveModel.mjs';
export { publicPuzzle, puzzleCells, puzzleLetters } from './puzzleModel.mjs';
export { QUESTION_BLOCK_TYPES, questionTypePatch } from './questionBlocks.mjs';
export {
	promptText,
	questionPrompt,
	questionPromptPatch,
	statementKey,
} from './questionPrompt.mjs';
export { hasTemplate } from './templateModel.mjs';
