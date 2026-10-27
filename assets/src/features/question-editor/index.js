import { createQuestionCanvas } from './QuestionCanvas';
import { createQuestionSettings } from './QuestionSettings';
import { createChoiceOptionsEditor } from './ChoiceOptionsEditor';
import { createTextAnswerEditor } from './TextAnswerEditor';
import { createMultipleChoiceEditor } from './MultipleChoiceEditor';
import { createSingleChoiceEditor } from './SingleChoiceEditor';
import { createTrueFalseEditor } from './TrueFalseEditor';
import { createShortTextEditor } from './ShortTextEditor';
import { createLongTextEditor } from './LongTextEditor';
import { createStatementEditor } from './StatementEditor';
import { createFillInTheBlankEditor } from './FillInTheBlankEditor';
import { createReorderEditor } from './ReorderEditor';
import { createMatchingEditor } from './MatchingEditor';
export { FormWorkspace } from './FormWorkspace';
export { BankAnswerFields } from './BankAnswerFields';
export {
	NumericalEditor,
	StructuredEditor,
	PracticeFeedbackFields,
} from './MathEditors';
export {
	DropdownBlanksEditor,
	CategorizeEditor,
	MultiBlankEditor,
	BuildExpressionEditor,
	ExpressionEditor,
	InteractivePreview,
} from './InteractiveEditors';
export {
	BuildChartEditor,
	CountBlocksEditor,
	FillLevelEditor,
	GridBuildEditor,
	MakeAmountEditor,
	NumberLineEditor,
	SetClockEditor,
	ShadeModelEditor,
} from './VisualEditors';
export { QuestionEditor } from './QuestionEditor';
export { QuestionLivePreview } from './QuestionLivePreview';
export { QuestionBlockContext, registerQuestionBlocks } from './QuestionBlocks';
export {
	questionTypePatch,
	questionPreviewModel,
	questionPreviewIssues,
} from './questionBlocks.mjs';
export const questionEditorComponents = {
	QuestionCanvas: createQuestionCanvas,
	QuestionSettings: createQuestionSettings,
	ChoiceOptionsEditor: createChoiceOptionsEditor,
	TextAnswerEditor: createTextAnswerEditor,
	MultipleChoiceEditor: createMultipleChoiceEditor,
	SingleChoiceEditor: createSingleChoiceEditor,
	TrueFalseEditor: createTrueFalseEditor,
	ShortTextEditor: createShortTextEditor,
	LongTextEditor: createLongTextEditor,
	StatementEditor: createStatementEditor,
	FillInTheBlankEditor: createFillInTheBlankEditor,
	ReorderEditor: createReorderEditor,
	MatchingEditor: createMatchingEditor,
};
