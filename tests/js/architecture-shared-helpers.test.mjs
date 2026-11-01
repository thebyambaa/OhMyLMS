import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	checkSharedHelperCopies,
	helperCopies,
} from '../../tools/architecture-rules/shared-helpers.mjs';
import {
	checkLegacyRouteUrls,
	legacyUrlLines,
	stripPhpComments,
} from '../../tools/architecture-rules/legacy-routes.mjs';
import {
	Project,
	runArchitectureChecks,
} from '../../tools/check-architecture.mjs';

const pluginRoot = path.resolve( import.meta.dirname, '../..' );

/** A throwaway plugin root holding the given files (path relative to the root => contents). */
function tree( files ) {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-shared-helpers-' )
	);
	for ( const [ file, contents ] of Object.entries( files ) ) {
		const full = path.join( root, file );
		fs.mkdirSync( path.dirname( full ), { recursive: true } );
		fs.writeFileSync( full, contents );
	}
	test.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	return root;
}

const FEATURE = 'assets/src/app/features';

/* Shapes of the copies deleted on 31 October 2026. */
const KEYED_REQUEST = `import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
export function useRequest< T >( load: ( signal: AbortSignal ) => Promise< T >, key: string ) {
	const loader = useRef( load );
	return { reload: useCallback( () => undefined, [ key ] ) };
}
`;
const OPERATION = `export const useOperation = () => ( { busy: false, error: null } );\n`;
const VALIDATORS = `export function invalidResponse(): Error {
	return new Error( 'The server returned invalid exam data.' );
}
export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
const text = ( value: unknown ): string => {
	if ( 'string' !== typeof value ) throw invalidResponse();
	return value;
};
export function positiveId( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) > 0 ? Number( value ) : 0;
}
`;
const MEDIA = `export function parseMedia( value: any ) {
	const sizes = value.media_details?.sizes;
	return { id: value.id, thumbnail_url: sizes?.thumbnail?.source_url ?? value.source_url };
}
`;
/* Shape of the picker copies deleted on 31 October 2026. */
const PICKER = `import { Modal } from '@wordpress/components';
export function MediaPicker( { title, onClose }: { title: string; onClose: () => void } ) {
	return <Modal title={ title } onRequestClose={ onClose } />;
}
`;

test( 'helper copies are found by declaration, module name and media_details reads', () => {
	assert.deepEqual( helperCopies( 'a/hooks.ts', KEYED_REQUEST ), [
		'useRequest',
	] );
	assert.deepEqual( helperCopies( 'a/useOperation.ts', OPERATION ), [
		'useOperation',
	] );
	assert.deepEqual(
		helperCopies( 'a/useRequest.ts', `export { x } from './x';\n` ),
		[ 'useRequest' ],
		're-export shims named after a hook count'
	);
	assert.deepEqual( helperCopies( 'a/model.ts', VALIDATORS ), [
		'positiveId',
		'record',
		'text',
	] );
	assert.deepEqual( helperCopies( 'a/model.ts', MEDIA ), [
		'parseMediaImage',
	] );
	assert.deepEqual(
		helperCopies(
			'a/model.tsx',
			`const read = ( { media_details }: any ) => media_details;\nexport default read;\n`
		),
		[ 'parseMediaImage' ],
		'destructuring media_details counts as parsing it'
	);
	assert.deepEqual( helperCopies( 'a/BadgeEditor.tsx', PICKER ), [
		'MediaPicker',
	] );
	assert.deepEqual(
		helperCopies(
			'a/fields.tsx',
			`import { memo } from 'react';\nexport const MediaPicker = memo( () => null );\n`
		),
		[ 'MediaPicker' ],
		'a MediaPicker binding counts whatever creates it'
	);
	assert.deepEqual(
		helperCopies(
			'a/MediaPicker.tsx',
			`export { MediaPicker } from '../../components/MediaPicker';\n`
		),
		[ 'MediaPicker' ],
		're-export shims named after the picker count'
	);
} );

