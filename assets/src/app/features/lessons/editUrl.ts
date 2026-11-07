/**
 * Address of a lesson in the WordPress block editor, relative to wp-admin. Lessons are written there;
 * the Content Hub only lists them.
 *
 * @param id Lesson post id.
 */
export function blockEditorUrl( id: number ) {
	return `post.php?post=${ id }&action=edit`;
}
