import { __, sprintf } from '@wordpress/i18n';
import { createValidators } from '../../lib/validation';
import { invalidResponse } from './model';

const { record, text } = createValidators( invalidResponse );

/** Why the server cannot render PDF pages (`GET /exam-imports/renderer`). */
export type RendererReason =
	| ''
	| 'not_configured'
	| 'process_disabled'
	| 'binary_unusable'
	| 'storage'
	| 'failed';

/** `GET /exam-imports/renderer`. */
export interface RendererStatus {
	available: boolean;
	/** `poppler`, or `imagick` when Imagick (Ghostscript) stands in for Poppler; empty when unavailable. */
	engine: string;
	version: string;
	reason: RendererReason;
	/** Why Poppler is not the engine; empty when it is. */
	poppler_reason: RendererReason;
	/** Crops can be queued: a renderer and Action Scheduler. */
	crops: boolean;
	max_side: number;
	/** Imagick lists PDF support; it renders only when it also reads a test page. */
	imagick: boolean;
}

/** `GET /exam-imports/jobs/{id}/source`: what the side-by-side viewer can show. */
export interface SourceInfo {
	/** Pages of the paper, 0 when not counted yet. */
	page_count: number;
	renderer: RendererStatus;
	/** Authenticated page image address with a `{page}` placeholder. */
	page_url: string;
	/** Authenticated PDF address of the paper. */
	document: string;
	/** Authenticated PDF address of the mark scheme, or empty. */
	mark_scheme: string;
}

const REASONS: readonly RendererReason[] = [
	'',
	'not_configured',
	'process_disabled',
	'binary_unusable',
	'storage',
	'failed',
];

function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function whole( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function address( value: unknown, optional = false ): string {
	const url = text( value );
	if ( ( optional && '' === url ) || /^https?:\/\//i.test( url ) ) {
		return url;
	}
	throw invalidResponse();
}

/**
 * Validate the renderer probe.
 *
 * @param value Parsed JSON.
 * @return The status.
 */
export function parseRenderer( value: unknown ): RendererStatus {
	const item = record( value );
	const reason = REASONS.find( ( entry ) => entry === item.reason );
	const popplerReason = REASONS.find(
		( entry ) => entry === ( item.poppler_reason ?? '' )
	);
	if ( undefined === reason || undefined === popplerReason ) {
		throw invalidResponse();
	}
	return {
		available: flag( item.available ),
		engine: text( item.engine ),
		version: text( item.version ),
		reason,
		poppler_reason: popplerReason,
		crops: flag( item.crops ),
		max_side: whole( item.max_side ),
		imagick: flag( item.imagick ),
	};
}

/**
 * Validate the source descriptor of a job.
 *
 * @param value Parsed JSON.
 * @return The descriptor.
 */
export function parseSource( value: unknown ): SourceInfo {
	const item = record( value );
	const pageUrl = address( item.page_url );
	if ( ! pageUrl.includes( '{page}' ) ) {
		throw invalidResponse();
	}
	return {
		page_count: whole( item.page_count ),
		renderer: parseRenderer( item.renderer ),
		page_url: pageUrl,
		document: address( item.document ),
		mark_scheme: address( item.mark_scheme, true ),
	};
}

/**
 * Address of one page image.
 *
 * @param source Source descriptor.
 * @param page   One-based page.
 * @return URL.
 */
export function pageImageUrl( source: SourceInfo, page: number ): string {
	return source.page_url.replace( '{page}', String( Math.max( 1, page ) ) );
}

/**
 * Address of the paper opened at a page in the browser's PDF viewer.
 *
 * @param source Source descriptor.
 * @param page   One-based page.
 * @return URL.
 */
export function documentUrl( source: SourceInfo, page: number ): string {
	return `${ source.document }#page=${ Math.max( 1, page ) }`;
}

/**
 * What the author reads about the renderer: what works, and what to do when pages cannot be rendered.
 *
 * @param status Renderer status.
 * @return Notice status and text.
 */
export function rendererMessage( status: RendererStatus ): {
	tone: 'success' | 'warning';
	text: string;
} {
	if ( status.available && 'imagick' === status.engine ) {
		return {
			tone: 'success',
			text: [
				sprintf(
					/* translators: 1: ImageMagick version; 2: largest side in pixels. */
					__(
						'Pages are rendered on this server with Imagick (ImageMagick %1$s and Ghostscript, up to %2$d pixels), because Poppler is not set up.',
						'ohmylms'
					),
					status.version,
					status.max_side
				),
				status.crops
					? __( 'Draw on a page to crop a figure.', 'ohmylms' )
					: __(
							'The import scheduler is unavailable, so crops cannot be made.',
							'ohmylms'
						),
			].join( ' ' ),
		};
	}
	if ( status.available ) {
		return {
			tone: 'success',
			text: status.crops
				? sprintf(
						/* translators: 1: Poppler version; 2: largest side in pixels. */
						__(
							'Pages are rendered on this server with Poppler %1$s (up to %2$d pixels). Draw on a page to crop a figure.',
							'ohmylms'
						),
						status.version,
						status.max_side
					)
				: sprintf(
						/* translators: %s: Poppler version. */
						__(
							'Pages are rendered with Poppler %s, but the import scheduler is unavailable, so crops cannot be made.',
							'ohmylms'
						),
						status.version
					),
		};
	}
	const reasons: Record< Exclude< RendererReason, '' >, string > = {
		not_configured: __(
			'No PDF renderer is configured. Ask your site administrator to install Poppler and set OHMYLMS_PDFTOPPM_BINARY in wp-config.php to the full path of pdftoppm.',
			'ohmylms'
		),
		process_disabled: __(
			'PHP on this server cannot run the PDF renderer because proc_open is disabled. Ask your host to enable it for this site.',
			'ohmylms'
		),
		binary_unusable: __(
			'OHMYLMS_PDFTOPPM_BINARY does not point to an executable pdftoppm file. Ask your site administrator to correct the path.',
			'ohmylms'
		),
		storage: __(
			'The private import folder (OHMYLMS_IMPORT_PRIVATE_DIR) is missing, not writable or inside the web root.',
			'ohmylms'
		),
		failed: __(
			'The configured PDF renderer did not answer as pdftoppm. Ask your site administrator to check the Poppler installation.',
			'ohmylms'
		),
	};
	const parts = [
		reasons[ status.reason || 'failed' ],
		__(
			"Until then, pages open in the browser's PDF viewer and figure crops cannot be made.",
			'ohmylms'
		),
	];
	if ( status.imagick ) {
		parts.push(
			__(
				'Imagick is installed but could not read a test PDF page, so it cannot stand in for Poppler: Ghostscript is missing or the ImageMagick security policy blocks PDF.',
				'ohmylms'
			)
		);
	}
	return { tone: 'warning', text: parts.join( ' ' ) };
}
