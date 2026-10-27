/**
 * Visual question widgets: number line, shade a model, base-ten blocks, clock, money,
 * jug, bar chart and grid builder. Each registers with OhMyLMSInteractive and answers by
 * writing hidden fields ( setAnswer ); the server grades them, nothing here knows the key.
 *
 * Every widget works with a pointer or touch and with the keyboard.
 */
( function () {
	'use strict';

	var host = window.OhMyLMSInteractive;
	if ( ! host ) {
		return;
	}
	var el = host.util.el;
	var setAnswer = host.util.setAnswer;
	var NS = 'http://www.w3.org/2000/svg';

	function svg( tag, attrs, kids ) {
		var node = document.createElementNS( NS, tag );
		Object.keys( attrs || {} ).forEach( function ( name ) {
			if ( name === 'text' ) {
				node.textContent = attrs[ name ];
			} else {
				node.setAttribute( name, attrs[ name ] );
			}
		} );
		( kids || [] ).forEach( function ( kid ) {
			node.appendChild( kid );
		} );
		return node;
	}

	function clamp( value, low, high ) {
		return Math.min( high, Math.max( low, value ) );
	}

	function round( value ) {
		return Math.round( value * 1e6 ) / 1e6;
	}

	/* One plain instruction line above each widget, so learners know what to do. */
	var HINTS = {
		'shade-model': 'Tap the parts to shade them. Tap a shaded part again to clear it.',
		'count-blocks': 'Use the plus and minus buttons to show the number with blocks.',
		'set-clock': 'Drag the hand, or use the buttons, to set the time.',
		'make-amount': 'Tap the notes and coins to add them to your total. Tap one in the tray to remove it.',
		'fill-level': 'Move the slider to fill the container to the right level.',
		'build-chart': 'Drag each bar up or down to the correct height.',
		'grid-build': 'Select squares on the grid to build the shape.',
	};
	var translate = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( text ) { return text; };
	var registerWidget = host.register;
	host.register = function ( type, widget ) {
		var build = widget.build;
		if ( HINTS[ type ] ) {
			widget.build = function ( root, config ) {
				root.appendChild( el( 'p', { class: 'ohmylms-vis-hint', text: translate( HINTS[ type ], 'ohmylms' ) } ) );
				build( root, config );
			};
		}
		return registerWidget.call( host, type, widget );
	};

	/** Pointer position in an SVG's own coordinates. */
	function local( root, event ) {
		var point = root.createSVGPoint();
		point.x = event.clientX;
		point.y = event.clientY;
		return point.matrixTransform( root.getScreenCTM().inverse() );
	}

	/** Press and drag: start( p ) on down, move( p ) while held. */
	function drag( target, svgRoot, move ) {
		var down = false;
		target.addEventListener( 'pointerdown', function ( event ) {
			down = true;
			if ( target.setPointerCapture ) {
				target.setPointerCapture( event.pointerId );
			}
			event.preventDefault();
			move( local( svgRoot, event ), event );
		} );
		target.addEventListener( 'pointermove', function ( event ) {
			if ( down ) {
				move( local( svgRoot, event ), event );
			}
		} );
		[ 'pointerup', 'pointercancel' ].forEach( function ( name ) {
			target.addEventListener( name, function () {
				down = false;
			} );
		} );
	}

	/* ---------- Number line ---------- */

	host.register( 'number-line', {
		build: function ( root, c ) {
			var min = Number( c.min );
			var max = Number( c.max );
			var step = Number( c.step ) || 1;
			var snap = Number( c.snap ) || step;
			var left = 30;
			var right = 570;
			var ticks = Math.max( 1, Math.round( ( max - min ) / step ) );
			var every = Math.ceil( ( ticks + 1 ) / 12 );
			var value = null;
			var xOf = function ( v ) {
				return left + ( ( v - min ) / ( max - min ) ) * ( right - left );
			};
			var s = svg( 'svg', {
				viewBox: '0 0 600 96',
				class: 'ohmylms-vis-svg ohmylms-vis-numberline',
				role: 'slider',
				tabindex: '0',
				'aria-label': 'Number line',
				'aria-valuemin': min,
				'aria-valuemax': max,
			} );
			s.appendChild( svg( 'line', { x1: left, x2: right, y1: 50, y2: 50, class: 'ohmylms-vis-axis' } ) );
			for ( var i = 0; i <= ticks; i++ ) {
				var v = min + i * step;
				s.appendChild( svg( 'line', { x1: xOf( v ), x2: xOf( v ), y1: 42, y2: 58, class: 'ohmylms-vis-axis' } ) );
				if ( i % every === 0 ) {
					s.appendChild( svg( 'text', { x: xOf( v ), y: 80, 'text-anchor': 'middle', class: 'ohmylms-vis-text', text: String( round( v ) ) } ) );
				}
			}
			var point = svg( 'circle', { r: 11, cy: 50, class: 'ohmylms-vis-point', visibility: 'hidden' } );
			s.appendChild( point );
			var tr = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( text ) { return text; };
			var hint = el( 'p', { class: 'ohmylms-vis-hint', text: tr( 'Click or tap the line to place your answer, or use the arrow keys.', 'ohmylms' ) } );
			var ghost = svg( 'circle', { r: 11, cy: 50, class: 'ohmylms-vis-ghost', visibility: 'hidden' } );
			s.insertBefore( ghost, point );
			var readout = el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite', text: tr( 'Your answer: not placed yet', 'ohmylms' ) } );
			function place( next ) {
				value = round( clamp( next, min, max ) );
				point.setAttribute( 'cx', xOf( value ) );
				point.setAttribute( 'visibility', 'visible' );
				s.setAttribute( 'aria-valuenow', value );
				readout.textContent = tr( 'Your answer: ', 'ohmylms' ) + String( value );
				setAnswer( root, 'value', value );
			}
			drag( s, s, function ( p ) {
				var raw = min + ( ( p.x - left ) / ( right - left ) ) * ( max - min );
				place( min + Math.round( ( raw - min ) / snap ) * snap );
			} );
			s.addEventListener( 'pointermove', function ( event ) {
				var p = local( s, event );
				var raw = min + ( ( p.x - left ) / ( right - left ) ) * ( max - min );
				var near = clamp( min + Math.round( ( raw - min ) / snap ) * snap, min, max );
				ghost.setAttribute( 'cx', xOf( near ) );
				ghost.setAttribute( 'visibility', 'visible' );
			} );
			s.addEventListener( 'pointerleave', function () {
				ghost.setAttribute( 'visibility', 'hidden' );
			} );
			s.addEventListener( 'keydown', function ( event ) {
				var base = value === null ? min : value;
				var moves = { ArrowRight: snap, ArrowUp: snap, ArrowLeft: -snap, ArrowDown: -snap };
				if ( moves[ event.key ] !== undefined ) {
					place( base + moves[ event.key ] );
				} else if ( event.key === 'Home' ) {
					place( min );
				} else if ( event.key === 'End' ) {
					place( max );
				} else {
					return;
				}
				event.preventDefault();
			} );
			root.appendChild( hint );
			root.appendChild( s );
			root.appendChild( readout );
		},
	} );

	/* ---------- Shade a model ---------- */

	host.register( 'shade-model', {
		build: function ( root, c ) {
			var parts = Number( c.parts ) || 8;
			var shape = c.shape || 'bar';
			var cols = shape === 'grid' ? Number( c.cols ) || Math.min( parts, 10 ) : parts;
			var rows = Math.ceil( parts / cols );
			var size = 44;
			var width = shape === 'circle' ? 220 : cols * size + 4;
			var height = shape === 'circle' ? 220 : rows * size + 4;
			var s = svg( 'svg', {
				viewBox: '0 0 ' + width + ' ' + height,
				class: 'ohmylms-vis-svg ohmylms-vis-model',
				role: 'group',
				'aria-label': 'Model with ' + parts + ' equal parts',
			} );
			s.style.maxWidth = Math.min( width, 640 ) + 'px';
			var count = el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite' } );
			var on = {};
			function sync() {
				count.textContent = Object.keys( on ).length + ' / ' + parts;
			}
			function toggle( index, cell ) {
				if ( on[ index ] ) {
					delete on[ index ];
				} else {
					on[ index ] = true;
				}
				cell.setAttribute( 'aria-checked', on[ index ] ? 'true' : 'false' );
				cell.classList.toggle( 'is-on', !! on[ index ] );
				setAnswer( root, 'c' + index, on[ index ] ? '1' : null );
				sync();
			}
			for ( var i = 0; i < parts; i++ ) {
				var shapeNode;
				if ( shape === 'circle' ) {
					var a0 = ( i / parts ) * 2 * Math.PI - Math.PI / 2;
					var a1 = ( ( i + 1 ) / parts ) * 2 * Math.PI - Math.PI / 2;
					var r = 100;
					var big = 1 / parts > 0.5 ? 1 : 0;
					shapeNode = svg( 'path', {
						d: 'M110 110 L' + ( 110 + r * Math.cos( a0 ) ) + ' ' + ( 110 + r * Math.sin( a0 ) ) +
							' A' + r + ' ' + r + ' 0 ' + big + ' 1 ' + ( 110 + r * Math.cos( a1 ) ) + ' ' + ( 110 + r * Math.sin( a1 ) ) + ' Z',
					} );
				} else {
					shapeNode = svg( 'rect', {
						x: 2 + ( i % cols ) * size,
						y: 2 + Math.floor( i / cols ) * size,
						width: size,
						height: size,
					} );
				}
				shapeNode.setAttribute( 'class', 'ohmylms-vis-cell' );
				shapeNode.setAttribute( 'role', 'checkbox' );
				shapeNode.setAttribute( 'aria-checked', 'false' );
				shapeNode.setAttribute( 'aria-label', 'Part ' + ( i + 1 ) );
				shapeNode.setAttribute( 'tabindex', '0' );
				( function ( index, cell ) {
					cell.addEventListener( 'click', function () {
						toggle( index, cell );
					} );
					cell.addEventListener( 'keydown', function ( event ) {
						if ( event.key === ' ' || event.key === 'Enter' ) {
							event.preventDefault();
							toggle( index, cell );
						}
					} );
				}( i, shapeNode ) );
				s.appendChild( shapeNode );
			}
			root.appendChild( s );
			root.appendChild( count );
			sync();
		},
	} );

	/* ---------- Base-ten blocks ---------- */

	var PLACE_VALUE = { thousands: 1000, hundreds: 100, tens: 10, ones: 1 };

	host.register( 'count-blocks', {
		build: function ( root, c ) {
			var places = c.places || [ 'hundreds', 'tens', 'ones' ];
			var max = Number( c.max_per_place ) || 20;
			var counts = {};
			var total = el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite' } );
			function sync() {
				var sum = 0;
				places.forEach( function ( place ) {
					sum += ( counts[ place ] || 0 ) * PLACE_VALUE[ place ];
				} );
				total.textContent = String( sum );
			}
			places.forEach( function ( place ) {
				counts[ place ] = 0;
				var tray = el( 'div', { class: 'ohmylms-blocks-tray', 'aria-hidden': 'true' } );
				var number = el( 'output', { class: 'ohmylms-blocks-count', text: '0' } );
				function update( next ) {
					counts[ place ] = clamp( next, 0, max );
					number.textContent = String( counts[ place ] );
					tray.textContent = '';
					for ( var i = 0; i < counts[ place ]; i++ ) {
						tray.appendChild( el( 'span', { class: 'ohmylms-block is-' + place } ) );
					}
					setAnswer( root, place, String( counts[ place ] ) );
					sync();
				}
				var minus = el( 'button', { type: 'button', class: 'ohmylms-tile ohmylms-key', 'aria-label': 'Remove one ' + place, text: '−' } );
				var plus = el( 'button', { type: 'button', class: 'ohmylms-tile ohmylms-key', 'aria-label': 'Add one ' + place, text: '+' } );
				minus.addEventListener( 'click', function () {
					update( counts[ place ] - 1 );
				} );
				plus.addEventListener( 'click', function () {
					update( counts[ place ] + 1 );
				} );
				root.appendChild(
					el( 'div', { class: 'ohmylms-blocks-row' }, [
						el( 'span', { class: 'ohmylms-blocks-name', text: place } ),
						minus,
						number,
						plus,
						tray,
					] )
				);
			} );
			root.appendChild( total );
			sync();
		},
	} );

	/* ---------- Clock ---------- */

	host.register( 'set-clock', {
		build: function ( root, c ) {
			var snap = Number( c.snap ) || 1;
			var minutes = 0; // 0..719 on the 12-hour face
			var s = svg( 'svg', { viewBox: '0 0 220 220', class: 'ohmylms-vis-svg ohmylms-vis-clock', role: 'group', 'aria-label': 'Clock' } );
			s.style.maxWidth = '320px';
			s.appendChild( svg( 'circle', { cx: 110, cy: 110, r: 100, class: 'ohmylms-vis-face' } ) );
			for ( var m = 0; m < 60; m++ ) {
				var a = ( m / 60 ) * 2 * Math.PI;
				var major = m % 5 === 0;
				var inner = major ? 86 : 92;
				s.appendChild( svg( 'line', {
					x1: 110 + 100 * Math.sin( a ), y1: 110 - 100 * Math.cos( a ),
					x2: 110 + inner * Math.sin( a ), y2: 110 - inner * Math.cos( a ),
					class: 'ohmylms-vis-axis',
				} ) );
				if ( major ) {
					var n = m === 0 ? 12 : m / 5;
					s.appendChild( svg( 'text', {
						x: 110 + 60 * Math.sin( a ), y: 110 - 60 * Math.cos( a ) + 5,
						'text-anchor': 'middle', class: 'ohmylms-vis-text', text: String( n ),
					} ) );
				}
			}
			var hourHand = svg( 'line', { x1: 110, y1: 110, class: 'ohmylms-vis-hand is-hour' } );
			var minuteHand = svg( 'line', { x1: 110, y1: 110, class: 'ohmylms-vis-hand is-minute' } );
			var handle = svg( 'circle', { r: 9, class: 'ohmylms-vis-point', tabindex: '0', role: 'slider', 'aria-label': 'Minute hand', 'aria-valuemin': 0, 'aria-valuemax': 59 } );
			s.appendChild( hourHand );
			s.appendChild( minuteHand );
			s.appendChild( handle );
			s.appendChild( svg( 'circle', { cx: 110, cy: 110, r: 4, class: 'ohmylms-vis-axis' } ) );
			var digital = el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite' } );
			function draw() {
				var hh = Math.floor( minutes / 60 ) % 12;
				var mm = minutes % 60;
				var ha = ( ( hh * 60 + mm ) / 720 ) * 2 * Math.PI;
				var ma = ( mm / 60 ) * 2 * Math.PI;
				hourHand.setAttribute( 'x2', 110 + 40 * Math.sin( ha ) );
				hourHand.setAttribute( 'y2', 110 - 40 * Math.cos( ha ) );
				minuteHand.setAttribute( 'x2', 110 + 82 * Math.sin( ma ) );
				minuteHand.setAttribute( 'y2', 110 - 82 * Math.cos( ma ) );
				handle.setAttribute( 'cx', 110 + 82 * Math.sin( ma ) );
				handle.setAttribute( 'cy', 110 - 82 * Math.cos( ma ) );
				handle.setAttribute( 'aria-valuenow', mm );
				var shownHour = hh === 0 ? 12 : hh;
				digital.textContent = c.show_digital || root.hasAttribute( 'data-touched' ) ? shownHour + ':' + ( mm < 10 ? '0' : '' ) + mm : '';
			}
			function commit() {
				var hh = Math.floor( minutes / 60 ) % 12;
				setAnswer( root, 'h', hh === 0 ? 12 : hh );
				setAnswer( root, 'm', minutes % 60 );
				draw();
			}
			function move( delta ) {
				minutes = ( ( ( minutes + delta ) % 720 ) + 720 ) % 720;
				commit();
			}
			drag( handle, s, function ( p ) {
				var angle = Math.atan2( p.x - 110, 110 - p.y );
				var minute = Math.round( ( ( ( angle + 2 * Math.PI ) % ( 2 * Math.PI ) ) / ( 2 * Math.PI ) ) * 60 / snap ) * snap % 60;
				var current = minutes % 60;
				var hour = Math.floor( minutes / 60 );
				// Crossing 12 carries the hour hand along, in either direction.
				if ( current > 45 && minute < 15 ) {
					hour += 1;
				} else if ( current < 15 && minute > 45 ) {
					hour -= 1;
				}
				minutes = ( ( ( hour * 60 + minute ) % 720 ) + 720 ) % 720;
				commit();
			} );
			handle.addEventListener( 'keydown', function ( event ) {
				var keys = { ArrowRight: snap, ArrowUp: snap, ArrowLeft: -snap, ArrowDown: -snap, PageUp: 60, PageDown: -60 };
				if ( keys[ event.key ] !== undefined ) {
					event.preventDefault();
					move( keys[ event.key ] );
				}
			} );
			var buttons = el( 'div', { class: 'ohmylms-expression-keys' } );
			[ [ '−1 h', -60 ], [ '−' + snap + ' min', -snap ], [ '+' + snap + ' min', snap ], [ '+1 h', 60 ] ].forEach( function ( b ) {
				var button = el( 'button', { type: 'button', class: 'ohmylms-tile ohmylms-key', text: b[ 0 ] } );
				button.addEventListener( 'click', function () {
					move( b[ 1 ] );
				} );
				buttons.appendChild( button );
			} );
			root.appendChild( s );
			root.appendChild( digital );
			root.appendChild( buttons );
			draw();
		},
	} );

	/* ---------- Make an amount ---------- */

	host.register( 'make-amount', {
		build: function ( root, c ) {
			var denominations = c.denominations || [];
			var symbol = c.symbol || '';
			var counts = {};
			var tray = el( 'div', { class: 'ohmylms-money-tray', role: 'group', 'aria-label': 'Your money' } );
			var total = el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite' } );
			function sync() {
				var sum = 0;
				tray.textContent = '';
				denominations.forEach( function ( d ) {
					for ( var i = 0; i < ( counts[ d ] || 0 ); i++ ) {
						var chip = el( 'button', { type: 'button', class: 'ohmylms-bill', 'aria-label': 'Remove ' + d + ' ' + symbol, text: d + ' ' + symbol } );
						chip.addEventListener( 'click', function () {
							counts[ d ] -= 1;
							setAnswer( root, 'd' + d, counts[ d ] || null );
							sync();
						} );
						tray.appendChild( chip );
					}
					sum += ( counts[ d ] || 0 ) * d;
				} );
				total.textContent = sum + ' ' + symbol;
			}
			var purse = el( 'div', { class: 'ohmylms-money-purse', role: 'group', 'aria-label': 'Bills and coins' } );
			denominations.forEach( function ( d ) {
				counts[ d ] = 0;
				var bill = el( 'button', { type: 'button', class: 'ohmylms-bill is-source', 'aria-label': 'Add ' + d + ' ' + symbol, text: d + ' ' + symbol } );
				bill.addEventListener( 'click', function () {
					if ( counts[ d ] < 99 ) {
						counts[ d ] += 1;
						setAnswer( root, 'd' + d, counts[ d ] );
						sync();
					}
				} );
				purse.appendChild( bill );
			} );
			root.appendChild( purse );
			root.appendChild( tray );
			root.appendChild( total );
			sync();
		},
	} );

	/* ---------- Fill to a level ---------- */

	host.register( 'fill-level', {
		build: function ( root, c ) {
			var min = Number( c.min ) || 0;
			var max = Number( c.max ) || 1000;
			var step = Number( c.step ) || 50;
			var unit = c.unit || '';
			var top = 14;
			var bottom = 206;
			var s = svg( 'svg', { viewBox: '0 0 160 220', class: 'ohmylms-vis-svg ohmylms-vis-jug', 'aria-hidden': 'true' } );
			s.style.maxWidth = '160px';
			var liquid = svg( 'rect', { x: 40, width: 80, class: 'ohmylms-vis-liquid' } );
			s.appendChild( liquid );
			s.appendChild( svg( 'rect', { x: 40, y: top, width: 80, height: bottom - top, class: 'ohmylms-vis-glass' } ) );
			var ticks = Math.max( 1, Math.round( ( max - min ) / step ) );
			var every = Math.ceil( ( ticks + 1 ) / 10 );
			for ( var i = 0; i <= ticks; i++ ) {
				var y = bottom - ( i / ticks ) * ( bottom - top );
				s.appendChild( svg( 'line', { x1: 40, x2: i % every === 0 ? 56 : 48, y1: y, y2: y, class: 'ohmylms-vis-axis' } ) );
				if ( i % every === 0 ) {
					s.appendChild( svg( 'text', { x: 36, y: y + 4, 'text-anchor': 'end', class: 'ohmylms-vis-text', text: String( round( min + i * step ) ) } ) );
				}
			}
			var input = el( 'input', { type: 'range', class: 'ohmylms-vis-range', min: min, max: max, step: step, value: min, 'aria-label': 'Level' + ( unit ? ' (' + unit + ')' : '' ) } );
			var readout = el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite' } );
			function draw() {
				var v = Number( input.value );
				var h = ( ( v - min ) / ( max - min ) ) * ( bottom - top );
				liquid.setAttribute( 'y', bottom - h );
				liquid.setAttribute( 'height', h );
				readout.textContent = v + ( unit ? ' ' + unit : '' );
			}
			input.addEventListener( 'input', function () {
				draw();
				setAnswer( root, 'value', round( Number( input.value ) ) );
			} );
			root.appendChild( el( 'div', { class: 'ohmylms-jug-row' }, [ input, s ] ) );
			root.appendChild( readout );
			draw();
		},
	} );

	/* ---------- Bar chart ---------- */

	host.register( 'build-chart', {
		build: function ( root, c ) {
			var categories = c.categories || [];
			var max = Number( c.max ) || 10;
			var step = Number( c.step ) || 1;
			var unit = c.unit || '';
			if ( c.table ) {
				var table = el( 'table', { class: 'ohmylms-blank-table' } );
				c.table.forEach( function ( row ) {
					table.appendChild( el( 'tr', {}, [ el( 'th', { scope: 'row', text: row.label } ), el( 'td', { text: row.value + ( unit ? ' ' + unit : '' ) } ) ] ) );
				} );
				root.appendChild( table );
			}
			var colWidth = 70;
			var left = 40;
			var top = 14;
			var bottom = 190;
			var width = left + categories.length * colWidth + 10;
			var s = svg( 'svg', { viewBox: '0 0 ' + width + ' 230', class: 'ohmylms-vis-svg ohmylms-vis-chart', role: 'group', 'aria-label': 'Bar chart' } );
			s.style.maxWidth = Math.max( 260, width * 1.6 ) + 'px';
			var lines = Math.max( 1, Math.round( max / step ) );
			var every = Math.ceil( ( lines + 1 ) / 8 );
			for ( var i = 0; i <= lines; i++ ) {
				var y = bottom - ( i / lines ) * ( bottom - top );
				s.appendChild( svg( 'line', { x1: left, x2: width - 10, y1: y, y2: y, class: 'ohmylms-vis-grid' } ) );
				if ( i % every === 0 ) {
					s.appendChild( svg( 'text', { x: left - 6, y: y + 4, 'text-anchor': 'end', class: 'ohmylms-vis-text', text: String( round( i * step ) ) } ) );
				}
			}
			categories.forEach( function ( category, index ) {
				var x = left + index * colWidth + 10;
				var value = 0;
				var control = svg( 'g', { class: 'ohmylms-vis-bar-control', role: 'slider', tabindex: '0', 'aria-label': category.label, 'aria-valuemin': 0, 'aria-valuemax': max, 'aria-orientation': 'vertical' } );
				var bar = svg( 'rect', { x: x, width: colWidth - 20, class: 'ohmylms-vis-bar' } );
				var handle = svg( 'g', { class: 'ohmylms-vis-bar-handle', 'aria-hidden': 'true' }, [
					svg( 'rect', { x: -15, y: -9, width: 30, height: 18, rx: 5 } ),
					svg( 'path', { d: 'M -6 -3 H 6 M -6 0 H 6 M -6 3 H 6' } ),
				] );
				control.appendChild( bar );
				control.appendChild( handle );
				var text = svg( 'text', { x: x + ( colWidth - 20 ) / 2, y: 210, 'text-anchor': 'middle', class: 'ohmylms-vis-text', text: category.label } );
				function draw() {
					var h = ( value / max ) * ( bottom - top );
					bar.setAttribute( 'y', bottom - h );
					bar.setAttribute( 'height', h );
					handle.setAttribute( 'transform', 'translate(' + ( x + ( colWidth - 20 ) / 2 ) + ' ' + ( bottom - h ) + ')' );
					control.setAttribute( 'aria-valuenow', value );
				}
				function set( next ) {
					value = round( clamp( Math.round( next / step ) * step, 0, max ) );
					draw();
					setAnswer( root, category.id, value );
				}
				drag( control, s, function ( p ) {
					set( ( ( bottom - p.y ) / ( bottom - top ) ) * max );
				} );
				control.addEventListener( 'keydown', function ( event ) {
					var keys = { ArrowUp: step, ArrowRight: step, ArrowDown: -step, ArrowLeft: -step };
					if ( keys[ event.key ] !== undefined ) {
						event.preventDefault();
						set( value + keys[ event.key ] );
					}
				} );
				// A transparent column makes short bars easy to grab.
				var hit = svg( 'rect', { x: x, y: top, width: colWidth - 20, height: bottom - top, class: 'ohmylms-vis-hit' } );
				drag( hit, s, function ( p ) {
					set( ( ( bottom - p.y ) / ( bottom - top ) ) * max );
				} );
				s.appendChild( hit );
				s.appendChild( control );
				s.appendChild( text );
				draw();
			} );
			root.appendChild( s );
			root.appendChild( el( 'p', { class: 'ohmylms-vis-chart-help', text: 'Drag the handles up or down to set each bar, or focus a handle and use the arrow keys.' } ) );
		},
	} );

	/* ---------- Grid builder ---------- */

	host.register( 'grid-build', {
		build: function ( root, c ) {
			var rows = Number( c.rows ) || 6;
			var cols = Number( c.cols ) || 6;
			var on = {};
			var grid = el( 'div', { class: 'ohmylms-grid', role: 'group', 'aria-label': 'Grid', style: 'grid-template-columns:repeat(' + cols + ',minmax(0,34px))' } );
			var measures = c.show_measures ? el( 'p', { class: 'ohmylms-vis-readout', 'aria-live': 'polite' } ) : null;
			function sync() {
				if ( ! measures ) {
					return;
				}
				var area = Object.keys( on ).length;
				var perimeter = 0;
				Object.keys( on ).forEach( function ( key ) {
					var q = key.split( ',' ).map( Number );
					[ [ -1, 0 ], [ 1, 0 ], [ 0, -1 ], [ 0, 1 ] ].forEach( function ( d ) {
						if ( ! on[ ( q[ 0 ] + d[ 0 ] ) + ',' + ( q[ 1 ] + d[ 1 ] ) ] ) {
							perimeter += 1;
						}
					} );
				} );
				measures.textContent = 'Area: ' + area + '   Perimeter: ' + perimeter;
			}
			var painting = null;
			function set( r, col, button, state ) {
				var key = r + ',' + col;
				if ( !! on[ key ] === state ) {
					return;
				}
				if ( state ) {
					on[ key ] = true;
				} else {
					delete on[ key ];
				}
				button.setAttribute( 'aria-checked', state ? 'true' : 'false' );
				button.classList.toggle( 'is-on', state );
				setAnswer( root, 'r' + r + 'c' + col, state ? '1' : null );
				sync();
			}
			for ( var r = 0; r < rows; r++ ) {
				for ( var col = 0; col < cols; col++ ) {
					var button = el( 'button', { type: 'button', class: 'ohmylms-grid-cell', role: 'checkbox', 'aria-checked': 'false', 'aria-label': 'Row ' + ( r + 1 ) + ', column ' + ( col + 1 ) } );
					( function ( rr, cc, node ) {
						// Press starts painting on or off; dragging across squares carries it along.
						node.addEventListener( 'pointerdown', function ( event ) {
							event.preventDefault();
							painting = ! on[ rr + ',' + cc ];
							set( rr, cc, node, painting );
						} );
						node.addEventListener( 'pointerenter', function ( event ) {
							if ( painting !== null && event.buttons ) {
								set( rr, cc, node, painting );
							}
						} );
						node.addEventListener( 'keydown', function ( event ) {
							if ( event.key === ' ' || event.key === 'Enter' ) {
								event.preventDefault();
								set( rr, cc, node, ! on[ rr + ',' + cc ] );
							}
						} );
					}( r, col, button ) );
					grid.appendChild( button );
				}
			}
			document.addEventListener( 'pointerup', function () {
				painting = null;
			} );
			root.appendChild( grid );
			if ( measures ) {
				root.appendChild( measures );
				sync();
			}
		},
	} );
}() );
