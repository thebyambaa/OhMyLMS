import { act } from '@testing-library/react';

export interface MediaFrameOptions {
	title: string;
	button: { text: string };
	library: { type: string | readonly string[] };
	multiple: boolean;
}

/** A stand-in for a `wp.media` select frame. */
export class FakeMediaFrame {
	readonly handlers = new Map< string, Array< () => void > >();
	selection: unknown = undefined;
	opened = 0;
	closed = 0;

	constructor( readonly options: MediaFrameOptions ) {}

	on( event: string, callback: () => void ) {
		this.handlers.set( event, [
			...( this.handlers.get( event ) ?? [] ),
			callback,
		] );
	}

	off( event: string ) {
		this.handlers.delete( event );
	}

	open() {
		this.opened++;
	}

	close() {
		this.closed++;
		this.trigger( 'close' );
	}

	state() {
		return {
			get: () => ( {
				first: () =>
					undefined === this.selection
						? undefined
						: { toJSON: () => this.selection },
			} ),
		};
	}

	trigger( event: string ) {
		for ( const callback of this.handlers.get( event ) ?? [] ) {
			callback();
		}
	}

	/**
	 * Pick an attachment the way the frame's select button does: the frame closes, then `select` fires.
	 *
	 * @param item Attachment JSON.
	 */
	choose( item: unknown ) {
		act( () => {
			this.selection = item;
			this.trigger( 'close' );
			this.trigger( 'select' );
		} );
	}

	/** Close the frame without a choice (Escape or the close button). */
	dismiss() {
		act( () => this.trigger( 'close' ) );
	}
}

/**
 * Install a fake `window.wp.media` factory.
 *
 * @return The factory mock, the created frames and a restore function.
 */
export function mockMediaFrame() {
	const target = window as unknown as { wp?: Record< string, unknown > };
	const previous = target.wp;
	const frames: FakeMediaFrame[] = [];
	const media = vi.fn( ( options: MediaFrameOptions ) => {
		const frame = new FakeMediaFrame( options );
		frames.push( frame );
		return frame;
	} );
	target.wp = { ...previous, media };
	return {
		media,
		frames,
		last: (): FakeMediaFrame => {
			const frame = frames.at( -1 );
			if ( ! frame ) {
				throw new Error( 'No media frame was opened.' );
			}
			return frame;
		},
		restore: () => {
			target.wp = previous;
		},
	};
}

/**
 * Attachment JSON as `wp.media` returns it.
 *
 * @param overrides Fields to replace.
 * @return The attachment.
 */
export function attachment( overrides: Record< string, unknown > = {} ) {
	return {
		id: 40,
		url: 'https://example.test/a.png',
		mime: 'image/png',
		title: 'Cover A',
		sizes: { thumbnail: { url: 'https://example.test/a-150.png' } },
		...overrides,
	};
}
