import fs from 'node:fs';
import path from 'node:path';

/**
 * REST routes are registered by WP_REST_Controller subclasses (docs/ENFORCEMENT.md, section i).
 *
 * A `register_rest_route()` call must sit inside a class that extends WP_REST_Controller, directly
 * or through other classes of the plugin (`Abstracts\RestController`, `GamificationController`, …)
 * or a core `WP_REST_*_Controller`. Calls in admin pages, blocks, hooks, gateways or services, and
 * calls outside any class, fail unless `restControllers` in tools/architecture-allowlist.json lists
 * the file with a reason.
 */

/** Folders scanned, relative to the plugin root. */
export const REST_ROUTE_ROOTS = [ 'includes', 'packages' ];

const ALLOWLIST = 'tools/architecture-allowlist.json';
const SKIPPED_DIRECTORIES = new Set( [ 'vendor', 'node_modules' ] );

const toPosix = ( file ) => file.split( path.sep ).join( '/' );

function phpFiles( directory ) {
	if ( ! fs.existsSync( directory ) ) return [];
	const files = [];
	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const full = path.join( directory, entry.name );
		if ( entry.isDirectory() ) {
			if ( ! SKIPPED_DIRECTORIES.has( entry.name ) )
				files.push( ...phpFiles( full ) );
		} else if ( entry.name.endsWith( '.php' ) ) files.push( full );
	}
	return files.sort();
}

const blank = ( text ) => text.replace( /[^\n]/g, ' ' );

/**
 * PHP source with comments, string and heredoc contents and inline HTML replaced by spaces.
 * Offsets and line numbers are unchanged, so braces and calls can be found in what remains.
 */
