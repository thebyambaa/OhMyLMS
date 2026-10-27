import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import generatorModule from '@babel/generator';
import { adaptApplication } from './application-adapters.mjs';
import { adaptVendors, adaptConnectionStatus } from './vendor-adapters.mjs';
import { adaptQuizzes } from './quiz-adapters.mjs';
import { adaptCourses } from './course-adapters.mjs';
import { adaptStudents } from './student-adapters.mjs';
import { adaptGamification } from './gamification-adapters.mjs';
import { adaptQuizReports } from './quiz-report-adapters.mjs';
import { adaptLearning } from './learning-adapters.mjs';
import { adaptMemberships } from './membership-adapters.mjs';
import { adaptCommunities } from './community-adapters.mjs';
import { adaptAnalytics } from './analytics-adapters.mjs';
import { adaptCommerce } from './commerce-adapters.mjs';
import { adaptCertificates } from './certificate-adapters.mjs';
import { adaptEmails } from './email-adapters.mjs';
import { adaptSettings } from './settings-adapters.mjs';
import { adaptIntegrations } from './integration-adapters.mjs';
import { adaptWebhooks } from './webhook-adapters.mjs';
import { adaptTaxonomies } from './taxonomy-adapters.mjs';
import { adaptSetup } from './setup-adapters.mjs';
import { adaptSlashCommands } from './slash-adapters.mjs';
const traverse = traverseModule.default || traverseModule;
const generate = generatorModule.default || generatorModule;
const root = path.resolve( import.meta.dirname, '..' );
const sourceRoot = path.join( root, 'assets/src' );
const manifest = JSON.parse(
	fs.readFileSync( path.join( sourceRoot, 'manifest.json' ), 'utf8' )
);
const outputRoot = path.resolve(
	process.env.OHMYLMS_BUILD_DIR ||
		path.join(
			root,
			process.argv.includes( '--parity' ) ? 'build/parity' : 'build'
		)
);
const hashes = {};
// Retired block assets must not survive rebuilds in an existing output directory.
for ( const retired of manifest.retiredAssets || [] ) {
	for ( const suffix of [ '', '.map' ] ) {
		const destination = path.resolve( outputRoot, retired + suffix );
		if ( ! destination.startsWith( outputRoot + path.sep ) ) {
			throw new Error(
				`Retired asset outside build directory: ${ retired }`
			);
		}
		if ( fs.existsSync( destination ) ) {
			fs.unlinkSync( destination );
		}
	}
}
for ( const asset of manifest.assets ) {
	const destination = path.join( outputRoot, asset.output );
	fs.mkdirSync( path.dirname( destination ), { recursive: true } );
	if ( ! asset.output.endsWith( '.js' ) ) {
		fs.copyFileSync( path.join( sourceRoot, asset.source ), destination );
	} else {
		const sources = {};
		const load = ( name ) => {
			const label = 'ohmylms-source:///' + name;
			const code = fs.readFileSync(
				path.join( sourceRoot, name ),
				'utf8'
			);
			sources[ label ] = code;
			return parse( code, {
				sourceType: 'unambiguous',
				sourceFilename: label,
				allowReturnOutsideFunction: true,
			} );
		};
		const ast = load( asset.source );
		const replacements = new Map(
			( asset.factories || [] ).map( ( f ) => {
				const factory = load( f.source ).program.body.find(
					( n ) => n.type === 'ExpressionStatement'
				).expression;
				if ( f.fragments )
					factory.body.body = f.fragments.flatMap(
						( name ) => load( name ).program.body
					);
				return [ f.marker, factory ];
			} )
		);
		traverse( ast, {
			Identifier( p ) {
				if ( replacements.has( p.node.name ) ) {
					p.replaceWith( replacements.get( p.node.name ) );
					p.skip();
				}
			},
		} );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Application adapters:', adaptApplication( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Quiz adapters:', adaptQuizzes( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Course adapters:', adaptCourses( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Student adapters:', adaptStudents( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Gamification adapters:', adaptGamification( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Learning adapters:', adaptLearning( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Quiz report adapters:', adaptQuizReports( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Membership adapters:', adaptMemberships( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Community adapters:', adaptCommunities( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Analytics adapters:', adaptAnalytics( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Commerce adapters:', adaptCommerce( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Certificate adapters:', adaptCertificates( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Email adapters:', adaptEmails( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Settings adapters:', adaptSettings( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Integration adapters:', adaptIntegrations( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Webhook adapters:', adaptWebhooks( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Taxonomy adapters:', adaptTaxonomies( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Setup adapters:', adaptSetup( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/vendors/vendors.js'
		)
			console.log( 'Vendor adapters:', adaptVendors( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Connection adapter:', adaptConnectionStatus( ast ) );
		if (
			process.argv.includes( '--extensions' ) &&
			asset.output === 'assets/dist/admin/ohmylms.js'
		)
			console.log( 'Slash command adapter:', adaptSlashCommands( ast ) );
		const result = generate(
			ast,
			{ sourceMaps: true, comments: true, compact: false },
			sources
		);
		fs.writeFileSync(
			destination,
			result.code +
				'\n//# sourceMappingURL=' +
				path.basename( destination ) +
				'.map\n'
		);
		fs.writeFileSync( destination + '.map', JSON.stringify( result.map ) );
	}
	hashes[ asset.output ] = crypto
		.createHash( 'sha256' )
		.update( fs.readFileSync( destination ) )
		.digest( 'hex' );
}
fs.writeFileSync(
	path.join( outputRoot, 'asset-hashes.json' ),
	JSON.stringify( hashes, null, 2 ) + '\n'
);
console.log(
	`Built ${ Object.keys( hashes ).length } assets solely from checked-in source.`
);
