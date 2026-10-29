import { act, renderHook, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { ReactNode } from 'react';
import { ApiError } from '../../lib/api';
import { listEmails, previewEmail, setEmailEnabled } from './api';
import { newOrder, preview, quizGraded } from './fixtures';
import {
	PREVIEW_DELAY_MS,
	useEmailPreview,
	useEmails,
	useUnsavedChangesGuard,
} from './hooks';
import type { EmailTemplate } from './types';

vi.mock( './api', () => ( {
	listEmails: vi.fn(),
	previewEmail: vi.fn(),
	setEmailEnabled: vi.fn(),
	getEmail: vi.fn(),
	getEmailSettings: vi.fn(),
} ) );

function collection( items: EmailTemplate[] ) {
	return { items, total: items.length, totalPages: 1 };
}

describe( 'useEmails', () => {
	beforeEach( () => {
		vi.mocked( listEmails ).mockResolvedValue(
			collection( [ quizGraded, newOrder ] )
		);
	} );

	it( 'applies a toggle at once and keeps the server copy', async () => {
		let resolve!: ( value: EmailTemplate ) => void;
		vi.mocked( setEmailEnabled ).mockReturnValue(
			new Promise( ( done ) => {
				resolve = done;
			} )
		);
		const view = renderHook( useEmails );
		await waitFor( () =>
			expect( view.result.current.items ).toHaveLength( 2 )
		);

		act( () => {
			void view.result.current.toggle( newOrder.id, true );
		} );
		expect(
			view.result.current.items.find(
				( item ) => item.id === newOrder.id
			)?.enabled
		).toBe( true );
		expect( view.result.current.pending ).toEqual( [ newOrder.id ] );

		// A second toggle of the same email while one is running is ignored.
		act( () => {
			void view.result.current.toggle( newOrder.id, false );
		} );
		expect( setEmailEnabled ).toHaveBeenCalledTimes( 1 );

		await act( async () => {
			resolve( { ...newOrder, enabled: true, subject: 'From server' } );
		} );
		expect(
			view.result.current.items.find(
				( item ) => item.id === newOrder.id
			)?.subject
		).toBe( 'From server' );
		expect( view.result.current.pending ).toEqual( [] );
		expect( view.result.current.toggleError ).toBeNull();
	} );

	it( 'rolls back and reports the server message when a toggle fails', async () => {
		vi.mocked( setEmailEnabled ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_email_cannot_edit',
				message: 'Sorry, you are not allowed to edit emails.',
			} )
		);
		const view = renderHook( useEmails );
		await waitFor( () =>
			expect( view.result.current.items ).toHaveLength( 2 )
		);

		await act( async () => {
			await view.result.current.toggle( quizGraded.id, false );
		} );
		expect(
			view.result.current.items.find(
				( item ) => item.id === quizGraded.id
			)?.enabled
		).toBe( true );
		expect( view.result.current.toggleError ).toBe(
			'Sorry, you are not allowed to edit emails.'
		);
		expect( view.result.current.pending ).toEqual( [] );

		act( () => view.result.current.clearToggleError() );
		expect( view.result.current.toggleError ).toBeNull();
	} );

	it( 'reloads with the new filters and ignores an obsolete response', async () => {
		let resolveOld!: ( value: ReturnType< typeof collection > ) => void;
		vi.mocked( listEmails ).mockImplementationOnce(
			() =>
				new Promise( ( done ) => {
					resolveOld = done;
				} )
		);
		vi.mocked( listEmails ).mockResolvedValue( collection( [ newOrder ] ) );
		const view = renderHook( useEmails );
		const oldSignal = vi.mocked( listEmails ).mock.calls[ 0 ]?.[ 1 ];
		act( () =>
			view.result.current.setFilters( {
				search: 'order',
				recipient_type: 'creator',
			} )
		);
		await waitFor( () =>
			expect(
				view.result.current.items.map( ( item ) => item.id )
			).toEqual( [ newOrder.id ] )
		);
		expect( oldSignal?.aborted ).toBe( true );
		await act( async () => {
			resolveOld( collection( [ quizGraded ] ) );
		} );
		expect( view.result.current.items.map( ( item ) => item.id ) ).toEqual(
			[ newOrder.id ]
		);
		expect( listEmails ).toHaveBeenLastCalledWith(
			{ search: 'order', recipient_type: 'creator' },
			expect.any( AbortSignal )
		);
	} );

	it( 'reports a failed list and cancels toggles on unmount', async () => {
		vi.mocked( listEmails ).mockRejectedValueOnce( new Error( 'Offline' ) );
		const failed = renderHook( useEmails );
		await waitFor( () =>
			expect( failed.result.current.error ).toBe( 'Offline' )
		);
		failed.unmount();

		vi.mocked( setEmailEnabled ).mockReturnValue( new Promise( () => {} ) );
		const view = renderHook( useEmails );
		await waitFor( () =>
			expect( view.result.current.items ).toHaveLength( 2 )
		);
		act( () => {
			void view.result.current.toggle( quizGraded.id, false );
		} );
		const signal = vi.mocked( setEmailEnabled ).mock.calls[ 0 ]?.[ 2 ];
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );

describe( 'useEmailPreview', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );
	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'waits for a pause in typing and keeps only the newest request', async () => {
		vi.mocked( previewEmail ).mockResolvedValue( preview );
		const view = renderHook(
			( { heading }: { heading: string } ) =>
				useEmailPreview( 'student_quiz_graded', { heading }, true ),
			{ initialProps: { heading: 'A' } }
		);
		await act( async () => {
			vi.advanceTimersByTime( PREVIEW_DELAY_MS - 100 );
		} );
		view.rerender( { heading: 'AB' } );
		await act( async () => {
			vi.advanceTimersByTime( PREVIEW_DELAY_MS - 100 );
		} );
		expect( previewEmail ).not.toHaveBeenCalled();
		await act( async () => {
			vi.advanceTimersByTime( 200 );
		} );
		expect( previewEmail ).toHaveBeenCalledTimes( 1 );
		expect( previewEmail ).toHaveBeenCalledWith(
			'student_quiz_graded',
			{ heading: 'AB' },
			expect.any( AbortSignal )
		);
		expect( view.result.current.preview ).toEqual( preview );
	} );

	it( 'does nothing while paused and reports errors', async () => {
		const view = renderHook(
			( { active }: { active: boolean } ) =>
				useEmailPreview( 'student_quiz_graded', {}, active ),
			{ initialProps: { active: false } }
		);
		await act( async () => {
			vi.advanceTimersByTime( PREVIEW_DELAY_MS * 2 );
		} );
		expect( previewEmail ).not.toHaveBeenCalled();

		vi.mocked( previewEmail ).mockRejectedValue(
			new Error( 'Preview broke' )
		);
		view.rerender( { active: true } );
		await act( async () => {
			vi.advanceTimersByTime( PREVIEW_DELAY_MS + 10 );
		} );
		expect( view.result.current.error ).toBe( 'Preview broke' );
		expect( view.result.current.loading ).toBe( false );
	} );
} );

