import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import { getSection, saveSection } from './api';
import { bronze, page, points, at } from './fixtures';
import { useResource, useSection, type ResourceApi } from './hooks';
import type { Level, LevelDraft } from './types';

vi.mock( './api', () => ( {
	getSection: vi.fn(),
	saveSection: vi.fn(),
	getOptions: vi.fn(),
} ) );

beforeEach( () => vi.clearAllMocks() );

function setup( active = true ) {
	vi.mocked( getSection ).mockResolvedValue( points as never );
	return renderHook(
		( { on }: { on: boolean } ) => useSection( 'points', on ),
		{ initialProps: { on: active } }
	);
}

describe( 'useSection', () => {
	it( 'requests nothing until the tab is first active', async () => {
		const view = setup( false );
		expect( getSection ).not.toHaveBeenCalled();
		view.rerender( { on: true } );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		view.rerender( { on: false } );
		view.rerender( { on: true } );
		expect( getSection ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'tracks dirty state and saves the draft', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		expect( view.result.current.dirty ).toBe( false );
		act( () =>
			view.result.current.update( ( draft ) => ( {
				...draft,
				rules: draft.rules.map( ( rule ) =>
					'lesson_complete' === rule.event
						? { ...rule, points: 9 }
						: rule
				),
			} ) )
		);
		expect( view.result.current.dirty ).toBe( true );
		vi.mocked( saveSection ).mockResolvedValue( {
			...points,
			rules: points.rules.map( ( rule ) =>
				'lesson_complete' === rule.event ? { ...rule, points: 9 } : rule
			),
		} as never );
		let ok = false;
		await act( async () => {
			ok = await view.result.current.save();
		} );
		expect( ok ).toBe( true );
		expect( view.result.current.dirty ).toBe( false );
		expect( view.result.current.justSaved ).toBe( true );
		expect( at( view.result.current.saved?.rules ?? [] ).points ).toBe( 9 );
	} );

	it( 'blocks an invalid draft without calling the server', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () =>
			view.result.current.update( ( draft ) => ( {
				...draft,
				rules: draft.rules.map( ( rule ) => ( {
					...rule,
					points: -4,
				} ) ),
			} ) )
		);
		let ok = true;
		await act( async () => {
			ok = await view.result.current.save();
		} );
		expect( ok ).toBe( false );
		expect( saveSection ).not.toHaveBeenCalled();
		expect(
			view.result.current.errors[ 'rules.lesson_complete.points' ]
		).toBeDefined();
	} );

	it( 'ignores a second save while one is pending', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () =>
			view.result.current.update( ( draft ) => ( {
				...draft,
				enabled: false,
			} ) )
		);
		let release: ( value: unknown ) => void = () => undefined;
		vi.mocked( saveSection ).mockReturnValue(
			new Promise( ( resolve ) => {
				release = resolve;
			} ) as never
		);
		let first: Promise< boolean > = Promise.resolve( false );
		act( () => {
			first = view.result.current.save();
		} );
		await act( async () => {
			expect( await view.result.current.save() ).toBe( false );
		} );
		expect( saveSection ).toHaveBeenCalledTimes( 1 );
		await act( async () => {
			release( { ...points, enabled: false } );
			await first;
		} );
	} );

	it( 'keeps the draft and reports server and permission errors', async () => {
		const view = setup();
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		act( () =>
			view.result.current.update( ( draft ) => ( {
				...draft,
				enabled: false,
			} ) )
		);
		vi.mocked( saveSection ).mockRejectedValue(
			new ApiError( {
				status: 500,
				code: 'boom',
				message: 'Server broke',
			} )
		);
		await act( async () => {
			await view.result.current.save();
		} );
		expect( view.result.current.saveError?.message ).toBe( 'Server broke' );
		expect( view.result.current.draft?.enabled ).toBe( false );
		vi.mocked( saveSection ).mockRejectedValue(
			new ApiError( { status: 403, code: 'f', message: 'No' } )
		);
		await act( async () => {
			await view.result.current.save();
		} );
		expect( view.result.current.saveError?.permission ).toBe( true );
	} );

	it( 'reports a load failure and can retry', async () => {
		vi.mocked( getSection ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'x', message: 'Down' } )
		);
		const view = renderHook( () => useSection( 'points', true ) );
		await waitFor( () =>
			expect( view.result.current.loadError ).not.toBeNull()
		);
		vi.mocked( getSection ).mockResolvedValue( points as never );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
	} );

	it( 'cancels the request on unmount', async () => {
		let signal: AbortSignal | undefined;
		vi.mocked( getSection ).mockImplementation( ( _section, given ) => {
			signal = given;
			return new Promise( () => undefined );
		} );
		const view = renderHook( () => useSection( 'points', true ) );
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );

describe( 'useResource', () => {
	function makeApi(): ResourceApi< Level, LevelDraft > {
		return {
			list: vi.fn().mockResolvedValue( page( [ bronze ] ) ),
			create: vi.fn().mockResolvedValue( bronze ),
			update: vi.fn().mockResolvedValue( bronze ),
			remove: vi.fn().mockResolvedValue( undefined ),
		};
	}
	const draft: LevelDraft = {
		name: 'Bronze',
		description: '',
		color: '#aa6633',
		text_color: '#ffffff',
		points: 0,
	};

	it( 'loads once active, then reloads after a change', async () => {
		const api = makeApi();
		const view = renderHook(
			( { on }: { on: boolean } ) => useResource( api, on ),
			{
				initialProps: { on: false },
			}
		);
		expect( api.list ).not.toHaveBeenCalled();
		view.rerender( { on: true } );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		expect( view.result.current.items ).toEqual( [ bronze ] );
		let ok = false;
		await act( async () => {
			ok = await view.result.current.save( null, draft );
		} );
		expect( ok ).toBe( true );
		expect( api.create ).toHaveBeenCalledWith(
			draft,
			expect.any( AbortSignal )
		);
		await waitFor( () => expect( api.list ).toHaveBeenCalledTimes( 2 ) );
		await act( async () => {
			await view.result.current.save( 'bronze', draft );
		} );
		expect( api.update ).toHaveBeenCalledWith(
			'bronze',
			draft,
			expect.any( AbortSignal )
		);
		await act( async () => {
			await view.result.current.remove( 'bronze' );
		} );
		expect( api.remove ).toHaveBeenCalledWith(
			'bronze',
			expect.any( AbortSignal )
		);
	} );

	it( 'reports a failed action and ignores overlapping ones', async () => {
		const api = makeApi();
		let release: () => void = () => undefined;
		api.create = vi.fn().mockReturnValue(
			new Promise< Level >( ( resolve ) => {
				release = () => resolve( bronze );
			} )
		);
		const view = renderHook( () => useResource( api, true ) );
		await waitFor( () =>
			expect( view.result.current.loaded ).toBe( true )
		);
		let first: Promise< boolean > = Promise.resolve( false );
		act( () => {
			first = view.result.current.save( null, draft );
		} );
		await waitFor( () =>
			expect( view.result.current.working ).toBe( true )
		);
		await act( async () => {
			expect( await view.result.current.remove( 'bronze' ) ).toBe(
				false
			);
		} );
		expect( api.remove ).not.toHaveBeenCalled();
		await act( async () => {
			release();
			await first;
		} );
		api.remove = vi
			.fn()
			.mockRejectedValue(
				new ApiError( { status: 422, code: 'x', message: 'Refused' } )
			);
		await act( async () => {
			expect( await view.result.current.remove( 'bronze' ) ).toBe(
				false
			);
		} );
		expect( view.result.current.actionError?.message ).toBe( 'Refused' );
		act( () => view.result.current.clearError() );
		expect( view.result.current.actionError ).toBeNull();
	} );

	it( 'reports a load failure', async () => {
		const api = makeApi();
		api.list = vi
			.fn()
			.mockRejectedValue(
				new ApiError( { status: 403, code: 'f', message: 'No' } )
			);
		const view = renderHook( () => useResource( api, true ) );
		await waitFor( () =>
			expect( view.result.current.loadError?.permission ).toBe( true )
		);
	} );
} );
