import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync, execSync } from 'node:child_process';

/** Compares the recovered Easy Email factories with a published npm release.
 * Usage: node tools/easy-email-audit.mjs [version]   (default 4.17.1)
 * Downloads the three packages into a temporary directory and compares the exported
 * names. It proves nothing about behaviour; docs/EASY-EMAIL-AUDIT.md explains how to read it.
 */
const root = path.resolve( import.meta.dirname, '..' );
const version = process.argv[ 2 ] || '4.17.1';
if ( ! /^\d+\.\d+\.\d+$/.test( version ) )
	throw new Error( `Invalid version: ${ version }` );
const factories = {
	'easy-email-core': 49050,
	'easy-email-editor': 58088,
	'easy-email-extensions': 47909,
};
const vendors = path.join( root, 'assets/src/modules/dist/vendors/vendors' );
const temp = fs.mkdtempSync( path.join( os.tmpdir(), 'ohmylms-easy-email-' ) );

function recoveredExports( id ) {
	const code = fs.readFileSync(
		path.join( vendors, `0-${ id }.js` ),
		'utf8'
	);
	const block =
		code.match( /\.d\([^,]+,\s*\{([\s\S]*?)\n\s*\}\);/ )?.[ 1 ] ?? '';
	// The factories use either `Name: () => local` or `alias: () => Name`.
	const pairs = [ ...block.matchAll( /(\w+):\s*\(\)\s*=>\s*(\w+)/g ) ];
	const aliased = pairs.some(
		( [ , key, value ] ) => key.length < value.length
	);
	return new Set(
		pairs.map( ( [ , key, value ] ) => ( aliased ? value : key ) )
	);
}

function publishedExports( name ) {
	const dir = path.join( temp, name );
	fs.mkdirSync( dir );
	const file = execSync( `npm pack ${ name }@${ version } --silent`, {
		cwd: dir,
		encoding: 'utf8',
	} ).trim();
	execFileSync( 'tar', [ 'xzf', file ], { cwd: dir } );
	const lib = path.join( dir, 'package/lib' );
	const names = new Set();
	for ( const entry of fs.readdirSync( lib ) ) {
		if ( ! /^index\d*(?:\.es)?\.js$/.test( entry ) ) continue;
		const code = fs.readFileSync( path.join( lib, entry ), 'utf8' );
		for ( const match of code.matchAll( /export\s*\{([^}]*)\}/g ) ) {
			for ( const part of match[ 1 ].split( ',' ) ) {
				const exported = part
					.trim()
					.split( /\s+as\s+/ )
					.pop();
				// Skip minified aliases such as `a0`; public names are longer.
				if ( exported && exported.length > 3 && exported !== 'default' )
					names.add( exported );
			}
		}
	}
	return names;
}

try {
	for ( const [ name, id ] of Object.entries( factories ) ) {
		const recovered = recoveredExports( id );
		const published = publishedExports( name );
		const sorted = ( set ) => [ ...set ].sort();
		console.log(
			`${ name }@${ version } vs factory ${ id }: recovered ${ recovered.size }, published ${ published.size }, shared ${
				[ ...recovered ].filter( ( x ) => published.has( x ) ).length
			}`
		);
		console.log(
			'  only recovered:',
			sorted(
				new Set(
					[ ...recovered ].filter( ( x ) => ! published.has( x ) )
				)
			).join( ', ' ) || '-'
		);
		console.log(
			'  only published:',
			sorted(
				new Set(
					[ ...published ].filter( ( x ) => ! recovered.has( x ) )
				)
			).join( ', ' ) || '-'
		);
	}
} finally {
	fs.rmSync( temp, { recursive: true, force: true } );
}
