import test from 'node:test';
import assert from 'node:assert/strict';
import {
	isCourseReturn,
	isReturnUrl,
} from '../../assets/src/features/learning/returnLink.mjs';

const origin = 'https://example.test';
const admin = `${ origin }/wp-admin/admin.php`;

test( 'native editors restore return links to the legacy page and the new app', () => {
	for ( const url of [
		`${ admin }?page=ohmylms#/content-hub/lessons`,
		`${ admin }?page=ohmylms#/course-edit/5/curriculum`,
		`${ admin }?page=ohmylms-app#/content-hub/curriculum/syllabus/24?node=s%3A1`,
		`${ admin }?page=ohmylms-app#/courses/5`,
		`${ admin }?page=ohmylms-app#/quizzes`,
		`${ admin }?page=ohmylms-app#/exams`,
		`${ admin }?page=ohmylms-app#/content-hub/question-bank`,
	] ) {
		assert.equal( isReturnUrl( url, origin ), true, url );
	}
} );

test( 'native editors reject foreign, non-LMS and malformed return links', () => {
	for ( const url of [
		`https://evil.test/wp-admin/admin.php?page=ohmylms-app#/courses/5`,
		`${ admin }?page=other#/content-hub/lessons`,
		`${ admin }?page=ohmylms-app#/settings`,
		`${ admin }?page=ohmylms-app#/coursesx`,
		'not a url',
	] ) {
		assert.equal( isReturnUrl( url, origin ), false, url );
	}
} );

test( 'course return links are labelled as courses in both admins', () => {
	assert.equal( isCourseReturn( `${ admin }?page=ohmylms#/course-edit/5` ), true );
	assert.equal( isCourseReturn( `${ admin }?page=ohmylms-app#/courses/5` ), true );
	assert.equal( isCourseReturn( `${ admin }?page=ohmylms-app#/courses` ), false );
	assert.equal(
		isCourseReturn( `${ admin }?page=ohmylms-app#/content-hub/lessons` ),
		false
	);
} );
