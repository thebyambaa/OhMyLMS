import apiFetch from '@wordpress/api-fetch';
import { del, get, patch, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	applyJob,
	checkRows,
	createExam,
	decideRow,
	deleteJob,
	getExam,
	getJob,
	listExams,
	publishExam,
	saveComposition,
	searchBank,
	startImport,
	updateExam,
	updateRow,
} from './api';
import { exam, job } from './fixtures';
import { formFromExam, toSettingsInput } from './model';

vi.mock( '@wordpress/api-fetch', () => ( { default: vi.fn() } ) );
vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	patch: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const invalid = expect.objectContaining( { code: 'invalid_response' } );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed exams API', () => {
	it( 'lists exams and bank questions with bounded queries', async () => {
		await listExams( 2, { search: 'mock', status: 'draft' } );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/exams/library',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					status: 'draft',
					search: 'mock',
				},
			} )
		);
		await searchBank( 12, '', 1 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/exams/library/12/bank',
			expect.any( Function ),
			expect.objectContaining( { query: { page: 1, per_page: 10 } } )
		);
	} );
	it( 'reads, creates, saves and publishes with the revision', async () => {
		vi.mocked( get ).mockResolvedValue( exam );
		await expect( getExam( 12 ) ).resolves.toEqual( exam );
		await expect( getExam( 13 ) ).rejects.toEqual( invalid );
		vi.mocked( post ).mockResolvedValue( exam );
		await createExam( '  Mock ', 45 );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/exams/library',
			{ title: 'Mock', duration_minutes: 45 },
			expect.anything()
		);
		vi.mocked( put ).mockResolvedValue( exam );
		await saveComposition(
			12,
			[ { kind: 'question', question_id: 5 } ],
			exam.revision
		);
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/exams/library/12/composition',
			{
				revision: exam.revision,
				items: [ { kind: 'question', question_id: 5 } ],
			},
			expect.anything()
		);
		await publishExam( 12, exam.revision, true );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/exams/library/12/publish',
			{ revision: exam.revision, confirm_overlap: true },
			expect.anything()
		);
		vi.mocked( patch ).mockResolvedValue( { ...exam, id: 'x' } );
		await expect(
			updateExam(
				12,
				toSettingsInput( formFromExam( exam ), exam.revision )
			)
		).rejects.toEqual( invalid );
	} );
	it( 'uploads files as multipart and checks rows without saving', async () => {
		vi.mocked( apiFetch ).mockResolvedValue( job );
		const file = new File( [ '%PDF-1.4' ], 'june.pdf', {
			type: 'application/pdf',
		} );
		await startImport( { file, target: 'exam' } );
		const call = vi.mocked( apiFetch ).mock.calls[ 0 ]![ 0 ] as {
			path: string;
			method: string;
			body: FormData;
		};
		expect( call.path ).toBe( '/ohmylms/v1/exam-imports/jobs' );
		expect( call.method ).toBe( 'POST' );
		expect( call.body.get( 'file' ) ).toBeInstanceOf( File );
		expect( call.body.get( 'dry_run' ) ).toBe( '0' );
		vi.mocked( apiFetch ).mockResolvedValue( {
			dry_run: true,
			counts: { create: 1 },
			rows: [],
		} );
		await checkRows( {
			text: 'exam,number',
			target: 'questions',
			format: 'csv',
		} );
		const check = vi.mocked( apiFetch ).mock.calls[ 1 ]![ 0 ] as {
			body: FormData;
		};
		expect( check.body.get( 'dry_run' ) ).toBe( '1' );
		expect( check.body.get( 'text' ) ).toBe( 'exam,number' );
		vi.mocked( apiFetch ).mockRejectedValue( {
			code: 'exam_upload',
			message: 'Choose a PDF within the upload limit.',
			data: { status: 400 },
		} );
		await expect(
			startImport( { file, target: 'exam' } )
		).rejects.toMatchObject( {
			status: 400,
			code: 'exam_upload',
		} );
	} );
	it( 'sends row decisions and apply with the review revision', async () => {
		vi.mocked( get ).mockResolvedValue( job );
		await expect( getJob( 8 ) ).rejects.toEqual( invalid );
		vi.mocked( patch ).mockResolvedValue( job );
		await updateRow( 7, 1, { notes: 'x' }, 3 );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/exam-imports/jobs/7/rows/1',
			{ notes: 'x', revision: 3 },
			expect.anything()
		);
		vi.mocked( post ).mockResolvedValue( job );
		await decideRow( 7, 0, 'reject', 4 );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/exam-imports/jobs/7/rows/0/reject',
			{ revision: 4 },
			expect.anything()
		);
		await applyJob( 7, 5, { title: 'June', duration_minutes: 60 } );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/exam-imports/jobs/7/apply',
			{ title: 'June', duration_minutes: 60, revision: 5 },
			expect.anything()
		);
		await expect( updateRow( 7, -1, {}, 1 ) ).rejects.toEqual( invalid );
		vi.mocked( del ).mockResolvedValue( { deleted: false } );
		await expect( deleteJob( 7 ) ).rejects.toEqual( invalid );
	} );
} );
