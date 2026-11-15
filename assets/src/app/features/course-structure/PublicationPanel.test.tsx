import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { PublicationPanel } from './PublicationPanel';
import * as api from './publication-api';

vi.mock( './publication-api', () => ( {
	previewPublication: vi.fn(),
	publishCourse: vi.fn(),
} ) );
const preview: api.Publication = {
	course_id: 12,
	revision: 'reviewed',
	title: 'Algebra',
	status: 'draft',
	mode: 'traditional',
	outcomes: 0,
	published_program_id: 0,
	unpublished_changes: true,
	readiness: [],
	units: [
		{
			id: 3,
			title: 'Unit 1',
			items: [
				{ id: 'a', type: 'lesson', title: 'Intro' },
				{ id: 'b', type: 'quiz', title: 'Check' },
			],
		},
	],
};
beforeEach( () => vi.resetAllMocks() );

it( 'reads the saved course by itself and publishes the shown revision with one button', async () => {
	const user = userEvent.setup();
	const onPublished = vi.fn();
	const onClose = vi.fn();
	vi.mocked( api.previewPublication ).mockResolvedValue( preview );
	vi.mocked( api.publishCourse ).mockResolvedValue( {
		...preview,
		status: 'publish',
		published_program_id: 8,
	} );
	render(
		<PublicationPanel
			course={ 12 }
			disabled={ false }
			onPublished={ onPublished }
			onClose={ onClose }
			viewLink={ <a href="https://example.test/algebra">View course</a> }
		/>
	);
	expect(
		await screen.findByText( '1 unit, 1 lesson, 1 quiz' )
	).toBeInTheDocument();
	expect( api.publishCourse ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Publish course' } )
	);
	await screen.findAllByText( 'Course published. Students can open it now.' );
	expect( api.publishCourse ).toHaveBeenCalledWith(
		preview,
		expect.any( AbortSignal )
	);
	expect( onPublished ).toHaveBeenCalledOnce();
	expect(
		screen.queryByRole( 'button', { name: 'Publish course' } )
	).not.toBeInTheDocument();
	expect( screen.getByRole( 'link', { name: 'View course' } ) ).toBeVisible();
	await user.click( screen.getByRole( 'button', { name: 'Done' } ) );
	expect( onClose ).toHaveBeenCalledOnce();
} );

it( 'waits for unsaved edits and blocks publication while readiness issues remain', async () => {
	vi.mocked( api.previewPublication ).mockResolvedValue( {
		...preview,
		readiness: [ 'Publish lesson first.' ],
	} );
	const view = render(
		<PublicationPanel
			course={ 12 }
			disabled={ true }
			onPublished={ vi.fn() }
		/>
	);
	expect( api.previewPublication ).not.toHaveBeenCalled();
	expect(
		screen.queryByRole( 'button', { name: 'Publish course' } )
	).not.toBeInTheDocument();
	view.rerender(
		<PublicationPanel
			course={ 12 }
			disabled={ false }
			onPublished={ vi.fn() }
		/>
	);
	await screen.findAllByText( 'Publish lesson first.' );
	expect(
		screen.getByRole( 'button', { name: 'Publish course' } )
	).toBeDisabled();
	expect( api.publishCourse ).not.toHaveBeenCalled();
} );

it( 'says what students see: nothing yet, or the last published version', async () => {
	vi.mocked( api.previewPublication ).mockResolvedValueOnce( preview );
	const { unmount } = render(
		<PublicationPanel
			course={ 12 }
			disabled={ false }
			onPublished={ vi.fn() }
		/>
	);
	expect(
		await screen.findByText( /cannot see this course yet/ )
	).toBeInTheDocument();
	unmount();
	vi.mocked( api.previewPublication ).mockResolvedValueOnce( {
		...preview,
		published_program_id: 4,
	} );
	render(
		<PublicationPanel
			course={ 12 }
			disabled={ false }
			onPublished={ vi.fn() }
		/>
	);
	expect(
		await screen.findByText( /still see the last published version/ )
	).toBeInTheDocument();
} );

it( 'offers another try when the saved course cannot be read', async () => {
	const user = userEvent.setup();
	vi.mocked( api.previewPublication )
		.mockRejectedValueOnce( new Error( 'Offline.' ) )
		.mockResolvedValueOnce( preview );
	render(
		<PublicationPanel
			course={ 12 }
			disabled={ false }
			onPublished={ vi.fn() }
		/>
	);
	await user.click(
		await screen.findByRole( 'button', { name: 'Try again' } )
	);
	expect(
		await screen.findByRole( 'button', { name: 'Publish course' } )
	).toBeEnabled();
} );
