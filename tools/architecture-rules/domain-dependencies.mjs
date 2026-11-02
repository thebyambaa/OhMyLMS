import fs from 'node:fs';
import path from 'node:path';
import { analyzePhp, isRestController } from './rest-controllers.mjs';
import { stripPhpComments } from './controller-persistence.mjs';

/** Composition and transport adapters may wire controllers; domain operations may not. */
const COMPOSITION = new Set( [
	'includes/OhMyLMS.php',
	'includes/Hooks/ApiHook.php',
	'includes/I18n/InterfaceLanguage.php',
	'includes/Engagement/XpHooks.php',
	'includes/Engagement/StreakHooks.php',
	'includes/XApi/Bootstrap.php',
	'includes/PostTypes/LessonPostType.php',
	'includes/PostTypes/QuestionPostType.php',
	'includes/PostTypes/QuizPostType.php',
	'packages/e-commerce/includes/Gateways/QPay/GatewayQPay.php',
	'includes/Gateways/Paypal/GatewayPaypal.php',
	'includes/Integrations/GoogleSignIn/GoogleSignIn.php',
	'includes/Integrations/MicrosoftSignIn/MicrosoftSignIn.php',
	'includes/Integrations/Zoom/Zoom.php',
	'includes/Integrations/GoogleMeet/GoogleMeet.php',
	'includes/Integrations/WPFusion/WPFusion.php',
] );

function files( directory ) {
	if ( ! fs.existsSync( directory ) ) return [];
	return fs
		.readdirSync( directory, { withFileTypes: true } )
		.flatMap( ( entry ) => {
			if ( [ 'vendor', 'node_modules' ].includes( entry.name ) )
				return [];
			const file = path.join( directory, entry.name );
			return entry.isDirectory()
				? files( file )
				: file.endsWith( '.php' )
					? [ file ]
					: [];
		} );
}

/** Reject statically resolved controller calls in domain code, including aliases/grouped imports. */
export function checkDomainDependencies( root ) {
	const analyses = [ 'includes', 'packages' ].flatMap( ( folder ) =>
		files( path.join( root, folder ) ).map( ( full ) => ( {
			file: path.relative( root, full ).split( path.sep ).join( '/' ),
			...analyzePhp( fs.readFileSync( full, 'utf8' ) ),
		} ) )
	);
	const index = new Map();
	for ( const analysis of analyses ) {
		for ( const entry of analysis.classes )
			index.set( entry.fqcn.toLowerCase(), entry );
	}
	const failures = [];
	for ( const analysis of analyses ) {
		if (
			analysis.classes.some( ( entry ) =>
				isRestController( entry.fqcn, index )
			) ||
			analysis.file.split( '/' ).includes( 'Rest' ) ||
			COMPOSITION.has( analysis.file ) ||
			analysis.file.endsWith( '/Bootstrap.php' )
		)
			continue;
		const seen = new Set();
		for ( const reference of analysis.references ) {
			const key = reference.name.toLowerCase();
			if ( seen.has( key ) ) continue;
			seen.add( key );
			if (
				isRestController( reference.name, index ) ||
				/\\Rest\\.*Controller$/i.test( reference.name )
			) {
				failures.push(
					`${ analysis.file }:${ reference.line } depends on ${ reference.name }; call the owning domain service instead`
				);
			}
		}
	}
	return { failures, allowed: [] };
}

/** Ratchet the ownership boundaries extracted in the modularization pass. */
export function checkStorageOwnership( root ) {
	const rules = [
		[
			'includes/Quiz/GradingPolicy.php',
			/ohmylms_(?:content|chapter)_relationship/g,
			'Learning\\CourseStructure owns course relationships',
		],
		[
			'includes/Student/Directory.php',
			/ohmylms_user_membership/g,
			'Membership\\Members owns membership rows',
		],
		[
			'includes/Hooks/CommonHook.php',
			/\$wpdb\b/g,
			'hook adapters call the owning domain instead of using SQL',
		],
	];
	const failures = [];
	for ( const [ file, pattern, reason ] of rules ) {
		const full = path.join( root, file );
		if ( ! fs.existsSync( full ) ) continue;
		const text = stripPhpComments( fs.readFileSync( full, 'utf8' ) );
		for ( const match of text.matchAll( pattern ) ) {
			failures.push(
				`${ file }:${ text.slice( 0, match.index ).split( '\n' ).length }: ${ reason }`
			);
		}
	}
	return { failures, allowed: [] };
}
