import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

test( 'paper registration is explicit, idempotent and installs span filters before blocks', () => {
	const file = 'assets/src/features/paper/paperBlocks.jsx';
	const source = fs
		.readFileSync( file, 'utf8' )
		.replace( /^import [\s\S]*?;$/gm, '' )
		.replace( /export /g, '' );
	const { code } = transformSync( source, {
		configFile: false,
		babelrc: false,
		presets: [
			[
				'@babel/preset-react',
				{ pragma: 'createElement', pragmaFrag: 'Fragment' },
			],
		],
	} );
	const events = [];
	const blocks = new Map();
	const scope = {
		__: ( text ) => text,
		paperTextDefaults: () => ( {
			'ohmylms/paper-instructions': { heading: 'Instructions' },
			'ohmylms/paper-formula-sheet': { heading: 'Formula sheet' },
		} ),
		createHigherOrderComponent: ( component ) => component,
		addFilter: ( hook, name, callback ) =>
			events.push( [ hook, callback ] ),
		getBlockType: ( name ) => blocks.get( name ),
		registerBlockType: ( name, definition ) => {
			events.push( [ name ] );
			blocks.set( name, definition );
		},
	};
	const register = new Function(
		...Object.keys( scope ),
		`${ code }; return registerPaperBlocks;`
	)( ...Object.values( scope ) );
	assert.equal(
		events.length,
		0,
		'importing the public entry must not register blocks'
	);
	register();
	assert.deepEqual(
		events.slice( 0, 3 ).map( ( event ) => event[ 0 ] ),
		[
			'blocks.registerBlockType',
			'editor.BlockEdit',
			'editor.BlockListBlock',
		]
	);
	assert.ok( blocks.size > 0 );
	const count = events.length;
	register();
	assert.equal(
		events.length,
		count,
		'repeated initialization must not duplicate hooks or blocks'
	);
	const spanFilter = events[ 0 ][ 1 ];
	assert.equal(
		spanFilter( { attributes: {} }, 'ohmylms/shared-multiple-choice' )
			.attributes.ohmylmsPaperSpan.type,
		'boolean'
	);
} );
