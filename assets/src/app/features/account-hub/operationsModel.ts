import { __ } from '@wordpress/i18n';
import {
	count,
	invalidResponse,
	list,
	nullableText,
	oneOf,
	positiveId,
	record,
	text,
} from './model';
import {
	SCHOOL_PANELS,
	type ClassWork,
	type GradeMapping,
	type GuardianRow,
	type PersonRef,
	type RemovalImpact,
	type ReportClass,
	type ReportExport,
	type ReportFigures,
	type ReportPeriod,
	type ReportSkill,
	type RolloverClass,
	type RolloverPlan,
	type RolloverResult,
	type RolloverTotals,
	type SampleSchool,
	type SchoolPanel,
	type SchoolRemoval,
	type SchoolReport,
	type Submission,
	type TitledRef,
	type YearRef,
} from './types';

const IMPACT_KEYS: readonly ( keyof RemovalImpact )[] = [
	'classes',
	'academic_years',
	'students',
	'teachers',
	'school_admins',
	'class_memberships',
	'teacher_links',
	'guardian_links',
	'invitations',
	'assignments',
	'course_links',
	'roster_enrolments',
	'managed_accounts',
];

const TOTAL_KEYS: readonly ( keyof RolloverTotals )[] = [
	'classes',
	'promoted_classes',
	'leaving_classes',
	'classes_created',
	'students_promoted',
	'students_leaving',
	'teachers_copied',
	'enrolments_moved',
	'enrolments_revoked',
	'course_links',
];

const REVIEW = /^[a-f0-9]{64}$/;

function bool( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function nullableCount( value: unknown ): number | null {
	return null === value ? null : count( value );
}

function strings( value: unknown ): string[] {
	return list( value ).map( text );
}

function personRef( value: unknown ): PersonRef {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		display_name: text( item.display_name ),
	};
}

/**
 * School sub-screen from the `panel` query parameter.
 *
 * @param value Raw parameter.
 * @return A known panel, `details` by default.
 */
export function panelFromParam( value: string | null ): SchoolPanel {
	return SCHOOL_PANELS.find( ( id ) => id === value ) ?? 'details';
}

/**
 * Parse what deleting a school would remove.
 *
 * @param value Parsed JSON.
 * @return The removal preview.
 */
export function parseRemoval( value: unknown ): SchoolRemoval {
	const item = record( value );
	const impact = record( item.impact );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		status: text( item.status ),
		can_delete: bool( item.can_delete ),
		confirm: text( item.confirm ),
		impact: Object.fromEntries(
			IMPACT_KEYS.map( ( key ) => [ key, count( impact[ key ] ) ] )
		) as unknown as RemovalImpact,
	};
}

/**
 * Parse a guardian row (link or open invitation).
 *
 * @param value Parsed JSON.
 * @return The row.
 */
export function parseGuardian( value: unknown ): GuardianRow {
	const item = record( value );
	return {
		kind: oneOf( item.kind, [ 'link', 'invitation' ] as const ),
		id: positiveId( item.id ),
		status: oneOf( item.status, [ 'active', 'pending' ] as const ),
		student: personRef( item.student ),
		parent: null === item.parent ? null : personRef( item.parent ),
		linked_at: nullableText( item.linked_at ),
		expires_at: nullableText( item.expires_at ),
		email_hint: nullableText( item.email_hint ),
	};
}

function figures( item: Record< string, unknown > ): ReportFigures {
	return {
		learners: count( item.learners ),
		active_learners: count( item.active_learners ),
		enrolled: count( item.enrolled ),
		completed: count( item.completed ),
		completion: nullableCount( item.completion ),
	};
}

function reportClass( value: unknown ): ReportClass {
	const item = record( value );
	return {
		...figures( item ),
		id: positiveId( item.id ),
		name: text( item.name ),
		grade: text( item.grade ),
		teachers: count( item.teachers ),
		assigned: count( item.assigned ),
		assigned_completed: count( item.assigned_completed ),
	};
}

function reportSkill( value: unknown ): ReportSkill {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		code: text( item.code ),
		assessed: count( item.assessed ),
		average: count( item.average ),
		below: count( item.below ),
	};
}

