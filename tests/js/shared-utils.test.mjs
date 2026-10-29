import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire, register } from 'node:module';
import { loadRecovered } from './recovered-fragments.mjs';

register( './shared-utils-hooks.mjs', import.meta.url );

const hostRequire = createRequire( import.meta.url );
const React = hostRequire( 'react' );
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const dayjs = hostRequire( 'dayjs' );

const utils = await import( '../../assets/src/shared/utils/index.js' );
const wpDate = await import( '../../assets/src/shared/utils/wpDate.mjs' );
const stubs = await import( './shared-utils-stubs.mjs' );

/**
 * Class list that records the order of every change.
 */
class ClassList {
	constructor( names, log, label ) {
		this.names = new Set( names );
		this.log = log;
		this.label = label;
	}

	add( name ) {
		this.log.push( `${ this.label }+${ name }` );
		this.names.add( name );
	}

	remove( name ) {
		this.log.push( `${ this.label }-${ name }` );
		this.names.delete( name );
	}
}

/**
 * Fake element with a class list.
 *
 * @param {string}   label Name used in the log.
 * @param {string[]} names Initial classes.
 * @param {string[]} log   Shared change log.
 * @returns {object} Element.
 */
function element( label, names, log ) {
	return { label, classList: new ClassList( names, log, label ) };
}

/**
 * Build a small admin menu and install it as the document.
 *
 * @param {object}  options
 * @param {boolean} options.withTopLevel Include the OhMyLMS top-level item.
 * @returns {{log: string[], selectors: string[]}} Change log and queries made.
 */
function installMenu( { withTopLevel } ) {
	const log = [];
	const wrap = element( 'wrap', [ 'wp-responsive-open' ], log );
	const topLevel = element(
		'top',
		[ 'wp-has-current-submenu', 'wp-menu-open', 'selected' ],
		log
	);
	const currentItem = element( 'old', [ 'current' ], log );
	const openItem = element(
		'open',
		[ 'wp-has-current-submenu', 'wp-menu-open', 'selected' ],
		log
	);
	const linkItem = element( 'target', [], log );
	const selectors = [];
	const menu = {
		getElementsByClassName: ( name ) => {
			selectors.push( 'class:' + name );
			return [ currentItem ];
		},
		querySelectorAll: ( selector ) => {
			selectors.push( 'all:' + selector );
			if ( '.wp-has-current-submenu' === selector ) {
				return [ openItem ];
			}
			return [ { parentElement: linkItem } ];
		},
		querySelector: ( selector ) => {
			selectors.push( 'one:' + selector );
			return withTopLevel ? topLevel : null;
		},
	};
	globalThis.document = {
		querySelector: ( selector ) =>
			'#adminmenu' === selector ? menu : wrap,
	};
	return { log, selectors };
}

test( 'highlightAdminMenu changes the menu like the recovered HG', () => {
	// The recovered code reads `document` from its own context.
	for ( const withTopLevel of [ true, false ] ) {
		for ( const open of [ 'ohmylms', true, false, '', undefined, 0 ] ) {
			for ( const slug of [ '/', 'courses', 'a/b', 'assessments' ] ) {
				const expected = installMenu( { withTopLevel } );
				const recovered = loadRecovered(
					[
						{
							fragment: 'shared/dashboard-income-overview.js',
							names: [ 'HG' ],
						},
					],
					{ document: globalThis.document }
				);
				recovered.HG( open, slug );
				const actual = installMenu( { withTopLevel } );
				utils.highlightAdminMenu( open, slug );
				assert.deepEqual( actual.log, expected.log );
				assert.deepEqual( actual.selectors, expected.selectors );
				assert.ok( expected.log.length > 0 );
			}
		}
	}
} );

test( 'link selectors match the dashboard and hash routes', () => {
	assert.equal(
		utils.getAdminMenuLinkSelector( '/' ),
		'li > a[href$="admin.php?page=ohmylms"], li > a[href*="admin.php?page=ohmylms?"]'
	);
	assert.equal(
		utils.getAdminMenuLinkSelector( 'courses' ),
		'li > a[href*="admin.php?page=ohmylms#/courses"]'
	);
} );

/**
 * Fake DOMParser that strips tags and decodes a few entities.
 */
class FakeParser {
	parseFromString( html ) {
		if ( html.includes( 'THROW' ) ) {
			throw new Error( 'parse failed' );
		}
		if ( html.includes( 'NULLTEXT' ) ) {
			return { documentElement: { textContent: null } };
		}
		const text = html
			.replace( /<[^>]*>/g, '' )
			.replace( /&amp;/g, '&' )
			.replace( /&lt;/g, '<' )
			.replace( /&#x([0-9a-f]+);/gi, ( match, hex ) =>
				String.fromCodePoint( parseInt( hex, 16 ) )
			);
		return { documentElement: { textContent: text } };
	}
}

