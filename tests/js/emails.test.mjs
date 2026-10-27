import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { adaptEmails } from '../../tools/email-adapters.mjs';

const generate = generatorModule.default || generatorModule;
const featureRoot = 'assets/src/features/emails/';
const sourceRoot = 'assets/src/';
const rows = JSON.parse( fs.readFileSync( featureRoot + 'components.json' ) );
const expected = [
	'EmailSettingsPage',
	'EmailTemplateList',
	'EmailButtonPosition',
	'EmailSenderOptions',
	'EmailPersonalization',
	'EmailEditorPage',
	'EmailEditor',
	'EmailEditorHeader',
	'EmailDesktopIcon',
	'EmailMobileIcon',
	'EmailPreview',
	'EmailFields',
];

test( 'email conversion exposes settings, templates and the complete editor component set', () => {
	assert.deepEqual(
		rows.map( ( row ) => row.name ),
		expected
	);
	assert.equal(
		new Set( rows.map( ( row ) => row.binding ) ).size,
		expected.length
	);
	for ( const row of rows ) {
		assert.ok(
			row.dependencies.includes( 'React' ),
			row.name + ' declares the React runtime'
		);
		const source = fs.readFileSync( featureRoot + row.file, 'utf8' );
		parse( source, { sourceType: 'module', plugins: [ 'jsx' ] } );
		assert.match(
			source,
			new RegExp( `export function create${ row.name }\\(` )
		);
	}
} );

test( 'email adapter replaces every recovered factory binding', () => {
	const manifest = JSON.parse(
		fs.readFileSync( sourceRoot + 'manifest.json' )
	);
	const factory = manifest.assets
		.find( ( asset ) => asset.output === 'assets/dist/admin/ohmylms.js' )
		.factories.find( ( item ) => item.id === '1841' );
	const fragments = factory.fragments
		.map( ( file ) => fs.readFileSync( sourceRoot + file, 'utf8' ) )
		.join( '\n' );
	const ast = parse( `({1841:function(){${ fragments }}})` );
	assert.deepEqual( adaptEmails( ast ), { components: expected.length } );
	const output = generate( ast, { comments: false, compact: true } ).code;
	for ( const name of expected )
		assert.match( output, new RegExp( `emailComponents\\.${ name }` ) );
} );
