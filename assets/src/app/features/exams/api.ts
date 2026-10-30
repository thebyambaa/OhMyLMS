import apiFetch from '@wordpress/api-fetch';
import { del, get, normalizeApiError, patch, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseBankQuestion,
	parseDryRun,
	parseExam,
	parseExamDetail,
	parseFigure,
	parseJob,
	parseJobDetail,
	parseSkill,
	positiveId,
	record,
} from './model';
import type {
	ApplyInput,
	CompositionInput,
	ImportStart,
	RowChanges,
	SettingsInput,
	StatusFilter,
} from './types';

const EXAMS = '/ohmylms/v1/exams/library';
const JOBS = '/ohmylms/v1/exam-imports/jobs';
const examPath = ( id: number ) => `${ EXAMS }/${ positiveId( id ) }`;
const jobPath = ( id: number ) => `${ JOBS }/${ positiveId( id ) }`;
const rowPath = ( id: number, index: number ) => {
	if ( ! Number.isSafeInteger( index ) || index < 0 ) {
		throw invalidResponse();
	}
	return `${ jobPath( id ) }/rows/${ index }`;
};

/** Rows per page in the exam and import lists. */
export const PER_PAGE = 20;

/** Rows per page in the question bank picker. */
export const BANK_PER_PAGE = 10;

/** Filters of the exam list. */
export interface ExamFilters {
	search: string;
	status: StatusFilter;
}

/**
 * List the author's exams, newest first.
 *
 * @param page    Page, from 1.
 * @param filters Search and status.
 * @param signal  Cancels the request.
 * @return Validated exams and totals.
 */
export function listExams(
	page: number,
	filters: ExamFilters,
	signal?: AbortSignal
) {
	return getCollection( EXAMS, parseExam, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			status: filters.status,
			...( filters.search ? { search: filters.search } : {} ),
		},
	} );
}

/**
 * Create a blank draft exam.
 *
 * @param title           Title.
 * @param durationMinutes Duration.
 * @param signal          Cancels the request.
 * @return The new exam.
 */
export async function createExam(
	title: string,
	durationMinutes: number,
	signal?: AbortSignal
) {
	return parseExamDetail(
		await post< unknown >(
			EXAMS,
			{ title: title.trim(), duration_minutes: durationMinutes },
			{ signal }
		)
	);
}

/**
 * Read one exam with settings and composition.
 *
 * @param id     Exam ID.
 * @param signal Cancels the request.
 * @return The exam.
 */
export async function getExam( id: number, signal?: AbortSignal ) {
	const exam = parseExamDetail(
		await get< unknown >( examPath( id ), { signal } )
	);
	if ( exam.id !== id ) {
		throw invalidResponse();
	}
	return exam;
}

/**
 * Save settings; a stale `revision` answers 409.
 *
 * @param id     Exam ID.
 * @param input  Settings.
 * @param signal Cancels the request.
 * @return The saved exam.
 */
export async function updateExam(
	id: number,
	input: SettingsInput,
	signal?: AbortSignal
) {
	return parseExamDetail(
		await patch< unknown >( examPath( id ), input, { signal } )
	);
}

/**
 * Replace the composition; a stale `revision` answers 409.
 *
 * @param id       Exam ID.
 * @param items    Sections and questions in order.
 * @param revision Revision the edit started from.
 * @param signal   Cancels the request.
 * @return The saved exam.
 */
export async function saveComposition(
	id: number,
	items: CompositionInput[],
	revision: string,
	signal?: AbortSignal
) {
	return parseExamDetail(
		await put< unknown >(
			`${ examPath( id ) }/composition`,
			{ revision, items },
			{ signal }
		)
	);
}

/**
 * Questions the author may add to an exam.
 *
 * @param id     Exam ID.
 * @param search Search text.
 * @param page   Page, from 1.
 * @param signal Cancels the request.
 * @return Questions and totals.
 */
