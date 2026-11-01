import fs from 'node:fs';
import path from 'node:path';
import { analyzePhp, isRestController } from './rest-controllers.mjs';

/**
 * REST controllers hold no persistence (docs/ENFORCEMENT.md, section j; docs/BACKEND-BOUNDARIES.md).
 *
 * Counts the lines of PHP code (comments excluded) that use `$wpdb` in each REST controller file: every
 * file in a `Rest` folder under includes (includes/Rest/V1 and the feature folders such as
 * includes/Engagement/Rest or includes/Integrations/Zoom/Includes/Rest), and every other file under
 * includes or packages that declares a WP_REST_Controller subclass (dashboard widget controllers,
 * add-on settings controllers). A file may not exceed its budget in
 * tools/controller-persistence-budgets.json; a file without a budget (a new controller) gets 0. Budgets
 * only go down: a file below its budget, or a budget for a file that is no longer a controller, fails
 * until the budget is lowered or removed.
 */

export const CONTROLLER_ROOT = 'includes/Rest/V1';
/** Folders searched for WP_REST_Controller subclasses, relative to the plugin root. */
export const CONTROLLER_CLASS_ROOTS = [ 'includes', 'packages' ];
export const controllerPersistenceBudgetsPath =
	'tools/controller-persistence-budgets.json';

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

/** True for a file in a `Rest` folder under includes, at any depth (path from the plugin root). */
export const inRestFolder = ( file ) =>
	file.startsWith( 'includes/' ) &&
	file.split( '/' ).slice( 1, -1 ).includes( 'Rest' );

/** PHP source with every comment replaced by spaces; strings are kept, line breaks stay put. */
export function stripPhpComments( text ) {
	let out = '';
	let index = 0;
	const length = text.length;
	const blank = ( part ) => part.replace( /[^\n]/g, ' ' );
	while ( index < length ) {
		const char = text[ index ];
		const next = text[ index + 1 ];
		if ( char === "'" || char === '"' ) {
			let end = index + 1;
			while ( end < length && text[ end ] !== char )
				end += text[ end ] === '\\' ? 2 : 1;
			end = Math.min( end + 1, length );
			out += text.slice( index, end );
			index = end;
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
		out += char;
		index++;
	}
	return out;
}

/** Lines of code in one PHP source that use `$wpdb`. */
export function countPersistence( text ) {
	return stripPhpComments( text.replace( /\r\n/g, '\n' ) )
		.split( '\n' )
		.filter( ( line ) => /\$wpdb(?![A-Za-z0-9_])/.test( line ) ).length;
}

/** Paths from the plugin root of every REST controller file, sorted. */
export function controllerFiles( root ) {
	const sources = new Map();
	const index = new Map();
	for ( const folder of CONTROLLER_CLASS_ROOTS ) {
		for ( const file of phpFiles( path.join( root, folder ) ) ) {
			const text = fs.readFileSync( file, 'utf8' );
			const { classes } = analyzePhp( text );
			for ( const entry of classes )
				index.set( entry.fqcn.toLowerCase(), entry );
			sources.set( toPosix( path.relative( root, file ) ), classes );
		}
	}
	const files = [];
	for ( const [ file, classes ] of sources ) {
		if (
			inRestFolder( file ) ||
			classes.some( ( entry ) => isRestController( entry.fqcn, index ) )
		)
			files.push( file );
	}
	return files.sort();
}

/** `$wpdb` line count of every controller file, keyed by its path from the plugin root. */
export function measureControllerPersistence( root ) {
	const counts = new Map();
	for ( const file of controllerFiles( root ) )
		counts.set(
			file,
			countPersistence(
				fs.readFileSync( path.join( root, file ), 'utf8' )
			)
		);
	return counts;
}

function loadBudgets( root ) {
	const file = path.join( root, controllerPersistenceBudgetsPath );
	if ( ! fs.existsSync( file ) ) return {};
	return JSON.parse( fs.readFileSync( file, 'utf8' ) ).files ?? {};
}

/** j. No new `$wpdb` use in REST controllers; budgets shrink as persistence moves to domain services. */
export function checkControllerPersistence(
	root,
	budgets = loadBudgets( root )
) {
	const counts = measureControllerPersistence( root );
	const failures = [];
	const violations = [];
	for ( const [ file, count ] of counts ) {
		const budget = budgets[ file ] ?? 0;
		if ( count > budget ) {
			violations.push( { file, count, budget } );
			failures.push(
				`${ file } uses $wpdb on ${ count } lines (budget ${ budget }); move the queries into the feature's domain service or repository`
			);
		} else if ( count < budget )
			failures.push(
				`${ file } uses $wpdb on ${ count } lines, below its budget of ${ budget }; lower it in ${ controllerPersistenceBudgetsPath }`
			);
	}
	for ( const file of Object.keys( budgets ).sort() ) {
		if ( ! counts.has( file ) )
			failures.push(
				`budget for ${ file } has no controller; remove it from ${ controllerPersistenceBudgetsPath }`
			);
	}
	return { failures, allowed: [], violations, counts };
}
