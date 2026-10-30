import { __ } from '@wordpress/i18n';
import { ApiError, post } from '../../lib/api';
import { positiveId } from './model';
import type { QuestionListQuery } from './types';

/** Most rows in one import and questions in one export. */
export const MAX_EXCHANGE_ROWS = 200;

/** Largest import text, in bytes. */
export const MAX_IMPORT_BYTES = 2000000;

export type ImportFormat = 'auto' | 'csv' | 'json';
export type ExportFormat = 'csv' | 'json';
export type RowStatus = 'create' | 'update' | 'unchanged' | 'error';

export interface ImportRowResult {
	row: number;
	key: string;
	status: RowStatus;
	id: number;
	errors: string[];
	warnings: string[];
}

/** A check (`dry_run`, with `review`) or an import report. */
export interface ImportReport {
	dry_run: boolean;
	committed: boolean;
	approved: number;
	counts: Record< RowStatus, number >;
	rows: ImportRowResult[];
	review: string | null;
	stopped: string | null;
}

export interface ImportOptions {
	skip_invalid: boolean;
	approve: boolean;
}

/** A downloadable export. */
export interface ExportFile {
	filename: string;
	mime: string;
	content: string;
	count: number;
	skipped: number;
	warnings: string[];
}

const STATUSES: readonly RowStatus[] = [
	'create',
	'update',
	'unchanged',
	'error',
];

function invalid(): never {
	throw new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned an invalid import report.',
			'ohmylms'
		),
	} );
}

function record( value: unknown ): Record< string, unknown > {
	return value && 'object' === typeof value && ! Array.isArray( value )
		? ( value as Record< string, unknown > )
		: invalid();
}

function strings( value: unknown ): string[] {
	if ( ! Array.isArray( value ) ) {
		return invalid();
	}
	return value.map( ( entry ) =>
		'string' === typeof entry ? entry : invalid()
	);
}

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

/**
 * Parse an import report.
 *
 * @param value Parsed JSON.
 * @return The report.
 */
export function parseImportReport( value: unknown ): ImportReport {
	const body = record( value );
	if ( ! Array.isArray( body.rows ) || 'boolean' !== typeof body.dry_run ) {
		return invalid();
	}
	const review = body.review ?? null;
	if (
		null !== review &&
		( 'string' !== typeof review || ! /^[a-f0-9]{64}$/.test( review ) )
	) {
		return invalid();
	}
	const counts = record( body.counts );
	return {
		dry_run: body.dry_run,
		committed: true === body.committed,
		approved: count( body.approved ?? 0 ),
		counts: {
			create: count( counts.create ?? 0 ),
			update: count( counts.update ?? 0 ),
			unchanged: count( counts.unchanged ?? 0 ),
			error: count( counts.error ?? 0 ),
		},
		rows: body.rows.map( ( entry ) => {
			const row = record( entry );
			const status = STATUSES.find( ( known ) => known === row.status );
			return {
				row: positiveId( row.row ),
				key: 'string' === typeof row.key ? row.key : '',
				status: status ?? invalid(),
				id: count( row.id ?? 0 ),
				errors: strings( row.errors ?? [] ),
				warnings: strings( row.warnings ?? [] ),
			};
		} ),
		review,
		stopped: 'string' === typeof body.stopped ? body.stopped : null,
	};
}

/**
 * Parse an export file.
 *
 * @param value Parsed JSON.
 * @return The file.
 */
export function parseExportFile( value: unknown ): ExportFile {
	const body = record( value );
	if (
		'string' !== typeof body.filename ||
		'string' !== typeof body.content ||
		! [ 'text/csv', 'application/json' ].includes( String( body.mime ) )
	) {
		return invalid();
	}
	return {
		filename: body.filename,
		mime: String( body.mime ),
		content: body.content,
		count: count( body.count ),
		skipped: count( body.skipped ?? 0 ),
		warnings: strings( body.warnings ?? [] ),
	};
}

/**
 * Check import text before it is sent.
 *
 * @param text Pasted or loaded text.
 * @return A message, or null.
 */
