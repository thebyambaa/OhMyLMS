import test from 'node:test';
import assert from 'node:assert/strict';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import {
	EditIcon,
	InfoIcon,
	SparkleIcon,
	WarningBubbleIcon,
} from '../../assets/src/shared/icons/index.js';

const hostRequire = installBrowserStubs();
const { createElement } = hostRequire( 'react' );
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
// 12470 is the wp.i18n shim, imported but unused by these icons.
const load = createLoader( { 12470: {} } );

const render = ( Component, props ) =>
	renderToStaticMarkup( createElement( Component, props ) );

const cases = [
	[
		'EditIcon (22601)',
		EditIcon,
		'22601',
		[ undefined, { width: 24 }, { height: '30' }, { width: 8, height: 9 } ],
	],
	[
		'InfoIcon (79476)',
		InfoIcon,
		'79476',
		[ undefined, { className: 'hint' }, { className: 'a b', width: 99 } ],
	],
	[
		'WarningBubbleIcon (75809)',
		WarningBubbleIcon,
		'75809',
		[ undefined, { className: 'ignored' } ],
	],
	[
		'SparkleIcon (98243)',
		SparkleIcon,
		'98243',
		[
			undefined,
			{ variant: 'secondary' },
			{ variant: 'secondary', width: 40, height: 40 },
			{ width: '12', height: '10' },
			{ variant: 'other' },
		],
	],
];

for ( const [ name, Component, id, propSets ] of cases ) {
	test( `${ name } renders the same markup as the recovered module`, () => {
		const Recovered = load( id ).A;
		for ( const props of propSets ) {
			const expected = render( Recovered, props );
			assert.ok( expected.includes( '<svg' ) );
			assert.equal(
				render( Component, props ),
				expected,
				JSON.stringify( props )
			);
		}
	} );
}

test( 'EditIcon keeps its default size and wrapper', () => {
	const markup = render( EditIcon );
	assert.match(
		markup,
		/^<span><svg class="edit-icon-svg" width="16" height="17"/
	);
} );

test( 'SparkleIcon switches layout on the secondary variant only', () => {
	assert.match( render( SparkleIcon ), /viewBox="0 0 30 26"/ );
	assert.match(
		render( SparkleIcon, { variant: 'secondary' } ),
		/viewBox="0 0 16 16"/
	);
} );
