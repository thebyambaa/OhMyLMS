import test from 'node:test';
import assert from 'node:assert/strict';
import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import {
	getToggleClass,
	getWrapperClass,
} from '../../assets/src/shared/spinner/model.mjs';

// @wordpress/components is not installed for Node, so only the class logic is
// compared; Switcher itself is checked by the recovered factory with a stub.
installBrowserStubs();
const ToggleControl = ( { __nextHasNoMarginBottom, ...props } ) =>
	createElement( 'toggle', props );
const Spinner = () => createElement( 'spinner' );
const Recovered = createLoader( { 2214: { ToggleControl, Spinner } } )(
	25946
).A;

const cases = [
	{},
	{ loading: true },
	{ isDefaultStyle: true },
	{ variant: 'danger', className: 'extra' },
	{ loading: true, isDefaultStyle: true, variant: 'x', className: 'a b' },
];

test( 'class strings match what the recovered switcher renders', () => {
	for ( const props of cases ) {
		const html = renderToStaticMarkup( createElement( Recovered, props ) );
		const wrapper = html.match( /^<div class="([^"]*)"/ )[ 1 ];
		const toggle = html.match( /<toggle class="([^"]*)"/ )[ 1 ];
		assert.equal(
			wrapper,
			getWrapperClass( props ),
			JSON.stringify( props )
		);
		assert.equal(
			toggle,
			getToggleClass( props ),
			JSON.stringify( props )
		);
	}
} );

test( 'defaults are primary, idle, with the WordPress look off', () => {
	assert.equal(
		getWrapperClass(),
		'ohmylms-switcher-wrapper ohmylms-switch-variant-primary   '
	);
	assert.equal(
		getToggleClass(),
		'ohmylms-switcher  ohmylms-switch-variant-primary'
	);
} );