test( 'bound validators, other arities, nested helpers and _fields strings are not copies', () => {
	const clean = `import { createValidators } from '../../lib/validation';
import { parseMediaImage } from '../../lib/media';
function invalidResponse(): Error {
	return new Error( 'x' );
}
const { text } = createValidators( invalidResponse );
export const { record, positiveId } = createValidators( invalidResponse );
function text2( values: Record< string, string >, name: string ) {
	return values[ name ];
}
export function settingText( values: Record< string, string >, name: string ) {
	function record( value: unknown ) {
		return value;
	}
	return record( values[ name ] );
}
function unusedText( values: Record< string, string >, name: string ): string {
	return values[ name ] ?? '';
}
export const query = { _fields: 'id,source_url,title,media_details' };
export const parse = ( item: unknown ) => parseMediaImage( item, { invalid: 'x' } );
`;
	assert.deepEqual( helperCopies( 'a/model.ts', clean ), [] );
	assert.deepEqual(
		helperCopies(
			'a/model.ts',
			`function text( values: Record< string, string >, name: string ) {\n\treturn values[ name ];\n}\n`
		),
		[],
		'a two-argument text() is a different helper'
	);
	assert.deepEqual(
		helperCopies(
			'a/BadgeEditor.tsx',
			`import { MediaPicker } from '../../components/MediaPicker';\nexport const Field = () => <MediaPicker label="x" />;\n`
		),
		[],
		'using the shared picker is not a copy'
	);
} );

test( 'checkSharedHelperCopies reports features, skips homes and tests, and honours the allow-list', () => {
	const root = tree( {
		'assets/src/app/hooks/useRequest.ts': KEYED_REQUEST,
		'assets/src/app/hooks/useOperation.ts': OPERATION,
		'assets/src/app/lib/validation.ts': VALIDATORS,
		'assets/src/app/lib/media.ts': MEDIA,
		'assets/src/app/components/MediaPicker.tsx': PICKER,
		[ `${ FEATURE }/gamma/MediaPicker.tsx` ]: PICKER,
		[ `${ FEATURE }/alpha/useRequest.ts` ]: KEYED_REQUEST,
		[ `${ FEATURE }/alpha/hooks.ts` ]: OPERATION,
		[ `${ FEATURE }/alpha/model.ts` ]: VALIDATORS,
		[ `${ FEATURE }/alpha/model.test.ts` ]: VALIDATORS,
		[ `${ FEATURE }/beta/media.ts` ]: MEDIA,
		'assets/src/app/components/helpers.ts': OPERATION,
	} );
	const project = new Project( root );
	const { violations, failures, allowed } = checkSharedHelperCopies(
		project,
		[
			{
				file: `${ FEATURE }/beta/media.ts`,
				helper: 'parseMediaImage',
				reason: 'Fixture: being moved.',
			},
			{
				file: `${ FEATURE }/gone/model.ts`,
				helper: 'record',
				reason: 'Fixture: deleted.',
			},
			{ file: `${ FEATURE }/alpha/hooks.ts`, helper: 'useOperation' },
		]
	);
	assert.deepEqual(
		violations
			.map( ( { file, helper } ) => `${ file } ${ helper }` )
			.sort(),
		[
			'assets/src/app/components/helpers.ts useOperation',
			`${ FEATURE }/alpha/hooks.ts useOperation`,
			`${ FEATURE }/alpha/model.ts positiveId`,
			`${ FEATURE }/alpha/model.ts record`,
			`${ FEATURE }/alpha/model.ts text`,
			`${ FEATURE }/alpha/useRequest.ts useRequest`,
			`${ FEATURE }/beta/media.ts parseMediaImage`,
			`${ FEATURE }/gamma/MediaPicker.tsx MediaPicker`,
		]
	);
	assert.deepEqual(
		allowed.map( ( entry ) => entry.reason ),
		[ 'Fixture: being moved.' ]
	);
	assert.ok(
		failures.some( ( failure ) =>
			failure.includes( 'sharedHelpers: entry' )
		),
		'an entry without a reason fails'
	);
	assert.ok(
		failures.includes(
			`${ FEATURE }/alpha/useRequest.ts defines its own useRequest; import useRequest from assets/src/app/hooks/useRequest.ts and use its key, keepData and errorShape options (add an option there when the behaviour differs)`
		)
	);
	assert.ok(
		failures.includes(
			`${ FEATURE }/alpha/model.ts defines its own record; import record from assets/src/app/lib/validation.ts and bind it to the feature error with createValidators( invalidResponse ) (add an option there when the behaviour differs)`
		)
	);
	assert.ok(
		failures.includes(
			`${ FEATURE }/gamma/MediaPicker.tsx defines its own MediaPicker; import MediaPicker from assets/src/app/components/MediaPicker.tsx and pass the feature labels, accept, fallback and invalid message as props (add an option there when the behaviour differs)`
		)
	);
	assert.ok(
		failures.includes(
			`stale allow-list entry (no longer violated, remove it): sharedHelpers ${ FEATURE }/gone/model.ts record`
		)
	);
	assert.ok(
		! failures.some( ( failure ) =>
			failure.includes( 'beta/media.ts parses' )
		)
	);
} );