/**
 * Parse a school report.
 *
 * @param value Parsed JSON.
 * @return The report.
 */
export function parseReport( value: unknown ): SchoolReport {
	const item = record( value );
	const school = record( item.school );
	const period = record( item.period );
	const summary = record( item.summary );
	const mastery = record( item.mastery );
	return {
		school: {
			id: positiveId( school.id ),
			name: text( school.name ),
			status: text( school.status ),
		},
		period: {
			days: Number(
				oneOf( String( period.days ), [ '7', '30', '90' ] as const )
			) as ReportPeriod,
			start: text( period.start ),
			end: text( period.end ),
		},
		summary: {
			...figures( summary ),
			classes: count( summary.classes ),
			archived_classes: count( summary.archived_classes ),
			assigned: count( summary.assigned ),
			assigned_completed: count( summary.assigned_completed ),
		},
		classes: list( item.classes ).map( reportClass ),
		more_classes: count( item.more_classes ),
		mastery: {
			skills: list( mastery.skills ).map( reportSkill ),
			average: nullableCount( mastery.average ),
			assessed: count( mastery.assessed ),
		},
		generated_at: text( item.generated_at ),
	};
}

/**
 * Parse the CSV export.
 *
 * @param value Parsed JSON.
 * @return File name, type and text.
 */
export function parseReportExport( value: unknown ): ReportExport {
	const item = record( value );
	const filename = text( item.filename );
	if ( ! /^[\w.-]+\.csv$/.test( filename ) ) {
		throw invalidResponse();
	}
	return {
		filename,
		content_type: text( item.content_type ),
		csv: text( item.csv ),
	};
}

function yearRef( value: unknown ): YearRef {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		label: text( item.label ),
		status: text( item.status ),
	};
}

function totals( value: unknown ): RolloverTotals {
	const item = record( value );
	return Object.fromEntries(
		TOTAL_KEYS.map( ( key ) => [ key, count( item[ key ] ) ] )
	) as unknown as RolloverTotals;
}

function rolloverClass( value: unknown ): RolloverClass {
	const item = record( value );
	const action = oneOf( item.action, [ 'promote', 'leave' ] as const );
	let target: RolloverClass[ 'target' ] = null;
	if ( 'promote' === action ) {
		const found = record( item.target );
		target = {
			id: null === found.id ? null : positiveId( found.id ),
			name: text( found.name ),
			grade: text( found.grade ),
			exists: bool( found.exists ),
		};
	}
	return {
		class_id: positiveId( item.class_id ),
		name: text( item.name ),
		grade: text( item.grade ),
		action,
		students: count( item.students ),
		teachers: count( item.teachers ),
		enrolments: count( item.enrolments ),
		links: count( item.links ),
		target,
	};
}

/**
 * Parse a rollover preview.
 *
 * @param value Parsed JSON.
 * @return The plan with its review token.
 */
export function parsePlan( value: unknown ): RolloverPlan {
	const item = record( value );
	const review = text( item.review );
	if ( ! REVIEW.test( review ) ) {
		throw invalidResponse();
	}
	return {
		from: yearRef( item.from ),
		to: yearRef( item.to ),
		classes: list( item.classes ).map( rolloverClass ),
		totals: totals( item.totals ),
		review,
	};
}

/**
 * Parse an applied rollover.
 *
 * @param value Parsed JSON.
 * @return The result.
 */
export function parseRolloverResult( value: unknown ): RolloverResult {
	const item = record( value );
	return {
		from: yearRef( item.from ),
		to: yearRef( item.to ),
		classes: list( item.classes ).map( ( entry ) => {
			const row = record( entry );
			return {
				source_id: positiveId( row.source_id ),
				target_id:
					null === row.target_id ? null : positiveId( row.target_id ),
				action: oneOf( row.action, [ 'promote', 'leave' ] as const ),
			};
		} ),
		totals: totals( item.totals ),
		already_applied: bool( item.already_applied ),
	};
}

/**
 * Parse the sample school state.
 *
 * @param value Parsed JSON.
 * @return The state.
 */
