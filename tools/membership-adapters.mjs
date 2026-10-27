import { createComponentAdapter } from './component-adapter.mjs';

export const adaptMemberships = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/memberships/components.json',
		import.meta.url
	),
	namespace: 'membershipComponents',
	label: 'membership',
	declarations: true,
} );
