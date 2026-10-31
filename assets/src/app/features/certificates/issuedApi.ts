import { __, sprintf } from '@wordpress/i18n';
import { get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseCertificate,
	parseCourseChoice,
	positiveId,
	record,
} from './model';

const BASE = '/ohmylms/v1/issued-certificates';

/** Rows per page in the issued certificates list. */
export const ISSUED_PER_PAGE = 20;

export type IssuedStatus = 'issued' | 'revoked';
export type IssuedSource = 'completion' | 'manual' | 'reissue';

/** One issued certificate as the administration routes return it. */
export interface IssuedCertificate {
	id: number;
	code: string;
	status: IssuedStatus;
	source: IssuedSource;
	student_name: string;
	learner: { id: number; exists: boolean; email: string };
	course: { id: number; title: string; exists: boolean };
	template: { id: number; name: string };
	completed_at: string | null;
	issued_at: string;
	revoked_at: string | null;
	replaces: number;
	links: { pdf: string; print: string; verify: string };
}

/** A learner offered by the manual issue form. */
export interface LearnerChoice {
	id: number;
	name: string;
	email: string;
}

/** Filters and sorting of the issued list, as the address keeps them. */
export interface IssuedQuery {
	page: number;
	search: string;
	status: string;
	course: string;
	certificate: string;
	source: string;
	orderby: string;
	order: string;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function date( value: unknown ): string | null {
	return null === value ? null : text( value );
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

/**
 * Validate an issued certificate from the server.
 *
 * @param value Parsed JSON.
 * @return The certificate.
 */
export function parseIssued( value: unknown ): IssuedCertificate {
	const item = record( value );
	const learner = record( item.learner );
	const course = record( item.course );
	const template = record( item.template );
	const links = record( item.links );
	return {
		id: positiveId( item.id ),
		code: text( item.code ),
		status: oneOf( item.status, [ 'issued', 'revoked' ] as const ),
		source: oneOf( item.source, [
			'completion',
			'manual',
			'reissue',
		] as const ),
		student_name: text( item.student_name ),
		learner: {
			id: count( learner.id ),
			exists: true === learner.exists,
			email: text( learner.email ),
		},
		course: {
			id: count( course.id ),
			title: text( course.title ),
			exists: true === course.exists,
		},
		template: { id: count( template.id ), name: text( template.name ) },
		completed_at: date( item.completed_at ),
		issued_at: text( item.issued_at ),
		revoked_at: date( item.revoked_at ),
		replaces: count( item.replaces ),
		links: {
			pdf: text( links.pdf ),
			print: text( links.print ),
			verify: text( links.verify ),
		},
	};
}

/**
 * Validate a learner choice.
 *
 * @param value Parsed JSON.
 * @return The learner.
 */
export function parseLearner( value: unknown ): LearnerChoice {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		email: text( item.email ),
	};
}

/**
 * Label of how a certificate was issued.
 *
 * @param source Source.
 * @return Label.
 */
export function sourceLabel( source: IssuedSource ): string {
	if ( 'manual' === source ) {
		return __( 'By an administrator', 'ohmylms' );
	}
	if ( 'reissue' === source ) {
		return __( 'Re-issued', 'ohmylms' );
	}
	return __( 'Course completion', 'ohmylms' );
}

/**
 * Date of an ISO timestamp in the browser's locale, or an empty string.
 *
 * @param iso RFC 3339 date.
 * @return Formatted date.
 */
export function formatIssuedDate( iso: string | null ): string {
	if ( ! iso ) {
		return '';
	}
	const time = Date.parse( iso );
	return Number.isNaN( time )
		? ''
		: new Intl.DateTimeFormat( undefined, { dateStyle: 'medium' } ).format(
				time
			);
}

/**
 * Accessible summary of a certificate for row labels and confirmations.
 *
 * @param item Certificate.
 * @return "Name, Course".
 */
export function issuedLabel( item: IssuedCertificate ): string {
	return sprintf(
		/* translators: 1: learner name, 2: course title. */
		__( '%1$s, %2$s', 'ohmylms' ),
		item.student_name || __( 'Unknown learner', 'ohmylms' ),
		item.course.title
	);
}

/**
 * List issued certificates. Empty filters are not sent.
 *
 * @param query  Page, filters and sorting.
 * @param signal Cancels the request.
 * @return Validated certificates and totals.
 */
export function listIssued( query: IssuedQuery, signal?: AbortSignal ) {
	return getCollection( BASE, parseIssued, {
		signal,
		query: {
			page: query.page,
			per_page: ISSUED_PER_PAGE,
			search: query.search.trim() || undefined,
			status: query.status,
			course: Number( query.course ) || undefined,
			certificate: Number( query.certificate ) || undefined,
			source: query.source || undefined,
			orderby: query.orderby,
			order: query.order,
		},
	} );
}

/**
 * Issue a certificate by hand.
 *
 * @param input                Learner, course and optional template (0: the course's).
 * @param input.user_id
 * @param input.course_id
 * @param input.certificate_id
 * @param signal               Cancels the request.
 * @return The new certificate.
 */
export async function issueCertificate(
	input: { user_id: number; course_id: number; certificate_id: number },
	signal?: AbortSignal
) {
	return parseIssued( await post< unknown >( BASE, input, { signal } ) );
}

/**
 * Revoke a certificate.
 *
 * @param id     Certificate id.
 * @param signal Cancels the request.
 * @return The revoked certificate.
 */
export async function revokeIssued( id: number, signal?: AbortSignal ) {
	return parseIssued(
		await post< unknown >(
			`${ BASE }/${ positiveId( id ) }/revoke`,
			undefined,
			{ signal }
		)
	);
}

/**
 * Re-issue a certificate with the current name, course title and template.
 *
 * @param id     Certificate id.
 * @param signal Cancels the request.
 * @return The new certificate.
 */
export async function reissueIssued( id: number, signal?: AbortSignal ) {
	return parseIssued(
		await post< unknown >(
			`${ BASE }/${ positiveId( id ) }/reissue`,
			undefined,
			{ signal }
		)
	);
}

/**
 * Search learners by name, e-mail or user name (at most 20).
 *
 * @param search Text.
 * @param signal Cancels the request.
 * @return Learners.
 */
export async function searchLearners( search: string, signal?: AbortSignal ) {
	const value = await get< unknown >( `${ BASE }/learners`, {
		signal,
		query: { search: search.trim() },
	} );
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( parseLearner );
}

/**
 * Courses for the course filter and the issue form (first 50 by title search).
 *
 * @param search Title search.
 * @param signal Cancels the request.
 * @return Courses.
 */
export async function listCourseOptions(
	search: string,
	signal?: AbortSignal
) {
	return (
		await getCollection(
			'/ohmylms/v1/certificates/courses',
			parseCourseChoice,
			{
				signal,
				query: { search: search.trim(), page: 1, per_page: 50 },
			}
		)
	).items;
}

/**
 * Templates for the template filter and the issue form (newest 100).
 *
 * @param signal Cancels the request.
 * @return Template ids and names.
 */
export async function listTemplateOptions( signal?: AbortSignal ) {
	return (
		await getCollection( '/ohmylms/v1/certificates', parseCertificate, {
			signal,
			query: { page: 1, per_page: 100, status: 'all' },
		} )
	).items.map( ( item ) => ( {
		id: item.id,
		name: item.name,
		status: item.status,
	} ) );
}
