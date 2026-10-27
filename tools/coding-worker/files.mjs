/** Host-side changes are bounded, hash-checked and journaled before application. */
import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';

export const hash = ( value ) =>
	crypto.createHash( 'sha256' ).update( value ).digest( 'hex' );
export function allowed( name ) {
	return (
		typeof name === 'string' &&
		! name.includes( '\\' ) &&
		! /[:*?"<>|\u0000-\u001f]/.test( name ) &&
		! name
			.split( '/' )
			.some(
				( part ) =>
					! part ||
					part === '.' ||
					part === '..' ||
					part.startsWith( '.' ) ||
					/[. ]$/.test( part ) ||
					/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\.|$)/i.test( part )
			) &&
		/^(assets\/(src|css|dist)\/|includes\/|modules\/|packages\/|templates\/|docs\/|tests\/|tools\/(?!coding-worker\/)|ohmylms\.php$|package(-lock)?\.json$|webpack\.config\.cjs$)/.test(
			name
		) &&
		/\.(php|js|jsx|mjs|cjs|json|css|scss|svg|md|txt|html|map)$/.test(
			name
		) &&
		! /(^|\/)(node_modules|vendor|recovered)(\/|$)/.test( name )
	);
}

/** Reject symlinks, junctions and traversal at every existing path component. */
export async function safePath( root, name ) {
	if ( ! allowed( name ) ) {
		throw new Error( `Disallowed update path: ${ name }` );
	}
	const canonical = await fs.realpath( root );
	if ( canonical.toLowerCase() !== path.resolve( root ).toLowerCase() ) {
		throw new Error( 'Checkout root changed or became a linked path.' );
	}
	let current = canonical;
	for ( const part of name.split( '/' ) ) {
		current = path.join( current, part );
		try {
			if (
				( await fs.lstat( current ) ).isSymbolicLink() ||
				( await fs.realpath( current ) ).toLowerCase() !==
					current.toLowerCase()
			) {
				throw new Error( 'Linked paths cannot be updated.' );
			}
		} catch ( error ) {
			if ( error.code !== 'ENOENT' ) {
				throw error;
			}
		}
	}
	return current;
}

export async function readOptional( filename ) {
	try {
		return await fs.readFile( filename );
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			return null;
		}
		throw error;
	}
}

export async function manifest( root, all = false ) {
	const entries = {};
	async function visit( directory, relative = '' ) {
		for ( const item of await fs.readdir( directory, {
			withFileTypes: true,
		} ) ) {
			const name = relative ? `${ relative }/${ item.name }` : item.name;
			if (
				item.name.startsWith( '.' ) ||
				[ 'node_modules', 'vendor', 'build', 'test-results' ].includes(
					item.name
				)
			) {
				continue;
			}
			if ( item.isSymbolicLink() ) {
				throw new Error( 'Isolated copy contains a linked path.' );
			}
			if ( item.isDirectory() ) {
				await visit( path.join( directory, item.name ), name );
			} else if ( item.isFile() && ( all || allowed( name ) ) ) {
				entries[ name ] = hash(
					await fs.readFile( path.join( directory, item.name ) )
				);
			}
		}
	}
	await visit( root );
	return entries;
}

/** Produce a bounded immutable review bundle, not a model-authored command. */
export async function review( baseline, work ) {
	const after = await manifest( work );
	const changes = [];
	let total = 0;
	for ( const name of new Set( [
		...Object.keys( baseline ),
		...Object.keys( after ),
	] ) ) {
		if ( baseline[ name ] === after[ name ] ) {
			continue;
		}
		const content = await readOptional( await safePath( work, name ) );
		if ( content?.includes( 0 ) ) {
			throw new Error( 'Binary updates require manual review.' );
		}
		total += content?.length || 0;
		if ( total > 5000000 || changes.length >= 100 ) {
			throw new Error(
				'Update exceeds the review limit. Request a smaller change.'
			);
		}
		changes.push( {
			name,
			before: baseline[ name ] || null,
			after: after[ name ] || null,
			content: content?.toString( 'base64' ) ?? null,
		} );
	}
	if ( ! changes.length ) {
		throw new Error( 'No supported file changes were produced.' );
	}
	return { changes, digest: hash( JSON.stringify( changes ) ) };
}

/** Check ALL files before writing any. Keep originals in a durable local journal. */
export async function applyReview(
	root,
	bundle,
	journalFile,
	rollback = false
) {
	if ( hash( JSON.stringify( bundle.changes ) ) !== bundle.digest ) {
		throw new Error( 'Review integrity check failed.' );
	}
	const journal = rollback
		? JSON.parse( await fs.readFile( journalFile, 'utf8' ) )
		: [];
	const plan = [];
	for ( const change of bundle.changes ) {
		const filename = await safePath( root, change.name );
		const current = await readOptional( filename );
		const expected = rollback ? change.after : change.before;
		if ( ( current === null ? null : hash( current ) ) !== expected ) {
			throw new Error(
				`Checkout changed since review: ${ change.name }. Nothing applied.`
			);
		}
		const original = rollback
			? journal.find( ( entry ) => entry.name === change.name )
			: null;
		if ( rollback && ! original ) {
			throw new Error( 'Recovery journal incomplete.' );
		}
		const content = rollback ? original.content : change.content;
		if (
			( content === null
				? null
				: hash( Buffer.from( content, 'base64' ) ) ) !==
			( rollback ? change.before : change.after )
		) {
			throw new Error( 'File integrity check failed.' );
		}
		plan.push( { filename, content } );
		if ( ! rollback ) {
			journal.push( {
				name: change.name,
				content: current?.toString( 'base64' ) ?? null,
			} );
		}
	}
	if ( ! rollback ) {
		await fs.writeFile( journalFile, JSON.stringify( journal ), {
			flag: 'wx',
			mode: 0o600,
		} );
	}
	// A crash may leave a partial application; the journal deliberately survives it.
	for ( const item of plan ) {
		if ( item.content === null ) {
			await fs.unlink( item.filename );
		} else {
			await fs.mkdir( path.dirname( item.filename ), {
				recursive: true,
			} );
			const temporary = `${ item.filename }.oml-${ crypto.randomUUID() }`;
			await fs.writeFile(
				temporary,
				Buffer.from( item.content, 'base64' )
			);
			await fs.rename( temporary, item.filename );
		}
	}
}
