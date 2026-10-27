/** Authenticated local pull worker. No listener, shell endpoint, or live code generation. */
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import dns from 'node:dns/promises';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';
import { hash, manifest, review, applyReview, allowed } from './files.mjs';

const directory = path.dirname( fileURLToPath( import.meta.url ) );
const models = {};
const credentials = {
	codex: 'OPENAI_API_KEY',
	claude: 'ANTHROPIC_API_KEY',
	gemini: 'GEMINI_API_KEY',
};
const image = process.env.OML_WORKER_IMAGE || 'ohmylms-coding-worker:1';
const root = await fs.realpath(
	process.env.OML_WORKER_CHECKOUT || process.cwd()
);
const state = path.resolve(
	process.env.OML_WORKER_STATE || path.join( os.homedir(), '.ohmylms-worker' )
);
const webRoot = path.resolve( root, '../../..' );
const inside = ( parent, candidate ) => {
	const relative = path.relative( parent, candidate );
	return (
		! relative ||
		( ! relative.startsWith( '..' ) && ! path.isAbsolute( relative ) )
	);
};
if (
	inside( webRoot, state ) ||
	! (
		await fs.readFile( path.join( root, 'ohmylms.php' ), 'utf8' )
	).includes( 'OhMyLMS' )
) {
	throw new Error(
		'Use the intended OhMyLMS checkout and a private state directory outside the web root.'
	);
}
const checkout = hash( root.replaceAll( '\\', '/' ) );
const token = process.env.OML_WORKER_TOKEN;
const endpoint = new URL(
	process.env.OML_WORKER_ENDPOINT || 'http://127.0.0.1/'
);
if (
	! /^oml_worker_[a-f0-9]{64}$/.test( token || '' ) ||
	endpoint.username ||
	endpoint.password ||
	( endpoint.search &&
		( endpoint.searchParams.size !== 1 ||
			endpoint.searchParams.get( 'rest_route' ) !==
				'/ohmylms/v1/admin-assistant/worker' ) ) ||
	endpoint.hash ||
	! [ 'http:', 'https:' ].includes( endpoint.protocol )
) {
	throw new Error(
		'Set the worker endpoint and pairing token in the local environment.'
	);
}
const addresses = await dns.lookup( endpoint.hostname, { all: true } );
if (
	! addresses.length ||
	addresses.some(
		( { address } ) =>
			! ( address === '::1' || address.startsWith( '127.' ) )
	)
) {
	throw new Error(
		'The development worker only connects to loopback sites.'
	);
}
await fs.mkdir( state, { recursive: true, mode: 0o700 } );
const stateReal = await fs.realpath( state );
if ( inside( webRoot, stateReal ) ) {
	throw new Error( 'Worker state resolves inside the web root.' );
}
const lock = await fs.open( path.join( state, 'worker.lock' ), 'wx', 0o600 );
await lock.writeFile( `${ process.pid }` );

function command( executable, args, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( executable, args, {
			shell: false,
			windowsHide: true,
			cwd: root,
			...options,
			stdio: [ 'pipe', 'pipe', 'pipe' ],
		} );
		let output = '';
		let failed = false;
		const timer = setTimeout( () => {
			failed = true;
			child.kill();
		}, options.timeout || 1200000 );
		child.stdout.on( 'data', ( chunk ) => {
			output += chunk;
			if ( output.length > 4000000 ) {
				failed = true;
				child.kill();
			}
		} );
		child.stderr.resume();
		child.on( 'error', ( error ) => {
			clearTimeout( timer );
			reject( error );
		} );
		child.on( 'close', ( code ) => {
			clearTimeout( timer );
			code === 0 && ! failed
				? resolve( output )
				: reject(
						new Error(
							`${ executable } failed or exceeded its limit.`
						)
					);
		} );
		child.stdin.end( options.input || '' );
	} );
}