describe( 'useUnsavedChangesGuard', () => {
	const wrapper = ( { children }: { children: ReactNode } ) => (
		<MemoryRouter initialEntries={ [ '/emails?email=x' ] }>
			{ children }
		</MemoryRouter>
	);

	it( 'navigates straight away when nothing changed', () => {
		const view = renderHook( () => useUnsavedChangesGuard( false ), {
			wrapper,
		} );
		act( () => view.result.current.requestLeave( '/emails' ) );
		expect( view.result.current.pendingPath ).toBeNull();
	} );

	it( 'holds back leaving while dirty until the user decides', () => {
		const view = renderHook( () => useUnsavedChangesGuard( true ), {
			wrapper,
		} );
		act( () => view.result.current.requestLeave( '/emails' ) );
		expect( view.result.current.pendingPath ).toBe( '/emails' );
		act( () => view.result.current.stay() );
		expect( view.result.current.pendingPath ).toBeNull();
		act( () => view.result.current.requestLeave( '/webhooks' ) );
		act( () => view.result.current.confirmLeave() );
		expect( view.result.current.pendingPath ).toBeNull();
	} );

	it( 'intercepts internal link clicks and the unload event only while dirty', () => {
		const link = document.createElement( 'a' );
		link.setAttribute( 'href', '#/webhooks' );
		document.body.append( link );
		const outside = document.createElement( 'a' );
		outside.setAttribute( 'href', 'https://example.com' );
		document.body.append( outside );

		const view = renderHook( () => useUnsavedChangesGuard( true ), {
			wrapper,
		} );
		const external = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		outside.dispatchEvent( external );
		expect( external.defaultPrevented ).toBe( false );

		const click = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		act( () => {
			link.dispatchEvent( click );
		} );
		expect( click.defaultPrevented ).toBe( true );
		expect( view.result.current.pendingPath ).toBe( '/webhooks' );

		const unload = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( unload );
		expect( unload.defaultPrevented ).toBe( true );

		view.unmount();
		const free = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		link.dispatchEvent( free );
		expect( free.defaultPrevented ).toBe( false );
		const afterUnload = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( afterUnload );
		expect( afterUnload.defaultPrevented ).toBe( false );
		link.remove();
		outside.remove();
	} );

	it( 'does not intercept modified clicks', () => {
		const link = document.createElement( 'a' );
		link.setAttribute( 'href', '#/webhooks' );
		document.body.append( link );
		renderHook( () => useUnsavedChangesGuard( true ), { wrapper } );
		const click = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
			ctrlKey: true,
		} );
		link.dispatchEvent( click );
		expect( click.defaultPrevented ).toBe( false );
		link.remove();
	} );
} );
