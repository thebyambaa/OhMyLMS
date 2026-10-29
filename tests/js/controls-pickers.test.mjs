import test from 'node:test';
import assert from 'node:assert/strict';
import { register } from 'node:module';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import {
	Renderer,
	findAll,
	memo,
	plain,
	probe,
	useEffect,
	useRef,
	useState,
} from './controls-pickers-harness.mjs';

// Authored picker modules import `@wordpress/components`, `@wordpress/element`
// and sibling controls; the hooks swap those for probes and a small renderer.
register( './controls-pickers-hooks.mjs', import.meta.url );
const model =
	await import( '../../assets/src/shared/controls/pickers/date-range-model.mjs' );
const colorModel =
	await import( '../../assets/src/shared/controls/pickers/color-model.mjs' );
const dom =
	await import( '../../assets/src/shared/controls/pickers/date-range-dom.mjs' );
const { ColorPickerWP, DateRangePickerWP } =
	await import( '../../assets/src/shared/controls/pickers/index.js' );

// Recovered factory 38093 (the controls kit). Everything it imports is replaced
// by probes except dayjs (74353) and its isBetween plugin (37872), which are
// the real recovered libraries; React hooks come from the test renderer.
const hostRequire = installBrowserStubs();
globalThis.wp = new Proxy( {}, { get: () => ( {} ) } );
const React = hostRequire( 'react' );
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const probeModule = () =>
	new Proxy(
		{},
		{
			get: ( target, key ) =>
				'__esModule' === key ? true : probe( String( key ) ),
		}
	);
const overrides = {
	12470: { __: ( text ) => text },
	41594: {
		createElement: React.createElement,
		Fragment: React.Fragment,
		memo,
		useState,
		useEffect,
		useRef,
	},
	91386: { useState, useEffect, useRef },
	94490: { A: probe( 'ButtonWP' ) },
	36032: { A: probe( 'CardWP' ) },
	4315: { A: probe( 'FlexWP' ) },
	99166: { A: probe( 'SpacerWP' ) },
	2214: probeModule(),
};
for ( const id of [
	11541, 12278, 15468, 21186, 22563, 24295, 25946, 26701, 27268, 39706, 43052,
	46005, 50127, 52485, 53725, 54870, 55907, 57677, 58273, 6425, 65, 66655,
	66718, 68119, 69986, 71847, 77558, 79476, 81381, 83154,
] ) {
	overrides[ id ] = probeModule();
}
const load = createLoader( overrides );
const recovered = load( 38093 );
const dayjs = load( 74353 );
dayjs.extend( load( 37872 ) );

const markup = ( Component, props ) => {
	const original = console.error;
	// The recovered icon uses hyphenated SVG attributes, which React warns about.
	console.error = () => {};
	try {
		return renderToStaticMarkup( React.createElement( Component, props ) );
	} finally {
		console.error = original;
	}
};

// ---------------------------------------------------------------- pure logic
// These tests compare the authored helpers with the real recovered dayjs.

const stamp = ( date ) => ( date.isValid() ? date.valueOf() : NaN );
const same = ( actual, expected ) =>
	assert.ok(
		Object.is( actual.getTime(), stamp( expected ) ),
		`${ actual } != ${ expected.format() }`
	);

const parseInputs = [
	'2024-05-09',
	'2024-05-09T13:45:10',
	'2024-05-09 13:45',
	'2024/5/9',
	'2024-05',
	'2024',
	'2024-05-09T13:45:10.123456',
	'2024-05-09T13:45:10Z',
	'2024-05-09T13:45:10+02:00',
	'May 9, 2024',
	'not a date',
	'',
	'0099-01-01',
	'2024-02-30',
	new Date( 2024, 4, 9, 7, 8, 9 ),
	1715247600000,
];

test( 'parseDay matches dayjs parsing, including invalid input', () => {
	for ( const input of parseInputs ) {
		same( model.parseDay( input ), dayjs( input ) );
	}
	same( model.parseDay( null ), dayjs( null ) );
	assert.ok( Math.abs( model.parseDay().getTime() - Date.now() ) < 1000 );
} );

