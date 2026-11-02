import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	checkControllerPersistence,
	controllerPersistenceBudgetsPath,
	countPersistence,
	measureControllerPersistence,
	stripPhpComments,
} from '../../tools/architecture-rules/controller-persistence.mjs';
import { runArchitectureChecks } from '../../tools/check-architecture.mjs';

const pluginRoot = path.resolve( import.meta.dirname, '../..' );

/** A throwaway plugin root holding the given files (path relative to the root => contents). */
function tree( files ) {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'ohmylms-controller-persistence-' )
	);
	for ( const [ file, contents ] of Object.entries( files ) ) {
		const full = path.join( root, file );
		fs.mkdirSync( path.dirname( full ), { recursive: true } );
		fs.writeFileSync( full, contents );
	}
	test.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	return root;
}

const QUERYING = `<?php
namespace OhMyLMS\\Rest\\V1;

class QueryingController {
	public function get_items() {
		global $wpdb;
		// $wpdb in a comment does not count.
		/* Nor in a block comment: $wpdb->get_var(). */
		$table = $wpdb->prefix . 'ohmylms_x';
		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}
}
`;

const CLEAN = `<?php
namespace OhMyLMS\\Rest\\V1;

use OhMyLMS\\Schools\\Directory;

/** Reads go through Directory, never $wpdb. */
class CleanController {
	public function get_items() {
		return Directory::count_schools( '%', 'all', null );
	}
}
`;

test( 'counts lines of code that use $wpdb, ignoring comments but not strings', () => {
	assert.equal( countPersistence( QUERYING ), 3 );
	assert.equal( countPersistence( CLEAN ), 0 );
	assert.equal(
		countPersistence(
			'<?php\n$sql = "DELETE FROM {$wpdb->options}"; # $wpdb\n$x = \'// $wpdb_other\';\n'
		),
		1
	);
	assert.equal(
		countPersistence( '<?php\r\n$a = $wpdb->get_var();\r\n// $wpdb\r\n' ),
		1
	);
} );

