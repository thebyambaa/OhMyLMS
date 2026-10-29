import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { buildAdminStyles } from './build-admin-styles.mjs';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import generatorModule from '@babel/generator';
import { adaptApplication } from './application-adapters.mjs';
import { adaptVendors, adaptConnectionStatus } from './vendor-adapters.mjs';
import { adaptEmailPackages } from './email-package-adapters.mjs';
import {
	auditVendorPackages,
	adaptVendorPackages,
} from './vendor-package-adapters.mjs';
import {
	auditEditorRuntime,
	adaptEditorRuntime,
} from './editor-runtime-adapters.mjs';
import {
	auditHighlightConsumers,
	adaptEditorPackages,
} from './editor-package-adapters.mjs';
import {
	adaptEmailRuntime,
	adaptEmailModel,
} from './email-runtime-adapters.mjs';
import {
	auditEmailVendors,
	relocateEmailVendors,
	emailVendorTarget,
} from './email-vendor-chunks.mjs';
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
buildAdminStyles( root, outputRoot );
const sourceCache = new Map();
const readSource = ( name ) => {
	if ( ! sourceCache.has( name ) )
		sourceCache.set(
			name,
			fs.readFileSync( path.join( sourceRoot, name ), 'utf8' )
		);
	return sourceCache.get( name );
};
const emailVendors = process.argv.includes( '--extensions' )
	? auditEmailVendors( manifest, readSource )
	: [];
if ( process.argv.includes( '--extensions' ) )
	auditHighlightConsumers( manifest, readSource );
if ( process.argv.includes( '--extensions' ) )
	auditEditorRuntime( manifest, readSource );
if ( process.argv.includes( '--extensions' ) )
	auditVendorPackages( manifest, readSource );
const emailAsset = manifest.assets.find(
	( asset ) => asset.output === emailVendorTarget
);
const emailVersion = crypto
	.createHash( 'sha256' )
	.update(
		JSON.stringify( [
			fs.readFileSync(
				path.join( root, 'tools/email-package-adapters.mjs' ),
				'utf8'
			),
			readSource( emailAsset.source ),
			...emailAsset.factories.map( ( factory ) =>
				readSource( factory.source )
			),
			...emailVendors.map( ( factory ) => readSource( factory.source ) ),
		] )
	)
	.digest( 'hex' )
	.slice( 0, 12 );
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
		if (
			! [
				'assets/dist/css/admin/ohmylms.css',
				'assets/dist/css/admin/ohmylms-rtl.css',
			].includes( asset.output )
		) {
			fs.copyFileSync(
				path.join( sourceRoot, asset.source ),
				destination
			);
		}
	} else {
		const sources = {};
		const load = ( name ) => {
			const label = 'ohmylms-source:///' + name;
			const code = readSource( name );
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
		if ( emailVendors.length ) {
			if ( asset.output === 'assets/dist/vendors/vendors.js' )
				adaptEmailModel( ast );
			relocateEmailVendors(
				ast,
				asset.output,
				new Map(
					emailVendors.map( ( factory ) => [
						factory.id,
						asset.output === emailVendorTarget
							? load( factory.source ).program.body.find(
									( node ) =>
										node.type === 'ExpressionStatement'
								).expression
							: null,
					] )
				)
			);
			if ( asset.output === 'assets/dist/admin/ohmylms.js' ) {
				console.log(
					'Deferred email runtime:',
					adaptEmailRuntime( ast )
				);
				let urls = 0;
				traverse( ast, {
					StringLiteral( literal ) {
						if ( literal.node.value === 'chunks/355.js' ) {
							literal.node.value += `?ver=${ emailVersion }`;
							urls++;
						}
					},
				} );
				if ( urls !== 1 )
					throw new Error( 'Email chunk URL mapping changed' );
			}
		}
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
		) {
			console.log( 'Vendor adapters:', adaptVendors( ast ) );
			console.log( 'Editor packages:', adaptEditorPackages( ast ) );
		}
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
		if ( process.argv.includes( '--extensions' ) )
			adaptEditorRuntime( ast, asset.output );
		if ( process.argv.includes( '--extensions' ) )
			adaptVendorPackages( ast, asset.output );
		if ( process.argv.includes( '--extensions' ) )
			adaptEmailPackages( ast, asset.output );
		const result = generate(
			ast,
			{
				sourceMaps: true,
				comments: true,
				// Keep the recovery baseline readable; ship compact application output.
				// This changes printing only, without renaming bindings or optimizing expressions.
				compact: process.argv.includes( '--extensions' ),
			},
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