test( 'formatDay matches dayjs for both formats', () => {
	for ( const input of parseInputs ) {
		for ( const [ ours, theirs ] of [
			[ model.DATE_FORMAT, 'YYYY-MM-DD' ],
			[ model.DISPLAY_FORMAT, 'DD/MM/YYYY' ],
		] ) {
			assert.equal(
				model.formatDay( model.parseDay( input ), ours ),
				dayjs( input ).format( theirs ),
				String( input )
			);
		}
	}
} );

const everyDay = [];
for (
	let t = Date.UTC( 2023, 10, 20 );
	t < Date.UTC( 2025, 2, 10 );
	t += 86400000
) {
	const day = new Date( t );
	everyDay.push(
		new Date(
			day.getUTCFullYear(),
			day.getUTCMonth(),
			day.getUTCDate(),
			13,
			5,
			6,
			7
		)
	);
}

test( 'week, month and day arithmetic matches dayjs on every day of 2024', () => {
	for ( const date of everyDay ) {
		const r = dayjs( date );
		same( model.startOfWeek( date ), r.startOf( 'week' ) );
		same( model.endOfWeek( date ), r.endOf( 'week' ) );
		same( model.startOfMonth( date ), r.startOf( 'month' ) );
		same( model.endOfMonth( date ), r.endOf( 'month' ) );
		same( model.addDays( date, -6 ), r.subtract( 6, 'days' ) );
		for ( const n of [ -1, 1, 13 ] ) {
			same( model.addMonths( date, n ), r.add( n, 'month' ) );
		}
		same( model.setMonth( date, 1 ), r.month( 1 ) );
		same( model.setYear( date, 2023 ), r.year( 2023 ) );
		same( model.setDayOfMonth( date, 31 ), r.date( 31 ) );
		same( model.setDayOfMonth( date, 0 ), r.date( 0 ) );
	}
} );

test( 'preset ranges match the recovered preset arithmetic', () => {
	const f = ( d ) => d.format( 'YYYY-MM-DD' );
	for ( const now of everyDay ) {
		const a = dayjs( now );
		assert.deepEqual( model.getPresetRange( 'today', now ), [
			f( a ),
			f( a ),
		] );
		assert.deepEqual( model.getPresetRange( 'this_week', now ), [
			f( a.startOf( 'week' ) ),
			f( a.endOf( 'week' ) ),
		] );
		assert.deepEqual( model.getPresetRange( 'last_7_days', now ), [
			f( a.subtract( 6, 'days' ) ),
			f( a ),
		] );
		assert.deepEqual( model.getPresetRange( 'this_month', now ), [
			f( a.startOf( 'month' ) ),
			f( a.endOf( 'month' ) ),
		] );
	}
	assert.deepEqual( model.getPresetRange( 'custom' ), [
		undefined,
		undefined,
	] );
} );

test( 'day comparisons match dayjs, valid and invalid', () => {
	const values = [
		...parseInputs.filter( ( v ) => 'string' === typeof v ),
		...everyDay.filter( ( _, i ) => 0 === i % 29 ),
	];
	for ( const a of values ) {
		for ( const b of values ) {
			const x = model.parseDay( a );
			const y = model.parseDay( b );
			assert.equal(
				model.isSameDay( x, y ),
				dayjs( a ).isSame( b, 'day' )
			);
			assert.equal(
				model.isAfterDay( x, y ),
				dayjs( a ).isAfter( b, 'day' )
			);
			assert.equal(
				model.isBeforeDay( x, y ),
				dayjs( a ).isBefore( b, 'day' )
			);
		}
	}
	const band = [ '2024-05-10', '2024-05-12T22:00:00', '2024-05-14', 'oops' ];
	for ( const d of everyDay.filter( ( _, i ) => 0 === i % 7 ) ) {
		for ( const from of band ) {
			for ( const to of band ) {
				assert.equal(
					model.isBetweenDays(
						d,
						model.parseDay( from ),
						model.parseDay( to )
					),
					dayjs( d ).isBetween( from, to, 'day', '[]' )
				);
			}
		}
	}
} );

