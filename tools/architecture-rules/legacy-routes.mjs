import fs from 'node:fs';
import path from 'node:path';

/**
 * PHP links into the admin use app addresses (docs/ENFORCEMENT.md, section k).
 *
 * `LegacyRoutes::url()` turns a legacy route name (`/quiz-edit/5`, `accounthub?tab=classes`) into an
 * admin address. New links call `LegacyRoutes::app_url( '/<app path>' )` instead, so the legacy
 * route table can shrink. Code (not comments) that calls `LegacyRoutes::url(` or passes it as a
 * callable fails outside LEGACY_ROUTE_OWNER unless `legacyRouteUrls` in
 * tools/architecture-allowlist.json lists the file with a reason
 * (`{ "file": "includes/...php", "reason": "..." }`).
 */

/** Folders and files scanned, relative to the plugin root. Tests call url() on purpose. */
export const LEGACY_ROUTE_ROOTS = [
	'includes',
	'templates',
	'packages',
	'modules',
	'ohmylms.php',
];

/** LegacyRoutes and the admin menu, commands and redirects that map legacy addresses live here. */
export const LEGACY_ROUTE_OWNER = 'includes/Admin/';

const ALLOWLIST = 'tools/architecture-allowlist.json';

const SKIPPED_DIRECTORIES = new Set( [ 'vendor', 'node_modules' ] );
const toPosix = ( file ) => file.split( path.sep ).join( '/' );

function phpFiles( target ) {
	if ( ! fs.existsSync( target ) ) return [];
	if ( fs.statSync( target ).isFile() )
		return target.endsWith( '.php' ) ? [ target ] : [];
	const files = [];
	for ( const entry of fs.readdirSync( target, { withFileTypes: true } ) ) {
		const full = path.join( target, entry.name );
		if ( entry.isDirectory() ) {
			if ( ! SKIPPED_DIRECTORIES.has( entry.name ) )
				files.push( ...phpFiles( full ) );
		} else if ( entry.name.endsWith( '.php' ) ) files.push( full );
	}
	return files.sort();
}

/**
 * PHP source with comments and inline HTML replaced by spaces (strings kept, line breaks in
 * place), so a `{@see LegacyRoutes::url()}` in a doc block is not a call.
 *
 * @param {string} text PHP source.
 * @return {string} Code only.
 */
export function stripPhpComments( text ) {
	let out = '';
	let index = 0;
	let php = false;
	const blank = ( part ) => part.replace( /[^\n]/g, ' ' );
	while ( index < text.length ) {
		if ( ! php ) {
			const open = text.indexOf( '<?', index );
			const end = open === -1 ? text.length : open + 2;
			out += blank( text.slice( index, end ) );
			index = end;
			php = true;
			continue;
		}
		const char = text[ index ];
		const next = text[ index + 1 ];
		if ( char === '?' && next === '>' ) {
			out += '  ';
			index += 2;
			php = false;
			continue;
		}
		if ( char === "'" || char === '"' ) {
			let end = index + 1;
			while ( end < text.length && text[ end ] !== char )
				end += text[ end ] === '\\' ? 2 : 1;
			end = Math.min( end + 1, text.length );
			out += text.slice( index, end );
			index = end;
			continue;
		}
		if ( char === '/' && next === '*' ) {
			const close = text.indexOf( '*/', index + 2 );
			const end = close === -1 ? text.length : close + 2;
			out += blank( text.slice( index, end ) );
			index = end;
			continue;
		}
		if (
			( char === '/' && next === '/' ) ||
			( char === '#' && next !== '[' )
		) {
			let end = index;
			while (
				end < text.length &&
				text[ end ] !== '\n' &&
				! ( text[ end ] === '?' && text[ end + 1 ] === '>' )
			)
				end++;
			out += blank( text.slice( index, end ) );
			index = end;
			continue;
		}
		out += char;
		index++;
	}
	return out;
}

const URL_CALL =
	/(?<![\w$\\])(?:\\?OhMyLMS\\Admin\\)?LegacyRoutes\s*::\s*url\s*\(|LegacyRoutes::class\s*,\s*(['"])url\1/g;

/**
 * Lines of one PHP source that call LegacyRoutes::url().
 *
 * @param {string} text PHP source.
 * @return {number[]} 1-based line numbers.
 */
export function legacyUrlLines( text ) {
	const code = stripPhpComments( text.replace( /\r\n/g, '\n' ) );
	return [ ...code.matchAll( URL_CALL ) ].map(
		( match ) => code.slice( 0, match.index ).split( '\n' ).length
	);
}

/** k. LegacyRoutes::url() only in includes/Admin and in allow-listed legacy-route inputs. */
export function checkLegacyRouteUrls( root, allowlist = [] ) {
	const violations = [];
	for ( const target of LEGACY_ROUTE_ROOTS ) {
		for ( const file of phpFiles( path.join( root, target ) ) ) {
			const relative = toPosix( path.relative( root, file ) );
			if ( relative.startsWith( LEGACY_ROUTE_OWNER ) ) continue;
			const lines = legacyUrlLines( fs.readFileSync( file, 'utf8' ) );
			if ( lines.length ) violations.push( { file: relative, lines } );
		}
	}
	const failures = [];
	const allowed = [];
	const justified = allowlist.filter(
		( entry ) =>
			typeof entry?.file === 'string' &&
			typeof entry.reason === 'string' &&
			entry.reason.trim()
	);
	for ( const entry of allowlist ) {
		if ( ! justified.includes( entry ) )
			failures.push(
				`${ ALLOWLIST } legacyRouteUrls: entry ${ JSON.stringify( entry ) } has no reason`
			);
	}
	for ( const violation of violations ) {
		const message = `${ violation.file }:${ violation.lines.join( ',' ) } builds an admin link from a legacy route name with LegacyRoutes::url(); use LegacyRoutes::app_url( '/<app path>' )`;
		const entry = justified.find(
			( candidate ) => candidate.file === violation.file
		);
		if ( entry )
			allowed.push( { ...violation, reason: entry.reason, message } );
		else failures.push( message );
	}
	for ( const entry of justified ) {
		if (
			! violations.some( ( violation ) => violation.file === entry.file )
		)
			failures.push(
				`stale allow-list entry (no longer violated, remove it): legacyRouteUrls ${ entry.file }`
			);
	}
	return { failures, allowed, violations };
}
