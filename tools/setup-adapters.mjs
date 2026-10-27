import { createComponentAdapter } from './component-adapter.mjs';

export const adaptSetup = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/setup/components.json',
		import.meta.url
	),
	namespace: 'setupComponents',
	label: 'setup',
	declarations: false,
} );
