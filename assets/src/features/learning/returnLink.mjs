// `NativeLessonEditor::return_url()` names the legacy LMS page or, when the new app replaces the screen
// (`LegacyRoutes::url()`), the app page with the mapped route.
const PAGES = [ 'ohmylms', 'ohmylms-app' ];
const ROUTES =
	/^#\/(?:content-hub|course-edit|courses|quizzes|exams|assignments)(?:[/?]|$)/;

/**
 * Whether a stored link is a same-site LMS screen the editor may return to.
 *
 * @param {string} value  Absolute URL.
 * @param {string} origin Origin of the editor page.
 * @return {boolean} True for an LMS admin route on this site.
 */
export function isReturnUrl( value, origin ) {
	try {
		const url = new URL( value );
		return (
			url.origin === origin &&
			PAGES.includes( url.searchParams.get( 'page' ) ) &&
			ROUTES.test( url.hash )
		);
	} catch {
		return false;
	}
}

/**
 * Whether the return link opens a course editor (legacy `#/course-edit/<id>` or the app's `#/courses/<id>`).
 *
 * @param {string} value Return URL.
 * @return {boolean} True for a course.
 */
export function isCourseReturn( value ) {
	return /#\/(?:course-edit|courses)\/\d/.test( value );
}
