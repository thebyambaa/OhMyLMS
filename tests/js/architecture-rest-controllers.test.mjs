import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	analyzePhp,
	checkRestControllers,
	stripPhp,
} from '../../tools/architecture-rules/rest-controllers.mjs';
import { runArchitectureChecks } from '../../tools/check-architecture.mjs';

const pluginRoot = path.resolve( import.meta.dirname, '../..' );

/** A throwaway plugin root holding the given files (path relative to the root => contents). */
function tree( files ) {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-rest-controllers-' )
	);
	for ( const [ file, contents ] of Object.entries( files ) ) {
		const full = path.join( root, file );
		fs.mkdirSync( path.dirname( full ), { recursive: true } );
		fs.writeFileSync( full, contents );
	}
	test.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	return root;
}

const ROUTE = `register_rest_route( 'ohmylms/v1', '/x', array( 'methods' => 'GET', 'callback' => '__return_null', 'permission_callback' => '__return_true' ) );`;

const BASE = `<?php
namespace OhMyLMS\\Abstracts;

use WP_REST_Controller;

abstract class RestController extends WP_REST_Controller {
	protected $namespace = 'ohmylms/v1';
}
`;

const files = ( result ) => result.violations.map( ( entry ) => entry.file );

test( 'controllers that reach WP_REST_Controller pass, directly or through plugin classes', () => {
	const root = tree( {
		'includes/Abstracts/RestController.php': BASE,
		'includes/Rest/V1/DirectController.php': `<?php
namespace OhMyLMS\\Rest\\V1;
class DirectController extends \\WP_REST_Controller {
	public function register_routes() { ${ ROUTE } }
}`,
		'includes/Rest/V1/BaseController.php': `<?php
namespace OhMyLMS\\Rest\\V1;
use OhMyLMS\\Abstracts\\RestController;
final class BaseController extends RestController {
	public function register_routes() { ${ ROUTE } }
}`,
		'includes/Engagement/Rest/GamificationController.php': `<?php
namespace OhMyLMS\\Engagement\\Rest;
use OhMyLMS\\Abstracts\\{RestController as Base, Other};
abstract class GamificationController extends Base {}`,
		'includes/Engagement/Rest/LevelsController.php': `<?php
namespace OhMyLMS\\Engagement\\Rest;
class LevelsController extends GamificationController {
	public function register_routes() { \\${ ROUTE } }
}`,
		'includes/Media/Rest/PostsController.php': `<?php
namespace OhMyLMS\\Media\\Rest;
class PostsController extends \\WP_REST_Posts_Controller {
	public function register_routes() { ${ ROUTE } }
}`,
	} );
	const result = checkRestControllers( root, [] );
	assert.deepEqual( result.failures, [] );
	assert.deepEqual( result.violations, [] );
} );

test( 'routes registered by hooks, blocks, services or file-level code fail', () => {
	const root = tree( {
		'includes/Abstracts/RestController.php': BASE,
		'includes/Blocks/PlayerBlock.php': `<?php
namespace OhMyLMS\\Blocks;
final class PlayerBlock {
	public static function init() {
		add_action( 'rest_api_init', static function () { ${ ROUTE } } );
	}
}`,
		'includes/Gateways/Gateway.php': `<?php
namespace OhMyLMS\\Gateways;
use OhMyLMS\\Abstracts\\PaymentGateway;
class Gateway extends PaymentGateway {
	public function routes() { ${ ROUTE } }
}`,
		'includes/Rest/V1/Unimported.php': `<?php
namespace OhMyLMS\\Rest\\V1;
class Unimported extends WP_REST_Controller {
	public function register_routes() { ${ ROUTE } }
}`,
		'includes/bootstrap.php': `<?php
add_action( 'rest_api_init', function () { ${ ROUTE } } );`,
	} );
	const result = checkRestControllers( root, [] );
	assert.deepEqual( files( result ), [
		'includes/Blocks/PlayerBlock.php',
		'includes/Gateways/Gateway.php',
		'includes/Rest/V1/Unimported.php',
		'includes/bootstrap.php',
	] );
	assert.equal( result.failures.length, 4 );
	assert.match(
		result.failures[ 0 ],
		/^includes\/Blocks\/PlayerBlock\.php:5 calls register_rest_route\(\) in OhMyLMS\\Blocks\\PlayerBlock \(no parent\); register routes in a WP_REST_Controller subclass/
	);
	assert.match(
		result.failures[ 1 ],
		/OhMyLMS\\Gateways\\Gateway \(OhMyLMS\\Abstracts\\PaymentGateway\)/
	);
	// Without `use WP_REST_Controller;` the name resolves inside the namespace, as in PHP.
	assert.match(
		result.failures[ 2 ],
		/OhMyLMS\\Rest\\V1\\Unimported \(OhMyLMS\\Rest\\V1\\WP_REST_Controller\)/
	);
	assert.match( result.failures[ 3 ], /code outside a class/ );
} );

test( 'comments, strings, heredocs, stubs and method calls are not registrations', () => {
	const root = tree( {
		'includes/Docs/Notes.php': `<?php
namespace OhMyLMS\\Docs;
/** Routes used to call register_rest_route( 'ohmylms/v1', ... ) here. */
final class Notes {
	// register_rest_route( 'old' );
	# register_rest_route( 'old' );
	public $text = 'register_rest_route( "x" )';
	public function help() {
		$this->server->register_rest_route( 'x' );
		Other::register_rest_route( 'x' );
		return <<<TEXT
register_rest_route( 'heredoc' );
TEXT;
	}
}
function register_rest_route( $namespace, $route, $args ) {}`,
	} );
	assert.deepEqual( checkRestControllers( root, [] ).violations, [] );
} );