export function stripPhp( text ) {
	let out = '';
	let index = 0;
	let php = false;
	const length = text.length;
	while ( index < length ) {
		if ( ! php ) {
			const open = text.indexOf( '<?', index );
			if ( open === -1 ) {
				out += blank( text.slice( index ) );
				break;
			}
			const tag = /^<\?(?:php\b|=)?/i.exec( text.slice( open ) )[ 0 ];
			out += blank( text.slice( index, open ) ) + blank( tag );
			index = open + tag.length;
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
		if (
			( char === '/' && next === '/' ) ||
			( char === '#' && next !== '[' )
		) {
			let end = index;
			while (
				end < length &&
				text[ end ] !== '\n' &&
				! ( text[ end ] === '?' && text[ end + 1 ] === '>' )
			)
				end++;
			out += blank( text.slice( index, end ) );
			index = end;
			continue;
		}
		if ( char === '/' && next === '*' ) {
			const close = text.indexOf( '*/', index + 2 );
			const end = close === -1 ? length : close + 2;
			out += blank( text.slice( index, end ) );
			index = end;
			continue;
		}
		if ( char === "'" || char === '"' || char === '`' ) {
			let end = index + 1;
			while ( end < length && text[ end ] !== char ) {
				end += text[ end ] === '\\' ? 2 : 1;
			}
			end = Math.min( end + 1, length );
			out += char + blank( text.slice( index + 1, end - 1 ) ) + char;
			index = end;
			continue;
		}
		if ( char === '<' && text.startsWith( '<<<', index ) ) {
			const heredoc = /^<<<[ \t]*(['"]?)([A-Za-z_]\w*)\1\r?\n/.exec(
				text.slice( index )
			);
			if ( heredoc ) {
				const terminator = new RegExp(
					`\\n[ \\t]*${ heredoc[ 2 ] }(?!\\w)`,
					'g'
				);
				terminator.lastIndex = index + heredoc[ 0 ].length - 1;
				const close = terminator.exec( text );
				const end = close ? close.index + close[ 0 ].length : length;
				out += blank( text.slice( index, end ) );
				index = end;
				continue;
			}
		}
		out += char;
		index++;
	}
	return out;
}

function matchingBrace( code, open ) {
	let depth = 0;
	for ( let index = open; index < code.length; index++ ) {
		if ( code[ index ] === '{' ) depth++;
		else if ( code[ index ] === '}' && --depth === 0 ) return index;
	}
	return code.length;
}

const CLASS_DECLARATION =
	/(?<![\w$\\]|::|->|new\s{1,20})class\s+([A-Za-z_]\w*)(?:\s+extends\s+(\\?[A-Za-z_][\w\\]*))?(?:\s+implements\s+[\w\\,\s]+?)?\s*\{/g;
const REGISTER_CALL =
	/(?<![\w$]|->|::|function\s{1,20})\\?register_rest_route\s*\(/g;

/** Namespace, imports, classes (with resolved parents) and register_rest_route() calls of one file. */
export function analyzePhp( text ) {
	const code = stripPhp( text );
	const lineAt = ( offset ) => code.slice( 0, offset ).split( '\n' ).length;
	const namespaces = [
		...code.matchAll(
			/(?<![\w$\\])namespace\s+([A-Za-z_][\w\\]*)\s*[;{]/g
		),
	].map( ( match ) => ( { offset: match.index, name: match[ 1 ] } ) );
	const namespaceAt = ( offset ) =>
		namespaces.filter( ( entry ) => entry.offset < offset ).at( -1 )
			?.name ?? '';
	const classes = [ ...code.matchAll( CLASS_DECLARATION ) ].map(
		( match ) => {
			const open = match.index + match[ 0 ].length - 1;
			return {
				name: match[ 1 ],
				namespace: namespaceAt( match.index ),
				extends: match[ 2 ] ?? null,
				start: open,
				end: matchingBrace( code, open ),
			};
		}
	);
	const insideClass = ( offset ) =>
		classes.some( ( entry ) => offset > entry.start && offset < entry.end );
	const uses = new Map();
	for ( const match of code.matchAll(
		/(?<![\w$\\])use\s+(?!function\b|const\b)([^;]+);/g
	) ) {
		if ( insideClass( match.index ) ) continue;
		const statement = match[ 1 ].replace( /\s+/g, ' ' ).trim();
		const group = /^\\?([\w\\]+)\\\s?\{(.*)\}$/.exec( statement );
		const items = group
			? group[ 2 ]
					.split( ',' )
					.map( ( item ) => `${ group[ 1 ] }\\${ item.trim() }` )
			: statement.split( ',' );
		for ( const item of items ) {
			const parts = /^\\?([\w\\]+?)(?:\s+as\s+(\w+))?$/i.exec(
				item.trim()
			);
			if ( ! parts ) continue;
			const alias = parts[ 2 ] ?? parts[ 1 ].split( '\\' ).at( -1 );
			uses.set( alias.toLowerCase(), parts[ 1 ] );
		}
	}
	const resolve = ( name, namespace ) => {
		if ( name.startsWith( '\\' ) ) return name.slice( 1 );
		const [ first, ...rest ] = name.split( '\\' );
		const imported = uses.get( first.toLowerCase() );
		if ( imported ) return [ imported, ...rest ].join( '\\' );
		return namespace ? `${ namespace }\\${ name }` : name;
	};
	for ( const entry of classes ) {
		entry.fqcn = entry.namespace
			? `${ entry.namespace }\\${ entry.name }`
			: entry.name;
		entry.parent = entry.extends
			? resolve( entry.extends, entry.namespace )
			: null;
	}
	const calls = [ ...code.matchAll( REGISTER_CALL ) ].map( ( match ) => {
		const owner = classes
			.filter(
				( entry ) =>
					match.index > entry.start && match.index < entry.end
			)
			.sort( ( a, b ) => b.start - a.start )[ 0 ];
		return { line: lineAt( match.index ), owner: owner ?? null };
	} );
	return { classes, calls };
}

/** True for WP_REST_Controller and core subclasses such as WP_REST_Posts_Controller. */
const isCoreController = ( fqcn ) =>
	/^wp_rest_(?:\w+_)?controller$/.test( fqcn.toLowerCase() );

/** Parent chain of a class, ending at a core class or at the first class outside the index. */
export function ancestry( fqcn, index ) {
	const chain = [ fqcn ];
	const seen = new Set();
	let current = fqcn;
	while ( current && ! isCoreController( current ) ) {
		const key = current.toLowerCase();
		if ( seen.has( key ) ) break;
		seen.add( key );
		current = index.get( key )?.parent ?? null;
		if ( current ) chain.push( current );
	}
	return chain;
}

export const isRestController = ( fqcn, index ) =>
	isCoreController( ancestry( fqcn, index ).at( -1 ) );

/** i. register_rest_route() only in WP_REST_Controller subclasses. */
export function checkRestControllers( root, allowlist = [] ) {
	const analyses = [];
	const index = new Map();
	for ( const folder of REST_ROUTE_ROOTS ) {
		for ( const file of phpFiles( path.join( root, folder ) ) ) {
			const analysis = analyzePhp( fs.readFileSync( file, 'utf8' ) );
			for ( const entry of analysis.classes )
				index.set( entry.fqcn.toLowerCase(), entry );
			if ( analysis.calls.length )
				analyses.push( {
					file: toPosix( path.relative( root, file ) ),
					...analysis,
				} );
		}
	}
	const violations = [];
	for ( const { file, calls } of analyses ) {
		const offending = calls.filter(
			( call ) =>
				! call.owner || ! isRestController( call.owner.fqcn, index )
		);
		if ( ! offending.length ) continue;
		const owners = [
			...new Set(
				offending.map( ( call ) =>
					call.owner
						? `${ call.owner.fqcn } (${ ancestry( call.owner.fqcn, index ).slice( 1 ).join( ' -> ' ) || 'no parent' })`
						: 'code outside a class'
				)
			),
		];
		violations.push( {
			file,
			lines: offending.map( ( call ) => call.line ),
			owners,
		} );
	}

	const failures = [];
	const allowed = [];
	for ( const entry of allowlist ) {
		if ( typeof entry?.reason !== 'string' || ! entry.reason.trim() )
			failures.push(
				`${ ALLOWLIST } restControllers: entry ${ JSON.stringify( entry ) } has no reason`
			);
	}
	for ( const violation of violations ) {
		const message = `${ violation.file }:${ violation.lines.join( ',' ) } calls register_rest_route() in ${ violation.owners.join( ', ' ) }; register routes in a WP_REST_Controller subclass (includes/Rest/V1 or the feature's Rest/ folder) listed in includes/Rest/Api.php`;
		const entry = allowlist.find(
			( candidate ) => candidate?.file === violation.file
		);
		if ( entry )
			allowed.push( { ...violation, reason: entry.reason, message } );
		else failures.push( message );
	}
	for ( const entry of allowlist ) {
		if (
			! violations.some( ( violation ) => violation.file === entry?.file )
		)
			failures.push(
				`stale allow-list entry (no longer violated, remove it): restControllers ${ entry?.file }`
			);
	}
	return { failures, allowed, violations };
}
