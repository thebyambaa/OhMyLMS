import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire, register } from 'node:module';
import {
	getBadgeClassName,
	getBadgeStyle,
	getCardStyle,
	getDividerStyle,
	getFlexItemStyle,
	getSurfaceStyle,
	getTagClassName,
} from '../../assets/src/shared/controls/layout/model.mjs';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import * as probes from './controls-layout-stubs.mjs';

// `@wordpress/components` only exists in the WordPress admin. The hook maps it
// to the same probes the recovered factories receive, so every comparison below
// is a real render of both implementations against identical stand-ins.
register( './controls-layout-hooks.mjs', import.meta.url );
const authored =
	await import( '../../assets/src/shared/controls/layout/index.js' );

const hostRequire = installBrowserStubs();
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const { createElement } = createRequire( import.meta.url )( 'react' );
globalThis.wp = {
	element: hostRequire( '@wordpress/element' ),
	i18n: hostRequire( '@wordpress/i18n' ),
	data: {},
};

// Factory 38093 is the recovered controls kit. Its other dependencies are
// unrelated to layout, so they are replaced by an inert stand-in; the layout
// exports and the factories they alias (4315, 83154, ...) load as shipped.
const real = new Set( [
	'38093',
	'41594',
	'91386',
	'83154',
	'4315',
	'36032',
	'99166',
	'50127',
	'71847',
	'66718',
	'58273',
	'27268',
] );
const known = { 2214: probes, 12470: globalThis.wp.i18n };
const inert = () =>
	new Proxy( function () {}, {
		get: ( target, key ) =>
			Symbol.toPrimitive === key ? () => '' : inert(),
		apply: () => inert(),
	} );
const overrides = new Proxy(
	{},
	{
		has: ( target, id ) => ! real.has( id ),
		get: ( target, id ) => known[ id ] ?? inert(),
	}
);
const recovered = createLoader( overrides )( 38093 );

test( 'every layout export exists in the recovered kit with the same kind', () => {
	assert.deepEqual( Object.keys( authored ).sort(), [
		'BadgeWP',
		'CardWP',
		'ContainerWP',
		'DividerWP',
		'FlexBlockWP',
		'FlexItemWP',
		'FlexWP',
		'GridWP',
		'HeadingWP',
		'SpacerWP',
		'SurfaceWP',
		'TagWP',
		'TextWP',
	] );
	for ( const name of Object.keys( authored ) ) {
		assert.ok( recovered[ name ], name );
		assert.equal(
			typeof authored[ name ],
			typeof recovered[ name ],
			`${ name } kind`
		);
		// memo, forwardRef or plain function.
		assert.equal(
			authored[ name ].$$typeof,
			recovered[ name ].$$typeof,
			`${ name } element type`
		);
	}
} );

const kid = ( tag = 'b' ) => createElement( tag, null, 'kid' );
const onClick = () => {};
const callbackRef = () => {};
const objectRef = { current: null };

