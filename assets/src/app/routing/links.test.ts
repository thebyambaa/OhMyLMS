import { isCourseEditorStep } from './links';

describe( 'isCourseEditorStep', () => {
	it( 'accepts the three steps of the same course', () => {
		for ( const path of [
			'/courses/7',
			'/courses/7/outline',
			'/courses/7/settings',
			'/courses/7/outline?focus=lesson:5',
		] ) {
			expect( isCourseEditorStep( 7, path ), path ).toBe( true );
		}
	} );

	it( 'rejects other courses, other screens and the list', () => {
		for ( const path of [
			'/courses',
			'/courses/70',
			'/courses/8/outline',
			'/courses/7/publish',
			'/courses/7/outline/extra',
			'/quizzes/7',
			'/content-hub/courses/7',
		] ) {
			expect( isCourseEditorStep( 7, path ), path ).toBe( false );
		}
	} );
} );
