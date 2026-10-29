import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	BlockForm,
	Certificate,
	CertificateInput,
	CertificateOptions,
	CertificateStatus,
	CourseChoice,
	CourseRef,
	FormErrors,
	FormState,
	Limits,
	MediaImage,
	Option,
	Orientation,
	Placeholder,
	Preview,
	SizeOption,
	TextAlign,
	TextBlock,
} from './types';

const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;
const BLOCK_ID = /^[A-Za-z0-9_-]{1,32}$/;
const PLACEHOLDER_TOKEN = /\{[^{}]*\}/g;
const ALIGNMENTS: readonly TextAlign[] = [ 'left', 'center', 'right' ];
const ORIENTATIONS: readonly Orientation[] = [ 'landscape', 'portrait' ];
const STATUSES: readonly CertificateStatus[] = [ 'draft', 'publish' ];

/** Allowed media types for the background and signature (mirrors the server allow-list). */
export const IMAGE_MIME_TYPES: readonly string[] = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
];

/**
 * Build the error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned invalid certificate data.',
			'ohmylms'
		),
	} );
}

/**
 * Narrow an unknown value to a plain object or fail with an invalid response.
 *
 * @param value Parsed JSON.
 * @return The object.
 */
export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function finite( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function boolean( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

/**
 * Validate a positive integer identifier.
 *
 * @param value Candidate id.
 * @return The id.
 */
export function positiveId( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 1
	) {
		throw invalidResponse();
	}
	return value;
}

function nonNegativeId( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Validate one text block from the server.
 *
 * @param value Parsed JSON.
 * @return The block.
 */
export function parseBlock( value: unknown ): TextBlock {
	const item = record( value );
	return {
		id: text( item.id ),
		text: text( item.text ),
		x: finite( item.x ),
		y: finite( item.y ),
		width: finite( item.width ),
		font_size: finite( item.font_size ),
		align: oneOf( item.align, ALIGNMENTS ),
		color: text( item.color ),
		bold: boolean( item.bold ),
	};
}

function parseCourseRef( value: unknown ): CourseRef {
	const item = record( value );
	return { id: positiveId( item.id ), title: text( item.title ) };
}

/**
 * Validate a template from the server.
 *
 * @param value Parsed JSON.
 * @return The template.
 */
export function parseCertificate( value: unknown ): Certificate {
	const item = record( value );
	const signature = record( item.signature );
	const courses = list( item.courses ).map( parseCourseRef );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		status: oneOf( item.status, STATUSES ),
		orientation: oneOf( item.orientation, ORIENTATIONS ),
		size: text( item.size ),
		font_family: text( item.font_family ),
		background_color: text( item.background_color ),
		background_id: nonNegativeId( item.background_id ),
		background_url: text( item.background_url ),
		signature: {
			image_id: nonNegativeId( signature.image_id ),
			x: finite( signature.x ),
			y: finite( signature.y ),
			width: finite( signature.width ),
		},
		signature_url: text( item.signature_url ),
		blocks: list( item.blocks ).map( parseBlock ),
		course_ids: list( item.course_ids ).map( positiveId ),
		courses,
		course_count: nonNegativeId( item.course_count ),
		created_at: text( item.created_at ),
		updated_at: text( item.updated_at ),
	};
}

function parseOptionList( value: unknown ): Option[] {
	const items = list( value );
	if ( ! items.length ) {
		throw invalidResponse();
	}
	return items.map( ( raw ) => {
		const option = record( raw );
		return { value: text( option.value ), label: text( option.label ) };
	} );
}

function parseLimits( value: unknown ): Limits {
	const item = record( value );
	return {
		max_blocks: positiveId( item.max_blocks ),
		max_text: positiveId( item.max_text ),
		min_font_size: positiveId( item.min_font_size ),
		max_font_size: positiveId( item.max_font_size ),
		max_courses: positiveId( item.max_courses ),
	};
}

/**
 * Validate the editor options from the server.
 *
 * @param value Parsed JSON.
 * @return The options.
 */
export function parseOptions( value: unknown ): CertificateOptions {
	const item = record( value );
	const sizes: SizeOption[] = list( item.sizes ).map( ( raw ) => {
		const size = record( raw );
		return {
			value: text( size.value ),
			label: text( size.label ),
			width_mm: finite( size.width_mm ),
			height_mm: finite( size.height_mm ),
		};
	} );
	const placeholders: Placeholder[] = list( item.placeholders ).map(
		( raw ) => {
			const placeholder = record( raw );
			return {
				value: text( placeholder.value ),
				label: text( placeholder.label ),
				sample: text( placeholder.sample ),
			};
		}
	);
	if ( ! sizes.length ) {
		throw invalidResponse();
	}
	return {
		orientations: parseOptionList( item.orientations ),
		sizes,
		font_families: parseOptionList( item.font_families ),
		alignments: parseOptionList( item.alignments ),
		statuses: parseOptionList( item.statuses ),
		placeholders,
		limits: parseLimits( item.limits ),
	};
}

/**
 * Validate a preview document from the server.
 *
 * @param value Parsed JSON.
 * @return The preview.
 */
export function parsePreview( value: unknown ): Preview {
	const item = record( value );
	const width = finite( item.width_mm );
	const height = finite( item.height_mm );
	if ( width <= 0 || height <= 0 ) {
		throw invalidResponse();
	}
	return { html: text( item.html ), width_mm: width, height_mm: height };
}

/**
 * Validate a course lookup row.
 *
 * @param value Parsed JSON.
 * @return The course.
 */
export function parseCourseChoice( value: unknown ): CourseChoice {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: text( item.status ),
	};
}

