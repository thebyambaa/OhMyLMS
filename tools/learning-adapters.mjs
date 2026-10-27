import { createComponentAdapter } from './component-adapter.mjs';

export const adaptLearning = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/learning/components.json',
		import.meta.url
	),
	namespace: 'learningComponents',
	label: 'learning',
	declarations: true,
} );
