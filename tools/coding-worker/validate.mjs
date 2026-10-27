/** Fixed validation entry point mounted read-only, without provider credentials. */
import { spawnSync } from 'node:child_process';
import { allowed } from './files.mjs';
const input = [];
for await ( const chunk of process.stdin ) {
	input.push( chunk );
}
const request = JSON.parse( Buffer.concat( input ).toString() );
const changed = ( request.changed || [] ).filter( allowed );
process.chdir( '/workspace' );
const checks = [];
const commands = [ [ 'npm', [ 'ci', '--ignore-scripts' ] ] ];
for ( const file of changed.filter( ( name ) => name.endsWith( '.php' ) ) ) {
	commands.push( [ 'php', [ '-l', file ] ] );
}
const javascript = changed.filter( ( name ) =>
	/^assets\/src\/(features|extensions)\/.+\.(jsx?|mjs)$/.test( name )
);
const css = changed.filter( ( name ) => /^assets\/css\/.+\.css$/.test( name ) );
if ( javascript.length ) {
	commands.push( [
		'node',
		[ 'node_modules/eslint/bin/eslint.js', ...javascript ],
	] );
}
if ( css.length ) {
	commands.push( [
		'node',
		[ 'node_modules/stylelint/bin/stylelint.mjs', ...css ],
	] );
}
commands.push(
	[ 'npm', [ 'run', 'lint:source' ] ],
	[ 'npm', [ 'test' ] ],
	[ 'npm', [ 'run', 'build' ] ]
);
for ( const [ executable, args ] of commands ) {
	const result = spawnSync( executable, args, {
		encoding: 'utf8',
		timeout: 480000,
		maxBuffer: 3000000,
	} );
	checks.push( {
		command: `${ executable } ${ args.join( ' ' ) }`,
		code: result.status,
		output: ( result.stdout + result.stderr ).slice( -10000 ),
	} );
	if ( result.status !== 0 ) {
		break;
	}
}
process.stdout.write(
	JSON.stringify( {
		passed:
			checks.length === commands.length &&
			checks.every( ( check ) => check.code === 0 ),
		checks,
	} )
);
