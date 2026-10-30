import test from 'node:test';
import assert from 'node:assert/strict';
import { transformSync } from '@babel/core';
import { codeForAssertions } from './sourceAssertions.mjs';
import {
	HUB_ALIASES,
	HUB_APP_ROUTES,
	HUB_EXTENSION_TABS,
	HUB_MENU_ROUTES,
	HUB_TABS,
	contentHubRoutes,
	courseEditPath,
	hashQuery,
	legacyCatalogTarget,
} from '../../assets/src/features/content-hub/hubRoutes.mjs';

test( 'The hub has Courses, Lessons, quiz and exam tabs, Skills and Curriculum under one base path', () => {
	assert.deepEqual(
		HUB_TABS.map( ( tab ) => tab.id ),
		[
			'courses',
			'lessons',
			'quizzes',
			'exams',
			'question-bank',
			'assignments',
			'skills',
			'curriculum',
		]
	);
	assert.deepEqual(
		HUB_TABS.map( ( tab ) => tab.label ),
		[
			'Courses',
			'Lessons',
			'Quizzes',
			'Exams',
			'Question Bank',
			'Assignments',
			'Skills',
			'Curriculum',
		]
	);
	assert.equal(
		HUB_TABS[ 0 ].path,
		'/content-hub/courses',
		'the hub address itself shows the first tab'
	);
	for ( const tab of HUB_TABS )
		assert.ok( tab.path.startsWith( '/content-hub' ), tab.path );
	assert.equal(
		new Set( HUB_TABS.map( ( tab ) => tab.path ) ).size,
		HUB_TABS.length
	);
} );

const allPages = {
	LessonsPage: () => null,
	ExamsPage: () => null,
	QuestionBankPage: () => null,
	SkillsPage: () => null,
	CurriculumPage: () => null,
	BundlesPage: () => null,
};

test( 'Hub routes reuse the application list screens and the SDK pages', () => {
	const Courses = () => null;
	const Quizzes = () => null;
	const Assignments = () => null;
	const seen = [];
	const screen = ( Component, tab ) => {
		seen.push( [ tab, Component ] );
		return { tab, Component };
	};
	const routes = contentHubRoutes(
		[
			{ path: '/courses', element: Courses },
			{ path: '/quizzes', element: Quizzes },
			{ path: '/assignments', element: Assignments },
			{ path: '/certificates', element: () => null },
		],
		allPages,
		screen
	);
	assert.deepEqual(
		routes.map( ( route ) => route.path ),
		[
			'/content-hub',
			'/content-hub/courses',
			'/content-hub/lessons',
			'/content-hub/quizzes',
			'/content-hub/exams',
			'/content-hub/question-bank',
			'/content-hub/assignments',
			'/content-hub/skills',
			'/content-hub/curriculum',
			'/content-hub/bundles',
			'/extensions/bundles',
			'/assessments',
			'/assessments/question-bank',
			'/assessments/assignments',
		]
	);
	const byTab = Object.fromEntries(
		seen.map( ( [ tab, Component ] ) => [ tab, Component ] )
	);
	assert.equal(
		byTab.courses,
		Courses,
		'the existing course list becomes the Courses tab'
	);
	assert.equal(
		byTab.quizzes,
		Quizzes,
		'the existing quiz list becomes the Quizzes tab'
	);
	assert.equal(
		byTab.assignments,
		Assignments,
		'the existing assignment list becomes the Assignments tab'
	);
	assert.equal( byTab.lessons, allPages.LessonsPage );
	assert.equal( byTab.exams, allPages.ExamsPage );
	assert.equal( byTab[ 'question-bank' ], allPages.QuestionBankPage );
	assert.equal( byTab.skills, allPages.SkillsPage );
	assert.equal( byTab.curriculum, allPages.CurriculumPage );
	assert.equal( byTab.bundles, undefined );
	assert.deepEqual(
		Object.keys( byTab ).sort(),
		HUB_TABS.map( ( tab ) => tab.id ).sort(),
		'every tab has a route'
	);
} );

test( 'The old Assessments addresses open the Quizzes, Question Bank and Assignments tabs', () => {
	const routes = contentHubRoutes(
		[
			{ path: '/quizzes', element: 'quizzes screen' },
			{ path: '/assignments', element: 'assignments screen' },
		],
		allPages,
		( Component, tab ) => ( { tab, Component } )
	);
	const at = ( path ) =>
		routes.find( ( route ) => route.path === path ).element;
	assert.equal( at( '/assessments' ), at( '/content-hub/quizzes' ) );
	assert.equal(
		at( '/assessments/question-bank' ),
		at( '/content-hub/question-bank' )
	);
	assert.equal(
		at( '/assessments/assignments' ),
		at( '/content-hub/assignments' )
	);
	assert.deepEqual( HUB_ALIASES, {
		'/content-hub/bundles': 'curriculum',
		'/extensions/bundles': 'curriculum',
		'/assessments': 'quizzes',
		'/assessments/question-bank': 'question-bank',
		'/assessments/assignments': 'assignments',
	} );
} );

