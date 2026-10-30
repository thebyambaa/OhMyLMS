import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { lesson } from './fixtures';
import { LessonWorkspace } from './LessonEditorPage';

vi.mock( './api', () => ( {
	readLesson: vi.fn(),
	saveLesson: vi.fn(),
	publishLesson: vi.fn(),
} ) );
vi.mock( './LessonBlocks', () => ( {
	default: ( {
		initial,
		onChange,
		disabled,
	}: {
		initial: string;
		onChange: ( value: string ) => void;
		disabled: boolean;
	} ) => (
		<textarea
			aria-label="Visual block content"
			value={ initial }
			disabled={ disabled }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
} ) );
beforeEach( () => vi.resetAllMocks() );
function open() {
	const reload = vi.fn();
	return {
		reload,
		...render(
			<MemoryRouter>
				<LessonWorkspace initial={ lesson } reload={ reload } />
			</MemoryRouter>
		),
	};
}

it( 'saves content and resource links without changing publication or placement', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.saveLesson ).mockResolvedValue( {
		...lesson,
		revision: 'saved',
	} );
	await user.type( screen.getByLabelText( 'Lesson title' ), ' revised' );
	await user.click(
		screen.getByRole( 'button', { name: 'Add resource link' } )
	);
	await user.type( screen.getByLabelText( 'Resource name' ), 'Worksheet' );
	await user.type(
		screen.getByLabelText( 'Resource URL' ),
		'https://example.com/file.pdf'
	);
	await user.click( screen.getByRole( 'button', { name: 'Save lesson' } ) );
	await screen.findAllByText(
		'Lesson saved. Publication status is unchanged.'
	);
	expect( api.saveLesson ).toHaveBeenCalledWith(
		{
			...lesson,
			title: 'Algebra lesson revised',
			resources: [
				{
					name: 'Worksheet',
					url: 'https://example.com/file.pdf',
					size: '',
				},
			],
		},
		expect.any( AbortSignal )
	);
	expect( api.publishLesson ).not.toHaveBeenCalled();
} );
it( 'keeps edits after conflicts and confirms before replacing dirty local state', async () => {
	const user = userEvent.setup();
	const { reload } = open();
	vi.mocked( api.saveLesson ).mockRejectedValue(
		new ApiError( {
			code: 'stale',
			status: 409,
			message: 'Changed in another session',
		} )
	);
	await user.type( screen.getByLabelText( 'Lesson title' ), ' unsaved' );
	await user.click( screen.getByRole( 'button', { name: 'Save lesson' } ) );
	await screen.findAllByText( 'Changed in another session' );
	expect( screen.getByLabelText( 'Lesson title' ) ).toHaveValue(
		'Algebra lesson unsaved'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Reload saved lesson' } )
	);
	expect( reload ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Discard and reload' } )
	);
	expect( reload ).toHaveBeenCalledOnce();
} );
it( 'requires review and confirmation before publishing the saved revision', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.publishLesson ).mockResolvedValue( {
		...lesson,
		status: 'publish',
		revision: 'published',
	} );
	await user.click(
		screen.getByRole( 'button', { name: 'Review lesson publication' } )
	);
	expect( api.publishLesson ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Confirm lesson publication' } )
	);
	await screen.findAllByText( 'Lesson published.' );
	expect( api.publishLesson ).toHaveBeenCalledWith(
		lesson,
		expect.any( AbortSignal )
	);
} );
it( 'prevents duplicate writes and aborts the owned request on unmount', async () => {
	const user = userEvent.setup();
	const { unmount } = open();
	vi.mocked( api.saveLesson ).mockImplementation(
		() => new Promise( () => {} )
	);
	await user.type( screen.getByLabelText( 'Lesson title' ), ' revised' );
	expect(
		screen.getByRole( 'button', { name: 'Review lesson publication' } )
	).toBeDisabled();
	await user.dblClick(
		screen.getByRole( 'button', { name: 'Save lesson' } )
	);
	await waitFor( () => expect( api.saveLesson ).toHaveBeenCalledOnce() );
	const signal = vi.mocked( api.saveLesson ).mock.calls[ 0 ]![ 1 ];
	unmount();
	expect( signal.aborted ).toBe( true );
} );
it( 'supports block source edits without dropping opaque custom markup', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'Edit block source' } )
	);
	expect( screen.getByLabelText( 'Block content source' ) ).toHaveValue(
		lesson.content
	);
	const source = '<!-- wp:vendor/reference {"reference":7} /-->';
	await user.clear( screen.getByLabelText( 'Block content source' ) );
	await user.paste( source );
	await user.click(
		screen.getByRole( 'button', { name: 'Use visual block editor' } )
	);
	expect( screen.getByLabelText( 'Visual block content' ) ).toHaveValue(
		source
	);
} );
