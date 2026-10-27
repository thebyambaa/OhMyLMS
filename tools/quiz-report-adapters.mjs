import { createComponentAdapter } from './component-adapter.mjs';

export const adaptQuizReports = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/quiz-reports/components.json',
		import.meta.url
	),
	namespace: 'quizReportComponents',
	label: 'quiz report',
	declarations: true,
} );
