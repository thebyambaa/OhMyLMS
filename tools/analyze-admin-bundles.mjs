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
	'assets/dist/admin/editor-tiptap.js',
	'assets/dist/admin/vendor-charts.js',
	'assets/dist/admin/vendor-emotion.js',
	'assets/dist/admin/email-packages.js',
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
const packageDirectory = path.join( root, 'assets/dist/admin/chunks' );
const emailPackageChunks = fs
	.readdirSync( packageDirectory )
	.filter( ( name ) => {
		if ( ! name.endsWith( '.js.map' ) ) return false;
		const map = JSON.parse(
			fs.readFileSync( path.join( packageDirectory, name ), 'utf8' )
		);
		return map.sources.some(
			( source ) =>
				/node_modules\/(?:@arco-design\/web-react|mjml-browser)\//.test(
					source
				) || source.endsWith( '/features/vendor-packages/email.js' )
		);
	} )
	.map( ( name ) => {
		const file = 'assets/dist/admin/chunks/' + name.slice( 0, -4 );
		const content = fs.readFileSync( path.join( root, file ) );
		return {
			file,
			bytes: content.length,
			gzipBytes: gzipSync( content ).length,
		};
	} );
console.log(
	JSON.stringify(
		{ bundles: rows, lazyEmailVendors, emailPackageChunks },
		null,
		2
	)
);
