import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const translations = {
	__: ( text ) => text,
	sprintf: ( text, value ) => text.replace( '%s', value ),
};
function load( filename, name, runtime ) {
	const source = fs
		.readFileSync( filename, 'utf8' )
		.replace(
			/import\s*\{([^}]+)\}\s*from\s*'([^']+)';/g,
			( _, names, module ) =>
				`const {${ names }} = runtime[${ JSON.stringify( module ) }];`
		)
		.replace( /export default |export /g, '' );
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
	return new Function(
		'runtime',
		`const Fragment = 'fragment';${ code };return ${ name };`
	)( runtime );
}
const prepareAttachments = load(
	'assets/src/features/assistant/attachment-files.mjs',
	'prepareAttachments',
	{ '@wordpress/i18n': translations }
);
const sample = ( name, size = 100 ) => ( { name, size, lastModified: 1 } );

test( 'selected and dropped files share count, size, type and duplicate limits', () => {
	const initial = prepareAttachments(
		[],
		[
			sample( 'source.pdf' ),
			sample( 'source.pdf' ),
			sample( 'unsafe.php' ),
			sample( 'empty.txt', 0 ),
			sample( 'big.png', 3000000 ),
		]
	);
	assert.equal( initial.attachments.length, 1 );
	assert.match( initial.error, /unsafe.php/ );
	assert.match( initial.error, /big.png/ );
	const more = prepareAttachments( initial.attachments, [
		sample( 'diagram.PNG' ),
		sample( 'notes.md' ),
		sample( 'fourth.csv' ),
	] );
	assert.equal( more.attachments.length, 3 );
	assert.match( more.error, /three files/ );
	assert.equal( initial.attachments.length, 1 );
} );

test( 'server upload limits take precedence over the composer fallback', () => {
	const result = prepareAttachments( [], [ sample( 'notes.txt', 101 ) ], {
		max_files: 1,
		max_bytes: 100,
		extensions: [ 'txt' ],
	} );
	assert.equal( result.attachments.length, 0 );
	assert.match( result.error, /too large/ );
} );

test( 'dropping a file adds an attachment without a network request or development controls', () => {
	const updates = [];
	const runtime = {
		'@wordpress/i18n': translations,
		'./attachment-files.mjs': { prepareAttachments },
		'@wordpress/element': {
			createElement: ( type, props, ...children ) => ( {
				type,
				props,
				children,
			} ),
			useState: ( value ) => [ value, ( next ) => updates.push( next ) ],
			useRef: () => ( { current: null } ),
			useEffect: () => {},
		},
	};
	const Panel = load(
		'assets/src/features/assistant/AssistantPanel.jsx',
		'AssistantPanel',
		runtime
	);
	const tree = Panel( { onClose() {}, active: true } );
	let prevented = false;
	tree.props.onDrop( {
		preventDefault: () => {
			prevented = true;
		},
		dataTransfer: { files: [ sample( 'dropped.pdf' ) ] },
	} );
	assert.equal( prevented, true );
	assert.ok(
		updates.some(
			( value ) =>
				Array.isArray( value ) &&
				value[ 0 ]?.file.name === 'dropped.pdf'
		)
	);
	assert.doesNotMatch(
		JSON.stringify( tree ),
		/Develop plugin|Coding provider|Worker setup/
	);
} );
