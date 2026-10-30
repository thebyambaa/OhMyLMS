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
	readiness: [],
	units: [],
};
beforeEach( () => vi.resetAllMocks() );

it( 'requires preview and explicit confirmation before publishing the reviewed revision', async () => {
	const user = userEvent.setup();
	const onPublished = vi.fn();
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
		/>
	);
	expect(
		screen.queryByRole( 'button', { name: 'Confirm publication' } )
	).not.toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Preview saved course' } )
	);
	await user.click(
		await screen.findByRole( 'button', { name: 'Review publication' } )
	);
	expect( api.publishCourse ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Confirm publication' } )
	);
	await screen.findAllByText( 'Course and learning program published.' );
	expect( api.publishCourse ).toHaveBeenCalledWith(
		preview,
		expect.any( AbortSignal )
	);
	expect( onPublished ).toHaveBeenCalledOnce();
} );

it( 'blocks publication while readiness issues remain and blocks preview for unsaved edits', async () => {
	const user = userEvent.setup();
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
	expect(
		screen.getByRole( 'button', { name: 'Preview saved course' } )
	).toBeDisabled();
	view.rerender(
		<PublicationPanel
			course={ 12 }
			disabled={ false }
			onPublished={ vi.fn() }
		/>
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Preview saved course' } )
	);
	await screen.findAllByText( 'Publish lesson first.' );
	expect(
		screen.getByRole( 'button', { name: 'Review publication' } )
	).toBeDisabled();
	expect( api.publishCourse ).not.toHaveBeenCalled();
} );
