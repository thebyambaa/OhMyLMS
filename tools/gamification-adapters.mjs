import { createComponentAdapter } from './component-adapter.mjs';

export const adaptGamification = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/gamification/components.json',
		import.meta.url
	),
	namespace: 'gamificationComponents',
	label: 'gamification',
	declarations: true,
} );