/**
 * Validate a WordPress media item; only supported image types are accepted.
 *
 * @param value Parsed JSON from `/wp/v2/media`.
 * @return The image.
 */
export function parseMedia( value: unknown ): MediaImage {
	const item = record( value );
	const mime = text( item.mime_type );
	if ( ! IMAGE_MIME_TYPES.includes( mime ) ) {
		throw invalidResponse();
	}
	const title = record( item.title );
	const url = text( item.source_url );
	let thumbnail = url;
	const details = item.media_details;
	if ( details && 'object' === typeof details ) {
		const sizes = ( details as Record< string, unknown > ).sizes;
		if ( sizes && 'object' === typeof sizes ) {
			const small = ( sizes as Record< string, unknown > ).thumbnail;
			if ( small && 'object' === typeof small ) {
				const sourceUrl = ( small as Record< string, unknown > )
					.source_url;
				if ( 'string' === typeof sourceUrl && sourceUrl ) {
					thumbnail = sourceUrl;
				}
			}
		}
	}
	return {
		id: positiveId( item.id ),
		url,
		thumbnail_url: thumbnail,
		title: text( title.rendered ),
	};
}

/* ---------------------------------------------------------------- placeholders */

/**
 * List the distinct `{token}` expressions in a text.
 *
 * @param value Text to scan.
 * @return Tokens in order of appearance.
 */
export function findPlaceholders( value: string ): string[] {
	return Array.from( new Set( value.match( PLACEHOLDER_TOKEN ) ?? [] ) );
}

/**
 * List the tokens in a text that the server does not know.
 *
 * @param value Text to scan.
 * @param known Registered tokens, for example `{student_name}`.
 * @return Unknown tokens.
 */
export function unknownPlaceholders(
	value: string,
	known: readonly string[]
): string[] {
	return findPlaceholders( value ).filter(
		( token ) => ! known.includes( token )
	);
}

/**
 * Append a placeholder to a text, separated by a space when needed.
 *
 * @param value Current text.
 * @param token Placeholder to add.
 * @return The new text.
 */
export function appendPlaceholder( value: string, token: string ): string {
	return value && ! /\s$/.test( value )
		? `${ value } ${ token }`
		: `${ value }${ token }`;
}