test( 'inline HTML and strings with braces do not move a call into the wrong class', () => {
	const code = `<?php
namespace OhMyLMS\\Mixed;
class Controller extends \\WP_REST_Controller {
	public function page() {
		?><style>.a { color: red; } }</style><?php
		echo "{$this->x} }";
	}
	public function register_routes() { ${ ROUTE } }
}
class Page {
	public function routes() { ${ ROUTE } }
}`;
	const analysis = analyzePhp( code );
	assert.deepEqual(
		analysis.calls.map( ( call ) => [ call.line, call.owner?.name ] ),
		[
			[ 8, 'Controller' ],
			[ 11, 'Page' ],
		]
	);
	assert.equal( stripPhp( code ).length, code.length );
	assert.equal(
		stripPhp( code ).split( '\n' ).length,
		code.split( '\n' ).length
	);
	const root = tree( { 'includes/Mixed/Both.php': code } );
	const [ violation ] = checkRestControllers( root, [] ).violations;
	assert.deepEqual( violation.lines, [ 11 ] );
	assert.deepEqual( violation.owners, [
		'OhMyLMS\\Mixed\\Page (no parent)',
	] );
} );

test( 'packages are scanned; vendor folders are not', () => {
	const root = tree( {
		'packages/shop/includes/Gateway.php': `<?php
namespace Shop;
class Gateway { public function routes() { ${ ROUTE } } }`,
		'packages/shop/vendor/lib/Thing.php': `<?php
class Thing { public function routes() { ${ ROUTE } } }`,
		'includes/vendor/Other.php': `<?php
class Other { public function routes() { ${ ROUTE } } }`,
	} );
	assert.deepEqual( files( checkRestControllers( root, [] ) ), [
		'packages/shop/includes/Gateway.php',
	] );
} );

test( 'allow-list entries need a reason, match by file and fail when stale', () => {
	const root = tree( {
		'includes/Hooks/Hooks.php': `<?php
final class Hooks { public static function routes() { ${ ROUTE } } }`,
		'includes/Hooks/More.php': `<?php
final class More { public static function routes() { ${ ROUTE } } }`,
	} );
	const result = checkRestControllers( root, [
		{ file: 'includes/Hooks/Hooks.php', reason: 'Moves next.' },
		{ file: 'includes/Hooks/More.php' },
		{ file: 'includes/Hooks/Gone.php', reason: 'Already moved.' },
	] );
	assert.deepEqual(
		result.allowed.map( ( entry ) => [ entry.file, entry.reason ] ),
		[
			[ 'includes/Hooks/Hooks.php', 'Moves next.' ],
			[ 'includes/Hooks/More.php', undefined ],
		]
	);
	assert.deepEqual( result.failures, [
		'tools/architecture-allowlist.json restControllers: entry {"file":"includes/Hooks/More.php"} has no reason',
		'stale allow-list entry (no longer violated, remove it): restControllers includes/Hooks/Gone.php',
	] );
} );

test( 'runArchitectureChecks reports the rule from allowlist.restControllers', () => {
	const root = tree( {
		'includes/Hooks/Hooks.php': `<?php
final class Hooks { public static function routes() { ${ ROUTE } } }`,
	} );
	const section = ( allowlist ) =>
		runArchitectureChecks( root, {
			allowlist,
			budgets: { assets: {} },
		} ).sections.find( ( entry ) =>
			entry.name.startsWith( 'REST routes are registered' )
		);
	assert.equal( section( {} ).failures.length, 1 );
	const allowed = section( {
		restControllers: [
			{ file: 'includes/Hooks/Hooks.php', reason: 'Moves next.' },
		],
	} );
	assert.deepEqual( allowed.failures, [] );
	assert.equal( allowed.allowed.length, 1 );
} );

test( 'the assessment, exam, AI, MCP and block routes are registered by controllers', () => {
	const moved = [
		'includes/AI/AdminPage.php',
		'includes/Assessment/QuestionPreview.php',
		'includes/Assessment/ResponseMedia.php',
		'includes/Blocks/QuestionPlayerBlock.php',
		'includes/Blocks/QuizPlayerBlock.php',
		'includes/ExamImport/Figures.php',
		'includes/ExamImport/Jobs.php',
		'includes/ExamImport/Reconstruction.php',
		'includes/ExamImport/Workflow.php',
		'includes/Exams/Composer.php',
		'includes/Exams/Library.php',
		'includes/MCP/Server.php',
		'includes/Practice/Dashboard.php',
		'includes/QuestionBank/Organisation.php',
	];
	for ( const file of moved ) {
		const text = fs.readFileSync( path.join( pluginRoot, file ), 'utf8' );
		assert.deepEqual( analyzePhp( text ).calls, [], file );
	}
	const api = fs.readFileSync(
		path.join( pluginRoot, 'includes/Rest/Api.php' ),
		'utf8'
	);
	for ( const controller of [
		'AI\\Rest\\SettingsController',
		'Assessment\\Rest\\QuestionPreviewController',
		'Assessment\\Rest\\ResponseMediaController',
		'Blocks\\Rest\\QuestionPlayerController',
		'Blocks\\Rest\\QuizPlayerController',
		'ExamImport\\Rest\\FiguresController',
		'ExamImport\\Rest\\JobsController',
		'ExamImport\\Rest\\WorkflowController',
		'Exams\\Rest\\ComposerController',
		'Exams\\Rest\\LibraryController',
		'MCP\\Rest\\ServerController',
		'Practice\\Rest\\DashboardController',
		'QuestionBank\\Rest\\OrganisationController',
	] )
		assert.ok(
			api.includes( `\\OhMyLMS\\${ controller }::class` ),
			`${ controller } is listed in includes/Rest/Api.php`
		);
} );