test( 'range label, pick and selection logic', () => {
	assert.equal(
		model.getRangeLabel( '2024-01-05', '2024-01-09', 'Pick' ),
		'05/01/2024 - 09/01/2024'
	);
	assert.equal( model.getRangeLabel( '2024-01-05', null, 'Pick' ), 'Pick' );
	assert.equal( model.getRangeLabel( '', '2024-01-09', 'Pick' ), 'Pick' );
	assert.deepEqual( model.pickDay( null, null, 'a' ), {
		type: 'start',
		start: 'a',
	} );
	assert.deepEqual( model.pickDay( 'first', 'last', 'a' ), {
		type: 'start',
		start: 'a',
	} );
	assert.deepEqual(
		model.pickDay( '2024-05-10', null, '2024-05-12T00:00:00' ),
		{
			type: 'range',
			startDate: '2024-05-10',
			endDate: '2024-05-12T00:00:00',
		}
	);
	assert.deepEqual(
		model.pickDay( '2024-05-10', undefined, '2024-05-02T00:00:00' ),
		{
			type: 'range',
			startDate: '2024-05-02T00:00:00',
			endDate: '2024-05-10',
		}
	);
	assert.deepEqual(
		model.resolveSelection( { startDate: null, endDate: null } ),
		{ close: true, value: [ null, null ] }
	);
	assert.deepEqual(
		model.resolveSelection( {
			startDate: '2024-05-02T10:00:00',
			endDate: '2024-05-10',
		} ),
		{
			close: true,
			value: [ '2024-05-02', '2024-05-10' ],
			dates: [ '2024-05-02', '2024-05-10' ],
		}
	);
} );

test( 'colour helpers', () => {
	assert.equal(
		colorModel.getIndicatorClassName( true ),
		'ohmylms-color-indicator-button ohmylms-active'
	);
	assert.equal(
		colorModel.getIndicatorClassName( false ),
		'ohmylms-color-indicator-button '
	);
	assert.equal(
		colorModel.getNextColor( '#111', { hex: '#222' }, false ),
		'#222'
	);
	assert.equal(
		colorModel.getNextColor( '#111', { hex: '#222' }, true ),
		'#111'
	);
	assert.equal(
		colorModel.getNextColor( '#111', undefined, false ),
		undefined
	);
	assert.equal( colorModel.getNextColor( '#111', null, false ), undefined );
	const inside = { contains: ( t ) => 'in' === t };
	assert.equal( colorModel.isOutside( inside, 'in' ), false );
	assert.equal( colorModel.isOutside( inside, 'out' ), true );
	assert.equal( colorModel.isOutside( null, 'out' ), false );
} );

// ------------------------------------------------------------ static markup
// Real renders of the authored and the recovered component, compared as HTML.

const colorCases = {
	'no props': {},
	'colour only': { initialColor: '#ff0000' },
	'reset and code': {
		initialColor: '#fff',
		isShowResetBtn: true,
		showColorCode: true,
		defaultColor: '#000',
	},
	'disabled, no alpha': {
		initialColor: '#123456',
		disabled: true,
		disableAlpha: true,
	},
	'reset without colour': { isShowResetBtn: true },
};
for ( const [ name, props ] of Object.entries( colorCases ) ) {
	test( `ColorPickerWP markup matches the recovered control: ${ name }`, () => {
		assert.equal(
			markup( ColorPickerWP, props ),
			markup( recovered.ColorPickerWP, props )
		);
	} );
}

