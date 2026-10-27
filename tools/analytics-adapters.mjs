import { createComponentAdapter } from './component-adapter.mjs';

export const adaptAnalytics = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/analytics/components.json',
		import.meta.url
	),
	namespace: 'analyticsComponents',
	label: 'analytics',
	declarations: true,
} );
