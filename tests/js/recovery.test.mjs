import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import { stringLiteral } from '@babel/types';
import crypto from 'node:crypto';
const root = path.resolve( import.meta.dirname, '../..' );
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'assets/src/manifest.json' ), 'utf8' )
);
const ignore = new Set( [
	'start',
	'end',
	'loc',
	'extra',
	'leadingComments',
	'trailingComments',
	'innerComments',
	'comments',
	'tokens',
] );
const canonical = ( text ) => {
	const ast = parse( text, {
		sourceType: 'unambiguous',
		allowReturnOutsideFunction: true,
	} );
	const traverse = traverseModule.default || traverseModule;
	// Babel adds braces to disambiguate nested if/else statements when printing.
	traverse( ast, {
		// Formatters may quote or unquote ordinary object property names.
		// Computed keys still evaluate expressions and must remain distinct.
		'ObjectProperty|ObjectMethod'( p ) {
			if ( ! p.node.computed && p.node.key.type === 'Identifier' ) {
				p.node.key = stringLiteral( p.node.key.name );
			}
		},
		BlockStatement( p ) {
			if (
				p.parentPath.isIfStatement() &&
				p.node.body.length === 1 &&
				/^(If|For|ForIn|ForOf|While|DoWhile|Expression|Return|Throw|Break|Continue|Empty)Statement$/.test(
					p.node.body[ 0 ].type
				) &&
				! p.node.directives.length
			)
				p.replaceWith( p.node.body[ 0 ] );
		},
	} );
	const flatten = ( node, op ) =>
		node.type === 'LogicalExpression' && node.operator === op
			? [ ...flatten( node.left, op ), ...flatten( node.right, op ) ]
			: [ node ];
	return crypto
		.createHash( 'sha256' )
		.update(
			JSON.stringify( ast, ( key, value ) => {
				if ( ignore.has( key ) ) return undefined;
				// Same-operator logical groups preserve evaluation order and short circuiting.
				if ( value?.type === 'LogicalExpression' )
					return {
						type: 'LogicalOperands',
						operator: value.operator,
						operands: flatten( value, value.operator ),
					};
				return value;
			} )
		)
		.digest( 'hex' );
};
test( 'asset comparison ignores property-key quoting while preserving values and computed keys', () => {
	assert.equal(
		canonical( '({ name: 1 })' ),
		canonical( '({ "name": 1 })' )
	);
	assert.notEqual(
		canonical( '({ name: 1 })' ),
		canonical( '({ name: 2 })' )
	);
	assert.notEqual(
		canonical( '({ name: 1 })' ),
		canonical( '({ [name]: 1 })' )
	);
} );
test( 'reassembled assets preserve shipped AST outside the authored admin app', () => {
	// The shipped admin app now includes the authored SDK adapters, rather than the recovered baseline.
	for ( const asset of manifest.assets.filter(
		( a ) =>
			a.output.endsWith( '.js' ) &&
			a.output !== 'assets/dist/admin/ohmylms.js' &&
			// Vendor-chunk tests compare every factory across these relocated bundles.
			! [
				'assets/dist/vendors/vendors.js',
				'assets/dist/chunks/355.js',
			].includes( a.output )
	) ) {
		assert.equal(
			canonical(
				fs.readFileSync(
					path.join( root, 'build/parity', asset.output ),
					'utf8'
				)
			),
			canonical(
				fs.readFileSync( path.join( root, asset.output ), 'utf8' )
			),
			asset.output
		);
	}
} );
test( 'every factory has editable source and generated maps embed source', () => {
	assert.ok( manifest.modules.length > 800 );
	for ( const module of manifest.modules )
		assert.ok(
			fs.existsSync( path.join( root, 'assets/src', module.source ) ),
			module.source
		);
	const map = JSON.parse(
		fs.readFileSync(
			path.join( root, 'build/parity/assets/dist/admin/ohmylms.js.map' ),
			'utf8'
		)
	);
	assert.ok( map.sources.length > 10 );
	assert.equal( map.sources.length, map.sourcesContent.length );
	assert.ok(
		map.sources.every( ( s ) => s.startsWith( 'ohmylms-source:///' ) )
	);
} );