const rangeCases = {
	'no dates': {},
	'both dates': {
		initialStartDate: '2024-01-05',
		initialEndDate: '2024-01-09',
	},
	'start only': { initialStartDate: '2024-01-05' },
	'date objects': {
		initialStartDate: new Date( 2024, 0, 5 ),
		initialEndDate: new Date( 2024, 11, 31 ),
	},
	'datetime strings': {
		initialStartDate: '2024-01-05 10:00:00',
		initialEndDate: '2024-01-09 10:00:00',
	},
	invalid: { initialStartDate: 'x', initialEndDate: 'y' },
};
for ( const [ name, props ] of Object.entries( rangeCases ) ) {
	test( `DateRangePickerWP closed markup matches the recovered control: ${ name }`, () => {
		assert.equal(
			markup( DateRangePickerWP, props ),
			markup( recovered.DateRangePickerWP, props )
		);
	} );
}

// -------------------------------------------------- behaviour in the renderer
// Both components are rendered by the test renderer with a fake document, so
// state, effects, handlers and DOM highlighting are compared step by step.

/**
 * Minimal calendar and document, enough for the highlight logic.
 *
 * @param {Array}   specs   Day buttons.
 * @param {Object}  options Options.
 * @param {boolean} options.monthYear Whether the month caption exists.
 * @return {Object} Handles on the fake DOM.
 */
function installCalendar( specs, { monthYear = true } = {} ) {
	const calendar = {};
	const observers = [];
	const documentListeners = {};
	const rows = new Map();
	const cells = specs.map( ( spec ) => {
		const classes = new Set();
		const listeners = {};
		return {
			spec,
			classes,
			textContent: spec.text,
			getAttribute: ( name ) => spec.attrs?.[ name ] ?? null,
			querySelector: () => null,
			closest: ( selector ) => {
				if ( 'table' === selector ) {
					return spec.captioned
						? {
								previousElementSibling: {
									querySelector: () => ( {} ),
								},
							}
						: null;
				}
				if ( '.components-datetime__date' === selector ) {
					return false === spec.outside ? null : calendar;
				}
				if ( 'tr' === selector ) {
					return rows.get( spec.row );
				}
				if ( 'tr:last-child' === selector ) {
					return spec.lastRow ? rows.get( spec.row ) : null;
				}
				return null;
			},
			classList: {
				add: ( ...names ) => names.forEach( ( n ) => classes.add( n ) ),
				remove: ( ...names ) =>
					names.forEach( ( n ) => classes.delete( n ) ),
			},
			addEventListener: ( type, fn ) =>
				( listeners[ type ] ||= [] ).push( fn ),
			removeEventListener: ( type, fn ) => {
				listeners[ type ] = ( listeners[ type ] || [] ).filter(
					( f ) => f !== fn
				);
			},
			fire: ( type ) =>
				( listeners[ type ] || [] ).forEach( ( fn ) => fn() ),
			listenerCount: () => Object.values( listeners ).flat().length,
		};
	} );
	cells.forEach( ( cell ) => {
		if ( ! rows.has( cell.spec.row ) ) {
			rows.set( cell.spec.row, {
				querySelector: () =>
					cells.find( ( c ) => c.spec.row === cell.spec.row ),
			} );
		}
	} );
	globalThis.document = {
		querySelector: ( selector ) => {
			if ( '.components-datetime__date' === selector ) {
				return calendar;
			}
			if ( '.components-datetime__date__month-year' === selector ) {
				return monthYear ? { textContent: 'May 2024' } : null;
			}
			return null;
		},
		querySelectorAll: ( selector ) =>
			'.components-datetime__date__day' === selector ? cells : [],
		addEventListener: ( type, fn ) =>
			( documentListeners[ type ] ||= [] ).push( fn ),
		removeEventListener: ( type, fn ) => {
			documentListeners[ type ] = (
				documentListeners[ type ] || []
			).filter( ( f ) => f !== fn );
		},
		listeners: documentListeners,
	};
	globalThis.MutationObserver = class {
		constructor( callback ) {
			this.callback = callback;
			this.connected = false;
			observers.push( this );
		}

		observe() {
			this.connected = true;
		}

		disconnect() {
			this.connected = false;
		}
	};
	return {
		cells,
		snapshot: () => cells.map( ( c ) => [ ...c.classes ].sort() ),
		mutate: () =>
			observers
				.filter( ( o ) => o.connected )
				.forEach( ( o ) => o.callback() ),
		active: () => observers.filter( ( o ) => o.connected ).length,
	};
}

