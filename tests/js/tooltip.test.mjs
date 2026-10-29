import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import {
	DEFAULT_PLACEMENT,
	getTooltipProps,
} from '../../assets/src/shared/tooltip/model.mjs';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';

// @wordpress/components is not installed for Node, so the Tooltip it provides is
// replaced by a stand-in that prints the props it receives. The recovered wrapper
// (factory 6425) is then compared with the authored prop mapping.
const hostRequire = installBrowserStubs();
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const { createElement } = createRequire( import.meta.url )( 'react' );

const Probe = ( props ) => {
	const { children, ...rest } = props;
	return createElement(
		'div',
		{ 'data-props': JSON.stringify( rest ) },
		children
	);
};
const recovered = createLoader( { 2214: { Tooltip: Probe } } )( 6425 ).A;

const authored = ( props ) =>
	createElement(
		Probe,
		getTooltipProps( props ),
		createElement(
			'span',
			{ style: { lineHeight: 0 }, className: 'tooltip-icon' },
			props.children
		)
	);

const cases = {
	'text only': { text: 'Total sold' },
	'title only': { title: 'Mark as correct' },
	'text beats title': { text: 'a', title: 'b' },
	'empty text falls back to title': { text: '', title: 'b' },
	'class and placement': {
		text: 'x',
		className: 'ohmylms-tooltip',
		placement: 'bottom',
	},
	'extra props pass through': { title: 't', delay: 100, shortcut: 'Ctrl' },
	'explicit undefined placement': { text: 'x', placement: undefined },
	'no text at all': {},
};

for ( const [ name, props ] of Object.entries( cases ) ) {
	test( `tooltip markup matches the recovered wrapper: ${ name }`, () => {
		const children = createElement( 'i', null, 'icon' );
		assert.equal(
			renderToStaticMarkup( authored( { ...props, children } ) ),
			renderToStaticMarkup( createElement( recovered, props, children ) )
		);
	} );
}

test( 'placement defaults to top and children are never forwarded', () => {
	assert.equal( DEFAULT_PLACEMENT, 'top' );
	const result = getTooltipProps( { title: 't', children: 'c' } );
	assert.deepEqual( result, { text: 't', placement: 'top' } );
} );
