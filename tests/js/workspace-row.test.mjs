import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const runtime = {
	'@wordpress/element': {
		createElement: ( type, props, ...children ) => ( {
			type,
			props,
			children,
		} ),
	},
	'@wordpress/i18n': {
		__: ( text ) => text,
		sprintf: ( text, value ) => text.replace( '%s', value ),
	},
	'@wordpress/components': {},
};
const source = fs
	.readFileSync( 'assets/src/features/curriculum/WorkspaceParts.jsx', 'utf8' )
	.replace(
		/import\s*\{([^}]+)\}\s*from\s*'([^']+)';/g,
		( _, names, module ) =>
			`const {${ names }} = runtime[${ JSON.stringify( module ) }];`
	)
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
const Row = new Function(
	'runtime',
	'const Fragment = "fragment";' + code + ';return Row;'
)( runtime );

test( 'skill rows render accessible drag handles using their imported formatting helper', () => {
	const onDrop = () => {};
	const row = Row( {
		kind: 'skill',
		title: 'Fractions',
		reorder: { onDrop, target: true, after: true },
	} );
	assert.equal( row.props.onDrop, onDrop );
	assert.match( row.props.className, /is-drop-after/ );
	const handle = row.children[ 0 ].children[ 0 ];
	assert.equal( handle.type, 'button' );
	assert.equal( handle.props.draggable, true );
	assert.equal(
		handle.props[ 'aria-label' ],
		'Reorder Fractions. Use Alt and arrow keys to move.'
	);
} );

test( 'skill drag handles are disabled while a change is being saved', () => {
	const row = Row( {
		kind: 'skill',
		title: 'Fractions',
		reorder: { disabled: true },
	} );
	const handle = row.children[ 0 ].children[ 0 ];
	assert.equal( handle.props.draggable, false );
	assert.equal( handle.props.disabled, true );
} );