const gridSpecs = () =>
	Array.from( { length: 35 }, ( _, i ) => {
		const date = new Date( 2024, 3, 28 + i );
		const day = String( date.getDate() );
		const kind = i % 5;
		let attrs = { 'data-date': dayjs( date ).format( 'YYYY-MM-DD' ) };
		if ( 1 === kind ) {
			attrs = {
				'data-date': dayjs( date ).format( 'YYYY-MM-DDTHH:mm:ss' ),
			};
		} else if ( 2 === kind ) {
			attrs = {
				'aria-label': `${ date.toLocaleString( 'en', {
					month: 'long',
				} ) } ${ day }, ${ date.getFullYear() }`,
			};
		}
		return {
			text: day,
			row: Math.floor( i / 7 ),
			lastRow: i >= 28,
			attrs,
		};
	} );

const named = ( r, name ) => findAll( r.tree, ( n ) => name === n.tag );
const labelled = ( r, className ) =>
	findAll( r.tree, ( n ) => className === n.props.className )[ 0 ];

/**
 * Drive a picker through a scenario and log what it renders and reports.
 *
 * @param {Function} Component Component under test.
 * @param {Object}   props     Props without onChange.
 * @param {Array}    steps     Functions receiving the renderer and the fake DOM.
 * @return {Array} Log to compare.
 */
function rangeScenario( Component, props, steps ) {
	const calls = [];
	const cal = installCalendar( gridSpecs() );
	const r = new Renderer( Component, {
		...props,
		onChange: ( v ) => calls.push( v ),
	} );
	const log = [ plain( r.tree ) ];
	steps.forEach( ( step ) => {
		step( r, cal );
		r.flush();
		log.push( {
			tree: plain( r.tree ),
			calls: structuredClone( calls ),
			classes: cal.snapshot(),
			observers: cal.active(),
		} );
	} );
	r.unmount();
	log.push( { observers: cal.active() } );
	return log;
}

const open = ( r ) => labelled( r, 'ohmylms-date-range-input' ).props.onClick();
const pick = ( value ) => ( r ) =>
	named( r, 'DatePicker' )[ 0 ].props.onChange( value );
const hover = ( index ) => ( r, cal ) =>
	cal.cells[ index ].fire( 'mouseenter' );
const leave = ( index ) => ( r, cal ) =>
	cal.cells[ index ].fire( 'mouseleave' );
const mutate = ( r, cal ) => cal.mutate();
const clear = ( r ) =>
	named( r, 'ButtonWP' )
		.find( ( n ) => n.props.isSecondary )
		.props.onClick();
const closePopover = ( r ) => named( r, 'Popover' )[ 0 ].props.onClose();
const preset = ( index ) => ( r ) =>
	named( r, 'DropdownMenu' )[ 0 ].props.controls[ index ].onClick();

const rangeFlows = {
	'open with a range and complete a new one': [
		{ initialStartDate: '2024-05-06', initialEndDate: '2024-05-09' },
		[
			open,
			mutate,
			pick( '2024-05-14T00:00:00' ),
			mutate,
			hover( 10 ),
			pick( '2024-05-03T00:00:00' ),
		],
	],
	'start only, hover forwards and backwards': [
		{ initialStartDate: '2024-05-10' },
		[
			open,
			hover( 20 ),
			mutate,
			leave( 20 ),
			hover( 5 ),
			mutate,
			pick( '2024-05-12T09:30:00' ),
		],
	],
	'start only, click before the start': [
		{ initialStartDate: '2024-05-10' },
		[ open, pick( '2024-05-01T00:00:00' ) ],
	],
	'no dates, two clicks, reopen shows the result': [
		{},
		[
			open,
			pick( '2024-05-08T00:00:00' ),
			hover( 12 ),
			pick( '2024-05-08T00:00:00' ),
			open,
			mutate,
		],
	],
	clear: [
		{ initialStartDate: '2024-05-06', initialEndDate: '2024-05-09' },
		[ open, clear, open, clear ],
	],
	'close the popover without choosing': [
		{ initialStartDate: '2024-05-06', initialEndDate: '2024-05-09' },
		[ open, closePopover, open, open ],
	],
};
for ( const [ name, [ props, steps ] ] of Object.entries( rangeFlows ) ) {
	test( `DateRangePickerWP behaves like the recovered control: ${ name }`, () => {
		assert.deepEqual(
			rangeScenario( DateRangePickerWP, props, steps ),
			rangeScenario( recovered.DateRangePickerWP, props, steps )
		);
	} );
}

