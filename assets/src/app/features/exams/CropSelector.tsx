import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { KeyboardEvent, PointerEvent } from 'react';
import {
	boxStyle,
	fromDrag,
	moveBy,
	nudge,
	pointIn,
	type Bounds,
	type Point,
} from './cropGeometry';

/** A saved figure crop drawn on the page for reference. */
export interface CropRegion {
	id: string;
	label: string;
	bounds: readonly number[];
}

interface CropSelectorProps {
	/** Authenticated page image. */
	src: string;
	alt: string;
	/** The selection; null shows the page without one. */
	bounds: Bounds | null;
	regions: readonly CropRegion[];
	/** Page width as a share of the viewer: 1 fits the width. */
	zoom: number;
	/** Changes when the viewer follows another question or figure; scrolls the selection into view. */
	focusKey: string;
	/** Drawing, moving and keys are off. */
	readOnly?: boolean;
	onChange: ( bounds: Bounds ) => void;
	onError: () => void;
}

type Gesture =
	| { kind: 'draw'; start: Point }
	| { kind: 'move'; start: Point; origin: Bounds };

function percent( value: number ): string {
	return String( Math.round( value * 1000 ) / 10 );
}

/**
 * A rendered page with a crop rectangle: drag (mouse, pen or touch) to draw one, drag inside it to
 * move it, or focus it and use the arrow keys (Shift resizes, Alt makes finer steps).
 *
 * @param props          Component properties.
 * @param props.src      Authenticated page image.
 * @param props.alt      Text alternative of the page.
 * @param props.bounds   The selection, or null.
 * @param props.regions  Saved crops drawn for reference.
 * @param props.zoom     Page width as a share of the viewer.
 * @param props.focusKey Scrolls the selection into view when it changes.
 * @param props.readOnly Drawing, moving and keys are off.
 * @param props.onChange Receives a new selection.
 * @param props.onError  The page image failed to load.
 * @return The page with its selection.
 */
export default function CropSelector( {
	src,
	alt,
	bounds,
	regions,
	zoom,
	focusKey,
	readOnly = false,
	onChange,
	onError,
}: CropSelectorProps ) {
	const help = useId();
	const scroller = useRef< HTMLDivElement >( null );
	const page = useRef< HTMLDivElement >( null );
	const [ gesture, setGesture ] = useState< Gesture | null >( null );
	const [ draft, setDraft ] = useState< Bounds | null >( null );
	const shown = draft ?? bounds;
	const top = bounds ? bounds[ 1 ] : 0;

	useEffect( () => {
		const box = scroller.current;
		const sheet = page.current;
		if ( box && sheet ) {
			box.scrollTop = Math.max( 0, top * sheet.clientHeight - 16 );
		}
		// Follow the question or figure, not every key press or drag.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ focusKey, src ] );

	function at( event: PointerEvent< HTMLDivElement > ): Point {
		return pointIn(
			event.currentTarget.getBoundingClientRect(),
			event.clientX,
			event.clientY
		);
	}

	function begin( event: PointerEvent< HTMLDivElement > ) {
		if (
			readOnly ||
			( 'mouse' === event.pointerType && 0 !== event.button )
		) {
			return;
		}
		event.preventDefault();
		event.currentTarget.setPointerCapture?.( event.pointerId );
		const start = at( event );
		const inside =
			bounds &&
			start.x >= bounds[ 0 ] &&
			start.x <= bounds[ 2 ] &&
			start.y >= bounds[ 1 ] &&
			start.y <= bounds[ 3 ];
		setGesture(
			inside && bounds
				? { kind: 'move', start, origin: bounds }
				: { kind: 'draw', start }
		);
		setDraft( null );
	}

	function track( event: PointerEvent< HTMLDivElement > ) {
		if ( ! gesture ) {
			return;
		}
		const point = at( event );
		const next =
			'move' === gesture.kind
				? moveBy(
						gesture.origin,
						point.x - gesture.start.x,
						point.y - gesture.start.y
					)
				: fromDrag( gesture.start, point );
		if ( next ) {
			setDraft( next );
		}
	}

	function end( event: PointerEvent< HTMLDivElement > ) {
		if ( ! gesture ) {
			return;
		}
		const point = at( event );
		const next =
			'move' === gesture.kind
				? moveBy(
						gesture.origin,
						point.x - gesture.start.x,
						point.y - gesture.start.y
					)
				: fromDrag( gesture.start, point );
		setGesture( null );
		setDraft( null );
		if ( next ) {
			onChange( next );
		}
	}

	function cancel() {
		setGesture( null );
		setDraft( null );
	}

	function key( event: KeyboardEvent< HTMLButtonElement > ) {
		if ( readOnly || ! bounds ) {
			return;
		}
		const next = nudge( bounds, event.key, event );
		if ( next ) {
			event.preventDefault();
			onChange( next );
		}
	}

	return (
		<div className="ohmylms-crop-selector" ref={ scroller }>
			<div
				ref={ page }
				className={
					readOnly
						? 'ohmylms-crop-page'
						: 'ohmylms-crop-page is-editable'
				}
				style={ { width: `${ zoom * 100 }%` } }
				onPointerDown={ begin }
				onPointerMove={ track }
				onPointerUp={ end }
				onPointerCancel={ cancel }
			>
				<img
					src={ src }
					alt={ alt }
					draggable={ false }
					onError={ onError }
				/>
				{ regions.map( ( region ) => (
					<span
						key={ region.id }
						className="ohmylms-crop-region"
						style={ boxStyle( region.bounds ) }
						aria-hidden="true"
					>
						<span className="ohmylms-crop-region-label">
							{ region.label }
						</span>
					</span>
				) ) }
				{ shown && (
					<button
						type="button"
						className="ohmylms-crop-selection"
						style={ boxStyle( shown ) }
						disabled={ readOnly }
						aria-roledescription={ __( 'crop area', 'ohmylms' ) }
						aria-label={ sprintf(
							/* translators: 1: left; 2: top; 3: right; 4: bottom, in percent of the page. */
							__(
								'Crop area: left %1$s%%, top %2$s%%, right %3$s%%, bottom %4$s%%',
								'ohmylms'
							),
							percent( shown[ 0 ] ),
							percent( shown[ 1 ] ),
							percent( shown[ 2 ] ),
							percent( shown[ 3 ] )
						) }
						aria-describedby={ readOnly ? undefined : help }
						onKeyDown={ key }
					/>
				) }
			</div>
			{ ! readOnly && (
				<p id={ help } className="screen-reader-text">
					{ __(
						'Drag on the page to draw a crop, or drag inside it to move it. With the crop focused, arrow keys move it, Shift and arrow keys resize it, and Alt makes the steps finer.',
						'ohmylms'
					) }
				</p>
			) }
		</div>
	);
}