const cases = {
	FlexWP: [
		{},
		{ justify: 'space-between', align: 'center', gap: 3 },
		{ direction: 'column', className: 'a', style: { padding: 4 } },
		{ wrap: true, expanded: false, ref: callbackRef },
		{ ref: objectRef },
	],
	FlexItemWP: [
		{},
		{ fullWidth: true },
		{ fullHeight: true, flex: '1 1 0', width: 100 },
		{ minWidth: 10, maxWidth: '50%', style: { width: 7, color: 'red' } },
		{ fullWidth: true, width: '40%', className: 'c', isBlock: true },
	],
	FlexBlockWP: [
		{},
		{ className: 'x', style: { gap: 1 } },
		{ onClick, 'data-id': 3 },
	],
	SpacerWP: [
		{},
		{ marginBottom: 4, marginTop: 0 },
		{ padding: 2, className: 's', style: { color: 'blue' } },
	],
	ContainerWP: [
		{},
		{ isFullWidth: true },
		{ isFullWidth: false, className: 'ignored' },
	],
	GridWP: [
		{},
		{ columns: 2, gap: 4 },
		{ templateColumns: '1fr 2fr', className: 'g', alignment: 'top' },
	],
	CardWP: [
		{},
		{ actions: kid( 'i' ) },
		{ actions: '' },
		{ actions: 0 },
		{ fullHeight: true, fullWidth: true, cursor: 'pointer' },
		{ padding: 8, margin: '0 auto', width: 300, height: 200 },
		{ minHeight: 50, fullHeight: true, borderRadius: 12 },
		{ style: { minHeight: 1, color: 'x' }, size: 'small' },
		{ isBorderless: true, className: 'card', onClick },
		{ ref: callbackRef },
		{ ref: objectRef },
	],
	SurfaceWP: [
		{},
		{ fullHeight: true },
		{ fullHeight: true, minHeight: 20 },
		{ minHeight: 300, style: { minHeight: 5, margin: 1 } },
		{ variation: 'dotted', className: 'surface' },
	],
	DividerWP: [
		{},
		{ color: '#000' },
		{ margin: 4, marginEnd: 2 },
		{ style: { color: 'red', height: 2 } },
		{ color: 'blue', style: { height: 2 }, className: 'd' },
		{ orientation: 'vertical' },
	],
	TextWP: [
		{},
		{ size: 14, weight: 600, color: '#111', className: 't' },
		{ html: true, children: '<b>bold</b>' },
		{ html: false, children: 'plain', as: 'p' },
		{ html: true, children: '<i>x</i>', className: 'rich', size: 12 },
		{ html: 1, children: undefined },
	],
	HeadingWP: [
		{},
		{ level: 3, className: 'h' },
		{ size: 'large', weight: 700, style: { margin: 0 } },
	],
	BadgeWP: [
		{},
		{ variant: 'success' },
		{ isBorderLess: true, isRounded: true, variant: 'warning' },
		{ padding: '2px 8px', margin: 3, cursor: 'pointer' },
		{ paddingX: 5, paddingY: 2, marginX: 1, marginY: 7 },
		{ padding: 1, paddingX: 5, paddingLeft: 9, paddingTop: 2 },
		{ margin: 1, marginX: 2, marginRight: 8, marginBottom: 4 },
		{ width: 40, height: 20, textTransform: 'uppercase', color: '#fff' },
		{ cursor: '', width: 0, style: { color: 'red', cursor: 'wait' } },
		{ className: 'own', title: 'hi', onClick },
	],
	TagWP: [
		{},
		{ label: 'React' },
		{ children: 'from children' },
		{ label: 'wins', children: 'loses', className: 'x' },
		{ label: 'a', state: 'active', 'data-id': 4 },
		{ label: 'b', icon: 'star' },
		{ label: 'c', showIcon: true },
		{ label: 'd', showIcon: true, icon: 'star' },
		{ label: 'e', removable: true },
		{ label: 'f', removable: true, onRemove: onClick },
	],
};

for ( const [ name, list ] of Object.entries( cases ) ) {
	list.forEach( ( props, index ) => {
		test( `${ name } markup matches the recovered factory: case ${ index }`, () => {
			const merged =
				'children' in props ? props : { ...props, children: kid() };
			assert.equal(
				renderToStaticMarkup(
					createElement( authored[ name ], merged )
				),
				renderToStaticMarkup(
					createElement( recovered[ name ], merged )
				)
			);
		} );
	} );
}

// The checks below do not render; they pin the pure helpers behind the
// components, which the renders above also cover indirectly.
test( 'style helpers keep the recovered precedence', () => {
	assert.deepEqual(
		getFlexItemStyle( { fullWidth: true, width: 5, style: { width: 9 } } ),
		{ width: 9 }
	);
	assert.deepEqual( getCardStyle( { fullHeight: true, minHeight: 3 } ), {
		minHeight: 3,
	} );
	assert.deepEqual( getSurfaceStyle( { fullHeight: true } ), {
		minHeight: '100%',
	} );
	assert.deepEqual( getDividerStyle( undefined, undefined ), {
		color: '#f4f5f7',
	} );
	assert.deepEqual( getDividerStyle( 'red', { color: 'blue' } ), {
		color: 'blue',
	} );
} );

test( 'badge and tag helpers', () => {
	assert.equal(
		getBadgeClassName( 'default', false, false ),
		'ohmylms-badge ohmylms-badge-default'
	);
	assert.equal(
		getBadgeClassName( 'x', true, true ),
		'ohmylms-badge ohmylms-badge-x ohmylms-badge-borderless ohmylms-badge-rounded'
	);
	assert.deepEqual( getBadgeStyle( { paddingX: 2, paddingLeft: 5 } ), {
		paddingLeft: 5,
		paddingRight: 2,
	} );
	assert.equal( getTagClassName(), 'ohmylms-tag ' );
} );
