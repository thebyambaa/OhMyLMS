import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';

/**
 * The admin app keeps one copy of its request hooks, response validators, media parser and media
 * picker (docs/ENFORCEMENT.md, section g). A file in assets/src/app outside their home module fails when it:
 *
 * - defines `useRequest` or `useOperation` (a declaration with that name, or a module named after
 *   it, re-export shims included);
 * - defines a one-argument `record`, `text` or `positiveId` (bind the shared ones to the feature's
 *   error instead: `const { record, text } = createValidators( invalidResponse );`);
 * - reads `media_details` from a `/wp/v2/media` item itself instead of calling `parseMediaImage`;
 * - defines a `MediaPicker` component (a declaration with that name, or a module named after it).
 *
 * Exceptions go in `sharedHelpers` of tools/architecture-allowlist.json as
 * `{ "file": "assets/src/app/...", "helper": "useRequest", "reason": "..." }`.
 */

const ALLOWLIST = 'tools/architecture-allowlist.json';

const HOOK_HOME = 'assets/src/app/hooks';
const VALIDATION_HOME = 'assets/src/app/lib/validation.ts';
const MEDIA_HOME = 'assets/src/app/lib/media.ts';

/** Helper name => where it lives and how to adapt it. */
export const SHARED_HELPERS = {
	useRequest: {
		kind: 'hook',
		home: `${ HOOK_HOME }/useRequest.ts`,
		hint: 'use its key, keepData and errorShape options',
	},
	useOperation: {
		kind: 'hook',
		home: `${ HOOK_HOME }/useOperation.ts`,
		hint: 'use its errorShape option and the optional success callback',
	},
	record: {
		kind: 'validator',
		home: VALIDATION_HOME,
		hint: 'bind it to the feature error with createValidators( invalidResponse )',
	},
	text: {
		kind: 'validator',
		home: VALIDATION_HOME,
		hint: 'bind it to the feature error with createValidators( invalidResponse )',
	},
	positiveId: {
		kind: 'validator',
		home: VALIDATION_HOME,
		hint: 'bind it to the feature error with createValidators( invalidResponse )',
	},
	parseMediaImage: {
		kind: 'media',
		home: MEDIA_HOME,
		hint: 'call parseMediaImage( item, { invalid, accept, fallback } )',
	},
	MediaPicker: {
		kind: 'component',
		home: 'assets/src/app/components/MediaPicker.tsx',
		hint: 'pass the feature labels, accept, fallback and invalid message as props',
	},
};

/** Kinds found by name alone: any binding or module with that name is a copy. */
const NAMED = [ 'hook', 'component' ];

const SOURCE = /\.(?:[cm]?[jt]sx?)$/;
const TEST =
	/\.(?:test|spec)\.[cm]?[jt]sx?$|(?:^|\/)(?:test|__tests__|__mocks__)\//;
const toPosix = ( file ) => file.split( path.sep ).join( '/' );

function sourceFiles( directory ) {
	if ( ! fs.existsSync( directory ) ) return [];
	const files = [];
	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		if ( entry.name === 'node_modules' ) continue;
		const full = path.join( directory, entry.name );
		if ( entry.isDirectory() ) files.push( ...sourceFiles( full ) );
		else if (
			SOURCE.test( entry.name ) &&
			! /\.d\.[cm]?ts$/.test( entry.name )
		)
			files.push( full );
	}
	return files.sort();
}

function plugins( file ) {
	if ( /\.[cm]?ts$/.test( file ) ) return [ 'typescript' ];
	if ( file.endsWith( '.tsx' ) ) return [ 'typescript', 'jsx' ];
	return [ 'jsx' ];
}

const isFunction = ( node ) =>
	!! node &&
	[ 'ArrowFunctionExpression', 'FunctionExpression' ].includes( node.type );

const keyName = ( node, computed ) => {
	if ( ! node ) return null;
	if ( ! computed && node.type === 'Identifier' ) return node.name;
	if ( node.type === 'StringLiteral' ) return node.value;
	return null;
};

/** Whether the tree reads a `media_details` member (`item.media_details`, `{ media_details }`). */
function readsMediaDetails( node ) {
	if ( ! node || typeof node.type !== 'string' ) return false;
	if (
		( node.type === 'MemberExpression' ||
			node.type === 'OptionalMemberExpression' ) &&
		keyName( node.property, node.computed ) === 'media_details'
	)
		return true;
	if (
		node.type === 'ObjectPattern' &&
		node.properties.some(
			( property ) =>
				property.type === 'ObjectProperty' &&
				keyName( property.key, property.computed ) === 'media_details'
		)
	)
		return true;
	for ( const [ key, value ] of Object.entries( node ) ) {
		if ( key === 'loc' || key.endsWith( 'Comments' ) ) continue;
		if ( Array.isArray( value ) ) {
			if ( value.some( readsMediaDetails ) ) return true;
		} else if (
			value &&
			typeof value === 'object' &&
			readsMediaDetails( value )
		)
			return true;
	}
	return false;
}

