import { createComponentAdapter } from './component-adapter.mjs';
const adapters = [ 'quiz-editor', 'question-editor' ].map( ( feature ) =>
	createComponentAdapter( {
		manifest: new URL(
			'../assets/src/features/' + feature + '/components.json',
			import.meta.url
		),
		namespace: 'quizComponents',
		label: feature,
		declarations: false,
	} )
);
export function adaptQuizzes( ast ) {
	return {
		components: adapters.reduce(
			( count, adapt ) => count + adapt( ast ).components,
			0
		),
	};
}
