import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseCertificate,
	parseCourseChoice,
	parseMedia,
	parseOptions,
	parsePreview,
	positiveId,
	record,
	toPreviewInput,
} from './model';
import type { CertificateInput } from './types';

const BASE = '/ohmylms/v1/certificates';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

/** Rows per page in the template list. */
export const PER_PAGE = 20;

/**
 * List templates, newest first.
 *
 * @param page   Page number, from 1.
 * @param search Name search; empty for all.
 * @param status `all`, `draft` or `publish`.
 * @param signal Cancels the request.
 * @return Validated templates and pagination totals.
 */
export function listCertificates(
	page: number,
	search: string,
	status: string,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseCertificate, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search,
			status,
			orderby: 'created_at',
			order: 'desc',
		},
	} );
}

/**
 * Read one template.
 *
 * @param id     Template id.
 * @param signal Cancels the request.
 * @return The validated template.
 */
export async function getCertificate( id: number, signal?: AbortSignal ) {
	return parseCertificate(
		await get< unknown >( itemPath( id ), { signal } )
	);
}

/**
 * Read the server's choices, placeholders and limits.
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
 * Create a template with only a name; the server supplies the default layout.
 *
 * @param name   Template name.
 * @param signal Cancels the request.
 * @return The created template.
 */
export async function createCertificate( name: string, signal?: AbortSignal ) {
	return parseCertificate(
		await post< unknown >( BASE, { name: name.trim() }, { signal } )
	);
}

/**
 * Save every editable field of a template.
 *
 * @param id     Template id.
 * @param input  Validated payload.
 * @param signal Cancels the request.
 * @return The saved template as the server stored it.
 */
export async function updateCertificate(
	id: number,
	input: CertificateInput,
	signal?: AbortSignal
) {
	return parseCertificate(
		await put< unknown >( itemPath( id ), input, { signal } )
	);
}

/**
 * Permanently delete a template.
 *
 * @param id     Template id.
 * @param signal Cancels the request.
 */
export async function deleteCertificate( id: number, signal?: AbortSignal ) {
	const response = record(
		await del< unknown >( itemPath( id ), { signal } )
	);
	if ( true !== response.deleted ) {
		throw invalidResponse();
	}
}

/**
 * Duplicate a template as a draft without course assignments.
 *
 * @param id     Template to copy.
 * @param signal Cancels the request.
 * @return The new template.
 */
export async function duplicateCertificate( id: number, signal?: AbortSignal ) {
	return parseCertificate(
		await post< unknown >( `${ itemPath( id ) }/duplicate`, undefined, {
			signal,
		} )
	);
}

/**
 * Render a server-side preview of the (possibly unsaved) layout.
 *
 * @param input  Validated payload.
 * @param signal Cancels the request.
 * @return A complete HTML document for a sandboxed frame, and the page size.
 */
export async function previewCertificate(
	input: CertificateInput,
	signal?: AbortSignal
) {
	return parsePreview(
		await post< unknown >( `${ BASE }/preview`, toPreviewInput( input ), {
			signal,
		} )
	);
}

/**
 * Search courses that can be assigned.
 *
 * @param search Title search.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated courses and totals.
 */
export function listCourses(
	search: string,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/courses`, parseCourseChoice, {
		signal,
		query: { search, page, per_page: 10 },
	} );
}

/**
 * Browse the media library through the core REST route (images of supported types only).
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
			per_page: 12,
			media_type: 'image',
			mime_type: 'image/jpeg,image/png,image/gif,image/webp',
			orderby: 'date',
			order: 'desc',
			_fields: 'id,source_url,mime_type,title,media_details',
		},
	} );
}
