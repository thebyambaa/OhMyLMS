import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
	checkDomainDependencies,
	checkStorageOwnership,
} from '../../tools/architecture-rules/domain-dependencies.mjs';

function tree( contents ) {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'oml-domains-' ) );
	test.after( () => fs.rmSync( root, { recursive: true, force: true } ) );
	for ( const [ name, text ] of Object.entries( contents ) ) {
		const file = path.join( root, name );
		fs.mkdirSync( path.dirname( file ), { recursive: true } );
		fs.writeFileSync( file, text );
	}
	return root;
}

test( 'domain dependencies reject construction, aliases, grouped imports and static calls', () => {
	const root = tree( {
		'includes/Schools/Service.php': String.raw`<?php
namespace OhMyLMS\Schools;
use OhMyLMS\Rest\V1\{AssignmentController as Grader, AnalyticsController};
class Service {
 public function grade() { return (new Grader())->update(); }
 public function scope() { return AnalyticsController::CAPABILITY; }
 public function direct() { return new \OhMyLMS\Rest\V1\QuizController(); }
}`,
	} );
	const { failures } = checkDomainDependencies( root );
	assert.equal( failures.length, 3 );
	assert.match( failures[ 0 ], /AssignmentController/ );
	assert.match( failures[ 1 ], /AnalyticsController/ );
	assert.match( failures[ 2 ], /QuizController/ );
} );

test( 'controller inheritance is detected outside a Rest directory; bootstrap wiring remains valid', () => {
	const root = tree( {
		'includes/Transport/Report.php': String.raw`<?php
namespace OhMyLMS\Transport;
class Report extends \WP_REST_Controller {}`,
		'includes/Schools/Bad.php': String.raw`<?php
namespace OhMyLMS\Schools;
use OhMyLMS\Transport\Report as Reports;
class Bad { public function run() { return new Reports(); } }`,
		'includes/Schools/Bootstrap.php': String.raw`<?php
namespace OhMyLMS\Schools;
class Bootstrap { public function init() { (new \OhMyLMS\Transport\Report())->register_routes(); } }`,
		'includes/Assignment/Grading.php': String.raw`<?php
namespace OhMyLMS\Assignment;
// new \OhMyLMS\Rest\V1\AssignmentController();
class Grading { public function review() { return 'AnalyticsController::CAPABILITY'; } }`,
	} );
	const { failures } = checkDomainDependencies( root );
	assert.equal( failures.length, 1 );
	assert.match( failures[ 0 ], /Schools\/Bad.php.*Transport\\Report/ );
} );

test( 'plugin domain operations do not call controllers', () => {
	assert.deepEqual(
		checkDomainDependencies( path.resolve( import.meta.dirname, '../..' ) )
			.failures,
		[]
	);
} );

test( 'a controller imported only as an injected parameter is still a forbidden domain dependency', () => {
	const root = tree( {
		'includes/Schools/Service.php': String.raw`<?php
namespace OhMyLMS\Schools;
use OhMyLMS\Rest\V1\AssignmentController as Grader;
class Service { public function run( Grader $grader ) { return $grader->review(); } }`,
	} );
	assert.equal( checkDomainDependencies( root ).failures.length, 1 );
} );

test( 'extracted ownership boundaries reject leaked SQL while ignoring comments', () => {
	const root = tree( {
		'includes/Quiz/GradingPolicy.php':
			'<?php $table = "ohmylms_chapter_relationship";',
		'includes/Student/Directory.php':
			'<?php $table = "ohmylms_user_membership";',
		'includes/Hooks/CommonHook.php':
			'<?php /* $wpdb is forbidden here */ $wpdb->query("SELECT 1");',
	} );
	assert.equal( checkStorageOwnership( root ).failures.length, 3 );
	assert.deepEqual(
		checkStorageOwnership( path.resolve( import.meta.dirname, '../..' ) )
			.failures,
		[]
	);
} );
