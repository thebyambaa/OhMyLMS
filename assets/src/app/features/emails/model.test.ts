import {
	changedFields,
	editorPath,
	emailId,
	findUnknownTags,
	insertTag,
	isHttpUrl,
	parseEmail,
	parsePreview,
	parseRecipients,
	parseSettings,
	parseTestResult,
	serverFieldError,
	settingsPatch,
	toDraft,
	toInput,
	validateDraft,
	validateSettings,
	validateTags,
} from './model';
import { newOrder, preview, quizGraded, settings } from './fixtures';

describe( 'email boundary parsing', () => {
	it( 'accepts a valid email and rejects wrong shapes', () => {
		expect( parseEmail( quizGraded ) ).toEqual( quizGraded );
		expect( () => parseEmail( { ...quizGraded, enabled: 1 } ) ).toThrow();
		expect( () =>
			parseEmail( { ...quizGraded, recipient_type: 'admin' } )
		).toThrow();
		expect( () => parseEmail( { ...quizGraded, id: 'Bad Id' } ) ).toThrow();
		expect( () =>
			parseEmail( { ...quizGraded, tags: [ null ] } )
		).toThrow();
		expect( () =>
			parseEmail( { ...quizGraded, recipient_email: [ 3 ] } )
		).toThrow();
		expect( () => parseEmail( null ) ).toThrow();
		expect( () => parseEmail( [] ) ).toThrow();
		expect(
			parseEmail( { ...quizGraded, course_suggestion_text: 'More' } )
				.course_suggestion_text
		).toBe( 'More' );
		expect( () =>
			parseEmail( { ...quizGraded, course_suggestion_text: 4 } )
		).toThrow();
	} );

	it( 'validates settings, previews and test results', () => {
		expect( parseSettings( settings ) ).toEqual( settings );
		expect( () =>
			parseSettings( { ...settings, button_position: 'middle' } )
		).toThrow();
		expect( () =>
			parseSettings( { ...settings, base_color: 3 } )
		).toThrow();
		expect( parsePreview( preview ) ).toEqual( preview );
		expect( () =>
			parsePreview( { ...preview, unknown_tags: 'x' } )
		).toThrow();
		expect(
			parseTestResult( { sent: true, to: 'a@example.com', subject: 'S' } )
		).toEqual( { sent: true, to: 'a@example.com', subject: 'S' } );
		expect( () =>
			parseTestResult( {
				sent: false,
				to: 'a@example.com',
				subject: 'S',
			} )
		).toThrow();
	} );

	it( 'only lets safe ids into URLs', () => {
		expect( emailId( 'student_quiz_graded' ) ).toBe(
			'student_quiz_graded'
		);
		for ( const bad of [ '', '../x', 'a/b', 'A', '1a', 'a b', 7, null ] ) {
			expect( () => emailId( bad ) ).toThrow();
		}
		expect( editorPath( 'student_quiz_graded' ) ).toBe(
			'/emails?email=student_quiz_graded'
		);
	} );
} );