test( 'DateRangePickerWP presets report the same ranges as the recovered control', () => {
	for ( const index of [ 0, 1, 2, 3, 4 ] ) {
		const props = {
			initialStartDate: '2020-01-01',
			initialEndDate: '2020-01-02',
		};
		assert.deepEqual(
			rangeScenario( DateRangePickerWP, props, [ preset( index ) ] ),
			rangeScenario( recovered.DateRangePickerWP, props, [
				preset( index ),
			] ),
			`preset ${ index }`
		);
	}
} );

test( 'DateRangePickerWP follows new props and works without onChange', () => {
	const run = ( Component ) => {
		installCalendar( gridSpecs() );
		const r = new Renderer( Component, {
			initialStartDate: '2024-01-01',
			initialEndDate: '2024-01-02',
		} );
		const log = [ plain( r.tree ) ];
		r.update( {
			initialStartDate: '2024-03-01',
			initialEndDate: '2024-03-31',
		} );
		log.push( plain( r.tree ) );
		preset( 0 )( r );
		r.flush();
		log.push( plain( r.tree ) );
		return log;
	};
	assert.deepEqual(
		run( DateRangePickerWP ),
		run( recovered.DateRangePickerWP )
	);
} );

test( 'highlight classes match the recovered control when cells carry only their number', () => {
	// No start or end yet, so the recovered control may guess dates from "today".
	const specs = [
		{ text: '30', row: 0, lastRow: false, captioned: true },
		{ text: '4', row: 0, lastRow: false, captioned: false },
		{ text: '1', row: 1, lastRow: false, captioned: false },
		{ text: '9', row: 1, lastRow: false, captioned: false },
		{ text: '28', row: 2, lastRow: true, captioned: false },
		{ text: '31', row: 2, lastRow: true, captioned: false },
		{
			text: '12',
			row: 2,
			lastRow: false,
			captioned: false,
			outside: false,
		},
		{ text: 'x', row: 2, lastRow: false, captioned: false },
	];
	const run = ( Component ) => {
		const cal = installCalendar( specs );
		const r = new Renderer( Component, { onChange() {} } );
		open( r );
		r.flush();
		const first = cal.snapshot();
		cal.cells[ 3 ].fire( 'mouseenter' );
		r.flush();
		return [
			first,
			cal.snapshot(),
			cal.cells.map( ( c ) => c.listenerCount() > 0 ),
		];
	};
	assert.deepEqual(
		run( DateRangePickerWP ),
		run( recovered.DateRangePickerWP )
	);
} );

test( 'hover listeners are replaced, not stacked, when the calendar re-renders', () => {
	const cal = installCalendar( gridSpecs() );
	const noop = () => {};
	for ( let i = 0; i < 3; i++ ) {
		dom.bindHover( globalThis.document, '2024-05-10', null, noop );
	}
	assert.ok( cal.cells.every( ( cell ) => 2 === cell.listenerCount() ) );
} );

// ----------------------------------------------- colour picker interactions

/**
 * Drive the colour picker through a scenario and log what it renders and reports.
 *
 * @param {Function} Component Component under test.
 * @param {Object}   props     Props without onChange.
 * @param {Array}    steps     Functions receiving the renderer.
 * @return {Array} Log to compare.
 */