/**
 * Replace placeholders with sample values (used for the accessible text summary of a block).
 *
 * @param value        Text with tokens.
 * @param placeholders Registered placeholders with sample values.
 * @return Text with samples.
 */
export function applySamples(
	value: string,
	placeholders: readonly Placeholder[]
): string {
	return placeholders.reduce(
		( result, item ) => result.split( item.value ).join( item.sample ),
		value
	);
}

/* ---------------------------------------------------------------- geometry */

/**
 * Parse a number typed in a form field.
 *
 * @param value Field text.
 * @return The number, or `NaN` when empty or not numeric.
 */
export function parseNumber( value: string ): number {
	return '' === value.trim() ? NaN : Number( value );
}

function inRange( value: number, min: number, max: number ): boolean {
	return Number.isFinite( value ) && value >= min && value <= max;
}

/**
 * Validate a position and width given as percentages of the page.
 *
 * @param x     Left edge, 0 to 100.
 * @param y     Top edge, 0 to 100.
 * @param width Width, 1 to 100, with `x + width` at most 100.
 * @return A message, or `null` when the geometry is valid.
 */
export function validateGeometry(
	x: string,
	y: string,
	width: string
): string | null {
	const left = parseNumber( x );
	const top = parseNumber( y );
	const wide = parseNumber( width );
	if (
		! inRange( left, 0, 100 ) ||
		! inRange( top, 0, 100 ) ||
		! inRange( wide, 1, 100 )
	) {
		return __(
			'Left and top must be 0 to 100 and width 1 to 100 (percent of the page).',
			'ohmylms'
		);
	}
	if ( left + wide > 100.0001 ) {
		return __(
			'Left position plus width cannot exceed 100% of the page.',
			'ohmylms'
		);
	}
	return null;
}

/* ---------------------------------------------------------------- form state */

/**
 * Convert a template into editable form state.
 *
 * @param certificate Template from the server.
 * @return Form state; the template is not modified.
 */
export function formFromCertificate( certificate: Certificate ): FormState {
	return {
		name: certificate.name,
		status: certificate.status,
		orientation: certificate.orientation,
		size: certificate.size,
		font_family: certificate.font_family,
		background_color: certificate.background_color,
		background_id: certificate.background_id,
		background_url: certificate.background_url,
		signature_image_id: certificate.signature.image_id,
		signature_url: certificate.signature_url,
		signature_x: String( certificate.signature.x ),
		signature_y: String( certificate.signature.y ),
		signature_width: String( certificate.signature.width ),
		blocks: certificate.blocks.map( ( block ) => ( {
			id: block.id,
			text: block.text,
			x: String( block.x ),
			y: String( block.y ),
			width: String( block.width ),
			font_size: String( block.font_size ),
			align: block.align,
			color: block.color,
			bold: block.bold,
		} ) ),
		courses: certificate.courses.map( ( course ) => ( { ...course } ) ),
	};
}

/**
 * Whether the form differs from the last saved state.
 *
 * @param current Current form.
 * @param saved   Form built from the last saved template.
 * @return True when there are unsaved changes.
 */
export function isDirty( current: FormState, saved: FormState ): boolean {
	return JSON.stringify( current ) !== JSON.stringify( saved );
}

/**
 * Convert form state into the REST payload. Call after {@link validateForm} reports no errors.
 *
 * @param form Form state.
 * @return Trimmed, typed payload; the form is not modified.
 */