describe( 'email draft', () => {
	it( 'round trips through the form and only reports real changes', () => {
		const draft = toDraft( newOrder );
		expect( draft.recipient_email ).toBe( 'owner@example.com' );
		expect( changedFields( draft, newOrder ) ).toEqual( [] );
		expect(
			changedFields(
				{ ...draft, recipient_email: 'owner@example.com,  ' },
				newOrder
			)
		).toEqual( [] );
		expect(
			changedFields(
				{ ...draft, subject: 'Other', heading: 'Else' },
				newOrder
			)
		).toEqual( [ 'subject', 'heading' ] );
		expect(
			changedFields(
				{ ...toDraft( quizGraded ), course_suggestion_text: 'x' },
				quizGraded
			)
		).toEqual( [] );
	} );

	it( 'builds minimal request bodies and trims single-line fields', () => {
		const draft = {
			...toDraft( newOrder ),
			subject: '  New  ',
			recipient_email: 'a@example.com\nb@example.com, c@example.com',
		};
		expect( toInput( draft, [ 'subject', 'recipient_email' ] ) ).toEqual( {
			subject: 'New',
			recipient_email: [
				'a@example.com',
				'b@example.com',
				'c@example.com',
			],
		} );
		expect( parseRecipients( ' a@b.co ;c@d.co  ' ) ).toEqual( [
			'a@b.co',
			'c@d.co',
		] );
	} );

	it( 'validates like the server', () => {
		const valid = toDraft( quizGraded );
		expect( validateDraft( valid, quizGraded ) ).toEqual( {} );
		const errors = validateDraft(
			{
				...valid,
				subject: '  ',
				heading: 'h'.repeat( 201 ),
				additional_content: '<p> </p><script>x</script>',
				button_text: 'b'.repeat( 61 ),
				button_link: 'javascript:alert(1)',
				footer_text: 'f'.repeat( 5001 ),
				recipient_email: 'owner@example.com',
			},
			quizGraded
		);
		expect( Object.keys( errors ).sort() ).toEqual(
			[
				'additional_content',
				'button_link',
				'button_text',
				'footer_text',
				'heading',
				'recipient_email',
				'subject',
			].sort()
		);
		expect(
			validateDraft(
				{
					...valid,
					additional_content: '<img src="https://e.com/a.png">',
				},
				quizGraded
			).additional_content
		).toBeUndefined();
		expect(
			validateDraft(
				{ ...toDraft( newOrder ), recipient_email: 'not-an-email' },
				newOrder
			).recipient_email
		).toBeDefined();
		expect(
			validateDraft(
				{
					...toDraft( newOrder ),
					recipient_email: Array.from(
						{ length: 11 },
						( _, index ) => `r${ index }@example.com`
					).join( ',' ),
				},
				newOrder
			).recipient_email
		).toMatch( /10/ );
		expect(
			validateDraft(
				{ ...valid, button_link: 'https://example.com/a?b=c' },
				quizGraded
			).button_link
		).toBeUndefined();
	} );

	it( 'finds merge tags the email does not support', () => {
		const allowed = quizGraded.tags.map( ( tag ) => tag.tag );
		expect(
			findUnknownTags(
				'{x_y} {student_name} [Important] [big_one] [Student_Name] {x_y} {STUDENT_NAME}',
				allowed
			)
		).toEqual( [ '{x_y}', '[big_one]' ] );
		const errors = validateTags(
			{
				...toDraft( quizGraded ),
				subject: 'About {assignment_name}',
				footer_text: '[secret_value]',
			},
			quizGraded
		);
		expect( Object.keys( errors ) ).toEqual( [ 'subject', 'footer_text' ] );
		expect( errors.subject ).toContain( '{assignment_name}' );
		expect( validateTags( toDraft( quizGraded ), quizGraded ) ).toEqual(
			{}
		);
	} );

	it( 'inserts a tag at the caret or over a selection', () => {
		expect( insertTag( 'Hi !', 3, 3, 'student_name' ) ).toEqual( {
			value: 'Hi {student_name}!',
			caret: 17,
		} );
		expect( insertTag( 'Hi NAME!', 3, 7, 'course_name' ).value ).toBe(
			'Hi {course_name}!'
		);
		expect( insertTag( 'ab', 99, 120, 'x' ).value ).toBe( 'ab{x}' );
		expect( insertTag( 'ab', -4, -9, 'x' ).value ).toBe( '{x}ab' );
	} );
} );

describe( 'global settings', () => {
	it( 'validates colors, addresses and sender text', () => {
		expect( validateSettings( settings ) ).toEqual( {} );
		const errors = validateSettings( {
			...settings,
			base_color: 'purple',
			background_color: '#12345',
			body_background_color: '#GGGGGG',
			body_text_color: '1F2328',
			branding_image: '/logo.png',
			sender_name: 'Evil <x@example.com>',
			sender_email: 'nope',
			footer_text: 'f'.repeat( 1001 ),
		} );
		expect( Object.keys( errors ).sort() ).toEqual(
			[
				'background_color',
				'base_color',
				'body_background_color',
				'body_text_color',
				'branding_image',
				'footer_text',
				'sender_email',
				'sender_name',
			].sort()
		);
		expect(
			validateSettings( { ...settings, sender_name: 'a\nb' } ).sender_name
		).toBeDefined();
		expect(
			validateSettings( { ...settings, sender_email: '' } ).sender_email
		).toBeUndefined();
	} );

	it( 'sends only changed, trimmed fields', () => {
		expect( settingsPatch( settings, settings ) ).toEqual( {} );
		expect(
			settingsPatch(
				{
					...settings,
					sender_name: ' New name ',
					button_position: 'right',
				},
				settings
			)
		).toEqual( { sender_name: 'New name', button_position: 'right' } );
	} );
} );

describe( 'helpers', () => {
	it( 'recognizes http addresses', () => {
		expect( isHttpUrl( 'https://example.com/x' ) ).toBe( true );
		expect( isHttpUrl( 'http://example.com' ) ).toBe( true );
		for ( const bad of [
			'ftp://example.com',
			'javascript:alert(1)',
			'/relative',
			'https://',
			'https://a b.com',
		] ) {
			expect( isHttpUrl( bad ) ).toBe( false );
		}
	} );

	it( 'maps 422 errors that name a form field', () => {
		const fields = [ 'subject', 'heading' ] as const;
		expect(
			serverFieldError(
				{ status: 422, message: 'Bad', details: { field: 'subject' } },
				fields
			)
		).toEqual( { field: 'subject', message: 'Bad' } );
		for ( const error of [
			{ status: 400, message: 'x', details: { field: 'subject' } },
			{ status: 422, message: 'x', details: { field: 'other' } },
			{ status: 422, message: 'x', details: undefined },
		] ) {
			expect( serverFieldError( error, fields ) ).toBeNull();
		}
	} );
} );