test( 'comment stripping keeps line breaks and string contents', () => {
	const stripped = stripPhpComments(
		"<?php\n/* a\nb */ $x = '/* not a comment */'; // tail\n#[Attribute]\n"
	);
	assert.equal( stripped.split( '\n' ).length, 5 );
	assert.match( stripped, /'\/\* not a comment \*\/'/ );
	assert.doesNotMatch( stripped, /tail/ );
	assert.match( stripped, /#\[Attribute\]/ );
} );

test( 'controllers within budget pass; a new controller defaults to 0', () => {
	const root = tree( {
		'includes/Rest/V1/QueryingController.php': QUERYING,
		'includes/Rest/V1/CleanController.php': CLEAN,
		'includes/Rest/V1/Nested/NewController.php': QUERYING,
		'includes/Schools/Directory.php': QUERYING,
	} );
	assert.deepEqual(
		Object.fromEntries( measureControllerPersistence( root ) ),
		{
			'includes/Rest/V1/CleanController.php': 0,
			'includes/Rest/V1/Nested/NewController.php': 3,
			'includes/Rest/V1/QueryingController.php': 3,
		}
	);
	const { failures, violations } = checkControllerPersistence( root, {
		'includes/Rest/V1/QueryingController.php': 3,
	} );
	assert.deepEqual( violations, [
		{
			file: 'includes/Rest/V1/Nested/NewController.php',
			count: 3,
			budget: 0,
		},
	] );
	assert.deepEqual( failures, [
		"includes/Rest/V1/Nested/NewController.php uses $wpdb on 3 lines (budget 0); move the queries into the feature's domain service or repository",
	] );
} );

const WIDGET = `<?php
namespace OhMyLMS\\Dashboard\\Widgets;

use OhMyLMS\\Abstracts\\RestController;

class WidgetController extends RestController {
	public function get_items() {
		global $wpdb;
		return $wpdb->get_var( 'SELECT 1' );
	}
}
`;

const BASE = `<?php
namespace OhMyLMS\\Abstracts;

abstract class RestController extends \\WP_REST_Controller {}
`;

const SERVICE = `<?php
namespace OhMyLMS\\Dashboard\\Widgets;

final class WidgetService {
	public static function rows() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT 1' );
	}
}
`;

test( 'feature Rest folders and WP_REST_Controller subclasses elsewhere are controllers too', () => {
	const root = tree( {
		'includes/Abstracts/RestController.php': BASE,
		'includes/Engagement/Rest/HelperTrait.php': QUERYING,
		'includes/Integrations/Zoom/Includes/Rest/ZoomController.php': QUERYING,
		'includes/Dashboard/Widgets/WidgetController.php': WIDGET,
		'includes/Dashboard/Widgets/WidgetService.php': SERVICE,
		'includes/Restaurant/Menu.php': SERVICE,
		'packages/e-commerce/includes/Rest/V1/OrdersController.php': WIDGET,
		'packages/e-commerce/includes/Orders/Store.php': SERVICE,
		'includes/Rest/V1/vendor/Library.php': SERVICE,
	} );
	assert.deepEqual(
		Object.fromEntries( measureControllerPersistence( root ) ),
		{
			'includes/Abstracts/RestController.php': 0,
			'includes/Dashboard/Widgets/WidgetController.php': 2,
			'includes/Engagement/Rest/HelperTrait.php': 3,
			'includes/Integrations/Zoom/Includes/Rest/ZoomController.php': 3,
			'packages/e-commerce/includes/Rest/V1/OrdersController.php': 2,
		}
	);
	const { violations } = checkControllerPersistence( root, {
		'includes/Engagement/Rest/HelperTrait.php': 3,
		'includes/Integrations/Zoom/Includes/Rest/ZoomController.php': 3,
		'packages/e-commerce/includes/Rest/V1/OrdersController.php': 2,
	} );
	assert.deepEqual( violations, [
		{
			file: 'includes/Dashboard/Widgets/WidgetController.php',
			count: 2,
			budget: 0,
		},
	] );
} );

test( 'budgets only go down: a lower count or a removed controller fails until the budget is updated', () => {
	const root = tree( {
		'includes/Rest/V1/QueryingController.php': QUERYING,
	} );
	const { failures, violations } = checkControllerPersistence( root, {
		'includes/Rest/V1/QueryingController.php': 5,
		'includes/Rest/V1/GoneController.php': 2,
	} );
	assert.deepEqual( violations, [] );
	assert.deepEqual( failures, [
		`includes/Rest/V1/QueryingController.php uses $wpdb on 3 lines, below its budget of 5; lower it in ${ controllerPersistenceBudgetsPath }`,
		`budget for includes/Rest/V1/GoneController.php has no controller; remove it from ${ controllerPersistenceBudgetsPath }`,
	] );
} );

test( 'budgets are read from tools/controller-persistence-budgets.json and reported by the architecture check', () => {
	const root = tree( {
		'includes/Rest/V1/QueryingController.php': QUERYING,
		[ controllerPersistenceBudgetsPath ]: JSON.stringify( {
			files: { 'includes/Rest/V1/QueryingController.php': 2 },
		} ),
	} );
	assert.deepEqual( checkControllerPersistence( root ).violations, [
		{
			file: 'includes/Rest/V1/QueryingController.php',
			count: 3,
			budget: 2,
		},
	] );
	const result = runArchitectureChecks( root, {
		allowlist: {},
		budgets: { assets: {} },
	} );
	const section = result.sections.find( ( entry ) =>
		/\$wpdb budgets/.test( entry.name )
	);
	assert.equal( section.failures.length, 1 );
	assert.equal( result.ok, false );
	const overridden = runArchitectureChecks( root, {
		allowlist: {},
		budgets: { assets: {} },
		controllerPersistenceBudgets: {
			'includes/Rest/V1/QueryingController.php': 3,
		},
	} ).sections.find( ( entry ) => /\$wpdb budgets/.test( entry.name ) );
	assert.deepEqual( overridden.failures, [] );
} );

test( 'the checked-in budgets match the plugin', () => {
	const budgets = JSON.parse(
		fs.readFileSync(
			path.join( pluginRoot, controllerPersistenceBudgetsPath ),
			'utf8'
		)
	).files;
	assert.ok( Object.values( budgets ).every( ( value ) => value > 0 ) );
	assert.deepEqual( checkControllerPersistence( pluginRoot ).failures, [] );
} );