test( 'decodeHtmlEntities returns what the recovered Ge returns', () => {
	globalThis.window = globalThis;
	globalThis.DOMParser = FakeParser;
	const { Ge } = loadRecovered(
		[
			{
				fragment: 'shared/course-builder-lesson-actions.js',
				names: [ 'Ge' ],
			},
		],
		{ DOMParser: FakeParser }
	);
	const inputs = [
		'Tom &amp; Jerry',
		'<b>Bold</b> &lt;tag&gt;',
		'<script>alert(1)</script>text',
		'café 中文 &#x1F600;',
		'',
		'THROW',
		'NULLTEXT',
		null,
		undefined,
		42,
		{},
		[ 'a' ],
		true,
	];
	for ( const input of inputs ) {
		assert.equal(
			utils.decodeHtmlEntities( input ),
			Ge( input ),
			String( input )
		);
	}
	assert.equal(
		utils.decodeHtmlEntities( 'Tom &amp; Jerry' ),
		'Tom & Jerry'
	);
	assert.equal( utils.decodeHtmlEntities( 7 ), '' );
} );

test( 'PriceDisplay renders the same markup as the recovered price', () => {
	const recovered = loadRecovered(
		[
			{
				fragment: 'shared/course-funnel-upsell-downsell.js',
				names: [ 'UH', 'qH' ],
			},
		],
		{ React, g: React }
	);
	const currencies = [ undefined, '$', '€', 'kr', '' ];
	const positions = [
		undefined,
		'left',
		'right',
		'left_space',
		'right_space',
		'other',
		null,
	];
	const prices = [
		undefined,
		0,
		5,
		1234.5,
		1234.567,
		0.005,
		-3,
		1e9,
		'12.3',
	];
	const dels = [ undefined, true, false ];
	let count = 0;
	for ( const currency of currencies ) {
		for ( const position of positions ) {
			for ( const price of prices ) {
				for ( const del of dels ) {
					const props = {
						currency,
						currency_pos: position,
						price,
						del,
					};
					assert.equal(
						renderToStaticMarkup(
							React.createElement( utils.PriceDisplay, props )
						),
						renderToStaticMarkup(
							React.createElement( recovered.qH, props )
						),
						JSON.stringify( props )
					);
					count++;
				}
			}
		}
	}
	assert.ok( 945 === count );
} );

test( 'price helpers', () => {
	assert.equal( utils.formatPriceAmount( 1234.567 ), '1,234.57' );
	assert.equal( utils.formatPriceAmount( 5 ), '5' );
	assert.deepEqual( utils.getPriceLayout( 'right_space', true ), {
		symbolFirst: false,
		style: {
			display: 'inline-flex',
			flexWrap: 'wrap',
			gap: '6px',
			textDecoration: 'line-through',
		},
	} );
	assert.equal( utils.getPriceLayout( 'nonsense', false ).symbolFirst, true );
} );

test( 'isValidDateRange matches the recovered xq', () => {
	const { xq } = loadRecovered(
		[ { fragment: 'shared/earnings-report.js', names: [ 'xq' ] } ],
		{ sn: () => dayjs }
	);
	const inputs = [
		[ '2024-01-01', '2024-01-31' ],
		[ dayjs( '2024-02-01' ), dayjs( '2024-02-09' ) ],
		[ new Date( 2024, 1, 1 ), new Date( 2024, 1, 2 ) ],
		[ '2024-01-01', 'not a date' ],
		[ 'nope', '2024-01-01' ],
		[ '2024-01-01' ],
		[ '2024-01-01', '2024-01-02', '2024-01-03' ],
		[ null, null ],
		[ undefined, undefined ],
		[],
		null,
		undefined,
		'2024-01-01',
		{ 0: '2024-01-01', 1: '2024-01-02', length: 2 },
		42,
	];
	for ( const input of inputs ) {
		assert.equal(
			utils.isValidDateRange( input, dayjs ),
			xq( input ),
			JSON.stringify( input )
		);
	}
	assert.equal( utils.isValidDateRange( inputs[ 0 ], dayjs ), true );
} );

test( 'wpDate re-exports the WordPress date functions', () => {
	assert.equal( wpDate.format, stubs.format );
	assert.equal( wpDate.dateI18n, stubs.dateI18n );
	const root = path.resolve( import.meta.dirname, '../..' );
	const fragment = fs.readFileSync(
		path.join(
			root,
			'assets/src/application/screens/course-report/CourseReportRoute.js'
		),
		'utf8'
	);
	assert.match( fragment, /wq = wp\.date;/ );
} );