function colorScenario( Component, props, steps ) {
	const calls = [];
	installCalendar( [] );
	const count = () =>
		( globalThis.document.listeners.mousedown || [] ).length;
	const r = new Renderer( Component, {
		...props,
		onChange: ( v ) => calls.push( v ),
	} );
	const log = [ plain( r.tree ) ];
	for ( const step of steps ) {
		step( r );
		r.flush();
		log.push( {
			tree: plain( r.tree ),
			calls: structuredClone( calls ),
			listeners: count(),
		} );
	}
	r.unmount();
	log.push( count() );
	return log;
}
const toggle = ( r ) =>
	findAll(
		r.tree,
		( n ) => 'Toggle color picker' === n.props[ 'aria-label' ]
	)[ 0 ].props.onClick();
const choose = ( hex ) => ( r ) =>
	named( r, 'ColorPicker' )[ 0 ].props.onChangeComplete( {
		hex,
		rgb: { r: 1 },
	} );
const reset = ( r ) => named( r, 'ButtonWP' )[ 0 ].props.onClick();
const mouseDown = ( inside ) => () =>
	globalThis.document.listeners.mousedown?.[ 0 ]?.( { target: { inside } } );

const colorFlows = {
	'open, choose, close by toggling': [
		{ initialColor: '#111111' },
		[ toggle, choose( '#abcdef' ), toggle ],
	],
	'click outside closes, click inside does not': [
		{ initialColor: '#111111' },
		[ toggle, mouseDown( true ), mouseDown( false ), toggle ],
	],
	'reset restores the default colour': [
		{
			initialColor: '#111111',
			isShowResetBtn: true,
			defaultColor: '#ffffff',
			showColorCode: true,
		},
		[ reset, toggle, choose( '#00ff00' ), reset ],
	],
	'disabled keeps the colour but still reports': [
		{ initialColor: '#111111', disabled: true, isShowResetBtn: true },
		[ toggle, choose( '#abcdef' ), reset ],
	],
	'colour without initial value': [ {}, [ toggle, choose( '#abcdef' ) ] ],
};
for ( const [ name, [ props, steps ] ] of Object.entries( colorFlows ) ) {
	test( `ColorPickerWP behaves like the recovered control: ${ name }`, () => {
		assert.deepEqual(
			colorScenario( ColorPickerWP, props, steps ),
			colorScenario( recovered.ColorPickerWP, props, steps )
		);
	} );
}

test( 'ColorPickerWP follows a new initialColor and ignores an empty one', () => {
	const run = ( Component ) => {
		installCalendar( [] );
		const r = new Renderer( Component, { initialColor: '#111111' } );
		const log = [ plain( r.tree ) ];
		r.update( { initialColor: '#222222' } );
		log.push( plain( r.tree ) );
		r.update( { initialColor: '' } );
		log.push( plain( r.tree ) );
		return log;
	};
	assert.deepEqual( run( ColorPickerWP ), run( recovered.ColorPickerWP ) );
} );

test( 'the range scenarios really exercise highlighting and reporting', () => {
	const classesSeen = ( flow ) => {
		const [ props, steps ] = rangeFlows[ flow ];
		const log = rangeScenario( recovered.DateRangePickerWP, props, steps );
		return {
			log,
			seen: new Set(
				log.flatMap( ( entry ) => ( entry.classes || [] ).flat() )
			),
		};
	};
	const hovering = classesSeen( 'start only, hover forwards and backwards' );
	assert.ok( hovering.seen.has( 'is-hover-range' ) );
	assert.deepEqual( hovering.log.at( -2 ).calls, [
		[ '2024-05-10', '2024-05-12' ],
	] );
	assert.equal( hovering.log.at( -1 ).observers, 0 );
	const complete = classesSeen( 'open with a range and complete a new one' );
	assert.ok( complete.seen.has( 'has-endpoint' ) );
	assert.ok( complete.seen.has( 'is-selected' ) );
} );
