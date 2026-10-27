/** Runs only inside the disposable container; never loaded by WordPress. */
import { createRequire } from 'node:module';
import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';

const requireProvider = createRequire( '/opt/providers/package.json' );
const load = async ( name ) => import( requireProvider.resolve( name ) );
const chunks = [];
for await ( const chunk of process.stdin ) {
	chunks.push( chunk );
}
const request = JSON.parse( Buffer.concat( chunks ).toString() );
await fs.mkdir( process.env.HOME, { recursive: true } );
process.chdir( '/workspace' );

/** Real runtime probes check installed packages, independently of account access. */
if ( request.probe ) {
	const versions = {};
	for ( const name of [
		'@openai/codex-sdk',
		'@anthropic-ai/claude-agent-sdk',
		'@google/gemini-cli',
	] ) {
		versions[ name ] = JSON.parse(
			await fs.readFile(
				`/opt/providers/node_modules/${ name }/package.json`,
				'utf8'
			)
		).version;
	}
	process.stdout.write( JSON.stringify( { versions } ) );
} else {
	const prompt = `You are working in a disposable OhMyLMS plugin development copy. Follow AGENTS.md and WordPress standards. Implement the administrator request, preserving existing changes. Edit authored source and rebuild generated assets. Do not access credentials, publish, deploy, commit, push, change account permissions, or claim actions outside this copy. Run relevant tests and explain remaining failures. Do not change tools/coding-worker or its security controls. Administrator request:\n${ request.prompt }`;
	let text = '';
	if ( request.provider === 'codex' ) {
		const { Codex } = await load( '@openai/codex-sdk' );
		const codex = new Codex( { apiKey: process.env.OPENAI_API_KEY } );
		const thread = codex.startThread( {
			workingDirectory: '/workspace',
			model: process.env.OML_PROVIDER_MODEL,
			// The outer container is the filesystem boundary; no host paths are mounted.
			sandboxMode: 'workspace-write',
			approvalPolicy: 'never',
			skipGitRepoCheck: true,
			networkAccessEnabled: false,
			webSearchMode: 'disabled',
		} );
		text = ( await thread.run( prompt ) ).finalResponse;
	} else if ( request.provider === 'claude' ) {
		const { query } = await load( '@anthropic-ai/claude-agent-sdk' );
		for await ( const message of query( {
			prompt,
			options: {
				cwd: '/workspace',
				model: process.env.OML_PROVIDER_MODEL,
				maxTurns: 30,
				permissionMode: 'acceptEdits',
				settingSources: [],
				allowedTools: [
					'Read',
					'Write',
					'Edit',
					'Glob',
					'Grep',
					'Bash',
				],
				disallowedTools: [ 'WebFetch', 'WebSearch', 'Agent' ],
				canUseTool: async () => ( {
					behavior: 'deny',
					message:
						'Only the explicitly configured tools are allowed.',
				} ),
			},
		} ) ) {
			if ( message.type === 'result' ) {
				if ( message.is_error || message.subtype !== 'success' ) {
					throw new Error(
						'Claude Agent did not complete successfully.'
					);
				}
				text = message.result || '';
			}
		}
	} else if ( request.provider === 'gemini' ) {
		const output = await new Promise( ( resolve, reject ) => {
			const child = spawn(
				'/opt/providers/node_modules/.bin/gemini',
				[
					'--prompt',
					prompt,
					'--model',
					process.env.OML_PROVIDER_MODEL,
					'--output-format',
					'json',
					'--approval-mode',
					'auto_edit',
				],
				{ shell: false, stdio: [ 'ignore', 'pipe', 'pipe' ] }
			);
			let stdout = '';
			child.stdout.on( 'data', ( chunk ) => {
				stdout += chunk;
				if ( stdout.length > 1000000 ) {
					child.kill();
				}
			} );
			child.stderr.resume();
			child.on( 'error', reject );
			child.on( 'close', ( code ) =>
				code === 0
					? resolve( stdout )
					: reject(
							new Error(
								'Gemini CLI did not complete successfully.'
							)
						)
			);
		} );
		const result = JSON.parse( output );
		if ( result.error ) {
			throw new Error( 'Gemini CLI returned an error.' );
		}
		text = result.response || '';
	} else {
		throw new Error( 'Unknown provider.' );
	}
	if ( ! text ) {
		throw new Error( 'Provider returned no final result.' );
	}
	process.stdout.write( JSON.stringify( { summary: text } ) );
}