export function searchBank(
	id: number,
	search: string,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ examPath( id ) }/bank`, parseBankQuestion, {
		signal,
		query: {
			page,
			per_page: BANK_PER_PAGE,
			...( search ? { search } : {} ),
		},
	} );
}

/**
 * Publish an exam (approves its pinned versions, as the legacy screen did).
 *
 * @param id             Exam ID.
 * @param revision       Revision the author reviewed.
 * @param confirmOverlap Accept that practice uses of its questions stop working.
 * @param signal         Cancels the request.
 * @return The published exam.
 */
export async function publishExam(
	id: number,
	revision: string,
	confirmOverlap: boolean,
	signal?: AbortSignal
) {
	return parseExamDetail(
		await post< unknown >(
			`${ examPath( id ) }/publish`,
			{ revision, confirm_overlap: confirmOverlap },
			{ signal }
		)
	);
}

// Imports.

/**
 * The author's import jobs, newest first.
 *
 * @param page   Page, from 1.
 * @param signal Cancels the request.
 * @return Jobs and totals.
 */
export function listJobs( page: number, signal?: AbortSignal ) {
	return getCollection( JOBS, parseJob, {
		signal,
		query: { page, per_page: PER_PAGE },
	} );
}

function startBody( input: ImportStart, dryRun: boolean ): FormData {
	const body = new FormData();
	if ( input.file ) {
		body.append( 'file', input.file );
	}
	if ( input.markScheme ) {
		body.append( 'mark_scheme', input.markScheme );
	}
	if ( input.text ) {
		body.append( 'text', input.text );
	}
	body.append( 'target', input.target );
	body.append( 'format', input.format ?? 'auto' );
	if ( input.name ) {
		body.append( 'name', input.name );
	}
	body.append( 'skip_invalid', input.skipInvalid ? '1' : '0' );
	body.append( 'dry_run', dryRun ? '1' : '0' );
	return body;
}

async function upload(
	body: FormData,
	signal?: AbortSignal
): Promise< unknown > {
	try {
		return await apiFetch< unknown >( {
			path: JOBS,
			method: 'POST',
			body,
			...( signal ? { signal } : {} ),
		} );
	} catch ( error ) {
		throw normalizeApiError( error );
	}
}

/**
 * Start an import: a PDF goes to the private job pipeline, rows become a committed job.
 *
 * @param input  File or pasted rows.
 * @param signal Cancels the request.
 * @return The new job.
 */
export async function startImport( input: ImportStart, signal?: AbortSignal ) {
	return parseJobDetail( await upload( startBody( input, false ), signal ) );
}

/**
 * Check CSV or JSON rows without saving anything.
 *
 * @param input  File or pasted rows.
 * @param signal Cancels the request.
 * @return Per-row report.
 */
export async function checkRows( input: ImportStart, signal?: AbortSignal ) {
	return parseDryRun( await upload( startBody( input, true ), signal ) );
}

/**
 * Read one job with its review rows (also used to poll its state).
 *
 * @param id     Job ID.
 * @param signal Cancels the request.
 * @return The job.
 */
export async function getJob( id: number, signal?: AbortSignal ) {
	const job = parseJobDetail(
		await get< unknown >( jobPath( id ), { signal } )
	);
	if ( job.id !== id ) {
		throw invalidResponse();
	}
	return job;
}

/**
 * Delete a job and its private sources.
 *
 * @param id     Job ID.
 * @param signal Cancels the request.
 */
export async function deleteJob( id: number, signal?: AbortSignal ) {
	if (
		true !==
		record( await del< unknown >( jobPath( id ), { signal } ) ).deleted
	) {
		throw invalidResponse();
	}
}

/**
 * Start or resume extraction through the configured AI provider.
 *
 * @param id     Job ID.
 * @param signal Cancels the request.
 * @return The job.
 */
export async function extractJob( id: number, signal?: AbortSignal ) {
	return parseJobDetail(
		await post< unknown >( `${ jobPath( id ) }/extract`, {}, { signal } )
	);
}

/**
 * Edit one review row; the row returns to "needs review".
 *
 * @param id       Job ID.
 * @param index    Row index.
 * @param changes  Changed fields.
 * @param revision Review revision.
 * @param signal   Cancels the request.
 * @return The job.
 */
export async function updateRow(
	id: number,
	index: number,
	changes: RowChanges,
	revision: number,
	signal?: AbortSignal
) {
	return parseJobDetail(
		await patch< unknown >(
			rowPath( id, index ),
			{ ...changes, revision },
			{ signal }
		)
	);
}

/**
 * Accept or reject one review row.
 *
 * @param id       Job ID.
 * @param index    Row index.
 * @param decision accept or reject.
 * @param revision Review revision.
 * @param signal   Cancels the request.
 * @return The job.
 */
export async function decideRow(
	id: number,
	index: number,
	decision: 'accept' | 'reject',
	revision: number,
	signal?: AbortSignal
) {
	return parseJobDetail(
		await post< unknown >(
			`${ rowPath( id, index ) }/${ decision }`,
			{ revision },
			{ signal }
		)
	);
}

/**
 * Commit accepted rows and create the exam or quiz. Repeating it changes nothing.
 *
 * @param id       Job ID.
 * @param revision Review revision.
 * @param input    Title and duration.
 * @param signal   Cancels the request.
 * @return The job.
 */
export async function applyJob(
	id: number,
	revision: number,
	input: ApplyInput,
	signal?: AbortSignal
) {
	return parseJobDetail(
		await post< unknown >(
			`${ jobPath( id ) }/apply`,
			{ ...input, revision },
			{ signal }
		)
	);
}

/**
 * Crops of a job, with authorised image URLs.
 *
 * @param id     Job ID.
 * @param signal Cancels the request.
 * @return Crops.
 */
export async function listFigures( id: number, signal?: AbortSignal ) {
	const value = await get< unknown >( `${ jobPath( id ) }/figures`, {
		signal,
	} );
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( parseFigure );
}

/**
 * Queue a crop of the question paper.
 *
 * @param id     Job ID.
 * @param page   Source page.
 * @param bounds Normalised left, top, right, bottom.
 * @param signal Cancels the request.
 * @return The crop.
 */
export async function createFigure(
	id: number,
	page: number,
	bounds: [ number, number, number, number ],
	signal?: AbortSignal
) {
	return parseFigure(
		await post< unknown >(
			`${ jobPath( id ) }/figures`,
			{ page, bounds },
			{ signal }
		)
	);
}

/**
 * Search the skill catalogue for tagging.
 *
 * @param search Search text.
 * @param signal Cancels the request.
 * @return Matches.
 */
export async function searchSkills( search: string, signal?: AbortSignal ) {
	return getCollection( '/ohmylms/v1/exam-imports/skills', parseSkill, {
		signal,
		query: { search, page: 1 },
	} );
}
