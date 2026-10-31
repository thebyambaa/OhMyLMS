import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import * as api from './api';
import LessonObjectives from './LessonObjectives';
import {
	objectivesPayload,
	type LessonObjectives as Objectives,
} from './model';

vi.mock( './api', () => ( {
	readObjectives: vi.fn(),
	saveObjectives: vi.fn(),
	listSyllabuses: vi.fn(),
	searchObjectives: vi.fn(),
	searchSkills: vi.fn(),
} ) );

const state: Objectives = {
	id: 12,
	revision: 'r1',
	skills: [
		{ id: 3, name: 'Slope', code: 'A.1', managed: false },
		{ id: 4, name: 'Intercept', code: 'A.2', managed: true },
	],
	mappings: [
		{
			syllabus_id: 70,
			term_id: 4,
			relation: 'teaches',
			name: 'Intercept',
			code: 'A.2',
			objective: 'Find the y-intercept.',
			topic: 'Lines / Graphs',
			syllabus_name: 'Algebra 1',
			missing: false,
		},
	],
	course_targets: [
		{
			syllabus_id: 70,
			term_id: 5,
			name: 'Gradient',
			code: 'A.3',
			objective: 'Compute a gradient.',
			topic: 'Lines / Graphs',
			syllabus_name: 'Algebra 1',
			course_id: 2,
			course_title: 'Geometry',
		},
	],
};

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.readObjectives ).mockResolvedValue( state );
} );

it( 'sends explicit skills only; mapped skills follow their mapping', () => {
	expect( objectivesPayload( state ) ).toEqual( {
		revision: 'r1',
		skill_ids: [ 3 ],
		mappings: [ { syllabus_id: 70, term_id: 4, relation: 'teaches' } ],
	} );
} );

it( 'adds a course objective, links a skill, changes a relationship and saves', async () => {
	vi.mocked( api.searchSkills ).mockResolvedValue( {
		items: [ { id: 9, name: 'Distance', code: '', managed: false } ],
		total: 1,
		pages: 1,
	} );
	vi.mocked( api.saveObjectives ).mockImplementation( async ( next ) => ( {
		...next,
		revision: 'r2',
	} ) );
	const user = userEvent.setup();
	render( <LessonObjectives lessonId={ 12 } /> );
	expect(
		await screen.findByText( 'A.2 · Intercept (from a syllabus objective)' )
	).toBeInTheDocument();
	expect(
		screen.queryByRole( 'button', { name: 'Unlink skill Intercept' } )
	).not.toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Add objective A.3 · Gradient' } )
	);
	expect(
		screen.getByText( 'A.3 · Gradient (from a syllabus objective)' )
	).toBeInTheDocument();
	const table = screen.getByRole( 'table', {
		name: 'Syllabus objectives of this lesson',
	} );
	await user.selectOptions(
		within( table ).getByRole( 'combobox', {
			name: 'Relationship to A.2 · Intercept',
		} ),
		'assesses'
	);
	await user.type(
		screen.getByRole( 'textbox', { name: 'Find a skill' } ),
		'dist'
	);
	await user.click( screen.getByRole( 'button', { name: 'Search skills' } ) );
	await user.click(
		await screen.findByRole( 'button', { name: 'Link skill Distance' } )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Unlink skill Slope' } )
	);
	expect(
		screen.getByText( 'Objectives have unsaved changes.' )
	).toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Save objectives' } )
	);
	expect(
		await screen.findByText( 'Lesson objectives saved.', notice )
	).toBeInTheDocument();
	const sent = vi.mocked( api.saveObjectives ).mock.calls[ 0 ]![ 0 ];
	expect( objectivesPayload( sent ) ).toEqual( {
		revision: 'r1',
		skill_ids: [ 9 ],
		mappings: [
			{ syllabus_id: 70, term_id: 4, relation: 'assesses' },
			{ syllabus_id: 70, term_id: 5, relation: 'teaches' },
		],
	} );
	expect(
		screen.getByRole( 'button', { name: 'Save objectives' } )
	).toBeDisabled();
} );

it( 'removing a mapping removes its managed skill; a conflict keeps edits and offers a reload', async () => {
	vi.mocked( api.saveObjectives ).mockRejectedValue(
		new ApiError( {
			status: 409,
			code: 'ohmylms_catalog_invalid',
			message: 'Objectives changed elsewhere.',
		} )
	);
	const user = userEvent.setup();
	render( <LessonObjectives lessonId={ 12 } /> );
	await user.click(
		await screen.findByRole( 'button', {
			name: 'Remove objective A.2 · Intercept',
		} )
	);
	expect(
		screen.queryByText( /Intercept \(from a syllabus objective\)/ )
	).not.toBeInTheDocument();
	expect(
		screen.getByText( 'No syllabus objectives mapped yet.' )
	).toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Save objectives' } )
	);
	expect(
		await screen.findByText( /Objectives changed elsewhere/, notice )
	).toBeInTheDocument();
	expect(
		screen.getByText( 'No syllabus objectives mapped yet.' )
	).toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Reload the saved objectives' } )
	);
	expect( api.readObjectives ).toHaveBeenCalledTimes( 2 );
} );

it( 'browses a syllabus and adds an objective from it', async () => {
	vi.mocked( api.listSyllabuses ).mockResolvedValue( {
		items: [
			{
				id: 71,
				name: 'Physics',
				code: 'PHY',
				version: '2026',
				grade: '',
				subject: '',
			},
		],
		total: 1,
		pages: 1,
	} );
	vi.mocked( api.searchObjectives ).mockResolvedValue( {
		items: [
			{
				syllabus_id: 71,
				term_id: 20,
				name: 'Speed',
				code: 'P.1',
				objective: 'Define speed.',
				topic: 'Motion',
			},
		],
		total: 1,
		pages: 1,
	} );
	const user = userEvent.setup();
	render( <LessonObjectives lessonId={ 12 } /> );
	await user.click(
		await screen.findByRole( 'button', {
			name: 'Browse syllabus objectives',
		} )
	);
	await user.selectOptions(
		await screen.findByRole( 'combobox', { name: 'Syllabus' } ),
		'71'
	);
	await user.type(
		screen.getByRole( 'textbox', { name: 'Find an objective' } ),
		'speed'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Search objectives' } )
	);
	expect( api.searchObjectives ).toHaveBeenCalledWith(
		12,
		71,
		'speed',
		expect.any( AbortSignal )
	);
	await user.click(
		await screen.findByRole( 'button', {
			name: 'Add objective P.1 · Speed',
		} )
	);
	const table = screen.getByRole( 'table', {
		name: 'Syllabus objectives of this lesson',
	} );
	expect(
		within( table ).getByText( 'Physics — Motion' )
	).toBeInTheDocument();
} );
