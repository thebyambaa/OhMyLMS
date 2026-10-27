import { createComponentAdapter } from './component-adapter.mjs';

export const adaptSettings = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/settings/components.json',
		import.meta.url
	),
	namespace: 'settingsComponents',
	label: 'settings',
	declarations: true,
} );
