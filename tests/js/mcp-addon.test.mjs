import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';

const traverse = traverseModule.default || traverseModule;
const root = path.resolve( import.meta.dirname, '../..' );
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'assets/src/manifest.json' ) )
);
const admin = manifest.assets.find(
	( asset ) => asset.output === 'assets/dist/admin/ohmylms.js'
);

test( 'MCP icon has the same intrinsic size as other add-on icons', () => {
	const svg = fs.readFileSync(
		path.join( root, 'assets/images/mcp-icon.svg' ),
		'utf8'
	);
	const openingTag = svg.match( /<svg\b[^>]*>/ )?.[ 0 ];
	assert.match( openingTag, /\bwidth="44"/ );
	assert.match( openingTag, /\bheight="44"/ );
} );

test( 'retired AI endpoints and cards are absent from shipped admin and SDK', () => {
	for ( const file of [
		'assets/dist/admin/ohmylms.js',
		'assets/dist/admin/extensions.js',
	] ) {
		const code = fs.readFileSync( path.join( root, file ), 'utf8' );
		assert.doesNotMatch(
			code,
			/AI Suite|\/ai\/settings|\/claude\/|ai-course-outline-gen|AiTextNode|AiImageNode/
		);
	}
} );

test( 'admin factories do not require deleted AI modules', () => {
	const ids = new Set(
		admin.factories.map( ( factory ) => Number( factory.id ) )
	);
	for ( const factory of admin.factories ) {
		const files = factory.fragments || [ factory.source ];
		const code = files
			.map( ( file ) =>
				fs.readFileSync( path.join( root, 'assets/src', file ), 'utf8' )
			)
			.join( '\n' );
		const ast = parse(
			factory.fragments ? `(function(e,t,n){${ code }})` : code
		);
		traverse( ast, {
			CallExpression( p ) {
				if (
					p.node.callee.type === 'Identifier' &&
					p.node.callee.name === 'n' &&
					p.node.arguments[ 0 ]?.type === 'NumericLiteral'
				) {
					const id = p.node.arguments[ 0 ].value;
					// Dependencies can also be provided by shared vendor chunks; these IDs were admin-only AI modules.
					if (
						[
							20378, 23949, 62489, 63386, 52770, 80224, 88935,
							68291, 97761, 94041, 98957,
						].includes( id )
					)
						assert.ok( ids.has( id ), `Missing module ${ id }` );
				}
			},
		} );
	}
} );
