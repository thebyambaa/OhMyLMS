import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

/** Extra scroll room lets edge nodes be centered and dragged away from the viewport edge. */
export const MAP_GUTTER = 240;
const MIN_ZOOM = 0.2;
const MAX_ZOOM = 2;

/**
 * Canvas navigation with pointer capture and zoom anchored to the pointer or viewport center.
 *
 * @param {Object} bounds Visible map dimensions.
 * @return {Object} Viewport ref, zoom controls and pointer handlers.
 */
export function useMapNavigation( bounds ) {
	const viewport = useRef( null );
	const [ zoom, setZoom ] = useState( 1 );
	const [ panning, setPanning ] = useState( false );
	const currentZoom = useRef( 1 );
	const pendingScroll = useRef( null );
	const pan = useRef( null );
	const changeZoom = useCallback( ( requested, point ) => {
		const element = viewport.current;
		if ( ! element ) {
			return;
		}
		const next = Math.max( MIN_ZOOM, Math.min( MAX_ZOOM, requested ) );
		const x = point?.x ?? element.clientWidth / 2;
		const y = point?.y ?? element.clientHeight / 2;
		const ratio = next / currentZoom.current;
		pendingScroll.current = {
			left:
				MAP_GUTTER +
				( element.scrollLeft + x - MAP_GUTTER ) * ratio -
				x,
			top:
				MAP_GUTTER + ( element.scrollTop + y - MAP_GUTTER ) * ratio - y,
		};
		currentZoom.current = next;
		setZoom( next );
	}, [] );
	useLayoutEffect( () => {
		if ( pendingScroll.current ) {
			viewport.current?.scrollTo( pendingScroll.current );
			pendingScroll.current = null;
		}
	}, [ zoom ] );
	const center = useCallback( ( x, y ) => {
		const element = viewport.current;
		if ( element ) {
			element.scrollTo( {
				left:
					MAP_GUTTER +
					x * currentZoom.current -
					element.clientWidth / 2,
				top:
					MAP_GUTTER +
					y * currentZoom.current -
					element.clientHeight / 2,
			} );
		}
	}, [] );
	const fit = () => {
		const element = viewport.current;
		if ( ! element || ! bounds.width || ! bounds.height ) {
			return;
		}
		const next = Math.max(
			MIN_ZOOM,
			Math.min(
				1,
				( element.clientWidth - 48 ) / bounds.width,
				( element.clientHeight - 48 ) / bounds.height
			)
		);
		changeZoom( next );
		// Fit can retain the current scale; apply the centering immediately in that case too.
		const position = {
			left:
				MAP_GUTTER +
				( bounds.width * next ) / 2 -
				element.clientWidth / 2,
			top:
				MAP_GUTTER +
				( bounds.height * next ) / 2 -
				element.clientHeight / 2,
		};
		pendingScroll.current = position;
		element.scrollTo( position );
	};
	useEffect( () => {
		const element = viewport.current;
		const wheel = ( event ) => {
			if ( ! event.ctrlKey && ! event.metaKey ) {
				return;
			}
			event.preventDefault();
			const box = element.getBoundingClientRect();
			const delta = event.deltaY * ( event.deltaMode === 1 ? 16 : 1 );
			changeZoom(
				currentZoom.current *
					Math.exp(
						-Math.max( -100, Math.min( 100, delta ) ) * 0.005
					),
				{ x: event.clientX - box.left, y: event.clientY - box.top }
			);
		};
		element.addEventListener( 'wheel', wheel, { passive: false } );
		return () => element.removeEventListener( 'wheel', wheel );
	}, [ changeZoom ] );
	const endPan = () => {
		pan.current = null;
		setPanning( false );
	};
	return {
		viewport,
		zoom,
		panning,
		center,
		fit,
		zoomIn: () => changeZoom( currentZoom.current + 0.1 ),
		zoomOut: () => changeZoom( currentZoom.current - 0.1 ),
		resetZoom: () => changeZoom( 1 ),
		minZoom: MIN_ZOOM,
		maxZoom: MAX_ZOOM,
		pointerHandlers: {
			onPointerDown( event ) {
				if ( event.button !== 0 && event.button !== 1 ) {
					return;
				}
				if (
					event.button === 0 &&
					event.target.closest(
						'.ohmylms-syllabus-map-card, button, input, select'
					)
				) {
					return;
				}
				event.preventDefault();
				const element = viewport.current;
				element.focus( { preventScroll: true } );
				element.setPointerCapture( event.pointerId );
				pan.current = {
					id: event.pointerId,
					x: event.clientX,
					y: event.clientY,
					left: element.scrollLeft,
					top: element.scrollTop,
				};
				setPanning( true );
			},
			onPointerMove( event ) {
				if ( pan.current?.id === event.pointerId ) {
					viewport.current.scrollTo( {
						left: pan.current.left + pan.current.x - event.clientX,
						top: pan.current.top + pan.current.y - event.clientY,
					} );
				}
			},
			onPointerUp( event ) {
				if ( viewport.current.hasPointerCapture( event.pointerId ) ) {
					viewport.current.releasePointerCapture( event.pointerId );
				}
				endPan();
			},
			onPointerCancel: endPan,
			onLostPointerCapture: endPan,
			onKeyDown( event ) {
				if ( event.target !== event.currentTarget ) {
					return;
				}
				if ( event.key === '+' || event.key === '=' ) {
					event.preventDefault();
					changeZoom( currentZoom.current + 0.1 );
				} else if ( event.key === '-' ) {
					event.preventDefault();
					changeZoom( currentZoom.current - 0.1 );
				} else if ( event.key === '0' ) {
					event.preventDefault();
					changeZoom( 1 );
				}
			},
		},
	};
}