export function parseSample( value: unknown ): SampleSchool {
	const item = record( value );
	const removal = record( item.removal );
	return {
		exists: bool( item.exists ),
		complete: bool( item.complete ),
		school_id: count( item.school_id ),
		classes: list( item.classes ).map( ( entry ) => {
			const row = record( entry );
			return {
				key: text( row.key ),
				id: positiveId( row.id ),
				name: text( row.name ),
			};
		} ),
		people: list( item.people ).map( ( entry ) => {
			const row = record( entry );
			return {
				key: text( row.key ),
				id: positiveId( row.id ),
				display_name: text( row.display_name ),
				role: text( row.role ),
			};
		} ),
		removal: {
			allowed: bool( removal.allowed ),
			remove: strings( removal.remove ),
			skip: strings( removal.skip ),
			blockers: strings( removal.blockers ),
		},
	};
}

/**
 * Parse work assigned to a class.
 *
 * @param value Parsed JSON.
 * @return The work.
 */
export function parseClassWork( value: unknown ): ClassWork {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		course_id: positiveId( item.course_id ),
		course_title: text( item.course_title ),
		content_id: count( item.content_id ),
		content_title: nullableText( item.content_title ),
		due_at: nullableText( item.due_at ),
		created_at: nullableText( item.created_at ),
		recipients: count( item.recipients ),
		completed: count( item.completed ),
	};
}

/**
 * Parse a course or activity choice.
 *
 * @param value Parsed JSON.
 * @return The choice.
 */
export function parseTitled( value: unknown ): TitledRef {
	const item = record( value );
	return { id: positiveId( item.id ), title: text( item.title ) };
}

/**
 * Parse a page of class submissions.
 *
 * @param value Parsed JSON.
 * @return Submissions and whether more exist.
 */
export function parseSubmissions( value: unknown ): {
	items: Submission[];
	has_more: boolean;
} {
	const item = record( value );
	return {
		items: list( item.items ).map( ( entry ) => {
			const row = record( entry );
			const file = nullableText( row.file_url );
			if ( null !== file && ! /^https?:\/\//.test( file ) ) {
				throw invalidResponse();
			}
			return {
				id: positiveId( row.id ),
				assignment_id: positiveId( row.assignment_id ),
				title: text( row.title ),
				student_name: text( row.student_name ),
				content: text( row.content ),
				score: nullableCount( row.score ),
				status: text( row.status ),
				note: text( row.note ),
				total_points: count( row.total_points ),
				file_url: file,
			};
		} ),
		has_more: bool( item.has_more ),
	};
}

/**
 * Suggested grade map for a year's grades: whole-number grades move up by one, the highest grade
 * of the school leaves, other grades stay as they are.
 *
 * @param grades Grades of the classes that move.
 * @return One mapping per distinct non-empty grade, in grade order.
 */
export function suggestGradeMap( grades: readonly string[] ): GradeMapping[] {
	const distinct = [ ...new Set( grades.map( ( grade ) => grade.trim() ) ) ]
		.filter( Boolean )
		.sort( ( a, b ) => a.localeCompare( b, undefined, { numeric: true } ) );
	const numbers = distinct.filter( ( grade ) => /^\d+$/.test( grade ) );
	const highest = numbers.length
		? Math.max( ...numbers.map( Number ) )
		: null;
	return distinct.map( ( grade ) => {
		if ( ! /^\d+$/.test( grade ) ) {
			return { from: grade, to: grade };
		}
		const next = Number( grade ) + 1;
		return {
			from: grade,
			to:
				Number( grade ) === highest && numbers.length > 1
					? ''
					: String( next ),
		};
	} );
}

/**
 * Validate a grade map before previewing.
 *
 * @param map Rows.
 * @return An error message, or an empty string.
 */
export function gradeMapError( map: readonly GradeMapping[] ): string {
	const seen = new Set< string >();
	for ( const row of map ) {
		const from = row.from.trim();
		if ( ! from || from.length > 50 || row.to.trim().length > 50 ) {
			return __(
				'Each grade needs a value of up to 50 characters.',
				'ohmylms'
			);
		}
		if ( seen.has( from ) ) {
			return __( 'Map each grade only once.', 'ohmylms' );
		}
		seen.add( from );
	}
	return '';
}
