import test from 'node:test';
import assert from 'node:assert/strict';
import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import { Avatar, getInitials } from '../../assets/src/shared/avatar/index.js';

installBrowserStubs();
const Recovered = createLoader()( 53725 ).A;

const cases = [
	{},
	{ src: 'https://x.test/a.png' },
	{ src: 'https://x.test/a.png', alt: 'Ada', size: 100, shape: 'square' },
	{ src: 'https://x.test/a.png', shape: 'rounded', className: 'extra' },
	{ src: '/a.png', href: '/profile', alt: 'Ada Lovelace' },
	{ src: '/a.png', style: { opacity: 0.5, borderRadius: '4px' } },
	{ src: '/a.png', wrapperStyle: { margin: 2 }, 'data-id': 3, title: 't' },
	{ src: '/a.png', onClick: undefined, id: 'img' },
	{ alt: 'ada lovelace byron' },
	{ alt: '  grace   hopper ', size: 40 },
	{ alt: 'x', size: 33, shape: 'square', style: { color: 'red' } },
	{
		alt: 'Ada',
		href: '/p',
		className: 'c',
		wrapperStyle: { '--size': '9px' },
	},
	{ href: '/p' },
];

test( 'markup matches the recovered avatar for each prop set', () => {
	for ( const props of cases ) {
		assert.equal(
			renderToStaticMarkup( createElement( Avatar, props ) ),
			renderToStaticMarkup( createElement( Recovered, props ) ),
			JSON.stringify( props )
		);
	}
} );

test( 'initials are the first letters of the first two words', () => {
	assert.equal( getInitials( 'ada lovelace byron' ), 'AL' );
	assert.equal( getInitials( '' ), '' );
	assert.equal( getInitials( undefined ), '' );
	assert.equal( getInitials( 'Plato' ), 'P' );
} );
