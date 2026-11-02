import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	formatPercent,
	getReportSummary,
	listSkills,
	parseQuizAttempt,
	parseSkill,
	parseSubmission,
	parseSummary,
	reportPath,
} from './report';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const summaryBody = {
	student_id: 12,
	courses: { enrolled: 2, completed: 1, in_progress: 1 },
	quizzes: { attempts: 3, in_review: 1, average_percent: 72.5 },
	assignments: { submitted: 2, graded: 1, passed: 1, failed: 0 },
	skills: { total: 4, review_due: 1, struggling: 0 },
};

beforeEach( () => vi.clearAllMocks() );

describe( 'student report parsers', () => {
	it( 'accepts the documented shapes', () => {
		expect( parseSummary( summaryBody ) ).toEqual( summaryBody );
		expect(
			parseQuizAttempt( {
				id: 5,
				quiz_id: 0,
				quiz_title: '',
				course_id: 3,
				course_title: 'Algebra',
				status: 'completed',
				score: 3,
				max_marks: 4,
				percentage: 75,
				started_at: null,
				submitted_at: '2026-10-01T10:20:00',
			} ).quiz_id
		).toBe( 0 );
		expect(
			parseSubmission( {
				id: 1,
				assignment_id: 2,
				assignment_title: 'Essay',
				course_id: 3,
				course_title: 'Algebra',
				status: 'submitted',
				result: null,
				score: null,
				max_score: 80,
				attempts: 1,
				submitted_at: null,
				graded_at: null,
			} ).score
		).toBeNull();
	} );

	it( 'rejects unexpected values', () => {
		expect( () =>
			parseSummary( { ...summaryBody, courses: null } )
		).toThrow();
		expect( () =>
			parseSubmission( {
				id: 1,
				assignment_id: 2,
				assignment_title: 'Essay',
				course_id: 3,
				course_title: 'Algebra',
				status: 'lost',
				result: null,
				score: null,
				max_score: 80,
				attempts: 1,
				submitted_at: null,
				graded_at: null,
			} )
		).toThrow();
		expect( () =>
			parseSkill( {
				skill_id: 1,
				name: 'Fractions',
				level: 'x',
				level_label: 'X',
				review_due: 1,
				struggling: false,
				evidence_count: 1,
				recent_accuracy: 0.5,
				last_evidence_at: null,
			} )
		).toThrow();
	} );
} );

describe( 'student report API', () => {
	it( 'reads the summary and bounded, filtered pages', async () => {
		vi.mocked( get ).mockResolvedValue( summaryBody );
		await expect( getReportSummary( 12 ) ).resolves.toEqual( summaryBody );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/students/12/report', {
			signal: undefined,
		} );
		await listSkills( 12, 2, 'review_due' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/students/12/skills',
			expect.any( Function ),
			{
				signal: undefined,
				query: { page: 2, per_page: 10, filter: 'review_due' },
			}
		);
		await listSkills( 12, 1, 'all' );
		expect( vi.mocked( getCollection ).mock.calls[ 1 ]?.[ 2 ] ).toEqual( {
			signal: undefined,
			query: { page: 1, per_page: 10 },
		} );
	} );

	it( 'refuses invalid ids before requesting', async () => {
		await expect( getReportSummary( 0 ) ).rejects.toThrow();
		expect( get ).not.toHaveBeenCalled();
	} );

	it( 'formats paths and percentages', () => {
		expect( reportPath( 7 ) ).toBe( '/students/7/report' );
		expect( formatPercent( null ) ).toBe( '—' );
		expect( formatPercent( 72.5 ) ).toMatch( /72[.,]5%/ );
	} );
} );