export function toInput( form: FormState ): CertificateInput {
	return {
		name: form.name.trim(),
		status: form.status,
		orientation: form.orientation,
		size: form.size,
		font_family: form.font_family,
		background_color: form.background_color.trim().toLowerCase(),
		background_id: form.background_id,
		signature: {
			image_id: form.signature_image_id,
			x: parseNumber( form.signature_x ),
			y: parseNumber( form.signature_y ),
			width: parseNumber( form.signature_width ),
		},
		blocks: form.blocks.map( ( block ) => ( {
			id: block.id,
			text: block.text.trim(),
			x: parseNumber( block.x ),
			y: parseNumber( block.y ),
			width: parseNumber( block.width ),
			font_size: parseNumber( block.font_size ),
			align: block.align,
			color: block.color.trim().toLowerCase(),
			bold: block.bold,
		} ) ),
		course_ids: form.courses.map( ( course ) => course.id ),
	};
}

/**
 * The template fields only (no name, status or courses), for the preview route.
 *
 * @param input Full payload.
 * @return Template fields.
 */
export function toPreviewInput(
	input: CertificateInput
): Omit< CertificateInput, 'name' | 'status' | 'course_ids' > {
	return {
		orientation: input.orientation,
		size: input.size,
		font_family: input.font_family,
		background_color: input.background_color,
		background_id: input.background_id,
		signature: input.signature,
		blocks: input.blocks,
	};
}

/**
 * Validate the whole form against the server options.
 *
 * @param form    Form state.
 * @param options Server options and limits.
 * @return Errors keyed by field path (`name`, `background_color`, `signature`, `blocks`,
 *         `blocks.<id>.text`, `blocks.<id>.layout`, `blocks.<id>.font_size`, `blocks.<id>.color`);
 *         empty when valid.
 */
export function validateForm(
	form: FormState,
	options: CertificateOptions
): FormErrors {
	const errors: FormErrors = {};
	const name = form.name.trim();
	if ( ! name || Array.from( name ).length > 200 ) {
		errors.name = __( 'Enter a name of 1 to 200 characters.', 'ohmylms' );
	}
	const has = ( choices: readonly Option[], value: string ) =>
		choices.some( ( choice ) => choice.value === value );
	if (
		! has( options.statuses, form.status ) ||
		! has( options.orientations, form.orientation ) ||
		! has( options.sizes, form.size ) ||
		! has( options.font_families, form.font_family )
	) {
		errors.layout = __(
			'Choose a supported status, orientation, page size and font.',
			'ohmylms'
		);
	}
	if ( ! HEX_COLOR.test( form.background_color.trim() ) ) {
		errors.background_color = __(
			'Enter a color as six hexadecimal digits, for example #ffffff.',
			'ohmylms'
		);
	}
	const signature = validateGeometry(
		form.signature_x,
		form.signature_y,
		form.signature_width
	);
	if ( signature ) {
		errors.signature = signature;
	}
	if ( form.blocks.length > options.limits.max_blocks ) {
		errors.blocks = sprintf(
			/* translators: %d: maximum number of text blocks. */
			__( 'Use no more than %d text blocks.', 'ohmylms' ),
			options.limits.max_blocks
		);
	}
	const known = options.placeholders.map( ( item ) => item.value );
	const seen = new Set< string >();
	for ( const block of form.blocks ) {
		const base = `blocks.${ block.id }`;
		const value = block.text.trim();
		if ( ! BLOCK_ID.test( block.id ) || seen.has( block.id ) ) {
			errors[ `${ base }.text` ] = __(
				'This text block has an invalid or duplicate identifier.',
				'ohmylms'
			);
		}
		seen.add( block.id );
		if ( ! value || Array.from( value ).length > options.limits.max_text ) {
			errors[ `${ base }.text` ] = sprintf(
				/* translators: %d: maximum characters in a text block. */
				__( 'Enter 1 to %d characters of text.', 'ohmylms' ),
				options.limits.max_text
			);
		} else {
			const unknown = unknownPlaceholders( value, known );
			if ( unknown.length ) {
				errors[ `${ base }.text` ] = sprintf(
					/* translators: %s: comma separated placeholders. */
					__( 'Unknown placeholder: %s', 'ohmylms' ),
					unknown.join( ', ' )
				);
			}
		}
		const geometry = validateGeometry( block.x, block.y, block.width );
		if ( geometry ) {
			errors[ `${ base }.layout` ] = geometry;
		}
		if (
			! inRange(
				parseNumber( block.font_size ),
				options.limits.min_font_size,
				options.limits.max_font_size
			)
		) {
			errors[ `${ base }.font_size` ] = sprintf(
				/* translators: 1: minimum font size, 2: maximum font size. */
				__( 'Font size must be %1$d to %2$d points.', 'ohmylms' ),
				options.limits.min_font_size,
				options.limits.max_font_size
			);
		}
		if ( ! HEX_COLOR.test( block.color.trim() ) ) {
			errors[ `${ base }.color` ] = __(
				'Enter a color as six hexadecimal digits, for example #1d2327.',
				'ohmylms'
			);
		}
	}
	if ( form.courses.length > options.limits.max_courses ) {
		errors.courses = sprintf(
			/* translators: %d: maximum number of assigned courses. */
			__( 'Assign no more than %d courses.', 'ohmylms' ),
			options.limits.max_courses
		);
	}
	return errors;
}

