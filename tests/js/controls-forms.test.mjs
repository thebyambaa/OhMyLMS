import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire, register } from 'node:module';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import * as probes from './controls-forms-stubs.mjs';

// @wordpress/components is not installed for Node. Both the recovered factories
// (module 2214) and the authored components get the same probe components, which
// print their props, so every comparison below is a real render comparison of the
// wrapper layer. Only the WordPress controls behind the probes are not rendered.
const hostRequire = installBrowserStubs();
// Factory 38093 reads wp.blockEditor while it initialises (alignment control).
globalThis.wp = { blockEditor: {} };
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const { createElement, createRef } = createRequire( import.meta.url )(
	'react'
);

register( './controls-forms-hooks.mjs', import.meta.url );
const forms = await import( '../../assets/src/shared/controls/forms/index.js' );

// Factory 38093 also imports the rest of the kit (modals, tables, pickers, ...)
// and their vendor bundles. Only the form controls are under test, so every
// module except React, the wp.* externals and the five form factories is an
// inert stand-in that never gets called.
const real = new Set( [ 38093, 41594, 43052, 55907, 54870, 15468, 26701 ] );
const inert = () =>
	new Proxy( () => undefined, {
		get: ( target, key ) =>
			'prototype' === key || '__esModule' === key ? undefined : inert(),
	} );
const overrides = new Proxy(
	{ 2214: probes, 12470: { __: ( text ) => text }, 37562: inert() },
	{
		has: ( target, id ) => id in target || ! real.has( Number( id ) ),
		get: ( target, id ) => ( id in target ? target[ id ] : inert() ),
	}
);
const load = createLoader( overrides );
const kit = load( 38093 );

const names = [
	'CheckboxWP',
	'FormFileUploadWP',
	'InputNumberWP',
	'InputWP',
	'RadioGroupIconWP',
	'RadioGroupWP',
	'RadioWP',
	'SearchControlWP',
	'SelectWP',
	'TextareaWP',
];

const options = [
	{ value: 'a', label: 'A' },
	{ value: 'b', label: 'B', disabled: true },
];
const icon = createElement( 'svg', { id: 'icon' } );
const noop = () => {};

const cases = {
	InputWP: {
		'label and value': { label: 'Title', value: 'x', onChange: noop },
		'overrides defaults': {
			__next40pxDefaultSize: false,
			help: 'h',
			type: 'email',
		},
		'no props': {},
	},
	InputNumberWP: {
		'value and bounds': { value: 3, min: 0, max: 10, onChange: noop },
		'style is copied': { style: { width: 80 }, value: 1 },
		'explicit undefined style': { style: undefined },
		'overrides defaults': { type: 'text', __unstableInputWidth: '5em' },
		'no props': {},
	},
	SelectWP: {
		'no options gets placeholder': { value: '' },
		'options and suffix icon': {
			options,
			suffixIcon: icon,
			value: 'a',
			label: 'L',
			onChange: noop,
		},
		'suffix beats suffixIcon': { suffix: 'own', suffixIcon: icon },
		'null options kept': { options: null },
		'children forwarded': {
			children: createElement( 'option', { value: 'z' }, 'Z' ),
			options: [],
		},
	},
	TextareaWP: {
		'prompt textarea': {
			className: 'ohmylms-text-generate-prompt-input',
			placeholder: 'Write here...',
			rows: 4,
			value: 'v',
			onChange: noop,
		},
		'overrides default': { __nextHasNoMarginBottom: false },
		'no props': {},
	},
	CheckboxWP: {
		'label and checked': { label: 'Tips', checked: true, onChange: noop },
		'disabled value': { value: 7, disabled: true },
		'overrides style and class': {
			style: { color: 'red' },
			className: 'x',
		},
		'no props': {},
	},
	RadioWP: {
		'selected options': { selected: 'a', options, onChange: noop },
		'no props': {},
	},
	RadioGroupWP: {
		'options and value': { options, value: 'a', onChange: noop },
		'empty options': { options: [] },
		'null option entries': { options: [ null, undefined, options[ 0 ] ] },
		'extra props': {
			options,
			label: 'L',
			isBlock: true,
			hideLabelFromVision: true,
		},
		'overrides defaults': { options, __nextHasNoMarginBottom: false },
	},
	RadioGroupIconWP: {
		'icon options': {
			options: [
				{ value: 'd', label: 'Desktop', icon },
				{ value: 'm', label: 'Mobile', icon },
			],
			value: 'd',
			onChange: noop,
		},
		'default options': {},
		'null options': { options: null },
		'null entry': { options: [ null ] },
	},
	SearchControlWP: {
		'value and placeholder': {
			value: 'q',
			placeholder: 'Search',
			onChange: noop,
		},
		'overrides default': { __nextHasNoMarginBottom: false },
		'no props': {},
	},
	FormFileUploadWP: {
		'string label': { label: 'Upload', accept: '.zip', onChange: noop },
		'element label': { label: createElement( 'b', null, 'Pick' ) },
		'no label': { accept: '.json' },
		'overrides size': { __next40pxDefaultSize: false, label: 'x' },
	},
};

