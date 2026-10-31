/** Name of the inline question block (registered by the native lesson editor; dynamic, so self-closing). */
export const INLINE_BLOCK = 'ohmylms/shared-multiple-choice';

/** An inline question reference found in lesson content. */
export interface InlineRef {
	/** Offset of the reference in the content. */
	start: number;
	end: number;
	kind: 'block' | 'shortcode';
	/** Question ID from the block attributes; 0 for shortcodes. */
	questionId: number;
	uuid: string;
	source: string;
}

const BLOCK =
	/<!--\s+wp:ohmylms\/shared-multiple-choice(?:\s+(\{[\s\S]*?\}))?\s+\/-->/g;
const SHORTCODE =
	/\[ohmylms_question[^\]]*?([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})[^\]]*\]/gi;

function attributes( json: string | undefined ): Record< string, unknown > {
	if ( ! json ) {
		return {};
	}
	try {
		const value: unknown = JSON.parse( json );
		return value && 'object' === typeof value && ! Array.isArray( value )
			? ( value as Record< string, unknown > )
			: {};
	} catch {
		return {};
	}
}

/**
 * Inline question blocks and `[ohmylms_question]` shortcodes, in reading order.
 *
 * @param content Serialized block content.
 * @return References with their offsets.
 */
export function findInline( content: string ): InlineRef[] {
	const refs: InlineRef[] = [];
	for ( const match of content.matchAll( BLOCK ) ) {
		const attrs = attributes( match[ 1 ] );
		const id = Number( attrs.questionId );
		refs.push( {
			start: match.index ?? 0,
			end: ( match.index ?? 0 ) + match[ 0 ].length,
			kind: 'block',
			questionId: Number.isSafeInteger( id ) && id > 0 ? id : 0,
			uuid:
				'string' === typeof attrs.uuid ? attrs.uuid.toLowerCase() : '',
			source: match[ 0 ],
		} );
	}
	for ( const match of content.matchAll( SHORTCODE ) ) {
		refs.push( {
			start: match.index ?? 0,
			end: ( match.index ?? 0 ) + match[ 0 ].length,
			kind: 'shortcode',
			questionId: 0,
			uuid: ( match[ 1 ] ?? '' ).toLowerCase(),
			source: match[ 0 ],
		} );
	}
	return refs.sort( ( a, b ) => a.start - b.start );
}

/**
 * Whether two references can trade places: both are standalone blocks, so moving keeps valid markup.
 *
 * @param refs References.
 * @param from Index.
 * @param to   Other index.
 * @return True when they can be swapped.
 */
export function canSwap(
	refs: InlineRef[],
	from: number,
	to: number
): boolean {
	return (
		'block' === refs[ from ]?.kind &&
		'block' === refs[ to ]?.kind &&
		from !== to
	);
}

/**
 * Swap two inline question blocks; the content around them is unchanged.
 *
 * @param content Content.
 * @param from    Index of one reference.
 * @param to      Index of the other.
 * @return The content with the two blocks swapped (unchanged when they cannot be swapped).
 */
export function swapInline(
	content: string,
	from: number,
	to: number
): string {
	const refs = findInline( content );
	if ( ! canSwap( refs, from, to ) ) {
		return content;
	}
	const [ first, second ] =
		from < to
			? [ refs[ from ] as InlineRef, refs[ to ] as InlineRef ]
			: [ refs[ to ] as InlineRef, refs[ from ] as InlineRef ];
	return (
		content.slice( 0, first.start ) +
		second.source +
		content.slice( first.end, second.start ) +
		first.source +
		content.slice( second.end )
	);
}

/**
 * Remove one inline question reference (and the blank line after a block).
 *
 * @param content Content.
 * @param index   Index of the reference.
 * @return The content without it.
 */
export function removeInline( content: string, index: number ): string {
	const ref = findInline( content )[ index ];
	if ( ! ref ) {
		return content;
	}
	let end = ref.end;
	if ( 'block' === ref.kind ) {
		const rest = /^\s*\n/.exec( content.slice( end ) );
		end += rest ? rest[ 0 ].length : 0;
	}
	return content.slice( 0, ref.start ) + content.slice( end );
}

/**
 * Serialized inline question block.
 *
 * @param questionId Question ID.
 * @param uuid       Question UUID.
 * @return Block markup.
 */
export function inlineBlock( questionId: number, uuid: string ): string {
	return `<!-- wp:${ INLINE_BLOCK } ${ JSON.stringify( {
		questionId,
		uuid,
	} ) } /-->`;
}

/**
 * Add an inline question block at the end of the lesson.
 *
 * @param content    Content.
 * @param questionId Question ID.
 * @param uuid       Question UUID.
 * @return The content with the block appended.
 */
export function appendInline(
	content: string,
	questionId: number,
	uuid: string
): string {
	const base = content.replace( /\s+$/, '' );
	return `${ base }${ base ? '\n\n' : '' }${ inlineBlock( questionId, uuid ) }\n`;
}
