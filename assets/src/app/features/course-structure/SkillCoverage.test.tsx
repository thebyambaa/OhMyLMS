import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './coverage-api';
import type { CoverageSkill, SkillCoverage as Report } from './coverage-api';
import { SkillCoverage } from './SkillCoverage';

vi.mock( './coverage-api', () => ( { getSkillCoverage: vi.fn() } ) );

const notice = { selector: '.components-notice__content' };

const skill = ( over: Partial< CoverageSkill > ): CoverageSkill => ( {
	id: 1,
	name: 'Skill',
	code: '',
	outcome: true,
	required: false,
	target: 'proficient',
	objective: false,
	practice: false,
	teaching: [],
	assessing: [],
	questions: 0,
	questionsNeed: 3,
	families: 0,
	familiesNeed: 2,
	needBasis: 'target',
	teachingOk: false,
	questionsOk: false,
	status: 'needs-both',
	...over,
} );

const lesson = {
	id: 9,
	type: 'lesson',
	title: 'Halves',
	unitId: 3,
	unit: 'Fractions',
};

const covered = skill( {
	id: 1,
	name: 'Add halves',
	code: 'F.1',
	required: true,
	target: 'mastered',
	teaching: [ lesson ],
	questions: 5,
	questionsNeed: 5,
	families: 3,
	familiesNeed: 3,
	teachingOk: true,
	questionsOk: true,
	status: 'covered',
} );
const noQuestions = skill( {
	id: 2,
	name: 'Compare tenths',
	teaching: [ { ...lesson, id: 10, title: 'Tenths' } ],
	teachingOk: true,
	status: 'needs-questions',
	needBasis: 'default',
	outcome: false,
	target: '',
} );
const noLesson = skill( {
	id: 3,
	name: 'Round',
	required: true,
	assessing: [
		{
			id: 20,
			type: 'quiz',
			title: 'Rounding quiz',
			unitId: 3,
			unit: 'Fractions',
		},
	],
	questions: 3,
	families: 2,
	questionsOk: true,
	status: 'needs-teaching',
} );
const nothing = skill( {
	id: 4,
	name: 'Estimate',
	objective: true,
	outcome: false,
	target: '',
} );

const summary = ( over = {} ) => ( {
	skills: 4,
	required: 2,
	covered: 1,
	needsTeaching: 1,
	needsQuestions: 1,
	needsBoth: 1,
	objectives: 1,
	objectivesTaught: 0,
	requiredNotReady: 1,
	...over,
} );

const report = ( skills: CoverageSkill[], over = {} ): Report => ( {
	courseId: 7,
	summary: summary( over ),
	skills,
} );

function renderView( unsaved = false ) {
	return render(
		<MemoryRouter>
			<SkillCoverage course={ 7 } unsaved={ unsaved } />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getSkillCoverage ).mockResolvedValue(
		report( [ nothing, noLesson, noQuestions, covered ] )
	);
} );

