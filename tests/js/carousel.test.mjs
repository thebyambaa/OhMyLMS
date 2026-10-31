import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
	autoplayTarget,
	canAdvance,
	clampIndex,
	dots,
	indexAt,
	keyTarget,
	lastIndex,
	visibleCount,
} from '../../assets/interactivity/carousel-state.js';

test( 'the last start leaves no empty space and never goes negative', () => {
	assert.equal( lastIndex( 10, 4 ), 6 );
	assert.equal( lastIndex( 3, 4 ), 0 );
	assert.equal( lastIndex( 0, 4 ), 0 );
	assert.equal( lastIndex( 5, 0 ), 4 );
} );

test( 'indices clamp, or wrap when looping', () => {
	assert.equal( clampIndex( -1, 10, 4 ), 0 );
	assert.equal( clampIndex( 7, 10, 4 ), 6 );
	assert.equal( clampIndex( 3, 10, 4 ), 3 );
	assert.equal( clampIndex( 7, 10, 4, true ), 0 );
	assert.equal( clampIndex( -1, 10, 4, true ), 6 );
	assert.equal( clampIndex( 1, 2, 4, true ), 0 );
} );

test( 'visible slides come from track, slide and step widths', () => {
	// 4 slides of 276px with a 24px gap in a 1176px track.
	assert.equal( visibleCount( 1176, 276, 300 ), 4 );
	// Sub-pixel layout still counts the last slide.
	assert.equal( visibleCount( 1175.6, 276, 300 ), 4 );
	// A partly visible slide is not counted.
	assert.equal( visibleCount( 1000, 276, 300 ), 3 );
	assert.equal( visibleCount( 300, 0, 0 ), 1 );
} );

test( 'scroll offsets map to the nearest start, RTL offsets are negative, the end is the last start', () => {
	assert.equal( indexAt( 0, 300, 1800, 10, 4 ), 0 );
	assert.equal( indexAt( 440, 300, 1800, 10, 4 ), 1 );
	assert.equal( indexAt( -610, 300, 1800, 10, 4 ), 2 );
	assert.equal( indexAt( 1799, 300, 1800, 10, 4 ), 6 );
	assert.equal( indexAt( 50, 0, 0, 3, 4 ), 0 );
} );

test( 'arrow keys follow the reading direction', () => {
	assert.equal( keyTarget( 'ArrowRight', false ), 'next' );
	assert.equal( keyTarget( 'ArrowLeft', false ), 'prev' );
	assert.equal( keyTarget( 'ArrowRight', true ), 'prev' );
	assert.equal( keyTarget( 'ArrowLeft', true ), 'next' );
	assert.equal( keyTarget( 'Home', true ), 'first' );
	assert.equal( keyTarget( 'End', false ), 'last' );
	assert.equal( keyTarget( 'Enter', false ), null );
} );

test( 'dots cover every reachable start and mark the current one', () => {
	const list = dots( 6, 4, 1, 'Go to slide %1$d of %2$d' );
	assert.deepEqual(
		list.map( ( dot ) => dot.label ),
		[ 'Go to slide 1 of 3', 'Go to slide 2 of 3', 'Go to slide 3 of 3' ]
	);
	assert.deepEqual(
		list.map( ( dot ) => dot.current ),
		[ false, true, false ]
	);
	assert.deepEqual( dots( 4, 4, 0, '%1$d' ), [] );
} );

test( 'autoplay pauses for hover, focus, the toggle, reduced motion and hidden pages, and rewinds at the end', () => {
	const context = {
		autoplay: true,
		playing: true,
		hovered: false,
		focused: false,
		count: 8,
		perView: 4,
	};
	assert.equal( canAdvance( context, false ), true );
	assert.equal( canAdvance( { ...context, hovered: true }, false ), false );
	assert.equal( canAdvance( { ...context, focused: true }, false ), false );
	assert.equal( canAdvance( { ...context, playing: false }, false ), false );
	assert.equal( canAdvance( { ...context, autoplay: false }, false ), false );
	assert.equal( canAdvance( { ...context, count: 4 }, false ), false );
	assert.equal( canAdvance( context, true ), false );
	assert.equal( canAdvance( context, false, true ), false );
	assert.equal( autoplayTarget( 2, 8, 4 ), 3 );
	assert.equal( autoplayTarget( 4, 8, 4 ), 0 );
} );

test( 'the store keeps arithmetic in the pure module and uses real buttons and the documented directives', () => {
	const store = fs.readFileSync( 'assets/interactivity/carousel.js', 'utf8' );
	const markup = fs.readFileSync( 'includes/Design/Carousel.php', 'utf8' );
	assert.match( store, /from '\.\/carousel-state\.js'/ );
	assert.match( store, /store\( 'ohmylms\/carousel'/ );
	assert.match( store, /prefers-reduced-motion: reduce/ );
	for ( const action of [
		'prev',
		'next',
		'goTo',
		'key',
		'sync',
		'hoverIn',
		'hoverOut',
		'focusIn',
		'focusOut',
		'togglePlay',
	] ) {
		assert.match( store, new RegExp( `\\b${ action }(\\(|:)` ) );
		if ( action !== 'prev' && action !== 'next' ) {
			// Arrow actions are built from the direction; the PHP test checks the rendered names.
			assert.match( markup, new RegExp( `actions\\.${ action }\\b` ) );
		}
	}
	assert.match( markup, /<button type="button" class="ohmylms-carousel-arrow/ );
	assert.doesNotMatch( store + markup, /\.slick\(|jQuery/ );
} );
