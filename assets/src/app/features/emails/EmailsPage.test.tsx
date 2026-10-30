import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import EmailsPage from './EmailsPage';
import { newOrder, preview, quizGraded, settings } from './fixtures';
import { emails } from './index';

vi.mock( './api', () => ( {
	listEmails: vi.fn(),
	getEmail: vi.fn(),
	saveEmail: vi.fn(),
	setEmailEnabled: vi.fn(),
	previewEmail: vi.fn(),
	sendTestEmail: vi.fn(),
	getEmailSettings: vi.fn(),
	saveEmailSettings: vi.fn(),
} ) );

function apiError(
	status: number,
	code: string,
	message: string,
	details?: unknown
) {
	return new ApiError( { status, code, message, details } );
}

/**
 * A notice's text; WordPress also repeats it in an invisible live region.
 * @param text
 */
const notice = ( text: string | RegExp ) =>
	screen.findByText( text, { selector: '.components-notice__content' } );

function page( entry = '/emails' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route path="/emails" element={ <EmailsPage /> } />
				<Route path="/webhooks" element={ <p>Webhooks page</p> } />
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.mocked( api.listEmails ).mockResolvedValue( {
		items: [ quizGraded, newOrder ],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( api.getEmailSettings ).mockResolvedValue( settings );
	vi.mocked( api.getEmail ).mockResolvedValue( quizGraded );
	vi.mocked( api.previewEmail ).mockResolvedValue( preview );
} );

describe( 'emails feature', () => {
	it( 'is hidden and makes no requests without manage_options', () => {
		renderShell( [ emails ], { route: '/emails' } );
		expect(
			screen.queryByRole( 'link', { name: 'Emails' } )
		).not.toBeInTheDocument();
		expect( api.listEmails ).not.toHaveBeenCalled();
	} );

	it( 'opens as the Engagement > Emails tab for administrators', async () => {
		renderShell( [ emails ], {
			route: '/emails',
			capabilities: { manage_options: true },
		} );
		expect(
			await screen.findByRole( 'table', { name: 'System emails' } )
		).toBeInTheDocument();
		expect( emails ).toMatchObject( {
			group: 'engagement',
			tab: 'emails',
		} );
	} );
} );

describe( 'email list', () => {
	it( 'shows a status while loading, then a named table with one switch per email', async () => {
		page();
		expect( screen.getByText( 'Loading emails…' ) ).toHaveAttribute(
			'role',
			'status'
		);
		const table = await screen.findByRole( 'table', {
			name: 'System emails',
		} );
		expect(
			within( table ).getByRole( 'link', { name: quizGraded.title } )
		).toHaveAttribute( 'href', '/emails?email=student_quiz_graded' );
		const first = screen.getByRole( 'checkbox', {
			name: `Send “${ quizGraded.title }”`,
		} );
		const second = screen.getByRole( 'checkbox', {
			name: `Send “${ newOrder.title }”`,
		} );
		expect( first ).toBeChecked();
		expect( second ).not.toBeChecked();
		expect(
			screen.getAllByText( '2 emails found.' )[ 0 ]
		).toBeInTheDocument();
	} );

	it( 'switches an email on with the keyboard, optimistically', async () => {
		const user = userEvent.setup();
		let finish!: ( email: typeof newOrder ) => void;
		vi.mocked( api.setEmailEnabled ).mockReturnValue(
			new Promise( ( done ) => {
				finish = done;
			} )
		);
		page();
		const toggle = await screen.findByRole( 'checkbox', {
			name: `Send “${ newOrder.title }”`,
		} );
		await user.tab();
		toggle.focus();
		await user.keyboard( ' ' );
		expect( toggle ).toBeChecked();
		expect( toggle ).toBeDisabled();
		expect( api.setEmailEnabled ).toHaveBeenCalledWith(
			newOrder.id,
			true,
			expect.any( AbortSignal )
		);
		finish( { ...newOrder, enabled: true } );
		await waitFor( () => expect( toggle ).toBeEnabled() );
		expect( toggle ).toBeChecked();
	} );

	it( 'puts the switch back and announces the permission error from the server', async () => {
		const user = userEvent.setup();
		vi.mocked( api.setEmailEnabled ).mockRejectedValue(
			apiError(
				403,
				'ohmylms_email_cannot_edit',
				'Sorry, you are not allowed to edit emails.'
			)
		);
		page();
		const toggle = await screen.findByRole( 'checkbox', {
			name: `Send “${ quizGraded.title }”`,
		} );
		await user.click( toggle );
		expect(
			await notice( 'Sorry, you are not allowed to edit emails.' )
		).toBeInTheDocument();
		expect( toggle ).toBeChecked();
		expect( toggle ).toBeEnabled();
	} );

	it( 'filters on the server by submitting the search form', async () => {
		const user = userEvent.setup();
		page();
		await screen.findByRole( 'table', { name: 'System emails' } );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search emails' } ),
			'quiz'
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Sent to' } ),
			'student'
		);
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		await waitFor( () =>
			expect( api.listEmails ).toHaveBeenLastCalledWith(
				{ search: 'quiz', recipient_type: 'student' },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'reports a failed load and retries', async () => {
		const user = userEvent.setup();
		vi.mocked( api.listEmails ).mockRejectedValueOnce(
			apiError( 500, 'internal', 'The list could not be loaded.' )
		);
		page();
		expect(
			await notice( /The list could not be loaded/ )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'System emails' } )
		).toBeInTheDocument();
	} );

	it( 'says when nothing matches', async () => {
		vi.mocked( api.listEmails ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		page();
		expect(
			await screen.findByText( 'No emails found.' )
		).toBeInTheDocument();
	} );
} );

describe( 'email editor', () => {
	async function openEditor() {
		const view = page( '/emails?email=student_quiz_graded' );
		await screen.findByRole( 'heading', {
			name: quizGraded.title,
			level: 2,
		} );
		return view;
	}

	it( 'loads the email, renders the sandboxed preview and shows the supported tags', async () => {
		await openEditor();
		expect( api.getEmail ).toHaveBeenCalledWith(
			'student_quiz_graded',
			expect.any( AbortSignal )
		);
		expect( screen.getByLabelText( 'Subject' ) ).toHaveValue(
			quizGraded.subject
		);
		expect( screen.getByLabelText( 'Message (HTML)' ) ).toHaveValue(
			quizGraded.additional_content
		);
		expect(
			screen.queryByLabelText( 'Recipients' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByLabelText( 'Course suggestion text' )
		).not.toBeInTheDocument();
		const frame = await screen.findByTitle(
			'Email preview',
			{},
			{ timeout: 3000 }
		);
		expect( frame ).toHaveAttribute( 'sandbox', '' );
		expect( frame.getAttribute( 'srcdoc' ) ).toContain(
			'Dear Alex Learner'
		);
		expect( screen.getByText( preview.subject ) ).toBeInTheDocument();
		expect( api.previewEmail ).toHaveBeenCalledWith(
			'student_quiz_graded',
			expect.objectContaining( { subject: quizGraded.subject } ),
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	it( 'saves only the changed fields and confirms', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveEmail ).mockResolvedValue( {
			...quizGraded,
			heading: 'A new heading',
		} );
		await openEditor();
		const heading = screen.getByLabelText( 'Heading' );
		await user.clear( heading );
		await user.type( heading, 'A new heading' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( api.saveEmail ).toHaveBeenCalledWith(
				'student_quiz_graded',
				{
					heading: 'A new heading',
				}
			)
		);
		expect( await notice( 'Email saved.' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	it( 'shows inline errors, moves focus to the first one and sends nothing', async () => {
		const user = userEvent.setup();
		await openEditor();
		const subject = screen.getByLabelText( 'Subject' );
		await user.clear( subject );
		await user.type( subject, 'About {{assignment_name}' );
		const heading = screen.getByLabelText( 'Heading' );
		await user.clear( heading );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText( 'Enter a heading.' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'This email does not support: {assignment_name}' )
		).toBeInTheDocument();
		expect( heading ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( subject ).toHaveAttribute( 'aria-invalid', 'true' );
		await waitFor( () => expect( subject ).toHaveFocus() );
		expect(
			await notice( 'Fix the highlighted fields.' )
		).toBeInTheDocument();
		expect( api.saveEmail ).not.toHaveBeenCalled();
	} );

	it( 'maps a server validation error onto its field and keeps the edit', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveEmail ).mockRejectedValue(
			apiError(
				422,
				'ohmylms_email_invalid_field',
				'The button link must be a full http or https address.',
				{
					field: 'button_link',
				}
			)
		);
		await openEditor();
		await user.type(
			screen.getByLabelText( 'Button link' ),
			'https://example.com/ok'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText(
				'The button link must be a full http or https address.'
			)
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Button link' ) ).toHaveValue(
			'https://example.com/ok'
		);
		expect( screen.getByLabelText( 'Button link' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
	} );

	it( 'shows a permission error from the server and keeps the draft', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveEmail ).mockRejectedValue(
			apiError(
				403,
				'ohmylms_email_cannot_edit',
				'Sorry, you are not allowed to edit emails.'
			)
		);
		await openEditor();
		await user.type( screen.getByLabelText( 'Button text' ), ' now' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await notice( 'Sorry, you are not allowed to edit emails.' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Button text' ) ).toHaveValue(
			'View result now'
		);
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toBeEnabled();
	} );

	it( 'shows a load failure with a way back', async () => {
		vi.mocked( api.getEmail ).mockRejectedValue(
			apiError( 404, 'ohmylms_email_not_found', 'Email not found.' )
		);
		page( '/emails?email=nope_missing' );
		expect( await notice( /Email not found/ ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to all emails' } )
		).toBeInTheDocument();
	} );

	it( 'sends a test of the unsaved draft and reports where it went', async () => {
		const user = userEvent.setup();
		vi.mocked( api.sendTestEmail ).mockResolvedValue( {
			sent: true,
			to: 'admin@example.com',
			subject: '[Test] S',
		} );
		await openEditor();
		await user.type( screen.getByLabelText( 'Heading' ), '!' );
		await user.click(
			screen.getByRole( 'button', { name: 'Send test email to me' } )
		);
		expect(
			await notice( 'Test email sent to admin@example.com.' )
		).toBeInTheDocument();
		expect( api.sendTestEmail ).toHaveBeenCalledWith(
			'student_quiz_graded',
			expect.objectContaining( { heading: `${ quizGraded.heading }!` } )
		);
		const body = vi.mocked( api.sendTestEmail ).mock.calls[ 0 ]?.[ 1 ];
		expect( body ).not.toHaveProperty( 'recipient_email' );
	} );

	it( 'explains a rate limited test and a failed send', async () => {
		const user = userEvent.setup();
		vi.mocked( api.sendTestEmail ).mockRejectedValueOnce(
			apiError(
				429,
				'ohmylms_email_test_rate_limited',
				'A test email was sent a moment ago. Wait before sending another.'
			)
		);
		vi.mocked( api.sendTestEmail ).mockRejectedValueOnce(
			apiError(
				500,
				'ohmylms_email_send_failed',
				'WordPress could not send the test email.'
			)
		);
		await openEditor();
		const button = screen.getByRole( 'button', {
			name: 'Send test email to me',
		} );
		await user.click( button );
		expect(
			await notice( /Wait before sending another/ )
		).toBeInTheDocument();
		await user.click( button );
		expect(
			await notice( 'WordPress could not send the test email.' )
		).toBeInTheDocument();
	} );

	it( 'does not send a test while the draft is invalid', async () => {
		const user = userEvent.setup();
		await openEditor();
		await user.clear( screen.getByLabelText( 'Subject' ) );
		await user.click(
			screen.getByRole( 'button', { name: 'Send test email to me' } )
		);
		expect(
			await notice( 'Fix the highlighted fields before sending a test.' )
		).toBeInTheDocument();
		expect( api.sendTestEmail ).not.toHaveBeenCalled();
	} );

	it( 'warns about unknown tags reported by the preview', async () => {
		vi.mocked( api.previewEmail ).mockResolvedValue( {
			...preview,
			unknown_tags: [ '{nope}' ],
		} );
		await openEditor();
		expect(
			await screen.findByText(
				'These tags are not replaced: {nope}',
				{ selector: '.components-notice__content' },
				{ timeout: 3000 }
			)
		).toBeInTheDocument();
	} );

	it( 'inserts a merge tag from the keyboard into the message', async () => {
		const user = userEvent.setup();
		await openEditor();
		const message = screen.getByLabelText( 'Message (HTML)' );
		await user.clear( message );
		await user.type( message, 'Hello ' );
		const group = screen.getByRole( 'group', {
			name: 'Insert a merge tag',
		} );
		const tag = within( group ).getByRole( 'button', {
			name: '{course_name}',
		} );
		tag.focus();
		await user.keyboard( '{Enter}' );
		expect( message ).toHaveValue( 'Hello {course_name}' );
		await waitFor( () => expect( message ).toHaveFocus() );
	} );

	it( 'shows recipients and the suggestion text only where supported', async () => {
		vi.mocked( api.getEmail ).mockResolvedValue( {
			...newOrder,
			course_suggestion_text: 'Try Geometry',
		} );
		page( '/emails?email=creator_new_order' );
		expect( await screen.findByLabelText( 'Recipients' ) ).toHaveValue(
			'owner@example.com'
		);
		expect( screen.getByLabelText( 'Course suggestion text' ) ).toHaveValue(
			'Try Geometry'
		);
	} );

	it( 'asks before discarding edits when leaving by a link, and lets the user stay', async () => {
		const user = userEvent.setup();
		await openEditor();
		await user.type( screen.getByLabelText( 'Heading' ), '!' );
		await user.click(
			screen.getByRole( 'link', { name: '← Back to all emails' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Discard unsaved changes?',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Heading' ) ).toHaveValue(
			`${ quizGraded.heading }!`
		);

		await user.click(
			screen.getByRole( 'link', { name: '← Back to all emails' } )
		);
		await user.click(
			within( await screen.findByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Discard and leave',
			} )
		);
		expect(
			await screen.findByRole( 'table', { name: 'System emails' } )
		).toBeInTheDocument();
	} );

	it( 'leaves without a prompt when nothing changed, and discard restores the saved text', async () => {
		const user = userEvent.setup();
		await openEditor();
		await user.type( screen.getByLabelText( 'Heading' ), '!' );
		await user.click(
			screen.getByRole( 'button', { name: 'Discard changes' } )
		);
		expect( screen.getByLabelText( 'Heading' ) ).toHaveValue(
			quizGraded.heading
		);
		await user.click(
			screen.getByRole( 'link', { name: '← Back to all emails' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			await screen.findByRole( 'table', { name: 'System emails' } )
		).toBeInTheDocument();
	} );
} );

describe( 'sender and branding settings', () => {
	it( 'saves only the changed fields', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveEmailSettings ).mockResolvedValue( {
			...settings,
			sender_name: 'New Academy',
			button_position: 'center',
		} );
		page();
		const name = await screen.findByLabelText( 'Sender name' );
		expect( name ).toHaveValue( 'Example Academy' );
		await user.clear( name );
		await user.type( name, 'New Academy' );
		await user.selectOptions(
			screen.getByLabelText( 'Button position' ),
			'center'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		await waitFor( () =>
			expect( api.saveEmailSettings ).toHaveBeenCalledWith( {
				sender_name: 'New Academy',
				button_position: 'center',
			} )
		);
		expect( await notice( 'Settings saved.' ) ).toBeInTheDocument();
	} );

	it( 'rejects bad colors and sender text before sending', async () => {
		const user = userEvent.setup();
		page();
		const color = await screen.findByLabelText( 'Accent color' );
		await user.clear( color );
		await user.type( color, 'purple' );
		const name = screen.getByLabelText( 'Sender name' );
		await user.clear( name );
		await user.type( name, 'Evil <x@example.com>' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			await screen.findByText( 'Use a color like #6E42D3.' )
		).toBeInTheDocument();
		expect(
			screen.getByText( /without line breaks, quotes or angle brackets/ )
		).toBeInTheDocument();
		await waitFor( () => expect( name ).toHaveFocus() );
		expect( api.saveEmailSettings ).not.toHaveBeenCalled();
	} );

	it( 'shows a server validation error on its field and a permission error as a message', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveEmailSettings ).mockRejectedValueOnce(
			apiError(
				422,
				'ohmylms_email_invalid_field',
				'The sender address must be a valid email address.',
				{
					field: 'sender_email',
				}
			)
		);
		vi.mocked( api.saveEmailSettings ).mockRejectedValueOnce(
			apiError(
				403,
				'ohmylms_email_cannot_edit',
				'Sorry, you are not allowed to edit emails.'
			)
		);
		page();
		const address = await screen.findByLabelText( 'Sender email' );
		await user.clear( address );
		await user.type( address, 'bad@example.com' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			await screen.findByText(
				'The sender address must be a valid email address.'
			)
		).toBeInTheDocument();
		expect( address ).toHaveAttribute( 'aria-invalid', 'true' );
		await user.type( address, 'x' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			await notice( 'Sorry, you are not allowed to edit emails.' )
		).toBeInTheDocument();
	} );

	it( 'reports a failed load of the settings', async () => {
		vi.mocked( api.getEmailSettings ).mockRejectedValue(
			new Error( 'Settings offline' )
		);
		page();
		expect( await notice( /Settings offline/ ) ).toBeInTheDocument();
	} );
} );