test( 'PHP comments, inline HTML and other classes do not count as LegacyRoutes::url() calls', () => {
	const source = `<p>Don't <?php echo 1; ?> it's</p>
<?php
/**
 * Links use {@see LegacyRoutes::url()} for old names.
 */
// LegacyRoutes::url( '/x' )
# LegacyRoutes::url( '/x' )
$a = OtherLegacyRoutes::url( '/x' );
$b = LegacyRoutes::app_url( '/courses' );
$c = \\OhMyLMS\\Admin\\LegacyRoutes::url( '/orders' );
$d = LegacyRoutes :: url ( '/orders' );
$e = array_map( array( LegacyRoutes::class, 'url' ), $routes );
$f = '// not a comment'; $g = LegacyRoutes::url( '/y' );
`;
	assert.deepEqual( legacyUrlLines( source ), [ 10, 11, 12, 13 ] );
	assert.equal(
		stripPhpComments( source ).split( '\n' ).length,
		source.split( '\n' ).length,
		'line numbers stay put'
	);
} );

test( 'checkLegacyRouteUrls allows includes/Admin and listed files, and reports stale entries', () => {
	const call = `<?php\nreturn \\OhMyLMS\\Admin\\LegacyRoutes::url( '/quiz-edit/5' );\n`;
	const root = tree( {
		'includes/Admin/Menu.php': call,
		'includes/Learning/NativeLessonEditor.php': call,
		'includes/Schools/Views.php': `<?php\n$a = 1;\n${ call.slice( 6 ) }`,
		'templates/admin/page.php': `<a href="<?php echo esc_url( LegacyRoutes::url( '/x' ) ); ?>">x</a>\n`,
		'tests/php/legacy-routes-integration.php': call,
		'vendor/x/y.php': call,
	} );
	const result = checkLegacyRouteUrls( root, [
		{
			file: 'includes/Learning/NativeLessonEditor.php',
			reason: 'Legacy input.',
		},
		{ file: 'includes/Gone.php', reason: 'Fixture: deleted.' },
	] );
	assert.deepEqual(
		result.violations.map( ( { file, lines } ) => `${ file }:${ lines }` ),
		[
			'includes/Learning/NativeLessonEditor.php:2',
			'includes/Schools/Views.php:3',
			'templates/admin/page.php:1',
		],
		'includes/Admin owns LegacyRoutes; tests and vendor are not scanned'
	);
	assert.deepEqual(
		result.allowed.map( ( entry ) => entry.reason ),
		[ 'Legacy input.' ]
	);
	assert.deepEqual( result.failures, [
		"includes/Schools/Views.php:3 builds an admin link from a legacy route name with LegacyRoutes::url(); use LegacyRoutes::app_url( '/<app path>' )",
		"templates/admin/page.php:1 builds an admin link from a legacy route name with LegacyRoutes::url(); use LegacyRoutes::app_url( '/<app path>' )",
		'stale allow-list entry (no longer violated, remove it): legacyRouteUrls includes/Gone.php',
	] );
	const missingReason = checkLegacyRouteUrls( root, [
		{ file: 'includes/Learning/NativeLessonEditor.php' },
	] );
	assert.ok(
		missingReason.failures.some( ( failure ) =>
			failure.includes( 'legacyRouteUrls: entry' )
		)
	);
	assert.ok(
		missingReason.failures.some( ( failure ) =>
			failure.startsWith( 'includes/Learning/NativeLessonEditor.php:2' )
		),
		'an entry without a reason allows nothing'
	);
	assert.deepEqual( checkLegacyRouteUrls( tree( {} ) ).failures, [] );
} );

test( 'the plugin has no helper copies and calls LegacyRoutes::url() only where allowed', () => {
	const result = runArchitectureChecks( pluginRoot );
	for ( const name of [
		'features use the shared request hooks, validators and media parser',
		'PHP admin links use app addresses, not LegacyRoutes::url()',
	] ) {
		const section = result.sections.find(
			( candidate ) => candidate.name === name
		);
		assert.ok( section, `section "${ name }" is registered` );
		assert.deepEqual( section.failures, [] );
	}
	const legacy = result.sections.find( ( section ) =>
		section.name.includes( 'LegacyRoutes::url()' )
	);
	assert.deepEqual(
		legacy.allowed.map( ( entry ) => entry.file ),
		[ 'includes/Learning/NativeLessonEditor.php' ]
	);
} );