function scrub( text ) {
	let safe = String( text );
	for ( const name of [
		...Object.values( credentials ),
		'OML_WORKER_TOKEN',
	] ) {
		if ( process.env[ name ] ) {
			safe = safe.replaceAll( process.env[ name ], '[redacted]' );
		}
	}
	return safe.replace(
		/(?:sk-[A-Za-z0-9_-]{12,}|AIza[A-Za-z0-9_-]{20,}|Bearer\s+[^\s"']+)/g,
		'[redacted]'
	);
}

async function container( work, entry, input, provider = '' ) {
	const name = `oml-${ crypto.randomUUID() }`;
	const args = [
		'run',
		'--rm',
		'--name',
		name,
		'--cap-drop',
		'ALL',
		'--security-opt',
		'no-new-privileges',
		'--pids-limit',
		'256',
		'--memory',
		'4g',
		'--cpus',
		'2',
		'--read-only',
		'--tmpfs',
		'/tmp:rw,nosuid,size=512m',
		'--mount',
		`type=bind,source=${ work },target=/workspace`,
		'--mount',
		`type=bind,source=${ directory },target=/runner,readonly`,
		'-w',
		'/workspace',
		'-e',
		'HOME=/tmp/oml-home',
	];
	if ( provider ) {
		args.push(
			'-e',
			credentials[ provider ],
			'-e',
			`OML_PROVIDER_MODEL=${ models[ provider ] }`
		);
	}
	args.push( image, 'node', `/runner/${ entry }.mjs` );
	try {
		return JSON.parse(
			await command( 'docker', args, { input: JSON.stringify( input ) } )
		);
	} finally {
		await command( 'docker', [ 'rm', '-f', name ], {
			timeout: 10000,
		} ).catch( () => {} );
	}
}

async function snapshot( work ) {
	const tracked = (
		await command( 'git', [
			'ls-files',
			'-z',
			'--cached',
			'--others',
			'--exclude-standard',
		] )
	)
		.split( '\0' )
		.filter( Boolean );
	let size = 0;
	for ( const relative of new Set( tracked ) ) {
		const parts = relative.split( '/' );
		if (
			parts.some(
				( part ) =>
					[
						'..',
						'.git',
						'.env',
						'.npmrc',
						'node_modules',
						'vendor',
						'test-results',
						'build',
					].includes( part ) || part.startsWith( '.env.' )
			) ||
			path.isAbsolute( relative )
		) {
			continue;
		}
		let source = root;
		let exists = true;
		for ( const part of parts ) {
			source = path.join( source, part );
			try {
				if ( ( await fs.lstat( source ) ).isSymbolicLink() ) {
					throw new Error( 'Source contains a linked path.' );
				}
			} catch ( error ) {
				if ( error.code === 'ENOENT' ) {
					exists = false;
					break;
				}
				throw error;
			}
		}
		if ( ! exists ) {
			continue;
		}
		const stat = await fs.stat( source );
		if ( ! stat.isFile() ) {
			continue;
		}
		size += stat.size;
		if ( stat.size > 30000000 || size > 600000000 ) {
			throw new Error( 'Source copy exceeds the development limit.' );
		}
		const target = path.join( work, relative );
		await fs.mkdir( path.dirname( target ), {
			recursive: true,
			mode: 0o777,
		} );
		await fs.copyFile( source, target );
		await fs.chmod( target, 0o666 );
	}
}

const providers = {};
async function probe() {
	let runtime;
	try {
		await command( 'docker', [ 'image', 'inspect', image ], {
			timeout: 15000,
		} );
		const probeWork = path.join( state, 'probe' );
		await fs.mkdir( probeWork, { recursive: true, mode: 0o777 } );
		runtime = await container( probeWork, 'provider', { probe: true } );
	} catch {
		runtime = null;
	}
	for ( const name of Object.keys( credentials ) ) {
		models[ name ] =
			process.env[ `OML_${ name.toUpperCase() }_MODEL` ] || '';
		const configured =
			!! process.env[ credentials[ name ] ] && !! models[ name ];
		providers[ name ] = {
			ready: !! runtime?.versions && configured,
			detail: ! runtime
				? 'Container runtime or provider image unavailable.'
				: ! configured
					? 'Set the provider API key and model on the local worker.'
					: 'Runtime installed and credentials configured; account access is verified when a job runs.',
		};
	}
}

async function post( body ) {
	const payload = JSON.stringify( { checkout, providers, ...body } );
	return new Promise( ( resolve, reject ) => {
		const transport =
			endpoint.protocol === 'https:' ? httpsRequest : httpRequest;
		const request = transport(
			endpoint,
			{
				method: 'POST',
				autoSelectFamily: false,
				// Pin the verified loopback address: subsequent DNS changes cannot receive the token.
				lookup: ( hostname, options, callback ) =>
					callback(
						null,
						addresses[ 0 ].address,
						addresses[ 0 ].family
					),
				headers: {
					Authorization: `Bearer ${ token }`,
					'Content-Type': 'application/json',
					'Content-Length': Buffer.byteLength( payload ),
				},
			},
			( response ) => {
				let content = '';
				response.on( 'data', ( chunk ) => {
					content += chunk;
					if ( content.length > 350000 ) {
						request.destroy(
							new Error( 'Worker response exceeds its limit.' )
						);
					}
				} );
				response.on( 'end', () => {
					if ( response.statusCode !== 200 ) {
						reject(
							new Error(
								`WordPress worker request rejected (${ response.statusCode }).`
							)
						);
						return;
					}
					try {
						resolve( JSON.parse( content ) );
					} catch {
						reject(
							new Error(
								'Worker endpoint did not return valid JSON.'
							)
						);
					}
				} );
			}
		);
		request.setTimeout( 15000, () =>
			request.destroy( new Error( 'Worker request timed out.' ) )
		);
		request.on( 'error', reject );
		request.end( payload );
	} );
}

async function execute( job ) {
	if (
		! /^[a-f0-9-]{36}$/.test( job.id ) ||
		job.checkout !== checkout ||
		! [ 'queued', 'apply_queued', 'rollback_queued' ].includes(
			job.operation
		)
	) {
		throw new Error( 'Unexpected job checkout or ID.' );
	}
	const jobDirectory = path.join( state, job.id );
	const bundleFile = path.join( jobDirectory, 'review.json' );
	const journalFile = path.join( jobDirectory, 'originals.json' );
	if ( job.operation !== 'queued' ) {
		const bundle = JSON.parse( await fs.readFile( bundleFile, 'utf8' ) );
		if ( job.digest !== bundle.digest ) {
			throw new Error( 'Approval does not match the local review.' );
		}
		const rollback = job.operation === 'rollback_queued';
		await applyReview( root, bundle, journalFile, rollback );
		return {
			status: rollback ? 'rolled_back' : 'applied',
			summary: rollback
				? 'Previous files restored. No database records were changed.'
				: 'Reviewed files applied. Recovery originals retained by the local worker.',
		};
	}
	await fs.mkdir( jobDirectory, { mode: 0o700 } );
	const work = path.join( jobDirectory, 'work' );
	await fs.mkdir( work, { mode: 0o777 } );
	await snapshot( work );
	const baseline = await manifest( work );
	const fullBaseline = await manifest( work, true );
	const beforeTexts = {};
	for ( const name of Object.keys( baseline ) ) {
		beforeTexts[ name ] = await fs.readFile(
			path.join( work, name ),
			'utf8'
		);
	}
	const generated = await container(
		work,
		'provider',
		{ provider: job.provider, prompt: job.prompt },
		job.provider
	);
	const proposed = await manifest( work );
	const changed = Object.keys( proposed ).filter(
		( name ) => proposed[ name ] !== baseline[ name ]
	);
	const validation = await container( work, 'validate', { changed } );
	const fullAfter = await manifest( work, true );
	for ( const name of new Set( [
		...Object.keys( fullBaseline ),
		...Object.keys( fullAfter ),
	] ) ) {
		if ( fullBaseline[ name ] !== fullAfter[ name ] && ! allowed( name ) ) {
			throw new Error(
				`Unsupported file changed: ${ name }. Review requires a narrower request.`
			);
		}
	}
	const bundle = await review( baseline, work );
	await fs.writeFile( bundleFile, JSON.stringify( bundle ), { mode: 0o600 } );
	const diffs = [];
	for ( const change of bundle.changes ) {
		const previous = beforeTexts[ change.name ] || '';
		const after =
			change.content === null
				? ''
				: Buffer.from( change.content, 'base64' ).toString( 'utf8' );
		const heading = `--- ${ change.name } (${ change.before || 'new' })\n+++ ${ change.name } (${ change.after || 'deleted' })`;
		if ( change.name.startsWith( 'assets/dist/' ) ) {
			diffs.push(
				`${ heading }\nGenerated asset rebuilt; ${ Buffer.byteLength( after ) } bytes. Exact contents included in review digest.`
			);
		} else {
			const oldLines = previous.split( '\n' );
			const newLines = after.split( '\n' );
			let first = 0;
			while (
				first < oldLines.length &&
				first < newLines.length &&
				oldLines[ first ] === newLines[ first ]
			) {
				first++;
			}
			let last = 0;
			while (
				last < oldLines.length - first &&
				last < newLines.length - first &&
				oldLines[ oldLines.length - 1 - last ] ===
					newLines[ newLines.length - 1 - last ]
			) {
				last++;
			}
			const start = Math.max( 0, first - 3 );
			const tail = Math.max( 0, last - 3 );
			diffs.push(
				`${ heading }\n@@ from line ${ start + 1 } @@\n${ oldLines
					.slice( start, oldLines.length - tail )
					.map( ( line ) => '-' + line )
					.join( '\n' ) }\n${ newLines
					.slice( start, newLines.length - tail )
					.map( ( line ) => '+' + line )
					.join( '\n' ) }`
			);
		}
	}
	const diff = scrub( diffs.join( '\n\n' ) );
	if ( diff.length > 180000 ) {
		throw new Error(
			'Review too large to display completely. Request a smaller change.'
		);
	}
	return {
		status: 'review',
		summary: scrub( generated.summary ).slice( 0, 12000 ),
		digest: bundle.digest,
		diff,
		checks_passed: validation.passed,
		checks: scrub( JSON.stringify( validation.checks, null, 2 ) ).slice(
			-20000
		),
	};
}

const pendingFile = path.join( state, 'pending-report.json' );
try {
	await probe();
	process.stdout.write(
		'OhMyLMS local worker ready. Provider readiness is reported to the administrator panel.\n'
	);
	while ( true ) {
		try {
			try {
				const pending = JSON.parse(
					await fs.readFile( pendingFile, 'utf8' )
				);
				await post( { report: pending } );
				await fs.unlink( pendingFile );
			} catch ( error ) {
				if ( error.code !== 'ENOENT' ) {
					throw error;
				}
			}
			const { job } = await post( {} );
			if ( job ) {
				const heartbeat = setInterval(
					() => post( { heartbeat: true } ).catch( () => {} ),
					20000
				);
				let report;
				try {
					report = await execute( job );
				} catch ( error ) {
					report = {
						status: 'failed',
						summary: scrub( error.message ).slice( 0, 2000 ),
					};
				} finally {
					clearInterval( heartbeat );
				}
				await fs.writeFile(
					pendingFile,
					JSON.stringify( {
						...report,
						id: job.id,
						lease: job.lease,
					} ),
					{ mode: 0o600 }
				);
			}
		} catch ( error ) {
			process.stderr.write( `${ scrub( error.message ) }\n` );
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 3000 ) );
	}
} finally {
	await lock.close();
	await fs.unlink( path.join( state, 'worker.lock' ) );
}