/**
 * Shared helpers one module defines again.
 *
 * @param {string} file Path, for the parser plugins and the module name.
 * @param {string} text Source.
 * @return {string[]} Helper names (keys of SHARED_HELPERS).
 */
export function helperCopies( file, text ) {
	let ast;
	try {
		ast = parse( text, {
			sourceType: 'module',
			plugins: plugins( file ),
			errorRecovery: true,
		} );
	} catch {
		return [];
	}
	const found = new Set();
	const stem = path.basename( file ).replace( /\.[^.]+$/, '' );
	if ( NAMED.includes( SHARED_HELPERS[ stem ]?.kind ) ) found.add( stem );
	const define = ( name, params ) => {
		const helper = SHARED_HELPERS[ name ];
		if ( ! helper || helper.kind === 'media' ) return;
		if ( helper.kind === 'validator' && params !== 1 ) return;
		found.add( name );
	};
	for ( const statement of ast.program.body ) {
		const node =
			statement.type === 'ExportNamedDeclaration' ||
			statement.type === 'ExportDefaultDeclaration'
				? statement.declaration
				: statement;
		if ( ! node ) continue;
		if (
			[ 'FunctionDeclaration', 'TSDeclareFunction' ].includes(
				node.type
			) &&
			node.id
		)
			define( node.id.name, node.params.length );
		if ( node.type === 'VariableDeclaration' )
			for ( const declarator of node.declarations ) {
				if ( declarator.id.type !== 'Identifier' ) continue;
				const name = declarator.id.name;
				if ( NAMED.includes( SHARED_HELPERS[ name ]?.kind ) )
					found.add( name );
				else if ( isFunction( declarator.init ) )
					define( name, declarator.init.params.length );
			}
	}
	if ( readsMediaDetails( ast.program ) ) found.add( 'parseMediaImage' );
	return [ ...found ].sort();
}

/** g (helpers). Features use the shared request hooks, validators, media parser and media picker. */
export function checkSharedHelperCopies( project, allowlist = [] ) {
	const homes = new Set(
		Object.values( SHARED_HELPERS ).map( ( helper ) => helper.home )
	);
	const violations = [];
	for ( const file of sourceFiles( project.app ) ) {
		const relative = toPosix( path.relative( project.root, file ) );
		if ( homes.has( relative ) || TEST.test( relative ) ) continue;
		const text = fs.readFileSync( file, 'utf8' ).replace( /\r\n/g, '\n' );
		for ( const helper of helperCopies( file, text ) )
			violations.push( { file: relative, helper } );
	}
	const describe = ( { file, helper } ) => {
		const { kind, home, hint } = SHARED_HELPERS[ helper ];
		const what =
			kind === 'media'
				? 'parses /wp/v2/media items itself'
				: `defines its own ${ helper }`;
		return `${ file } ${ what }; import ${ helper } from ${ home } and ${ hint } (add an option there when the behaviour differs)`;
	};
	const failures = [];
	const allowed = [];
	const justified = allowlist.filter(
		( entry ) => typeof entry?.reason === 'string' && entry.reason.trim()
	);
	for ( const entry of allowlist ) {
		if ( ! justified.includes( entry ) )
			failures.push(
				`${ ALLOWLIST } sharedHelpers: entry ${ JSON.stringify( entry ) } has no reason`
			);
	}
	const matches = ( violation, entry ) =>
		violation.file === entry.file && violation.helper === entry.helper;
	for ( const violation of violations ) {
		const entry = justified.find( ( candidate ) =>
			matches( violation, candidate )
		);
		if ( entry )
			allowed.push( {
				...violation,
				reason: entry.reason,
				message: describe( violation ),
			} );
		else failures.push( describe( violation ) );
	}
	for ( const entry of justified ) {
		if ( ! violations.some( ( violation ) => matches( violation, entry ) ) )
			failures.push(
				`stale allow-list entry (no longer violated, remove it): sharedHelpers ${ entry.file } ${ entry.helper }`
			);
	}
	return { failures, allowed, violations };
}
