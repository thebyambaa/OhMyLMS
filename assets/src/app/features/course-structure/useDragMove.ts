import { useEffect, useState } from '@wordpress/element';
import type { CSSProperties, PointerEvent } from 'react';
import { unitKey } from './reorder';

export interface DragState {
	source: string;
	target?: string;
	after?: boolean;
}

/**
 * Pointer dragging (mouse, pen and touch share one path) between elements marked `data-oml-drop`.
 * The handle captures the pointer, so the row under it is found by position. Escape, a cancelled
 * pointer or a release away from every row ends the drag without a move.
 *
 * @param drop     Called with the source key, the target key and whether it was the target's lower half.
 * @param disabled Ignore new drags, for example while a save is running.
 */
export function useDragMove(
	drop: ( source: string, target: string, after: boolean ) => void,
	disabled: boolean
) {
	const [ drag, setDrag ] = useState< DragState | null >( null );
	const active = !! drag;
	useEffect( () => {
		if ( ! active ) {
			return;
		}
		const cancel = ( event: KeyboardEvent ) =>
			'Escape' === event.key && setDrag( null );
		window.addEventListener( 'keydown', cancel );
		return () => window.removeEventListener( 'keydown', cancel );
	}, [ active ] );
	const handle = ( source: string ) => ( {
		onPointerDown( event: PointerEvent< HTMLElement > ) {
			if ( disabled || event.button > 0 ) {
				return;
			}
			event.preventDefault();
			event.currentTarget.setPointerCapture?.( event.pointerId );
			setDrag( { source } );
		},
		onPointerMove( event: PointerEvent< HTMLElement > ) {
			if ( drag?.source !== source ) {
				return;
			}
			const row = document
				.elementFromPoint?.( event.clientX, event.clientY )
				?.closest< HTMLElement >(
					source.startsWith( unitKey( '' ) )
						? '[data-oml-drop^="unit:"]'
						: '[data-oml-drop]'
				);
			const box = row?.getBoundingClientRect();
			const target = row?.dataset.omlDrop;
			setDrag( {
				source,
				target: target === source ? undefined : target,
				after: !! box && event.clientY > box.top + box.height / 2,
			} );
		},
		onPointerUp() {
			if ( drag?.source === source && drag.target ) {
				drop( source, drag.target, !! drag.after );
			}
			setDrag( null );
		},
		onPointerCancel: () => setDrag( null ),
	} );
	return { drag, handle };
}

/**
 * Inline feedback for a draggable row: the dragged row fades, the target shows an insertion line.
 * @param drag
 * @param key
 */
export function dragStyle(
	drag: DragState | null,
	key: string
): CSSProperties | undefined {
	if ( drag?.source === key ) {
		return { opacity: 0.5 };
	}
	if ( drag?.target === key ) {
		return {
			boxShadow: `inset 0 ${
				drag.after ? -2 : 2
			}px 0 var(--wp-admin-theme-color)`,
		};
	}
	return undefined;
}

/** Pointer-only grip; the move buttons and unit select are the keyboard and screen reader path. */
export const GRIP: CSSProperties = {
	cursor: 'grab',
	touchAction: 'none',
	userSelect: 'none',
	padding: '0 var(--oml-space-2)',
};