/**
 * Whether a set of errors is empty.
 *
 * @param errors Result of {@link validateForm}.
 * @return True when the form is valid.
 */
export function isValid( errors: FormErrors ): boolean {
	return 0 === Object.keys( errors ).length;
}

/**
 * Map a server validation error (HTTP 400 or 422) onto a form field path.
 *
 * @param error Failure thrown by the API client.
 * @return The field path and message, or `null` when the error is not tied to a field.
 */
export function serverFieldError(
	error: ApiError
): { path: string; message: string } | null {
	const details = error.details;
	if ( ! details || 'object' !== typeof details ) {
		return null;
	}
	const { field, block_id: blockId } = details as Record< string, unknown >;
	if ( 'string' === typeof blockId ) {
		const kind = /geometry|bounds/.test( error.code ) ? 'layout' : 'text';
		return {
			path: `blocks.${ blockId }.${ kind }`,
			message: error.message,
		};
	}
	if ( 'string' === typeof field && field ) {
		return { path: field, message: error.message };
	}
	return null;
}

/**
 * Next unused block identifier.
 *
 * @param blocks Existing blocks.
 * @return An id such as `block-3`.
 */
export function nextBlockId( blocks: readonly BlockForm[] ): string {
	const used = new Set( blocks.map( ( block ) => block.id ) );
	let index = blocks.length + 1;
	while ( used.has( `block-${ index }` ) ) {
		index++;
	}
	return `block-${ index }`;
}

/**
 * A new text block placed below the last one.
 *
 * @param blocks Existing blocks.
 * @return A valid block.
 */
export function newBlock( blocks: readonly BlockForm[] ): BlockForm {
	const last = blocks[ blocks.length - 1 ];
	const lastY = last ? parseNumber( last.y ) : NaN;
	const y = Number.isFinite( lastY ) ? Math.min( 90, lastY + 10 ) : 50;
	return {
		id: nextBlockId( blocks ),
		text: __( 'New text', 'ohmylms' ),
		x: '10',
		y: String( y ),
		width: '80',
		font_size: '20',
		align: 'center',
		color: '#1d2327',
		bold: false,
	};
}

/**
 * Move a block up or down.
 *
 * @param blocks Blocks.
 * @param index  Index of the block to move.
 * @param delta  -1 to move up, 1 to move down.
 * @return A new array; the input is returned unchanged when the move is impossible.
 */
export function moveBlock< T >(
	blocks: readonly T[],
	index: number,
	delta: -1 | 1
): T[] {
	const target = index + delta;
	const moving = blocks[ index ];
	const other = blocks[ target ];
	if ( undefined === moving || undefined === other ) {
		return [ ...blocks ];
	}
	const copy = [ ...blocks ];
	copy[ index ] = other;
	copy[ target ] = moving;
	return copy;
}
