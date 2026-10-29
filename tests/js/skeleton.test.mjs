import test from 'node:test';
import assert from 'node:assert/strict';
import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import { Skeleton } from '../../assets/src/shared/skeleton/index.js';

const hostRequire = installBrowserStubs();
// wp.element is not available in Node; use the host React for the recovered memo.
const Recovered = createLoader( { 91386: hostRequire( 'react' ) } )( 68119 ).A;

const cases = [
	{},
	{ active: false },
	{ avatar: true },
	{ title: false },
	{ paragraph: false },
	{ rows: 0 },
	{ rows: 5, className: 'extra' },
	{ active: true, paragraph: false, rows: 10 },
	{ avatar: true, title: false, className: 'a b', 'data-id': '7' },
	{ active: false, avatar: true, className: 'x', style: { margin: 4 } },
];

test( 'markup matches the recovered skeleton for each prop set', () => {
	for ( const props of cases ) {
		assert.equal(
			renderToStaticMarkup( createElement( Skeleton, props ) ),
			renderToStaticMarkup( createElement( Recovered, props ) ),
			JSON.stringify( props )
		);
	}
} );

test( 'defaults render an active skeleton with a title and three lines', () => {
	const html = renderToStaticMarkup( createElement( Skeleton ) );
	assert.match(
		html,
		/^<div class="ohmylms-skeleton ohmylms-skeleton-active">/
	);
	assert.equal( html.split( 'ohmylms-skeleton-line' ).length - 1, 3 );
	assert.ok( ! html.includes( 'ohmylms-skeleton-avatar' ) );
} );
