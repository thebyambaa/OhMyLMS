import { get, patch, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	getEmail,
	getEmailSettings,
	listEmails,
	previewEmail,
	saveEmail,
	saveEmailSettings,
	sendTestEmail,
	setEmailEnabled,
} from './api';
import { preview, quizGraded, settings } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	patch: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

describe( 'typed emails API', () => {
	it( 'lists emails with only the filters that are set', async () => {
		await listEmails( { search: '  quiz ', recipient_type: 'student' } );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/emails',
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					per_page: 100,
					search: 'quiz',
					recipient_type: 'student',
				},
			}
		);
		await listEmails( { search: ' ', recipient_type: 'all' } );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/emails',
			expect.any( Function ),
			{
				signal: undefined,
				query: {
					per_page: 100,
					search: undefined,
					recipient_type: undefined,
				},
			}
		);
	} );

	it( 'reads and validates one email', async () => {
		vi.mocked( get ).mockResolvedValue( quizGraded );
		expect( await getEmail( 'student_quiz_graded' ) ).toEqual( quizGraded );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/emails/student_quiz_graded',
			{ signal: undefined }
		);
		vi.mocked( get ).mockResolvedValueOnce( {
			...quizGraded,
			enabled: 'yes',
		} );
		await expect( getEmail( 'student_quiz_graded' ) ).rejects.toMatchObject(
			{
				code: 'invalid_response',
			}
		);
		await expect( getEmail( '../settings' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'sends PUT bodies with only the given fields and PATCH for the toggle', async () => {
		vi.mocked( put ).mockResolvedValue( quizGraded );
		await saveEmail( 'student_quiz_graded', { subject: 'New' } );
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/emails/student_quiz_graded',
			{ subject: 'New' },
			expect.any( Object )
		);
		vi.mocked( patch ).mockResolvedValue( {
			...quizGraded,
			enabled: false,
		} );
		const saved = await setEmailEnabled( 'student_quiz_graded', false );
		expect( saved.enabled ).toBe( false );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/emails/student_quiz_graded',
			{ enabled: false },
			expect.any( Object )
		);
	} );

	it( 'posts drafts to the preview and test routes and validates the answers', async () => {
		vi.mocked( post ).mockResolvedValueOnce( preview );
		expect(
			await previewEmail( 'student_quiz_graded', { heading: 'H' } )
		).toEqual( preview );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/emails/student_quiz_graded/preview',
			{ heading: 'H' },
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( {
			sent: true,
			to: 'admin@example.com',
			subject: '[Test] S',
		} );
		const result = await sendTestEmail( 'student_quiz_graded', {} );
		expect( result.to ).toBe( 'admin@example.com' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/emails/student_quiz_graded/test',
			{},
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( { sent: false } );
		await expect(
			sendTestEmail( 'student_quiz_graded', {} )
		).rejects.toMatchObject( { code: 'invalid_response' } );
	} );

	it( 'reads and writes the global settings', async () => {
		vi.mocked( get ).mockResolvedValue( settings );
		expect( await getEmailSettings() ).toEqual( settings );
		expect( get ).toHaveBeenLastCalledWith( '/ohmylms/v1/emails/settings', {
			signal: undefined,
		} );
		vi.mocked( put ).mockResolvedValue( settings );
		await saveEmailSettings( { sender_name: 'N' } );
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/emails/settings',
			{ sender_name: 'N' },
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValueOnce( {
			...settings,
			button_position: 'up',
		} );
		await expect( getEmailSettings() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
} );
