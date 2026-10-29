/**
 * Routing data for the Content Hub, kept free of React so it can be tested on its own.
 *
 * The hub replaces the Courses, Skills, Curriculum, Course Bundles and Assessments submenus. Its tabs
 * are Courses, Lessons, Quizzes, Exams, Question Bank, Assignments, Skills and Curriculum, and
 * the hub itself (`#/content-hub`) opens the first of them. Every address those screens had keeps working
 * and opens the matching tab (`#/courses`, `#/quizzes`, `#/assignments`, `#/assessments`,
 * `#/assessments/question-bank`, `#/assessments/assignments`, `#/extensions/skills`,
 * `#/extensions/question-bank`, `#/extensions/curriculum`, `#/extensions/bundles`, `#/categories` and
 * `#/tags`), so existing links and bookmarks survive. The retired Catalog addresses
 * (`#/content-hub/catalog` and `#/content-hub/catalog/ID`) open the Courses tab and the course's editor.
 */
export const HUB_PATH = '/content-hub';

export const HUB_TABS = [
	{ id: 'courses', path: '/content-hub/courses', label: 'Courses' },
	{ id: 'lessons', path: '/content-hub/lessons', label: 'Lessons' },
	{ id: 'quizzes', path: '/content-hub/quizzes', label: 'Quizzes' },
	{ id: 'exams', path: '/content-hub/exams', label: 'Exams' },
	{
		id: 'question-bank',
		path: '/content-hub/question-bank',
		label: 'Question Bank',
	},
	{
		id: 'assignments',
		path: '/content-hub/assignments',
		label: 'Assignments',
	},
	{ id: 'skills', path: '/content-hub/skills', label: 'Skills' },
	{ id: 'curriculum', path: '/content-hub/curriculum', label: 'Curriculum' },
];

/** SDK admin pages that are hub tabs, keyed by their `#/extensions/<id>` address. */
export const HUB_EXTENSION_TABS = {
	skills: 'skills',
	'question-bank': 'question-bank',
	curriculum: 'curriculum',
};

/** The application's own list screens that are hub tabs, keyed by their old route. */
export const HUB_APP_ROUTES = {
	'/courses': 'courses',
	'/quizzes': 'quizzes',
	'/assignments': 'assignments',
};

/** The retired Assessments addresses and the tab each one opens. */
export const HUB_ALIASES = {
	'/content-hub/bundles': 'curriculum',
	'/extensions/bundles': 'curriculum',
	'/assessments': 'quizzes',
	'/assessments/question-bank': 'question-bank',
	'/assessments/assignments': 'assignments',
};

/** Hub-provided page for each tab that is not one of the application's own screens. */
const TAB_PAGES = {
	lessons: 'LessonsPage',
	exams: 'ExamsPage',
	'question-bank': 'QuestionBankPage',
	skills: 'SkillsPage',
	curriculum: 'CurriculumPage',
};

/** Core screens that are opened from the hub; the admin menu keeps Content Hub highlighted for them. */
export const HUB_MENU_ROUTES = [
	'/course-edit/:id/:step?/:subStep?',
	'/course/:id/report',
	'/courses/:id/students',
	'/lesson-edit/:id',
	'/quiz-edit/:id',
	'/quiz-report/:id',
	'/quiz-report/:id/grade-quiz/:quizId',
	'/assignment-edit/:id',
	'/assignment-report/:id',
	'/assignment-report/:id/grade-assignment/:assignmentId',
];

/**
 * The syllabus workspace, a full-page editor opened from the Curriculum tab (a syllabus is also a course).
 * It has no hub tabs of its own, like the course editor, and keeps the Content Hub menu entry highlighted.
 */
export const SYLLABUS_ROUTE = '/content-hub/curriculum/syllabus/:id';

/**
 * Where a course is opened: its own editor, which the Courses tab lists.
 * @param courseId
 */
export const courseEditPath = ( courseId ) =>
	`/course-edit/${ Number( courseId ) }`;

/**
 * Where an address of the retired Catalog tab leads: the course's editor, or the Courses tab.
 * @param hash
 */
export function legacyCatalogTarget( hash ) {
	const match = /#\/content-hub\/catalog\/(\d+)/.exec( String( hash || '' ) );
	return match ? courseEditPath( match[ 1 ] ) : '/content-hub/courses';
}

/**
 * Query parameters of the current hash route, e.g. `#/content-hub/skills?add=1`.
 * @param hash
 */
export function hashQuery( hash ) {
	const text = String( hash || '' );
	const index = text.indexOf( '?' );
	return new URLSearchParams( index === -1 ? '' : text.slice( index + 1 ) );
}

/**
 * Hub routes. `screen(Component, tab)` wraps a page in the hub frame; `pages` are the tab bodies and
 * `routes` the application's own route table (its `/courses`, `/quizzes` and `/assignments` screens
 * become the Courses, Quizzes and Assignments tabs, and are left out when the application lacks them).
 * The old Assessments addresses reuse the screens of the tabs they open, and the hub's own address shows
 * the first tab. `redirect(resolve)` makes the screen that sends the retired Catalog addresses on to
 * `resolve(hash)`.
 * @param routes
 * @param pages
 * @param screen
 * @param redirect
 */
export function contentHubRoutes( routes, pages, screen, redirect ) {
	const appRoute = Object.fromEntries(
		Object.entries( HUB_APP_ROUTES ).map( ( [ path, tab ] ) => [
			tab,
			path,
		] )
	);
	const hub = [];
	const screens = {};
	for ( const tab of HUB_TABS ) {
		const Component = appRoute[ tab.id ]
			? routes.find( ( route ) => route.path === appRoute[ tab.id ] )
					?.element
			: pages[ TAB_PAGES[ tab.id ] ];
		if ( appRoute[ tab.id ] && ! Component ) {
			continue;
		}
		screens[ tab.id ] = screen( Component, tab.id );
		hub.push( { path: tab.path, element: screens[ tab.id ] } );
	}
	const home = screens[ HUB_TABS[ 0 ].id ] || Object.values( screens )[ 0 ];
	const aliases = Object.entries( HUB_ALIASES )
		.filter( ( [ , tab ] ) => screens[ tab ] )
		.map( ( [ path, tab ] ) => ( { path, element: screens[ tab ] } ) );
	const retired = redirect
		? [ '/content-hub/catalog', '/content-hub/catalog/:courseId' ].map(
				( path ) => ( {
					path,
					element: redirect( legacyCatalogTarget ),
				} )
			)
		: [];
	return [
		...( home ? [ { path: HUB_PATH, element: home } ] : [] ),
		...hub,
		...aliases,
		...retired,
	];
}
