import { __, sprintf } from '@wordpress/i18n';
import { ApiError, get, post } from '../../lib/api';
import { positiveId } from './model';

/** Largest quiz file, in bytes. */
export const MAX_QUIZ_FILE_BYTES = 10485760;

/** Longest quiz name. */
export const MAX_QUIZ_NAME = 200;

export interface QuizFileSummary {
	questions: number;
	points: number;
	types: Record< string, number >;
	randomized: number;
	media: number;
}

/** A checked (dry run, with `review`) or imported quiz file. */
export interface QuizImportResult {
	id: number | null;
	name: string;
	renamed: boolean;
	format: string;
	dry_run: boolean;
	summary: QuizFileSummary;
	warnings: string[];
	review: string | null;
}

/** A downloadable quiz file. */
export interface QuizFile {
	filename: string;
	mime: string;
	content: string;
	warnings: string[];
}

/** One problem of a refused file; `question` 0 is the file as a whole. */
export interface QuizFileProblem {
	question: number;
	message: string;
}

function invalid(): never {
	throw new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned an invalid quiz file report.',
			'ohmylms'
		),
	} );
}

function record( value: unknown ): Record< string, unknown > {
	return value && 'object' === typeof value && ! Array.isArray( value )
		? ( value as Record< string, unknown > )
		: invalid();
}

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}

function warnings( value: unknown ): string[] {
	if ( undefined === value ) {
		return [];
	}
	if ( ! Array.isArray( value ) ) {
		return invalid();
	}
	return value.map( ( entry ) => {
		if ( 'string' === typeof entry ) {
			return entry;
		}
		const row = record( entry );
		return 'string' === typeof row.message ? row.message : invalid();
	} );
}

/**
 * Parse a check or import result.
 *
 * @param value Parsed JSON.
 * @return The result.
 */
export function parseQuizImport( value: unknown ): QuizImportResult {
	const body = record( value );
	if ( 'boolean' !== typeof body.dry_run || 'string' !== typeof body.name ) {
		return invalid();
	}
	const review = body.review ?? null;
	if (
		body.dry_run &&
		( 'string' !== typeof review || ! /^[a-f0-9]{64}$/.test( review ) )
	) {
		return invalid();
	}
	const summary = record( body.summary );
	return {
		id: body.dry_run ? null : positiveId( body.id ),
		name: body.name,
		renamed: true === body.renamed,
		format: 'string' === typeof body.format ? body.format : '',
		dry_run: body.dry_run,
		summary: {
			questions: count( summary.questions ),
			points: Number( summary.points ) || 0,
			types: Object.fromEntries(
				Object.entries( record( summary.types ?? {} ) ).map(
					( [ type, total ] ) => [ type, count( total ) ]
				)
			),
			randomized: count( summary.randomized ?? 0 ),
			media: count( summary.media ?? 0 ),
		},
		warnings: warnings( body.warnings ),
		review: 'string' === typeof review ? review : null,
	};
}

/**
 * Parse an exported quiz file.
 *
 * @param value Parsed JSON.
 * @return The file.
 */
export function parseQuizFile( value: unknown ): QuizFile {
	const body = record( value );
	if (
		'string' !== typeof body.filename ||
		'string' !== typeof body.content ||
		'string' !== typeof body.mime
	) {
		return invalid();
	}
	return {
		filename: body.filename,
		mime: body.mime,
		content: body.content,
		warnings: warnings( body.warnings ),
	};
}

/**
 * Problems of a refused file (`ohmylms_quiz_import_invalid`), or an empty list.
 *
 * @param error Thrown value.
 * @return Problems with their question numbers.
 */
export function fileProblems( error: unknown ): QuizFileProblem[] {
	if ( ! ( error instanceof ApiError ) || ! error.details ) {
		return [];
	}
	const details = error.details as { errors?: unknown };
	if ( ! Array.isArray( details.errors ) ) {
		return [];
	}
	return details.errors.flatMap( ( entry ) => {
		if ( ! entry || 'object' !== typeof entry ) {
			return [];
		}
		const row = entry as Record< string, unknown >;
		return 'string' === typeof row.message
			? [
					{
						question: Number( row.question ) || 0,
						message: row.message,
					},
				]
			: [];
	} );
}

/**
 * Describe a problem with its question number.
 *
 * @param problem Problem.
 * @return Text.
 */
export function problemText( problem: QuizFileProblem ): string {
	return problem.question
		? sprintf(
				/* translators: 1: question number, 2: problem. */
				__( 'Question %1$d: %2$s', 'ohmylms' ),
				problem.question,
				problem.message
			)
		: problem.message;
}

/**
 * Check a file before it is sent.
 *
 * @param content File text.
 * @param name    Optional quiz name.
 * @return A message, or null.
 */
export function quizFileProblem(
	content: string,
	name: string
): string | null {
	if ( ! content.trim() ) {
		return __( 'Choose a quiz file or paste its text.', 'ohmylms' );
	}
	if ( new TextEncoder().encode( content ).length > MAX_QUIZ_FILE_BYTES ) {
		return __( 'The file is larger than 10 MB.', 'ohmylms' );
	}
	if ( Array.from( name.trim() ).length > MAX_QUIZ_NAME ) {
		return sprintf(
			/* translators: %d: maximum name length. */
			__( 'Use at most %d characters for the quiz name.', 'ohmylms' ),
			MAX_QUIZ_NAME
		);
	}
	return null;
}

/**
 * Whether an import was refused because it is not the checked file.
 *
 * @param error Thrown value.
 * @return True for 409 `ohmylms_quiz_import_review_stale`.
 */
export function isQuizReviewStale( error: unknown ): boolean {
	return (
		error instanceof ApiError &&
		'ohmylms_quiz_import_review_stale' === error.code
	);
}

/**
 * Check a quiz file without creating anything.
 *
 * @param content File text.
 * @param name    Quiz name, or '' for the name in the file.
 * @param signal  Cancels the request.
 * @return The summary and review token.
 */
export async function checkQuizFile(
	content: string,
	name: string,
	signal?: AbortSignal
) {
	return parseQuizImport(
		await post< unknown >(
			'/ohmylms/v1/quiz/import',
			{ content, name: name.trim(), format: 'auto', dry_run: true },
			{ signal }
		)
	);
}

/**
 * Import the checked file as a new draft quiz.
 *
 * @param content The checked text.
 * @param name    The checked name.
 * @param review  Review token of the check.
 * @param signal  Cancels the request.
 * @return The new quiz.
 */
export async function importQuizFile(
	content: string,
	name: string,
	review: string,
	signal?: AbortSignal
) {
	return parseQuizImport(
		await post< unknown >(
			'/ohmylms/v1/quiz/import',
			{
				content,
				name: name.trim(),
				format: 'auto',
				dry_run: false,
				review,
			},
			{ signal }
		)
	);
}

/**
 * Export a quiz and all of its questions.
 *
 * @param id     Quiz id.
 * @param signal Cancels the request.
 * @return The file.
 */
export async function exportQuiz( id: number, signal?: AbortSignal ) {
	return parseQuizFile(
		await get< unknown >( `/ohmylms/v1/quiz/${ positiveId( id ) }/export`, {
			signal,
		} )
	);
}
