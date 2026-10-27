import { createComponentAdapter } from './component-adapter.mjs';

export const adaptEmails = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/emails/components.json',
		import.meta.url
	),
	namespace: 'emailComponents',
	label: 'email',
	declarations: true,
} );
