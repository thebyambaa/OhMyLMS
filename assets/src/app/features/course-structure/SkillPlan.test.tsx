import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { OutlineWorkspace } from './CourseEditorPage';
import type { Outline } from './model';
import * as planApi from './skill-plan-api';
import type { SkillPlan, SkillState } from './skill-plan-api';

vi.mock( './api', () => ( {
	readOutline: vi.fn(),
	saveOutline: vi.fn(),
	deleteUnit: vi.fn(),
	findResources: vi.fn(),
	createResource: vi.fn(),
} ) );
vi.mock( './skill-plan-api', () => ( {
	getSkillPlan: vi.fn(),
	setUnitTopics: vi.fn(),
	linkLessonSkills: vi.fn(),
} ) );
vi.mock( '../content-bank', () => ( {
	useBankTerms: () => ( {
		terms: { folders: [], tags: [] },
		paths: [],
		error: '',
		reload: () => undefined,
	} ),
} ) );

const outline: Outline = {
	course_id: 12,
	revision: 'rev',
	units: [
		{ id: 8, title: 'Algebra', number: '1', description: '' },
		{ id: 9, title: 'Geometry', number: '2', description: '' },
	],
	program: {
		mode: 'blended',
		recognize_prior: true,
		evidence_days: 90,
		bank_ids: [],
		groups: [],
		outcomes: [],
		items: [
			{
				id: 'a1',
				type: 'lesson',
				content_id: 20,
				chapter_id: 8,
				required: true,
				name: 'Solving equations',
			},
			{
				id: 'a2',
				type: 'lesson',
				content_id: 21,
				chapter_id: 9,
				required: true,
				name: 'Triangles',
			},
		],
	},
};

const teaches = (
	id: number,
	title: string,
	unitId: number
): SkillState[ 'teaching' ][ number ] => ( {
	id,
	title,
	unitId,
	unit: unitId === 8 ? 'Algebra' : 'Geometry',
} );

const state = ( over: Partial< SkillState > = {} ): SkillState => ( {
	name: '',
	code: '',
	status: 'covered',
	teaching: [],
	questions: 3,
	questionsNeed: 3,
	teachingOk: true,
	questionsOk: true,
	...over,
} );

function plan( over: Partial< SkillPlan > = {} ): SkillPlan {
	return {
		courseId: 12,
		curricula: [
			{ id: 1, name: 'Digital SAT' },
			{ id: 2, name: 'IGCSE Maths' },
		],
		curriculum: 1,
		topics: [
			{
				id: 10,
				name: 'Algebra topic',
				depth: 1,
				groups: [
					{ id: 100, name: 'Linear', skills: [ 7, 8 ] },
					{ id: 101, name: 'Quadratic', skills: [ 9 ] },
				],
			},
			{
				id: 11,
				name: 'Geometry topic',
				depth: 1,
				groups: [ { id: 102, name: 'Shapes', skills: [ 30 ] } ],
			},
		],
		names: {
			7: { name: 'Solve one-step equations', code: 'A.1' },
			8: { name: 'Solve two-step equations', code: 'A.2' },
			9: { name: 'Factorise quadratics', code: 'A.3' },
			30: { name: 'Use similar triangles', code: 'G.1' },
		},
		skills: {
			7: state( {
				name: 'Solve one-step equations',
				teaching: [ teaches( 20, 'Solving equations', 8 ) ],
			} ),
			8: state( {
				name: 'Solve two-step equations',
				status: 'needs-teaching',
				teachingOk: false,
			} ),
			30: state( {
				name: 'Use similar triangles',
				teaching: [ teaches( 21, 'Triangles', 9 ) ],
				questions: 1,
				questionsOk: false,
			} ),
		},
		unitTopics: {},
		...over,
	};
}

function open( saved = true, initial: Outline = outline ) {
	const view = render(
		<MemoryRouter>
			<OutlineWorkspace initial={ initial } reload={ vi.fn() } />
		</MemoryRouter>
	);
	return { ...view, saved };
}

const panel = () => screen.getByRole( 'complementary', { name: 'Skills' } );

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( planApi.getSkillPlan ).mockResolvedValue( plan() );
} );