test( 'the authored kit exports exactly the form controls', () => {
	assert.deepEqual( Object.keys( forms ).sort(), names );
	for ( const name of names ) {
		assert.ok( name in kit, `${ name } exists in factory 38093` );
	}
} );

for ( const [ name, group ] of Object.entries( cases ) ) {
	for ( const [ label, props ] of Object.entries( group ) ) {
		test( `${ name } markup matches the recovered kit: ${ label }`, () => {
			assert.equal(
				renderToStaticMarkup( createElement( forms[ name ], props ) ),
				renderToStaticMarkup( createElement( kit[ name ], props ) )
			);
		} );
	}
}

test( 'every export has at least one real render case', () => {
	assert.deepEqual( Object.keys( cases ).sort(), names );
} );

test( 'TextareaWP forwards its ref like the recovered component', () => {
	const ref = createRef();
	for ( const Component of [ forms.TextareaWP, kit.TextareaWP ] ) {
		const markup = renderToStaticMarkup(
			createElement( Component, { ref, value: 'a' } )
		);
		assert.match( markup, /data-ref="yes"/ );
	}
	const bare = renderToStaticMarkup(
		createElement( forms.TextareaWP, { value: 'a' } )
	);
	assert.equal(
		bare,
		renderToStaticMarkup( createElement( kit.TextareaWP, { value: 'a' } ) )
	);
	assert.match( bare, /data-ref="no"/ );
} );

test( 'FormFileUploadWP is memoised like the recovered component', () => {
	assert.equal(
		forms.FormFileUploadWP.$$typeof,
		kit.FormFileUploadWP.$$typeof
	);
} );

test( 'RadioGroupWP requires options like the recovered component', () => {
	for ( const Component of [ forms.RadioGroupWP, kit.RadioGroupWP ] ) {
		assert.throws(
			() => renderToStaticMarkup( createElement( Component, {} ) ),
			TypeError
		);
	}
} );

/**
 * Render a component and return the props the first probe of the given kind got.
 *
 * @param {Function} Component Component under test.
 * @param {Object}   props     Props passed to it.
 * @param {string}   probeName Probe that should receive the props.
 * @return {Object} Props received by the probe.
 */
function probeProps( Component, props, probeName ) {
	probes.received.length = 0;
	renderToStaticMarkup( createElement( Component, props ) );
	return probes.received.find( ( entry ) => probeName === entry.name ).props;
}

const callbackCases = [
	[ 'InputWP', 'TextControl', 'typed' ],
	[ 'InputNumberWP', 'NumberControl', '12', { event: {} } ],
	[ 'SelectWP', 'SelectControl', 'b' ],
	[ 'TextareaWP', 'TextareaControl', 'long text' ],
	[ 'CheckboxWP', 'CheckboxControl', true ],
	[ 'RadioWP', 'RadioControl', 'b' ],
	[ 'RadioGroupWP', 'ToggleGroupControl', 'b' ],
	[ 'RadioGroupIconWP', 'ToggleGroupControl', 'm' ],
	[ 'SearchControlWP', 'SearchControl', 'query' ],
	[ 'FormFileUploadWP', 'FormFileUpload', { target: { files: [ 'f' ] } } ],
];

for ( const [ name, probeName, ...args ] of callbackCases ) {
	test( `${ name } hands callbacks the same arguments as the recovered kit`, () => {
		const seen = { authored: [], recovered: [] };
		for ( const [ side, Component ] of [
			[ 'authored', forms[ name ] ],
			[ 'recovered', kit[ name ] ],
		] ) {
			const onChange = ( ...received ) => seen[ side ].push( received );
			const props = { options, onChange };
			probeProps( Component, props, probeName ).onChange( ...args );
		}
		assert.deepEqual( seen.authored, [ args ] );
		assert.deepEqual( seen.authored, seen.recovered );
	} );
}

test( 'the callback is passed through by identity, not wrapped', () => {
	const onChange = () => {};
	for ( const [ name, probeName ] of callbackCases ) {
		const props = { options, onChange };
		assert.equal(
			probeProps( forms[ name ], props, probeName ).onChange,
			onChange
		);
	}
} );
