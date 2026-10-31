import {
	store,
	getContext,
	getElement,
	withScope,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	autoplayTarget,
	canAdvance,
	clampIndex,
	dots,
	indexAt,
	keyTarget,
	lastIndex,
	visibleCount,
} from './carousel-state.js';

/** Scroll-snap carousel. Markup: includes/Design/Carousel.php; the track scrolls natively without this module. */
const reduceMotion = () =>
	Boolean(
		window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches
	);

function parts( element ) {
	const root = element.closest( '[data-wp-interactive="ohmylms/carousel"]' );
	const track = root?.querySelector( '.ohmylms-carousel-track' ) || null;
	return {
		track,
		slides: track ? [ ...track.children ] : [],
		rtl: Boolean( track ) && getComputedStyle( track ).direction === 'rtl',
	};
}

function geometry( track, slides ) {
	const style = getComputedStyle( track );
	const first = slides[ 0 ]?.getBoundingClientRect();
	const second = slides[ 1 ]?.getBoundingClientRect();
	const slideWidth = first ? first.width : 0;
	return {
		width:
			track.clientWidth -
			( parseFloat( style.paddingLeft ) || 0 ) -
			( parseFloat( style.paddingRight ) || 0 ),
		slideWidth,
		step: second ? Math.abs( second.left - first.left ) : slideWidth,
		maxOffset: track.scrollWidth - track.clientWidth,
	};
}

function measure( context, element ) {
	const { track, slides } = parts( element );
	if ( ! track ) {
		return;
	}
	const size = geometry( track, slides );
	context.count = slides.length;
	context.perView = visibleCount( size.width, size.slideWidth, size.step );
	context.index = indexAt(
		track.scrollLeft,
		size.step,
		size.maxOffset,
		context.count,
		context.perView
	);
}

function go( index ) {
	const { track, slides, rtl } = parts( getElement().ref );
	if ( ! track || ! slides.length ) {
		return;
	}
	const context = getContext();
	const target = clampIndex(
		index,
		context.count,
		context.perView,
		context.loop
	);
	const left = target * geometry( track, slides ).step;
	track.scrollTo( {
		left: rtl ? -left : left,
		behavior: reduceMotion() ? 'auto' : 'smooth',
	} );
	context.index = target;
}

const { state } = store( 'ohmylms/carousel', {
	state: {
		get scrollable() {
			const context = getContext();
			return context.count > context.perView;
		},
		get arrowsHidden() {
			return ! getContext().arrows || ! state.scrollable;
		},
		get dotsHidden() {
			return ! getContext().dots || ! state.scrollable;
		},
		get atStart() {
			const context = getContext();
			return ! context.loop && context.index <= 0;
		},
		get atEnd() {
			const context = getContext();
			return (
				! context.loop &&
				context.index >= lastIndex( context.count, context.perView )
			);
		},
		get dots() {
			const context = getContext();
			return dots(
				context.count,
				context.perView,
				context.index,
				context.labels.dot
			);
		},
		get playLabel() {
			const context = getContext();
			return context.playing ? context.labels.pause : context.labels.play;
		},
	},
	actions: {
		prev() {
			go( getContext().index - 1 );
		},
		next() {
			go( getContext().index + 1 );
		},
		goTo() {
			go( getContext().dot.index );
		},
		key: withSyncEvent( ( event ) => {
			const { track, rtl } = parts( getElement().ref );
			const target = keyTarget( event.key, rtl );
			if ( event.target !== track || ! target ) {
				return;
			}
			event.preventDefault();
			const context = getContext();
			go(
				{
					first: 0,
					last: lastIndex( context.count, context.perView ),
					prev: context.index - 1,
					next: context.index + 1,
				}[ target ]
			);
		} ),
		sync() {
			measure( getContext(), getElement().ref );
		},
		hoverIn() {
			getContext().hovered = true;
		},
		hoverOut() {
			getContext().hovered = false;
		},
		focusIn() {
			getContext().focused = true;
		},
		focusOut( event ) {
			if ( ! getElement().ref.contains( event.relatedTarget ) ) {
				getContext().focused = false;
			}
		},
		togglePlay() {
			const context = getContext();
			context.playing = ! context.playing;
		},
	},
	callbacks: {
		mount() {
			const { ref } = getElement();
			const { track } = parts( ref );
			if ( ! track ) {
				return undefined;
			}
			const context = getContext();
			const update = withScope( () => measure( context, ref ) );
			update();
			context.mounted = true;
			const observer =
				typeof ResizeObserver === 'function'
					? new ResizeObserver( update )
					: null;
			observer?.observe( track );
			let timer = 0;
			if ( context.autoplay ) {
				if ( reduceMotion() ) {
					context.playing = false;
				}
				timer = setInterval(
					withScope( () => {
						if (
							canAdvance(
								context,
								reduceMotion(),
								document.hidden
							)
						) {
							go(
								autoplayTarget(
									context.index,
									context.count,
									context.perView
								)
							);
						}
					} ),
					context.autoplaySpeed
				);
			}
			return () => {
				observer?.disconnect();
				clearInterval( timer );
			};
		},
	},
} );
