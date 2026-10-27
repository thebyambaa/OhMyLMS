import { createComponentAdapter } from './component-adapter.mjs';

export const adaptCourses = createComponentAdapter( {
	manifest: new URL(
		'../assets/src/features/courses/components.json',
		import.meta.url
	),
	namespace: 'courseComponents',
	label: 'course',
	declarations: true,
} );
