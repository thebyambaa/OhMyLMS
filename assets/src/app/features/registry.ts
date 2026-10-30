import { hasCapability } from '../bootstrap/config';
import {
	accountHub,
	accountHubClass,
	accountHubRoster,
	accountHubSchool,
} from './account-hub';
import {
	assignmentEditor,
	assignmentGrading,
	assignments,
	assignmentSubmissions,
} from './assignments';
import { certificateEditor, certificates } from './certificates';
import { coupons, orderDetail, orders } from './commerce';
import { subscriptionDetail, subscriptions } from './commerce/subscriptions';
import { contentHub, contentHubOverview } from './content-hub';
import { courseEditor, courses } from './courses';
import {
	courseEditor as courseStructureEditor,
	courses as courseStructures,
	courseSettings,
} from './course-structure';
import {
	bundles,
	curriculum,
	curriculumCompare,
	skillMap,
	subjectProfiles,
} from './curriculum';
import { courseReport, courseRoster, dashboard, earnings } from './dashboard';
import { emails } from './emails';
import { examEditor, examImportReview, exams } from './exams';
import { extensionPages } from './extensions';
import { gamification } from './gamification';
import { integrations } from './integrations';
import { lessons, lessonEditor } from './lessons';
import { membershipEditor, memberships } from './memberships';
import { questionEditor, questions } from './questions';
import { quizAttempts, quizEditor, quizReports, quizzes } from './quizzes';
import { sessionEditor, sessions } from './sessions';
import { settings } from './settings';
import { setup } from './setup';
import { studentReport, students } from './students';
import { syllabusEditor } from './syllabus';
import { taxonomies } from './taxonomies';
import { webhooks } from './webhooks';
import type { FeatureDefinition } from './types';

/**
 * Every feature of the app. Add new features with an explicit import and one entry; nothing is discovered
 * automatically. Each names its admin page and tab (`group`, `tab`), which must match
 * includes/Admin/AdminNavigation.php (checked by routing/navigation-contract.test.ts).
 */
export const features: readonly FeatureDefinition[] = [
	dashboard,
	earnings,
	courseReport,
	courseRoster,
	setup,
	contentHubOverview,
	contentHub,
	curriculum,
	skillMap,
	subjectProfiles,
	curriculumCompare,
	bundles,
	syllabusEditor,
	courseStructures,
	courseStructureEditor,
	courses,
	courseEditor,
	quizzes,
	quizEditor,
	quizAttempts,
	quizReports,
	assignments,
	assignmentEditor,
	assignmentSubmissions,
	assignmentGrading,
	exams,
	examImportReview,
	examEditor,
	courseSettings,
	lessons,
	lessonEditor,
	questions,
	questionEditor,
	orders,
	orderDetail,
	coupons,
	subscriptions,
	subscriptionDetail,
	memberships,
	membershipEditor,
	students,
	studentReport,
	accountHub,
	accountHubSchool,
	accountHubRoster,
	accountHubClass,
	certificates,
	certificateEditor,
	sessions,
	sessionEditor,
	gamification,
	emails,
	integrations,
	settings,
	taxonomies,
	webhooks,
	extensionPages,
];

/**
 * Features the current user may open.
 *
 * @param all          Feature list, normally {@link features}.
 * @param capabilities Capability map from the bootstrap data.
 * @return Features whose capability requirement is met, in the original order.
 */
export function getVisibleFeatures(
	all: readonly FeatureDefinition[],
	capabilities: Readonly< Record< string, boolean > >
): readonly FeatureDefinition[] {
	return all.filter( ( feature ) =>
		hasCapability( capabilities, feature.capability )
	);
}

const ADDRESS_NAMES = [ 'page', 'tab', 'section', 'view' ];

/**
 * Describe mistakes in a feature list: duplicate ids or paths, malformed paths, and addresses that
 * cannot work (a Dashboard tab, a tab elsewhere missing, a path parameter named like an address slot
 * without being mapped).
 *
 * @param all Feature list to check.
 * @return Human-readable problems; empty when the list is valid.
 */
export function findRegistryProblems(
	all: readonly FeatureDefinition[]
): string[] {
	const problems: string[] = [];
	const ids = new Set< string >();
	const paths = new Set< string >();
	for ( const feature of all ) {
		if ( ( 'dashboard' === feature.group ) === Boolean( feature.tab ) ) {
			problems.push(
				`Feature "${ feature.id }" needs a tab, except on the Dashboard.`
			);
		}
		for ( const name of ( feature.path.match( /:[A-Za-z_]+/g ) ?? [] ).map(
			( token ) => token.slice( 1 )
		) ) {
			if (
				ADDRESS_NAMES.includes( name ) &&
				! feature.params?.[ name ] &&
				! feature.tab?.includes( `:${ name }` )
			) {
				problems.push(
					`Feature "${ feature.id }" must map its ":${ name }" parameter in params.`
				);
			}
		}
		if ( ids.has( feature.id ) ) {
			problems.push( `Duplicate feature id "${ feature.id }".` );
		}
		if ( paths.has( feature.path ) ) {
			problems.push( `Duplicate feature path "${ feature.path }".` );
		}
		if (
			! feature.path.startsWith( '/' ) ||
			feature.path.includes( '*' )
		) {
			problems.push(
				`Feature "${ feature.id }" must have an absolute path without wildcards.`
			);
		}
		ids.add( feature.id );
		paths.add( feature.path );
	}
	return problems;
}
