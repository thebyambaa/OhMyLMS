import type { Lesson } from './model';

/** Synthetic component-test data. */
export const lesson: Lesson = {
	id: 12,
	title: 'Algebra lesson',
	status: 'draft',
	revision: 'lesson-token',
	content: '<!-- wp:paragraph --><p>Use \\alpha.</p><!-- /wp:paragraph -->',
	type: 'text',
	external_url: '',
	preview_enable: false,
	enable_comments: false,
	resources: [],
	native_editor_url: 'http://800.local/wp-admin/post.php?post=12&action=edit',
};
