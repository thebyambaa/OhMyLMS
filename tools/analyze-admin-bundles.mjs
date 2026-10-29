import fs from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';
import {
	auditEmailVendors,
	emailVendorTarget,
} from './email-vendor-chunks.mjs';

const root = path.resolve( import.meta.dirname, '..' );
const files = [
	'assets/dist/admin/ohmylms.js',
	'assets/dist/admin/extensions.js',
	'assets/dist/vendors/vendors.js',
	'assets/dist/admin/editor-lowlight.js',
	'assets/dist/vendors/editor.js',
	'assets/dist/vendors/chartjs.js',
	'assets/dist/vendors/emotion.js',
];
const rows = files.map( ( file ) => {
	const content = fs.readFileSync( path.join( root, file ) );
	return {
		file,
		bytes: content.length,
		gzipBytes: gzipSync( content ).length,
	};
} );
// Gzip sizes estimate compressed payloads; server configuration determines transfer size.
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'assets/src/manifest.json' ), 'utf8' )
);
const lazyEmailVendors = auditEmailVendors( manifest, ( name ) =>
	fs.readFileSync( path.join( root, 'assets/src', name ), 'utf8' )
).map( ( module ) => ( { ...module, loadedWith: emailVendorTarget } ) );
console.log( JSON.stringify( { bundles: rows, lazyEmailVendors }, null, 2 ) );
