import { createComponentAdapter } from './component-adapter.mjs';

export const adaptStudents = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/students/components.json',
		import.meta.url
	),
	namespace: 'studentComponents',
	label: 'student',
	declarations: true,
} );
