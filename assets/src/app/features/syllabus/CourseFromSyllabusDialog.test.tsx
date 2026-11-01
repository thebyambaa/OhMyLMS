import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import { CourseFromSyllabusDialog } from './CourseFromSyllabusDialog';
import { preview } from './fixtures';
import * as generation from './generation';

vi.mock( './generation', async ( original ) => ( {
	...( await original< typeof import( './generation' ) >() ),
	readSources: vi.fn(),
	readSource: vi.fn(),
	createFromSyllabus: vi.fn(),
} ) );
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( generation.readSource ).mockResolvedValue( preview );
	vi.mocked( generation.createFromSyllabus ).mockResolvedValue( {
		id: 40,
		name: 'Geometry course',
		units: [ { id: 41, name: 'Geometry' } ],
		objectives: 1,
		lessons: 1,
	} );
} );
function open( syllabusId?: number, onClose = vi.fn() ) {
	render(
		<MemoryRouter initialEntries={ [ '/start' ] }>
			<Routes>
				<Route
					path="/start"
					element={
						<CourseFromSyllabusDialog
							syllabusId={ syllabusId }
							onClose={ onClose }
						/>
					}
				/>
				<Route
					path="/courses/:id/outline"
					element={ <p>Course outline reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
	return onClose;
}
it( 'selects a topic, reviews the generated outline and creates the draft course', async () => {
	const user = userEvent.setup();
	open( 5 );
	await user.click(
		await screen.findByLabelText( 'Every skill in Geometry' )
	);
	await user.type(
		screen.getByLabelText( 'Course title' ),
		'Geometry course'
	);
	expect( screen.getByText( '1 skill selected (at most 200).' ) );
	await user.click(
		screen.getByRole( 'button', { name: 'Review course outline' } )
	);
	const review = screen.getByRole( 'region', {
		name: 'Review course outline',
	} );
	expect(
		within( review ).getByText( 'Draft course: Geometry course' )
	).toBeInTheDocument();
	expect(
		within( review ).getByText(
			'1 units, 1 draft lessons and 1 linked skills.'
		)
	).toBeInTheDocument();
	expect( within( review ).getByText( 'Geometry' ) ).toBeInTheDocument();
	expect(
		within(
			within( review ).getByRole( 'list', {
				name: 'Skills linked to G · Angles',
			} )
		).getByText( 'G.1 Measure angles' )
	).toBeInTheDocument();
	expect( generation.createFromSyllabus ).not.toHaveBeenCalled();
	await user.click(
		within( review ).getByRole( 'button', { name: 'Create draft course' } )
	);
	expect(
		await screen.findByText( 'Course outline reached' )
	).toBeInTheDocument();
	expect( generation.createFromSyllabus ).toHaveBeenCalledWith(
		{
			syllabusId: 5,
			title: 'Geometry course',
			skillIds: [ 12 ],
			revision: 'source-revision',
		},
		expect.any( AbortSignal )
	);
} );
it( 'selects individual skills and leaves unselected groups out of the plan', async () => {
	const user = userEvent.setup();
	open( 5 );
	await user.click( await screen.findByLabelText( 'A.2 Use expressions' ) );
	expect(
		screen.getByLabelText( 'Every skill in A · Algebra' )
	).toHaveProperty( 'indeterminate', true );
	await user.click(
		screen.getByRole( 'button', { name: 'Review course outline' } )
	);
	const review = screen.getByRole( 'region', {
		name: 'Review course outline',
	} );
	expect(
		within( review ).getByText( 'Draft course: Mathematics syllabus' )
	).toBeInTheDocument();
	expect( within( review ).queryByText( 'Geometry' ) ).toBeNull();
	expect( within( review ).queryByText( 'A.1 Solve equations' ) ).toBeNull();
	await user.click(
		within( review ).getByRole( 'button', { name: 'Create draft course' } )
	);
	await waitFor( () =>
		expect( generation.createFromSyllabus ).toHaveBeenCalledWith(
			expect.objectContaining( { title: '', skillIds: [ 11 ] } ),
			expect.any( AbortSignal )
		)
	);
} );
it( 'reloads a changed syllabus after a stale creation and keeps the remaining selection', async () => {
	const user = userEvent.setup();
	vi.mocked( generation.createFromSyllabus ).mockRejectedValue(
		new ApiError( {
			status: 409,
			code: 'ohmylms_curriculum_invalid',
			message:
				'The syllabus changed. Reload the selection before creating the course.',
		} )
	);
	open( 5 );
	await user.click(
		await screen.findByLabelText( 'Every skill in A · Algebra' )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Review course outline' } )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Create draft course' } )
	);
	expect(
		await screen.findByText(
			'The syllabus changed. Reload the selection before creating the course.'
		)
	).toBeInTheDocument();
	vi.mocked( generation.readSource ).mockResolvedValue( {
		...preview,
		revision: 'changed-revision',
		topics: [
			{
				...preview.topics[ 0 ]!,
				lessons: [
					{
						...preview.topics[ 0 ]!.lessons[ 0 ]!,
						skills: [
							preview.topics[ 0 ]!.lessons[ 0 ]!.skills[ 1 ]!,
						],
					},
				],
			},
		],
	} );
	await user.click(
		screen.getByRole( 'button', {
			name: 'Reload the syllabus and review again',
		} )
	);
	expect(
		await screen.findByText( '1 skill selected (at most 200).' )
	).toBeInTheDocument();
	expect( generation.readSource ).toHaveBeenCalledTimes( 2 );
	expect( screen.getByLabelText( 'A.2 Use expressions' ) ).toBeChecked();
} );
it( 'refuses more skills than a course may start from', async () => {
	const user = userEvent.setup();
	vi.mocked( generation.readSource ).mockResolvedValue( {
		...preview,
		topics: [
			{
				id: 5,
				name: 'Large',
				depth: 0,
				lessons: [
					{
						id: 7,
						title: 'Many',
						skills: Array.from( { length: 201 }, ( _, index ) => ( {
							term_id: index + 1,
							name: `Skill ${ index + 1 }`,
							code: '',
							objective: '',
						} ) ),
					},
				],
			},
		],
	} );
	open( 5 );
	await user.click(
		await screen.findByRole( 'button', { name: 'Select every skill' } )
	);
	expect(
		screen.getByText(
			'A course can start from at most 200 skills. Clear some, or create more than one course.',
			{ selector: '.components-notice__content' }
		)
	).toBeInTheDocument();
	expect(
		screen.getByRole( 'button', { name: 'Review course outline' } )
	).toBeDisabled();
	await user.click( screen.getByLabelText( 'Skill 1' ) );
	expect(
		screen.getByRole( 'button', { name: 'Review course outline' } )
	).toBeEnabled();
} );
it( 'lets the author choose the syllabus when none is given', async () => {
	const user = userEvent.setup();
	vi.mocked( generation.readSources ).mockResolvedValue( [
		{
			id: 5,
			name: 'Mathematics syllabus',
			code: '0580',
			version: '2026',
			item_type: 'syllabus',
		},
	] );
	const onClose = open();
	expect( generation.readSource ).not.toHaveBeenCalled();
	await user.selectOptions( await screen.findByLabelText( 'Syllabus' ), '5' );
	expect(
		await screen.findByLabelText( 'Every skill in Geometry' )
	).toBeInTheDocument();
	expect( generation.readSource ).toHaveBeenCalledWith(
		5,
		expect.any( AbortSignal )
	);
	await user.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
	expect( onClose ).toHaveBeenCalled();
	expect( generation.createFromSyllabus ).not.toHaveBeenCalled();
} );
