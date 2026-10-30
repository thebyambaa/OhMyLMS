import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseCertificateAssignment,
	parseCourse,
	parseCourseDetail,
	parseMedia,
	parseOptions,
	positiveId,
	record,
} from './model';
import type { CourseFilters, CourseInput } from './types';

const BASE = '/ohmylms/v1/courses';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

/** Rows per page in the course list. */
export const PER_PAGE = 20;

/** Images per page in the media picker. */
export const MEDIA_PER_PAGE = 12;

/**
 * List courses, newest first. Empty filters are not sent.
 *
 * @param page    Page number, from 1.
 * @param filters Search text, status, price type and creation dates.
 * @param signal  Cancels the request.
 * @return Validated courses and pagination totals.
 */
export function listCourses(
	page: number,
	filters: CourseFilters,
	signal?: AbortSignal
) {
	const query: Record< string, string | number > = {
		page,
		per_page: PER_PAGE,
		orderby: 'created_at',
		order: 'desc',
		status: filters.status || 'all',
	};
	if ( filters.search ) {
		query.search = filters.search;
	}
	if ( filters.price_type && 'all' !== filters.price_type ) {
		query.price_type = filters.price_type;
	}
	if ( filters.created_after ) {
		query.created_after = filters.created_after;
	}
	if ( filters.created_before ) {
		query.created_before = filters.created_before;
	}
	return getCollection( BASE, parseCourse, { signal, query } );
}

/**
 * Read one course with its text fields (and password, for users who can edit it).
 *
 * @param id     Course id.
 * @param signal Cancels the request.
 * @return The validated course.
 */
export async function getCourse( id: number, signal?: AbortSignal ) {
	return parseCourseDetail(
		await get< unknown >( itemPath( id ), {
			signal,
			query: { context: 'edit' },
		} )
	);
}

/**
 * Read the server's choices, currency settings, limits and the user's abilities.
 *
 * @param signal Cancels the request.
 * @return Validated options.
 */
export async function getOptions( signal?: AbortSignal ) {
	return parseOptions(
		await get< unknown >( `${ BASE }/options`, { signal } )
	);
}

/**
 * Create a draft course with only a title.
 *
 * @param title  Course title.
 * @param signal Cancels the request.
 * @return The created course.
 */
export async function createCourse( title: string, signal?: AbortSignal ) {
	return parseCourseDetail(
		await post< unknown >( BASE, { title: title.trim() }, { signal } )
	);
}

/**
 * Save the editable fields of a course.
 *
 * @param id     Course id.
 * @param input  Validated payload.
 * @param signal Cancels the request.
 * @return The saved course as the server stored it.
 */
export async function updateCourse(
	id: number,
	input: CourseInput,
	signal?: AbortSignal
) {
	return parseCourseDetail(
		await put< unknown >( itemPath( id ), input, { signal } )
	);
}

/**
 * Move a course to the trash.
 *
 * @param id     Course id.
 * @param signal Cancels the request.
 * @return The trashed course.
 */
export async function trashCourse( id: number, signal?: AbortSignal ) {
	return parseCourseDetail(
		await del< unknown >( itemPath( id ), { signal } )
	);
}

/**
 * Restore a trashed course (it comes back as a draft).
 *
 * @param id     Course id.
 * @param signal Cancels the request.
 * @return The restored course.
 */
export async function restoreCourse( id: number, signal?: AbortSignal ) {
	return parseCourseDetail(
		await post< unknown >( `${ itemPath( id ) }/restore`, undefined, {
			signal,
		} )
	);
}

/**
 * Permanently delete a course (refused while students are enrolled).
 *
 * @param id     Course id.
 * @param signal Cancels the request.
 */
export async function deleteCourse( id: number, signal?: AbortSignal ) {
	const response = record(
		await del< unknown >( itemPath( id ), {
			signal,
			query: { force: 'true' },
		} )
	);
	if ( true !== response.deleted ) {
		throw invalidResponse();
	}
}

/**
 * Duplicate a course as a draft without students.
 *
 * @param id     Course to copy.
 * @param signal Cancels the request.
 * @return The new course.
 */
export async function duplicateCourse( id: number, signal?: AbortSignal ) {
	return parseCourseDetail(
		await post< unknown >( `${ itemPath( id ) }/duplicate`, undefined, {
			signal,
		} )
	);
}

const certificatePath = ( id: number ) =>
	`/ohmylms/v1/authoring/courses/${ positiveId( id ) }/certificate`;

/**
 * Read the certificate template a course awards and the templates the user may assign.
 *
 * @param id     Course id.
 * @param signal Cancels the request.
 * @return The validated assignment.
 */
export async function getCourseCertificate( id: number, signal?: AbortSignal ) {
	const result = parseCertificateAssignment(
		await get< unknown >( certificatePath( id ), { signal } )
	);
	if ( result.course_id !== id ) {
		throw invalidResponse();
	}
	return result;
}

/**
 * Replace the course's certificate template. A change made elsewhere since `expected` answers 409.
 *
 * @param id            Course id.
 * @param certificateId Template id, or 0 for none.
 * @param expected      Template id that was read.
 * @param signal        Cancels the request.
 * @return The stored assignment.
 */
export async function saveCourseCertificate(
	id: number,
	certificateId: number,
	expected: number,
	signal?: AbortSignal
) {
	const result = parseCertificateAssignment(
		await put< unknown >(
			certificatePath( id ),
			{
				certificate_id: certificateId,
				expected_certificate_id: expected,
			},
			{ signal }
		)
	);
	if ( result.course_id !== id ) {
		throw invalidResponse();
	}
	return result;
}

/**
 * List images for the thumbnail picker through the core media route.
 *
 * @param search Title search.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated images and totals.
 */
export function listMedia(
	search: string,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( '/wp/v2/media', parseMedia, {
		signal,
		query: {
			search,
			page,
			per_page: MEDIA_PER_PAGE,
			media_type: 'image',
			mime_type: 'image/jpeg,image/png,image/gif,image/webp',
			orderby: 'date',
			order: 'desc',
			_fields: 'id,source_url,mime_type,title,media_details',
		},
	} );
}
