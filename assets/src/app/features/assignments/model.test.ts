import {
	assignment,
	graded,
	pending,
	pendingDetail,
	summary,
	thread,
} from './fixtures';
import {
	formFromAssignment,
	formatSize,
	isDirty,
	isWebUrl,
	parseAssignment,
	parseAssignmentDetail,
	parseComment,
	parsePrerequisiteOptions,
	parseScore,
	parseSubmission,
	parseSubmissionDetail,
	parseThread,
	serverFieldError,
	submissionLabel,
	toInput,
	validateDelivery,
	validateForm,
} from './model';
import { ApiError } from '../../lib/api';

const invalid = expect.objectContaining( { code: 'invalid_response' } );

describe( 'assignment parsers', () => {
	it( 'accept the contract and reject drift', () => {
		expect( parseAssignment( summary ) ).toEqual( summary );
		expect( parseAssignmentDetail( assignment ) ).toEqual( assignment );
		expect( () => parseAssignment( { ...summary, id: 0 } ) ).toThrow(
			invalid
		);
		expect( () =>
			parseAssignment( { ...summary, status: 'pending' } )
		).toThrow( invalid );
		expect( () =>
			parseAssignment( { ...summary, time_limit_unit: 'year' } )
		).toThrow( invalid );
		expect( () =>
			parseAssignment( { ...summary, allow_file_uploads: '1' } )
		).toThrow( invalid );
		expect( () =>
			parseAssignmentDetail( { ...assignment, placements: {} } )
		).toThrow( invalid );
		expect( () =>
			parseAssignmentDetail( {
				...assignment,
				placements: [ { ...assignment.placements[ 0 ], source: 'x' } ],
			} )
		).toThrow( invalid );
	} );
	it( 'accept submissions and reject inconsistent or unsafe ones', () => {
		expect( parseSubmission( pending ) ).toEqual( pending );
		expect( parseSubmissionDetail( graded ) ).toEqual( graded );
		expect( () => parseSubmission( { ...pending, score: 10 } ) ).toThrow(
			invalid
		);
		expect( () =>
			parseSubmission( { ...pending, status: 'graded' } )
		).toThrow( invalid );
		expect( () =>
			parseSubmission( { ...pending, result: 'excellent' } )
		).toThrow( invalid );
		expect( () =>
			parseSubmissionDetail( {
				...pendingDetail,
				attachments: [
					{
						...pendingDetail.attachments[ 0 ],
						url: 'javascript:alert(1)',
					},
				],
			} )
		).toThrow( invalid );
	} );
} );

