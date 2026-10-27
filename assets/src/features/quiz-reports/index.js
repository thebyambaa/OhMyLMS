import { createQuizReport } from './QuizReport';
import { createQuizGrading } from './QuizGrading';
import { createQuizQuestionHeader } from './QuizQuestionHeader';
import { createSingleChoiceResult } from './SingleChoiceResult';
import { createMultipleChoiceResult } from './MultipleChoiceResult';
import { createTextAnswerResult } from './TextAnswerResult';
import { createReorderResult } from './ReorderResult';
import { createMatchingResult } from './MatchingResult';
import { createQuizQuestionResults } from './QuizQuestionResults';
import { createQuizResultSummary } from './QuizResultSummary';
export const quizReportComponents = {
	QuizReport: createQuizReport,
	QuizGrading: createQuizGrading,
	QuizQuestionHeader: createQuizQuestionHeader,
	SingleChoiceResult: createSingleChoiceResult,
	MultipleChoiceResult: createMultipleChoiceResult,
	TextAnswerResult: createTextAnswerResult,
	ReorderResult: createReorderResult,
	MatchingResult: createMatchingResult,
	QuizQuestionResults: createQuizQuestionResults,
	QuizResultSummary: createQuizResultSummary,
};
