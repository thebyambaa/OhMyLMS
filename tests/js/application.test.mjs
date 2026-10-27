import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { adaptApplication } from '../../tools/application-adapters.mjs';

const generate = generatorModule.default || generatorModule;
const sourceRoot = 'assets/src/';

test( 'recovered application source has no license or comparison modules', () => {
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
	const hits = adaptApplication( ast );
	const output = generate( ast, { comments: false, compact: true } ).code;

	assert.doesNotMatch( output, /path:\s*["']\/(?:license|free-vs-pro)["']/ );
	assert.doesNotMatch( output, /ohmylms-free-vs-pro-page/ );
	assert.doesNotMatch(
		output,
		/ohmylms\/v1\/license\/(?:activate|deactivate)/
	);
} );
