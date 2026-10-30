// `NativeLessonEditor::return_url()` names the LMS screen to return to: an admin address
// (`admin.php?page=ohmylms-content&tab=lessons`, see includes/Admin/AdminNavigation.php). Links saved
// before the admin pages had addresses name the legacy page or the retired app page with a hash route.
const PAGES = [ 'ohmylms', 'ohmylms-app' ];
const ROUTES =
	/^#\/(?:content-hub|course-edit|courses|quizzes|exams|assignments)(?:[/?]|$)/;
const CONTENT_PAGE = 'ohmylms-content';
const CONTENT_TABS = [
	'courses',
	'lessons',
	'quizzes',
	'exams',
	'assignments',
	'question-bank',
	'skills',
	'curriculum',
];

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
		if (
			url.origin !== origin ||
			! url.pathname.endsWith( '/admin.php' )
		) {
			return false;
		}
		const page = url.searchParams.get( 'page' );
		if ( CONTENT_PAGE === page ) {
			return CONTENT_TABS.includes( url.searchParams.get( 'tab' ) ?? '' );
		}
		return PAGES.includes( page ) && ROUTES.test( url.hash );
	} catch {
		return false;
	}
}

/**
 * Whether the return link opens a course editor (`tab=courses&id=<id>`, or the older `#/course-edit/<id>`
 * and `#/courses/<id>`).
 *
 * @param {string} value Return URL.
 * @return {boolean} True for a course.
 */
export function isCourseReturn( value ) {
	if ( /#\/(?:course-edit|courses)\/\d/.test( value ) ) {
		return true;
	}
	try {
		const query = new URL( value ).searchParams;
		return (
			CONTENT_PAGE === query.get( 'page' ) &&
			'courses' === query.get( 'tab' ) &&
			/^\d+$/.test( query.get( 'id' ) ?? '' )
		);
	} catch {
		return false;
	}
}