export function importProblem( text: string ): string | null {
	if ( ! text.trim() ) {
		return __( 'Paste rows or choose a CSV or JSON file.', 'ohmylms' );
	}
	if ( new TextEncoder().encode( text ).length > MAX_IMPORT_BYTES ) {
		return __( 'The file must be smaller than 2 MB.', 'ohmylms' );
	}
	return null;
}

/**
 * Whether the reviewed report can be imported with these options.
 *
 * @param report      Check result.
 * @param skipInvalid Import valid rows even when others have errors.
 * @return True when something would be written.
 */
export function canImport( report: ImportReport, skipInvalid: boolean ) {
	const writes = report.counts.create + report.counts.update;
	return writes > 0 && ( skipInvalid || 0 === report.counts.error );
}

/**
 * Whether an error means the reviewed check is out of date.
 *
 * @param error Thrown value.
 * @return True for 409 `ohmylms_import_review_stale`.
 */
export function isReviewStale( error: unknown ): boolean {
	return (
		error instanceof ApiError &&
		'ohmylms_import_review_stale' === error.code
	);
}

/**
 * Check rows without writing.
 *
 * @param text   CSV or JSON text.
 * @param format Format, or auto.
 * @param signal Cancels the request.
 * @return The report with its review token.
 */
export async function checkImport(
	text: string,
	format: ImportFormat,
	signal?: AbortSignal
) {
	return parseImportReport(
		await post< unknown >(
			'/ohmylms/v1/question-bank/import',
			{ text, format, dry_run: true },
			{ signal }
		)
	);
}

/**
 * Import checked rows. A changed check answers 409 `ohmylms_import_review_stale`.
 *
 * @param text    The checked text.
 * @param format  Format.
 * @param review  Review token of the check.
 * @param options Skip invalid rows, approve.
 * @param signal  Cancels the request.
 * @return The import report.
 */
export async function applyImport(
	text: string,
	format: ImportFormat,
	review: string,
	options: ImportOptions,
	signal?: AbortSignal
) {
	return parseImportReport(
		await post< unknown >(
			'/ohmylms/v1/question-bank/import',
			{ text, format, dry_run: false, review, ...options },
			{ signal }
		)
	);
}

/** What to export: selected IDs, or the list's current filters. */
export type ExportScope =
	{ ids: readonly number[] } | { filters: Omit< QuestionListQuery, 'page' > };

/**
 * Export questions as import rows.
 *
 * @param scope  Selected IDs or list filters.
 * @param format CSV or JSON.
 * @param signal Cancels the request.
 * @return The file.
 */
export async function exportQuestions(
	scope: ExportScope,
	format: ExportFormat,
	signal?: AbortSignal
) {
	const body =
		'ids' in scope
			? { format, ids: scope.ids.map( positiveId ) }
			: {
					format,
					filters: {
						status: scope.filters.status,
						...( scope.filters.search.trim()
							? { search: scope.filters.search.trim() }
							: {} ),
						...( scope.filters.type
							? { type: scope.filters.type }
							: {} ),
						...( scope.filters.difficulty
							? { difficulty: scope.filters.difficulty }
							: {} ),
					},
				};
	return parseExportFile(
		await post< unknown >( '/ohmylms/v1/questions/export', body, {
			signal,
		} )
	);
}

/**
 * Offer a file to the browser as a download.
 *
 * @param file Export file: name, MIME type and text.
 */
export function saveFile(
	file: Pick< ExportFile, 'filename' | 'mime' | 'content' >
) {
	const url = URL.createObjectURL(
		new Blob( [ file.content ], { type: `${ file.mime };charset=utf-8` } )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = file.filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

/**
 * Translated row status.
 *
 * @param status Status.
 * @return Label.
 */
export function rowStatusLabel( status: RowStatus ): string {
	return {
		create: __( 'New question', 'ohmylms' ),
		update: __( 'Update', 'ohmylms' ),
		unchanged: __( 'Unchanged', 'ohmylms' ),
		error: __( 'Error', 'ohmylms' ),
	}[ status ];
}
