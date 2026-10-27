import { createComponentAdapter } from './component-adapter.mjs';

export const adaptCommunities = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/communities/components.json',
		import.meta.url
	),
	namespace: 'communityComponents',
	label: 'community',
	declarations: true,
} );
