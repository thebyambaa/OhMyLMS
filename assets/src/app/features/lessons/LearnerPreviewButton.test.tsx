import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import * as client from '../../lib/api';
import { ApiError } from '../../lib/api';
import * as preview from './learnerPreview';
import { LearnerPreviewButton } from './LearnerPreviewButton';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof client >() ),
	post: vi.fn(),
} ) );

const links = {
	id: 12,
	url: 'http://800.local/ohmylms-lesson/one/?ohmylms_lesson_preview=12&preview_nonce=abc',
	bypass_url:
		'http://800.local/ohmylms-lesson/one/?ohmylms_lesson_preview=12&preview_bypass=1',
	expires: 1_800_000_000,
	status: 'publish',
};

function fakeTab() {
	return {
		opener: {} as unknown,
		closed: false,
		close: vi.fn(),
		location: { href: '' },
	};
}

beforeEach( () => vi.restoreAllMocks() );

it( 'validates the minted links', async () => {
	vi.mocked( client.post ).mockResolvedValue( links );
	await expect( preview.createLearnerPreview( 12 ) ).resolves.toEqual(
		links
	);
	expect( client.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/lessons/12/learner-preview',
		undefined,
		{ signal: undefined }
	);
	await expect( preview.createLearnerPreview( 13 ) ).rejects.toThrow();
	expect( () =>
		preview.parseLearnerPreview( { ...links, url: 'javascript:alert(1)' } )
	).toThrow();
	expect( () =>
		preview.parseLearnerPreview( { ...links, expires: '1' } )
	).toThrow();
} );

it( 'opens the tab in the click and points it at the signed link', async () => {
	const tab = fakeTab();
	const open = vi
		.spyOn( window, 'open' )
		.mockReturnValue( tab as unknown as Window );
	vi.spyOn( preview, 'createLearnerPreview' ).mockResolvedValue( links );
	const user = userEvent.setup();
	render( <LearnerPreviewButton lessonId={ 12 } dirty={ false } /> );
	await user.click(
		screen.getByRole( 'button', { name: 'Preview as learner' } )
	);
	expect( open ).toHaveBeenCalledWith( '', '_blank' );
	expect( tab.opener ).toBeNull();
	await waitFor( () => expect( tab.location.href ).toBe( links.url ) );
	expect( preview.createLearnerPreview ).toHaveBeenCalledWith(
		12,
		expect.any( AbortSignal )
	);
	expect(
		screen.getByText( /Opens the lesson as an enrolled learner sees it/ )
	).toBeInTheDocument();
} );

it( 'offers the link when the browser blocked the tab, and says unsaved edits are not shown', async () => {
	vi.spyOn( window, 'open' ).mockReturnValue( null );
	vi.spyOn( preview, 'createLearnerPreview' ).mockResolvedValue( links );
	const user = userEvent.setup();
	render( <LearnerPreviewButton lessonId={ 12 } dirty /> );
	expect(
		screen.getByText( /Preview shows the last saved version/ )
	).toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Preview as learner' } )
	);
	const link = await screen.findByRole( 'link', {
		name: 'Open the learner preview',
	} );
	expect( link ).toHaveAttribute( 'href', links.url );
	expect( link ).toHaveAttribute( 'target', '_blank' );
} );

it( 'closes the tab and shows the refusal', async () => {
	const tab = fakeTab();
	vi.spyOn( window, 'open' ).mockReturnValue( tab as unknown as Window );
	vi.spyOn( preview, 'createLearnerPreview' ).mockRejectedValue(
		new ApiError( {
			code: 'ohmylms_lesson_preview_forbidden',
			status: 403,
			message: 'You cannot preview this lesson.',
		} )
	);
	const user = userEvent.setup();
	render( <LearnerPreviewButton lessonId={ 12 } dirty={ false } /> );
	await user.click(
		screen.getByRole( 'button', { name: 'Preview as learner' } )
	);
	await waitFor( () =>
		expect(
			document.querySelector( '.components-notice.is-error' )
		).toHaveTextContent( 'You cannot preview this lesson.' )
	);
	expect( tab.close ).toHaveBeenCalled();
	expect( tab.location.href ).toBe( '' );
} );