describe( 'assignment form', () => {
	const form = formFromAssignment( assignment );
	it( 'round-trips the editable fields with the stale-save base', () => {
		expect( form.max_score ).toBe( '50' );
		expect( isDirty( form, form ) ).toBe( false );
		expect( isDirty( { ...form, title: 'Changed' }, form ) ).toBe( true );
		expect( toInput( form, assignment.updated_at ) ).toEqual( {
			title: 'Essay on rivers',
			instructions: '<p>Write 500 words.</p>',
			status: 'publish',
			max_score: 50,
			pass_score: 25,
			time_limit_enabled: false,
			time_limit: 10,
			time_limit_unit: 'week',
			allow_file_uploads: true,
			max_files: 5,
			file_size_limit_enabled: true,
			max_file_size_mb: 5,
			resources: [
				{
					type: 'file',
					attachment_id: 77,
					name: 'Brief',
					url: 'https://example.test/wp-content/uploads/brief.pdf',
					size: '20 KB',
				},
			],
			prerequisites: { enable: true, ids: [ 41 ] },
			drip: {
				enable: false,
				type: 'enrollment-from-x-days',
				days: 0,
				date: '',
				time: '',
			},
			comments_enabled: true,
			base_modified: '2026-10-30T09:20:00Z',
		} );
		expect(
			isDirty(
				{
					...form,
					prerequisites: { ...form.prerequisites, items: [] },
				},
				form
			)
		).toBe( true );
	} );
	it( 'validates delivery settings with the server rules', () => {
		const link = {
			type: 'link' as const,
			attachment_id: 0,
			name: 'Atlas',
			url: 'https://example.org',
			size: '',
		};
		expect( validateDelivery( { ...form, resources: [ link ] } ) ).toEqual(
			{}
		);
		expect(
			validateDelivery( {
				...form,
				resources: [ { ...link, url: 'ftp://example.org' } ],
			} ).resources
		).toMatch( /full address/ );
		expect(
			validateDelivery( {
				...form,
				resources: [ { ...link, name: ' ' } ],
			} ).resources
		).toMatch( /name/ );
		expect(
			validateDelivery( {
				...form,
				resources: Array.from( { length: 21 }, () => link ),
			} ).resources
		).toMatch( /at most 20/ );
		const release = { ...form.drip, enable: true };
		expect(
			validateDelivery( {
				...form,
				drip: { ...release, type: 'specific-date' },
			} ).drip
		).toMatch( /date/ );
		expect(
			validateDelivery( {
				...form,
				drip: { ...release, type: 'specific-date', date: '2026-02-30' },
			} ).drip
		).toBe( 'Enter a real date.' );
		expect(
			validateDelivery( {
				...form,
				drip: {
					...release,
					type: 'specific-date',
					date: '2026-02-28',
					time: '24:00',
				},
			} ).drip
		).toMatch( /time/ );
		expect(
			validateDelivery( { ...form, drip: { ...release, days: 3651 } } )
				.drip
		).toMatch( /0 to 3650/ );
		expect( isWebUrl( 'javascript:alert(1)' ) ).toBe( false );
		expect( isWebUrl( 'https://example.org/a b' ) ).toBe( true );
	} );
	it( 'parses threads and prerequisite choices strictly', () => {
		expect( parseThread( thread ) ).toEqual( thread );
		expect( () => parseThread( { ...thread, role: 'guest' } ) ).toThrow(
			invalid
		);
		expect( () =>
			parseComment( { ...thread.items[ 0 ], body: 5 } )
		).toThrow( invalid );
		expect(
			parsePrerequisiteOptions( {
				items: [
					{ id: 4, title: 'Quiz', type: 'quiz', status: 'draft' },
				],
				total: 1,
				pages: 1,
				scope: 'library',
			} ).scope
		).toBe( 'library' );
		expect( () =>
			parseAssignmentDetail( { ...assignment, drip: { enable: true } } )
		).toThrow( invalid );
	} );
	it( 'validates with the server limits', () => {
		expect( validateForm( form ) ).toEqual( {} );
		expect(
			Object.keys(
				validateForm( {
					...form,
					title: '  ',
					max_score: '0',
					max_files: '21',
					max_file_size_mb: 'big',
					time_limit: '366',
				} )
			).sort()
		).toEqual( [
			'max_file_size_mb',
			'max_files',
			'max_score',
			'time_limit',
			'title',
		] );
		expect(
			validateForm( { ...form, max_score: '10', pass_score: '11' } )
				.pass_score
		).toMatch( /cannot be higher/ );
		expect(
			validateForm( { ...form, pass_score: '2.5' } ).pass_score
		).toBe( 'Enter a whole number from 0 to 1000.' );
	} );
	it( 'maps server field errors', () => {
		expect(
			serverFieldError(
				new ApiError( {
					status: 422,
					code: 'ohmylms_assignment_invalid_pass_score',
					message: 'Too high.',
				} )
			)
		).toEqual( { field: 'pass_score', message: 'Too high.' } );
		expect(
			serverFieldError(
				new ApiError( { status: 409, code: 'x', message: 'y' } )
			)
		).toBeNull();
	} );
} );

describe( 'grading helpers', () => {
	it( 'parses scores within 0..max', () => {
		expect( parseScore( '40', 50 ) ).toEqual( { score: 40 } );
		expect( parseScore( '0', 50 ) ).toEqual( { score: 0 } );
		expect( parseScore( '51', 50 ) ).toHaveProperty( 'error' );
		expect( parseScore( '-1', 50 ) ).toHaveProperty( 'error' );
		expect( parseScore( '4.5', 50 ) ).toHaveProperty( 'error' );
		expect( parseScore( '', 50 ) ).toHaveProperty( 'error' );
	} );
	it( 'labels and sizes', () => {
		expect( submissionLabel( pending ) ).toBe( 'Needs grading' );
		expect( submissionLabel( graded ) ).toBe( 'Passed' );
		expect( submissionLabel( { ...graded, result: 'failed' } ) ).toBe(
			'Failed'
		);
		expect( formatSize( 512 ) ).toBe( '512 B' );
		expect( formatSize( 20480 ) ).toBe( '20 KB' );
		expect( formatSize( 3145728 ) ).toBe( '3.0 MB' );
	} );
} );
