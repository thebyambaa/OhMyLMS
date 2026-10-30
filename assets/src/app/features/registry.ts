import { hasCapability } from '../bootstrap/config';
import { accountHub, accountHubClass, accountHubSchool } from './account-hub';
import {
	assignmentEditor,
	assignmentGrading,
	assignments,
	assignmentSubmissions,
} from './assignments';
import { certificateEditor, certificates } from './certificates';
import { coupons, orderDetail, orders } from './commerce';
import { contentHub } from './content-hub';
import { courseEditor, courses } from './courses';
import {
	courseEditor as courseStructureEditor,
	courses as courseStructures,
	courseSettings,
} from './course-structure';
import { curriculum } from './curriculum';
import { courseReport, dashboard, earnings } from './dashboard';
import { emails } from './emails';
import { extensionPages } from './extensions';
import { gamification } from './gamification';
import { home } from './home';
import { integrations } from './integrations';
import { lessons, lessonEditor } from './lessons';
import { membershipEditor, memberships } from './memberships';
import { questionEditor, questions } from './questions';
import { quizAttempts, quizEditor, quizzes } from './quizzes';
import { settings } from './settings';
import { setup } from './setup';
import { students } from './students';
import { syllabusEditor } from './syllabus';
import { taxonomies } from './taxonomies';
import { webhooks } from './webhooks';
import type { FeatureDefinition } from './types';

/**
 * Every feature of the app, in navigation order. Add new features with an explicit import and one entry;
 * nothing is discovered automatically.
 */
export const features: readonly FeatureDefinition[] = [
	dashboard,
	earnings,
	courseReport,
	home,
	setup,
	contentHub,
	curriculum,
	syllabusEditor,
	courseStructures,
	courseStructureEditor,
	courses,
	courseEditor,
	quizzes,
	quizEditor,
	quizAttempts,
	assignments,
	assignmentEditor,
	assignmentSubmissions,
	assignmentGrading,
	courseSettings,
	lessons,
	lessonEditor,
	questions,
	questionEditor,
	orders,
	orderDetail,
	coupons,
	memberships,
	membershipEditor,
	students,
	accountHub,
	accountHubSchool,
	accountHubClass,
	certificates,
	certificateEditor,
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

/**
 * Describe mistakes in a feature list: duplicate ids or paths, and malformed paths.
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
