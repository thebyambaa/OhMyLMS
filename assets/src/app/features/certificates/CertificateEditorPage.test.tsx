import {
	fireEvent,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import CertificateEditorPage from './CertificateEditorPage';
import { certificate, options } from './fixtures';

vi.mock( './api', () => ( {
	getCertificate: vi.fn(),
	getOptions: vi.fn(),
	updateCertificate: vi.fn(),
	previewCertificate: vi.fn(),
	listCourses: vi.fn(),
	listMedia: vi.fn(),
} ) );

function renderEditor( route = '/certificates/7' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route
					path="/certificates/:id"
					element={ <CertificateEditorPage /> }
				/>
				<Route
					path="/certificates"
					element={ <p>List route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

async function loaded() {
	await screen.findByRole( 'textbox', { name: 'Name' } );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getCertificate ).mockResolvedValue( certificate );
	vi.mocked( api.getOptions ).mockResolvedValue( options );
	vi.mocked( api.previewCertificate ).mockResolvedValue( {
		html: '<!DOCTYPE html><p>server preview</p>',
		width_mm: 297,
		height_mm: 210,
	} );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [
			{ id: 11, title: 'Algebra', status: 'publish' },
			{ id: 12, title: 'Geometry', status: 'draft' },
		],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( api.listMedia ).mockResolvedValue( {
		items: [
			{
				id: 9,
				url: 'https://example.test/logo.png',
				thumbnail_url: 'https://example.test/logo-150.png',
				title: 'Logo',
			},
		],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.updateCertificate ).mockImplementation(
		async ( id, input ) => ( {
			...certificate,
			...input,
			id,
			background_url: input.background_id
				? 'https://example.test/logo.png'
				: '',
			signature_url: '',
			courses: input.course_ids.map( ( courseId ) => ( {
				id: courseId,
				title: `Course ${ courseId }`,
			} ) ),
			course_count: input.course_ids.length,
		} )
	);
} );

describe( 'CertificateEditorPage', () => {
	it( 'rejects an invalid route id without requesting anything', () => {
		renderEditor( '/certificates/abc' );
		expect(
			screen.getByText(
				'This certificate template link is invalid.',
				notice
			)
		).toBeInTheDocument();
		expect( api.getCertificate ).not.toHaveBeenCalled();
	} );
	it( 'loads a template into labelled fields and renders the server preview in a sandboxed frame', async () => {
		renderEditor();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading certificate template'
		);
		await loaded();
		expect(
			screen.getByRole( 'heading', {
				name: 'Edit certificate template: Completion',
			} )
		).toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'Completion'
		);
		for ( const label of [
			'Status',
			'Orientation',
			'Page size',
			'Font',
		] ) {
			expect(
				screen.getByRole( 'combobox', { name: label } )
			).toBeInTheDocument();
		}
		expect(
			screen.getByRole( 'textbox', { name: 'Background color' } )
		).toHaveValue( '#ffffff' );
		const first = within(
			screen.getByRole( 'group', { name: 'Text block 1' } )
		);
		expect( first.getByRole( 'textbox', { name: 'Text' } ) ).toHaveValue(
			'Certificate of Completion'
		);
		expect(
			first.getByRole( 'spinbutton', { name: 'Left (% of page)' } )
		).toHaveValue( 10 );
		expect( first.getByRole( 'checkbox', { name: 'Bold' } ) ).toBeChecked();
		const frame = await screen.findByTitle( 'Certificate preview' );
		expect( frame ).toHaveAttribute( 'sandbox', '' );
		expect( frame ).toHaveAttribute(
			'srcdoc',
			'<!DOCTYPE html><p>server preview</p>'
		);
		expect( api.previewCertificate ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByRole( 'list', { name: 'Assigned courses' } )
		).toHaveTextContent( 'Algebra' );
	} );
	it( 'saves typed values, then shows the server copy and clears the unsaved state', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		const name = screen.getByRole( 'textbox', { name: 'Name' } );
		await user.clear( name );
		await user.type( name, 'Gold  ' );
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toBeInTheDocument();
		const first = within(
			screen.getByRole( 'group', { name: 'Text block 1' } )
		);
		fireEvent.change(
			first.getByRole( 'spinbutton', { name: 'Left (% of page)' } ),
			{
				target: { value: '12.5' },
			}
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Orientation' } ),
			'portrait'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		await waitFor( () =>
			expect( api.updateCertificate ).toHaveBeenCalledTimes( 1 )
		);
		const [ id, input ] = vi.mocked( api.updateCertificate ).mock
			.calls[ 0 ]!;
		expect( id ).toBe( 7 );
		expect( input ).toMatchObject( {
			name: 'Gold',
			orientation: 'portrait',
			course_ids: [ 11 ],
			signature: { image_id: 0, x: 65, y: 80, width: 25 },
		} );
		expect( input.blocks[ 0 ] ).toMatchObject( {
			id: 'title',
			x: 12.5,
			y: 18,
			bold: true,
		} );
		expect(
			await screen.findByText( 'Template saved.', notice )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'heading', {
				name: 'Edit certificate template: Gold',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'You have unsaved changes.' )
		).not.toBeInTheDocument();
	} );
	it( 'blocks an invalid save, marks the field, and moves focus to the summary', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		const first = within(
			screen.getByRole( 'group', { name: 'Text block 1' } )
		);
		const text = first.getByRole( 'textbox', { name: 'Text' } );
		fireEvent.change( text, { target: { value: 'Dear {first_name}' } } );
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		expect( api.updateCertificate ).not.toHaveBeenCalled();
		expect( await first.findByRole( 'alert' ) ).toHaveTextContent(
			'Unknown placeholder: {first_name}'
		);
		expect( text ).toBeInvalid();
		expect( text ).toHaveAccessibleDescription( /Unknown placeholder/ );
		const summary = screen.getByText(
			'Fix the highlighted fields before saving.',
			notice
		);
		expect( summary.closest( '[tabindex="-1"]' ) ).toHaveFocus();
		fireEvent.change( screen.getByRole( 'textbox', { name: 'Name' } ), {
			target: { value: '' },
		} );
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		expect(
			await screen.findByText( 'Enter a name of 1 to 200 characters.' )
		).toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toBeInvalid();
	} );
	it( 'keeps every edit and shows the field error when the server rejects the layout', async () => {
		const user = userEvent.setup();
		vi.mocked( api.updateCertificate ).mockRejectedValueOnce(
			new ApiError( {
				status: 422,
				code: 'ohmylms_certificate_block_out_of_bounds',
				message: 'A text block must stay inside the page.',
				details: { status: 422, field: 'title', block_id: 'title' },
			} )
		);
		renderEditor();
		await loaded();
		const name = screen.getByRole( 'textbox', { name: 'Name' } );
		await user.clear( name );
		await user.type( name, 'Edited' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		const first = within(
			screen.getByRole( 'group', { name: 'Text block 1' } )
		);
		expect(
			await first.findByText( 'A text block must stay inside the page.' )
		).toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'Edited'
		);
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		expect(
			await screen.findByText( 'Template saved.', notice )
		).toBeInTheDocument();
	} );
	it( 'reports a server error on save without losing edits', async () => {
		const user = userEvent.setup();
		vi.mocked( api.updateCertificate ).mockRejectedValueOnce(
			new Error( 'Server on fire' )
		);
		renderEditor();
		await loaded();
		const name = screen.getByRole( 'textbox', { name: 'Name' } );
		await user.type( name, ' two' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		expect(
			await screen.findByText( 'Server on fire', notice )
		).toBeInTheDocument();
		expect( name ).toHaveValue( 'Completion two' );
		expect(
			screen.getByRole( 'button', { name: 'Save template' } )
		).toBeEnabled();
	} );
	it( 'shows a permission error from the server and offers no retry', async () => {
		vi.mocked( api.getCertificate ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_certificate_cannot_view',
				message:
					'Sorry, you are not allowed to view certificate templates.',
			} )
		);
		renderEditor();
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to view certificate templates.',
				notice
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'textbox', { name: 'Name' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', {
				name: 'Back to certificate templates',
			} )
		).toBeInTheDocument();
	} );
	it( 'offers a retry after a network failure', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getCertificate ).mockRejectedValueOnce(
			new Error( 'Offline' )
		);
		renderEditor();
		expect(
			await screen.findByText( 'Offline', notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await loaded();
	} );
	it( 'asks before leaving with unsaved changes', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.type( screen.getByRole( 'textbox', { name: 'Name' } ), '!' );
		await user.click(
			screen.getByRole( 'link', {
				name: 'Back to certificate templates',
			} )
		);
		const dialog = within(
			screen.getByRole( 'dialog', { name: 'Discard unsaved changes?' } )
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Keep editing' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'Completion!'
		);
		await user.click(
			screen.getByRole( 'link', {
				name: 'Back to certificate templates',
			} )
		);
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Discard changes',
			} )
		);
		expect(
			await screen.findByText( 'List route reached' )
		).toBeInTheDocument();
	} );
	it( 'leaves without a prompt when nothing changed', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'link', {
				name: 'Back to certificate templates',
			} )
		);
		expect(
			await screen.findByText( 'List route reached' )
		).toBeInTheDocument();
	} );
	it( 'refreshes the preview on demand and refuses to preview invalid values', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await screen.findByTitle( 'Certificate preview' );
		await user.click(
			screen.getByRole( 'button', { name: 'Refresh preview' } )
		);
		await waitFor( () =>
			expect( api.previewCertificate ).toHaveBeenCalledTimes( 2 )
		);
		fireEvent.change(
			screen.getByRole( 'textbox', { name: 'Background color' } ),
			{ target: { value: 'red' } }
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Refresh preview' } )
		);
		expect(
			await screen.findByText(
				'Fix the highlighted fields to refresh the preview.',
				notice
			)
		).toBeInTheDocument();
		expect( api.previewCertificate ).toHaveBeenCalledTimes( 2 );
		expect(
			screen.getByRole( 'textbox', { name: 'Background color' } )
		).toBeInvalid();
	} );
	it( 'shows a preview failure from the server', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		vi.mocked( api.previewCertificate ).mockRejectedValueOnce(
			new ApiError( {
				status: 422,
				code: 'ohmylms_certificate_invalid_image',
				message:
					'Choose a JPEG, PNG, GIF or WebP image from the media library.',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Refresh preview' } )
		);
		expect(
			await screen.findByText(
				'Choose a JPEG, PNG, GIF or WebP image from the media library.',
				notice
			)
		).toBeInTheDocument();
	} );
	it( 'adds, moves, removes blocks and inserts placeholders with the keyboard', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		screen.getByRole( 'button', { name: 'Add text block' } ).focus();
		await user.keyboard( '{Enter}' );
		const third = within(
			screen.getByRole( 'group', { name: 'Text block 3' } )
		);
		expect( third.getByRole( 'textbox', { name: 'Text' } ) ).toHaveValue(
			'New text'
		);
		const insert = screen.getByRole( 'button', {
			name: 'Insert Completion date into text block 3',
		} );
		insert.focus();
		await user.keyboard( ' ' );
		expect( third.getByRole( 'textbox', { name: 'Text' } ) ).toHaveValue(
			'New text {date}'
		);
		expect(
			screen.getByRole( 'button', { name: 'Move text block 1 up' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Move text block 3 down' } )
		).toBeDisabled();
		await user.click(
			screen.getByRole( 'button', { name: 'Move text block 3 up' } )
		);
		expect(
			within(
				screen.getByRole( 'group', { name: 'Text block 2' } )
			).getByRole( 'textbox', { name: 'Text' } )
		).toHaveValue( 'New text {date}' );
		await user.click(
			screen.getByRole( 'button', { name: 'Remove text block 2' } )
		);
		expect(
			screen.queryByRole( 'group', { name: 'Text block 3' } )
		).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		await waitFor( () =>
			expect( api.updateCertificate ).toHaveBeenCalled()
		);
		expect(
			vi
				.mocked( api.updateCertificate )
				.mock.calls[ 0 ]![ 1 ].blocks.map( ( block ) => block.id )
		).toEqual( [ 'title', 'student' ] );
	} );
	it( 'chooses a background image from the media library and removes it again', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose background image' } )
		);
		const picker = within(
			screen.getByRole( 'dialog', { name: 'Choose background image' } )
		);
		expect(
			await picker.findByRole( 'list', { name: 'Images' } )
		).toBeInTheDocument();
		await user.click(
			picker.getByRole( 'button', { name: 'Use image Logo' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			screen.getByText( /Background image: #9/ )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		await waitFor( () =>
			expect( api.updateCertificate ).toHaveBeenCalled()
		);
		expect(
			vi.mocked( api.updateCertificate ).mock.calls[ 0 ]![ 1 ]
				.background_id
		).toBe( 9 );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove background image',
			} )
		);
		expect(
			screen.getByText( /No background image\./ )
		).toBeInTheDocument();
	} );
	it( 'reports a media library error inside the picker and closes with Escape', async () => {
		const user = userEvent.setup();
		vi.mocked( api.listMedia ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'rest_forbidden',
				message: 'Sorry, you are not allowed to do that.',
			} )
		);
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose signature image' } )
		);
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to do that.',
				notice
			)
		).toBeInTheDocument();
		await user.keyboard( '{Escape}' );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
	} );
	it( 'assigns and removes courses', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			await screen.findByRole( 'checkbox', { name: 'Geometry' } )
		);
		expect(
			screen.getByRole( 'list', { name: 'Assigned courses' } )
		).toHaveTextContent( 'Geometry' );
		await user.click(
			screen.getByRole( 'button', { name: 'Remove course Algebra' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save template' } )
		);
		await waitFor( () =>
			expect( api.updateCertificate ).toHaveBeenCalled()
		);
		expect(
			vi.mocked( api.updateCertificate ).mock.calls[ 0 ]![ 1 ].course_ids
		).toEqual( [ 12 ] );
	} );
	it( 'searches courses with the Enter key', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await screen.findByRole( 'checkbox', { name: 'Geometry' } );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search courses' } ),
			'geo{Enter}'
		);
		await waitFor( () =>
			expect( api.listCourses ).toHaveBeenLastCalledWith(
				'geo',
				1,
				expect.any( AbortSignal )
			)
		);
	} );
} );