describe( 'SkillCoverage', () => {
	it( 'loads the report for the course and lists every skill', async () => {
		renderView();
		const table = await screen.findByRole( 'table' );
		expect( api.getSkillCoverage ).toHaveBeenCalledWith(
			7,
			expect.any( AbortSignal )
		);
		for ( const name of [
			'Add halves',
			'Compare tenths',
			'Round',
			'Estimate',
		] ) {
			expect( within( table ).getByText( name ) ).toBeInTheDocument();
		}
		expect( within( table ).getByText( 'F.1' ) ).toBeInTheDocument();
	} );

	it( 'shows how many skills are in each state', async () => {
		renderView();
		const stats = await screen.findByRole( 'list', {
			name: 'Coverage summary',
		} );
		for ( const label of [
			'Covered',
			'Needs a lesson',
			'Needs questions',
			'Needs both',
		] ) {
			expect(
				within( stats ).getByText( label ).previousSibling
			).toHaveTextContent( '1' );
		}
	} );

	it( 'says where a skill is taught, links the lesson and shows its unit', async () => {
		renderView();
		const row = ( await screen.findByText( 'Add halves' ) ).closest(
			'tr'
		)!;
		expect(
			within( row ).getByRole( 'link', { name: 'Halves' } )
		).toHaveAttribute( 'href', '/content-hub/lessons/9' );
		expect( within( row ).getByText( 'in Fractions' ) ).toBeInTheDocument();
		expect(
			within( row ).getByText( 'Required · Mastered' )
		).toBeInTheDocument();
		expect( within( row ).getByText( 'Covered' ) ).toBeInTheDocument();
	} );

	it( 'says plainly when no lesson teaches a skill, and lists the quiz it is tagged on', async () => {
		renderView();
		const row = ( await screen.findByText( 'Round' ) ).closest( 'tr' )!;
		expect( within( row ).getByText( 'No lesson' ) ).toBeInTheDocument();
		expect(
			within( row ).getByRole( 'link', { name: 'Rounding quiz' } )
		).toHaveAttribute( 'href', '/quizzes/20' );
		expect(
			within( row ).getByText( 'Needs a lesson' )
		).toBeInTheDocument();
	} );

	it( 'shows the question numbers against what is needed, and what they are measured against', async () => {
		renderView();
		const full = ( await screen.findByText( 'Add halves' ) ).closest(
			'tr'
		)!;
		expect(
			within( full ).getByText( '5 of 5 questions, 3 of 3 families' )
		).toBeInTheDocument();
		expect(
			within( full ).queryByText( 'Measured against Proficient' )
		).not.toBeInTheDocument();
		const loose = screen.getByText( 'Compare tenths' ).closest( 'tr' )!;
		expect(
			within( loose ).getByText( '0 of 3 questions, 0 of 2 families' )
		).toBeInTheDocument();
		expect(
			within( loose ).getByText( 'Measured against Proficient' )
		).toBeInTheDocument();
		expect(
			within( loose ).getByText( 'Linked only' )
		).toBeInTheDocument();
	} );

	it( 'marks syllabus objectives, and says how many are taught', async () => {
		renderView();
		const row = ( await screen.findByText( 'Estimate' ) ).closest( 'tr' )!;
		expect(
			within( row ).getByText( 'Syllabus objective' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Syllabus objectives taught by a lesson: 0 of 1.'
			)
		).toBeInTheDocument();
	} );

	it( 'warns that required skills without enough questions block publishing', async () => {
		renderView();
		expect(
			await screen.findByText(
				/1 required skill does not have enough approved questions yet/
			)
		).toBeInTheDocument();
	} );

	it( 'says so when every required skill is ready', async () => {
		vi.mocked( api.getSkillCoverage ).mockResolvedValue(
			report( [ covered ], { requiredNotReady: 0, required: 1 } )
		);
		renderView();
		expect(
			await screen.findByText(
				'Every required skill has enough approved questions.',
				notice
			)
		).toBeInTheDocument();
	} );

	it( 'filters to what needs attention, to required skills and to objectives', async () => {
		const user = userEvent.setup();
		renderView();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Needs attention (3)' } )
		);
		expect( screen.queryByText( 'Add halves' ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'Estimate' ) ).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Required (2)' } )
		);
		expect( screen.getByText( 'Add halves' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Round' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Estimate' ) ).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Syllabus objectives (1)' } )
		);
		expect( screen.getByText( 'Estimate' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Round' ) ).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'All skills (4)' } )
		);
		expect( screen.getByText( 'Round' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'All skills (4)' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	it( 'says when a filter matches nothing', async () => {
		vi.mocked( api.getSkillCoverage ).mockResolvedValue(
			report( [ covered ], { requiredNotReady: 0 } )
		);
		const user = userEvent.setup();
		renderView();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Needs attention (0)' } )
		);
		expect(
			screen.getByText( 'No skills match this filter.' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'explains what to do when the course has no skills yet', async () => {
		vi.mocked( api.getSkillCoverage ).mockResolvedValue(
			report( [], {
				skills: 0,
				required: 0,
				covered: 0,
				needsTeaching: 0,
				needsQuestions: 0,
				needsBoth: 0,
				objectives: 0,
				requiredNotReady: 0,
			} )
		);
		renderView();
		expect(
			await screen.findByText(
				'No skills are connected to this course yet.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Open the Curriculum step' } )
		).toHaveAttribute( 'href', '/courses/7/outline' );
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'warns that unsaved changes are not in the report', async () => {
		renderView( true );
		expect(
			await screen.findByText(
				/This report is based on the saved outline/
			)
		).toBeInTheDocument();
	} );

	it( 'has no unsaved warning when everything is saved', async () => {
		renderView( false );
		await screen.findByRole( 'table' );
		expect(
			screen.queryByText( /based on the saved outline/ )
		).not.toBeInTheDocument();
	} );

	it( 'reloads from the server on Refresh', async () => {
		const user = userEvent.setup();
		renderView();
		await screen.findByRole( 'table' );
		await user.click( screen.getByRole( 'button', { name: 'Refresh' } ) );
		await screen.findByRole( 'table' );
		expect( api.getSkillCoverage ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'shows a failure and lets the author try again', async () => {
		vi.mocked( api.getSkillCoverage )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 500,
					code: 'server',
					message: 'The report could not be built.',
				} )
			)
			.mockResolvedValueOnce(
				report( [ covered ], { requiredNotReady: 0 } )
			);
		const user = userEvent.setup();
		renderView();
		expect(
			await screen.findByText( /The report could not be built/, notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByRole( 'table' ) ).toBeInTheDocument();
	} );
} );
