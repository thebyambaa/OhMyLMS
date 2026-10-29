import fs from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import { all, common } from 'lowlight';

const root = path.resolve( import.meta.dirname, '..' );
const traverse = traverseModule.default || traverseModule;
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'assets/src/manifest.json' ), 'utf8' )
);
const literalNames = new Set();
const dynamicConsumers = [];
const consumers = [];
for ( const module of manifest.modules ) {
	const code = fs.readFileSync(
		path.join( root, 'assets/src', module.source ),
		'utf8'
	);
	if ( ! /\b94608\b/.test( code ) || Number( module.id ) === 94608 ) continue;
	consumers.push( module.source );
	traverse( parse( code ), {
		CallExpression( call ) {
			const { callee, arguments: args } = call.node;
			if (
				callee.type !== 'MemberExpression' ||
				callee.property.name !== 'createElement' ||
				args[ 0 ]?.type !== 'MemberExpression' ||
				args[ 0 ].property.name !== 'Icon'
			)
				return;
			const binding = call.scope.getBinding( args[ 0 ].object.name );
			if ( binding?.path.node.init?.arguments?.[ 0 ]?.value !== 94608 )
				return;
			const property = args[ 1 ]?.properties?.find(
				( item ) => item.key.name === 'name'
			);
			if ( property?.value.type === 'StringLiteral' )
				literalNames.add( property.value.value );
			else
				dynamicConsumers.push( {
					source: module.source,
					expression: property
						? code.slice( property.value.start, property.value.end )
						: '(spread props)',
				} );
		},
	} );
}
const bundles = [
	'assets/dist/vendors/vendors.js',
	'assets/dist/admin/editor-lowlight.js',
	'assets/dist/admin/editor-tiptap.js',
	'assets/dist/vendors/editor.js',
].map( ( file ) => {
	const code = fs.readFileSync( path.join( root, file ) );
	return { file, bytes: code.length, gzipBytes: gzipSync( code ).length };
} );
console.log(
	JSON.stringify(
		{
			lowlight: {
				languages: Object.keys( all ).length,
				commonLanguages: Object.keys( common ).length,
			},
			icons: {
				consumers,
				literalNames: [ ...literalNames ].sort(),
				dynamicConsumers,
			},
			bundles,
			combinedBytes: bundles.reduce(
				( total, bundle ) => total + bundle.bytes,
				0
			),
			combinedGzipBytes: bundles.reduce(
				( total, bundle ) => total + bundle.gzipBytes,
				0
			),
		},
		null,
		2
	)
);