test( 'Without the application list screens the hub still serves its own tabs', () => {
	const routes = contentHubRoutes( [], allPages, ( c, t ) => t );
	assert.deepEqual(
		routes.map( ( route ) => route.path ),
		[
			'/content-hub',
			'/content-hub/lessons',
			'/content-hub/exams',
			'/content-hub/question-bank',
			'/content-hub/skills',
			'/content-hub/curriculum',
			'/content-hub/bundles',
			'/extensions/bundles',
			'/assessments/question-bank',
		]
	);
	assert.deepEqual( HUB_APP_ROUTES, {
		'/courses': 'courses',
		'/quizzes': 'quizzes',
		'/assignments': 'assignments',
	} );
} );

test( 'Course addresses, the retired Catalog addresses and hash queries', () => {
	assert.equal( courseEditPath( 42 ), '/course-edit/42' );
	assert.equal( courseEditPath( '7' ), '/course-edit/7' );
	assert.equal(
		legacyCatalogTarget( '#/content-hub/catalog/42' ),
		'/course-edit/42'
	);
	assert.equal(
		legacyCatalogTarget( '#/content-hub/catalog/42?x=1' ),
		'/course-edit/42'
	);
	assert.equal(
		legacyCatalogTarget( '#/content-hub/catalog' ),
		'/content-hub/courses'
	);
	assert.equal( legacyCatalogTarget( undefined ), '/content-hub/courses' );
	assert.equal(
		hashQuery( '#/content-hub/skills?add=123' ).get( 'add' ),
		'123'
	);
	assert.equal( hashQuery( '#/content-hub/skills' ).get( 'add' ), null );
	assert.equal( hashQuery( undefined ).get( 'add' ), null );
} );

test( 'Editors opened from the hub keep its menu entry highlighted', () => {
	assert.ok(
		HUB_MENU_ROUTES.includes( '/course-edit/:id/:step?/:subStep?' )
	);
	assert.ok( HUB_MENU_ROUTES.includes( '/lesson-edit/:id' ) );
	for ( const path of [
		'/quiz-edit/:id',
		'/quiz-report/:id',
		'/quiz-report/:id/grade-quiz/:quizId',
		'/assignment-edit/:id',
		'/assignment-report/:id',
		'/assignment-report/:id/grade-assignment/:assignmentId',
	] )
		assert.ok( HUB_MENU_ROUTES.includes( path ), path );
	for ( const path of Object.keys( HUB_APP_ROUTES ) )
		assert.ok(
			! HUB_MENU_ROUTES.includes( path ),
			`${ path } is a hub tab, not a bare route`
		);
} );

test( 'The hub address shows the Courses tab, and without course screens the first tab there is', () => {
	const screen = ( Component, tab ) => ( { tab, Component } );
	const withCourses = contentHubRoutes(
		[ { path: '/courses', element: 'courses screen' } ],
		allPages,
		screen
	);
	const at = ( routes, path ) =>
		routes.find( ( route ) => route.path === path ).element;
	assert.equal(
		at( withCourses, '/content-hub' ),
		at( withCourses, '/content-hub/courses' )
	);
	const without = contentHubRoutes( [], allPages, screen );
	assert.equal(
		at( without, '/content-hub' ),
		at( without, '/content-hub/lessons' )
	);
	assert.equal(
		without.filter( ( route ) => route.path === '/content-hub' ).length,
		1
	);
} );

test( 'The retired Catalog addresses are redirected rather than left without a page', () => {
	const made = [];
	const redirect = ( resolve ) => {
		const element = { redirectTo: resolve };
		made.push( element );
		return element;
	};
	const routes = contentHubRoutes(
		[],
		allPages,
		( Component, tab ) => tab,
		redirect
	);
	const retired = routes.filter( ( route ) =>
		route.path.startsWith( '/content-hub/catalog' )
	);
	assert.deepEqual(
		retired.map( ( route ) => route.path ),
		[ '/content-hub/catalog', '/content-hub/catalog/:courseId' ]
	);
	for ( const route of retired )
		assert.equal( route.element.redirectTo, legacyCatalogTarget );
	assert.equal(
		contentHubRoutes( [], allPages, ( Component, tab ) => tab ).some(
			( route ) => route.path.startsWith( '/content-hub/catalog' )
		),
		false,
		'no redirect is made unless one is supplied'
	);
} );