describe( 'the skill panel beside the outline', () => {
	it( 'lists the chosen curriculum with how much of it the course teaches', async () => {
		open();
		expect(
			await within( panel() ).findByText(
				'2 of 4 skills are taught in this course.'
			)
		).toBeInTheDocument();
		expect(
			within( panel() ).getByRole( 'combobox', { name: 'Curriculum' } )
		).toHaveValue( '1' );
		expect(
			within( panel() ).getByText( 'Algebra topic' )
		).toBeInTheDocument();
		expect(
			within( panel() ).getByText(
				'Select a unit to choose the topics it covers, or a lesson to connect it to skills.'
			)
		).toBeInTheDocument();
		expect( planApi.getSkillPlan ).toHaveBeenCalledWith(
			12,
			0,
			expect.any( AbortSignal )
		);
	} );

	it( 'reads another curriculum when it is chosen', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.selectOptions(
			within( panel() ).getByRole( 'combobox', { name: 'Curriculum' } ),
			'2'
		);
		await waitFor( () =>
			expect( planApi.getSkillPlan ).toHaveBeenLastCalledWith(
				12,
				2,
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'filters the skills by what is typed', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.type(
			within( panel() ).getByRole( 'searchbox', {
				name: 'Search skills',
			} ),
			'triangles'
		);
		expect(
			within( panel() ).getByText( /Use similar triangles/ )
		).toBeInTheDocument();
		expect(
			within( panel() ).queryByText( /Solve one-step equations/ )
		).not.toBeInTheDocument();
	} );

	it( 'follows a selected lesson: its connected skills are checked and the others can be added', async () => {
		vi.mocked( planApi.linkLessonSkills ).mockResolvedValue( {
			lessonSkills: [ 7, 8 ],
			skills: {
				...plan().skills,
				8: state( {
					name: 'Solve two-step equations',
					teaching: [ teaches( 20, 'Solving equations', 8 ) ],
				} ),
			},
		} );
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		);
		expect(
			within( panel() ).getByRole( 'heading', {
				name: 'Skills for Solving equations',
			} )
		).toBeInTheDocument();
		expect(
			within( panel() ).getByRole( 'checkbox', {
				name: /Solve one-step equations/,
			} )
		).toBeChecked();
		const two = within( panel() ).getByRole( 'checkbox', {
			name: /Solve two-step equations/,
		} );
		expect( two ).not.toBeChecked();
		await user.click( two );
		expect( planApi.linkLessonSkills ).toHaveBeenCalledWith(
			12,
			20,
			{ add: [ 8 ] },
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect(
				within( panel() ).getByRole( 'checkbox', {
					name: /Solve two-step equations/,
				} )
			).toBeChecked()
		);
		// The outline row now shows how many skills the lesson has.
		expect(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		).toHaveTextContent( 'Skills (2)' );
	} );

	it( 'disconnects a skill from the lesson', async () => {
		vi.mocked( planApi.linkLessonSkills ).mockResolvedValue( {
			lessonSkills: [],
			skills: { ...plan().skills, 7: state( { name: 'x' } ) },
		} );
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		);
		await user.click(
			within( panel() ).getByRole( 'checkbox', {
				name: /Solve one-step equations/,
			} )
		);
		expect( planApi.linkLessonSkills ).toHaveBeenCalledWith(
			12,
			20,
			{ remove: [ 7 ] },
			expect.any( AbortSignal )
		);
	} );

	it( 'is selected by clicking the lesson row too', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click(
			screen
				.getByRole( 'link', { name: 'Edit Triangles' } )
				.closest( 'li' )!
		);
		expect(
			within( panel() ).getByRole( 'heading', {
				name: 'Skills for Triangles',
			} )
		).toBeInTheDocument();
	} );

	it( 'shows only the topics chosen for the unit, with a way to see them all', async () => {
		vi.mocked( planApi.getSkillPlan ).mockResolvedValue(
			plan( { unitTopics: { 8: [ 10 ] } } )
		);
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		// The unit row counts its topics.
		expect( await screen.findByText( /1 topic/ ) ).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		);
		expect(
			within( panel() ).getByText( 'Algebra topic' )
		).toBeInTheDocument();
		expect(
			within( panel() ).queryByText( 'Geometry topic' )
		).not.toBeInTheDocument();
		await user.click(
			within( panel() ).getByRole( 'button', { name: 'All topics' } )
		);
		expect(
			within( panel() ).getByText( 'Geometry topic' )
		).toBeInTheDocument();
	} );

	it( 'narrows to the connected skills on request', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		);
		await user.click(
			within( panel() ).getByRole( 'checkbox', {
				name: 'Only connected',
			} )
		);
		expect(
			within( panel() ).getByRole( 'checkbox', {
				name: /Solve one-step equations/,
			} )
		).toBeInTheDocument();
		expect(
			within( panel() ).queryByRole( 'checkbox', {
				name: /Solve two-step equations/,
			} )
		).not.toBeInTheDocument();
	} );

	it( 'selects the lesson that teaches a skill when its name is chosen', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click(
			within( panel() ).getByRole( 'button', {
				name: 'Select lesson Triangles',
			} )
		);
		expect(
			within( panel() ).getByRole( 'heading', {
				name: 'Skills for Triangles',
			} )
		).toBeInTheDocument();
	} );

	it( 'chooses the topics of a saved unit', async () => {
		vi.mocked( planApi.setUnitTopics ).mockResolvedValue( { 8: [ 11 ] } );
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click( screen.getByRole( 'button', { name: 'Algebra' } ) );
		expect(
			within( panel() ).getByRole( 'heading', {
				name: 'Topics for Algebra',
			} )
		).toBeInTheDocument();
		await user.click(
			within( panel() ).getByRole( 'checkbox', {
				name: /Geometry topic/,
			} )
		);
		expect( planApi.setUnitTopics ).toHaveBeenCalledWith(
			12,
			8,
			[ 11 ],
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect(
				within( panel() ).getByRole( 'checkbox', {
					name: /Geometry topic/,
				} )
			).toBeChecked()
		);
	} );

	it( 'asks to save the outline before connecting while it has unsaved changes', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click( screen.getByRole( 'button', { name: 'Add unit' } ) );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		);
		expect(
			within( panel() ).getByText( /Save the outline first/ )
		).toBeInTheDocument();
		expect(
			within( panel() ).getByRole( 'checkbox', {
				name: /Solve one-step equations/,
			} )
		).toBeDisabled();
	} );

	it( 'tells a new, unsaved unit to be saved before it gets topics', async () => {
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click( screen.getByRole( 'button', { name: 'Add unit' } ) );
		await user.click( screen.getByRole( 'button', { name: 'New unit' } ) );
		expect(
			within( panel() ).getByText(
				'Save the outline to choose topics for this new unit.'
			)
		).toBeInTheDocument();
	} );

	it( 'shows a refusal from the server', async () => {
		vi.mocked( planApi.linkLessonSkills ).mockRejectedValue(
			new ApiError( {
				status: 400,
				code: 'ohmylms_lesson_not_in_course',
				message: 'That lesson is not in this course.',
			} )
		);
		const user = userEvent.setup();
		open();
		await within( panel() ).findByText( 'Algebra topic' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Connect skills for Solving equations',
			} )
		);
		await user.click(
			within( panel() ).getByRole( 'checkbox', {
				name: /Solve two-step equations/,
			} )
		);
		expect(
			await within( panel() ).findByText(
				'That lesson is not in this course.'
			)
		).toBeInTheDocument();
	} );

	it( 'says when the plan cannot be read, and tries again', async () => {
		vi.mocked( planApi.getSkillPlan ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'x', message: 'Broke.' } )
		);
		const user = userEvent.setup();
		open();
		expect(
			await within( panel() ).findByText( 'Broke.' )
		).toBeInTheDocument();
		await user.click(
			within( panel() ).getByRole( 'button', { name: 'Retry' } )
		);
		expect(
			await within( panel() ).findByText( 'Algebra topic' )
		).toBeInTheDocument();
		expect( api.saveOutline ).not.toHaveBeenCalled();
	} );
} );
