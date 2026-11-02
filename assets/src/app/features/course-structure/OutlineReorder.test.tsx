import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { OutlineWorkspace } from './CourseEditorPage';
import { outline } from './fixtures';
import type { Activity, Outline } from './model';

vi.mock( './api', () => ( {
	readOutline: vi.fn(),
	saveOutline: vi.fn(),
	deleteUnit: vi.fn(),
	findResources: vi.fn(),
	createResource: vi.fn(),
} ) );
vi.mock( '../curriculum', () => ( { CoursePlacement: () => null } ) );

const activity = ( id: string, chapter: number, name: string ): Activity => ( {
	id,
	type: 'lesson',
	content_id: 20 + Number( id.slice( -1 ) ),
	chapter_id: chapter,
	required: true,
	name,
} );
/** Test data only: Algebra holds three activities, Geometry one. */
const draft: Outline = {
	...outline,
	program: {
		...outline.program,
		items: [
			outline.program.items[ 0 ]!,
			activity( 'activity-2', 8, 'Check' ),
			activity( 'activity-3', 8, 'Review' ),
			activity( 'activity-4', 9, 'Essay' ),
		],
	},
};
const row = ( key: string ) =>
	document.querySelector< HTMLElement >( `[data-oml-drop="${ key }"]` )!;
const grip = ( key: string ) =>
	row( key ).querySelector< HTMLElement >( '[title="Drag to reorder"]' )!;
const names = ( unit: string ) =>
	within( screen.getByRole( 'region', { name: unit } ) )
		.getAllByRole( 'listitem' )
		.map( ( item ) => item.textContent?.match( /⠿(\w+)/ )?.[ 1 ] );
const saved = ( index = 0 ) =>
	vi.mocked( api.saveOutline ).mock.calls[ index ]![ 1 ] as Outline;

let under: Element | null = null;
beforeEach( () => {
	vi.resetAllMocks();
	under = null;
	Object.defineProperty( document, 'elementFromPoint', {
		configurable: true,
		value: () => under,
	} );
	vi.mocked( api.saveOutline ).mockImplementation( async ( next ) => ( {
		...next,
		revision: 'saved',
	} ) );
} );
function open( initial: Outline = draft ) {
	const reload = vi.fn();
	render(
		<MemoryRouter>
			<OutlineWorkspace initial={ initial } reload={ reload } />
		</MemoryRouter>
	);
	return reload;
}
/**
 * Press a grip, move over a row and release.
 * @param source Dragged row key.
 * @param target Row key under the pointer.
 * @param after  Release over the lower half.
 */
function dragTo( source: string, target: string, after: boolean ) {
	const handle = grip( source );
	fireEvent.pointerDown( handle, { button: 0, pointerId: 1 } );
	under = row( target ).firstElementChild ?? row( target );
	fireEvent.pointerMove( handle, { clientX: 1, clientY: after ? 1 : 0 } );
	fireEvent.pointerUp( handle, { pointerId: 1 } );
}

describe( 'dragging', () => {
	it( 'moves an activity into another unit and saves it with the read revision', async () => {
		open();
		dragTo( 'activity-1', 'activity-4', true );
		expect( names( 'Geometry' ) ).toEqual( [ 'Essay', 'First' ] );
		expect(
			screen.getByText( 'Moved First lesson to position 2 in Geometry.' )
		).toBeInTheDocument();
		expect( document.activeElement ).toBe( row( 'activity-1' ) );
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
		expect( api.saveOutline ).toHaveBeenCalledOnce();
		const payload = saved();
		expect( payload.revision ).toBe( 'read-token' );
		expect( payload.program.items[ 3 ] ).toMatchObject( {
			id: 'activity-1',
			chapter_id: 9,
			skill_ids: [ 7 ],
		} );
		expect( payload.program.items[ 3 ] ).not.toHaveProperty( 'group_id' );
		expect( screen.getByText( 'Saved draft' ) ).toBeInTheDocument();
	} );
	it( 'reorders within a unit before the hovered row', async () => {
		open();
		dragTo( 'activity-3', 'activity-1', false );
		expect( names( 'Algebra' ) ).toEqual( [ 'Review', 'First', 'Check' ] );
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
	} );
	it( 'moves a unit and keeps every placement', async () => {
		open();
		dragTo( 'unit:8', 'unit:9', true );
		expect(
			screen.getByText( 'Moved unit Algebra to position 2.' )
		).toBeInTheDocument();
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
		expect( saved().units.map( ( unit ) => unit.id ) ).toEqual( [ 9, 8 ] );
		expect( saved().program ).toEqual( draft.program );
		expect( document.activeElement ).toBe( row( 'unit:8' ) );
	} );
	it( 'cancels with Escape, a release away from rows, or a plain click', () => {
		open();
		const handle = grip( 'activity-1' );
		fireEvent.pointerDown( handle, { button: 0 } );
		under = row( 'activity-4' );
		fireEvent.pointerMove( handle, { clientY: 1 } );
		expect( row( 'activity-4' ).style.boxShadow ).toContain(
			'var(--wp-admin-theme-color)'
		);
		fireEvent.keyDown( window, { key: 'Escape' } );
		fireEvent.pointerUp( handle );
		fireEvent.pointerDown( handle, { button: 0 } );
		under = row( 'activity-4' );
		fireEvent.pointerMove( handle, { clientY: 1 } );
		under = document.body;
		fireEvent.pointerMove( handle, { clientY: 1 } );
		fireEvent.pointerUp( handle );
		fireEvent.pointerDown( handle, { button: 0 } );
		fireEvent.pointerUp( handle );
		fireEvent.pointerDown( handle, { button: 2 } );
		expect( names( 'Algebra' ) ).toEqual( [ 'First', 'Check', 'Review' ] );
		expect( api.saveOutline ).not.toHaveBeenCalled();
	} );
	it( 'ignores new drags while a move is saving and focuses the moved row meanwhile', async () => {
		let finish: ( value: Outline ) => void = () => {};
		vi.mocked( api.saveOutline ).mockImplementation(
			( next ) =>
				new Promise( ( resolve ) => {
					finish = () => resolve( { ...next, revision: 'saved' } );
				} )
		);
		open();
		dragTo( 'activity-2', 'activity-4', false );
		expect( document.activeElement ).toBe( row( 'activity-2' ) );
		dragTo( 'activity-1', 'activity-4', false );
		expect( names( 'Algebra' ) ).toEqual( [ 'First', 'Review' ] );
		await act( async () => finish( draft ) );
		expect( api.saveOutline ).toHaveBeenCalledOnce();
	} );
} );

describe( 'saving and rollback', () => {
	it( 'undoes a refused move and keeps the outline saved', async () => {
		vi.mocked( api.saveOutline ).mockRejectedValue(
			new ApiError( {
				status: 400,
				code: 'rest_invalid_param',
				message: 'The unit test must be the last activity in its unit.',
			} )
		);
		open();
		dragTo( 'activity-1', 'activity-4', true );
		expect( names( 'Geometry' ) ).toEqual( [ 'Essay', 'First' ] );
		await screen.findAllByText(
			'The move was not saved and has been undone. The unit test must be the last activity in its unit.'
		);
		expect( names( 'Geometry' ) ).toEqual( [ 'Essay' ] );
		expect( names( 'Algebra' ) ).toEqual( [ 'First', 'Check', 'Review' ] );
		expect( screen.getByText( 'Saved draft' ) ).toBeInTheDocument();
		expect(
			screen.getAllByRole( 'button', { name: 'Reload saved outline' } )
		).toHaveLength( 1 );
		expect(
			screen.queryByText( /Moved First lesson/ )
		).not.toBeInTheDocument();
	} );
	it( 'undoes a stale move and offers to reload the saved outline', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveOutline ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_program_stale',
				message: 'The outline changed after it was loaded.',
			} )
		);
		const reload = open();
		dragTo( 'unit:9', 'unit:8', false );
		await screen.findAllByText(
			/The move was not saved and has been undone/
		);
		const notice = document.querySelector< HTMLElement >(
			'.components-notice.is-error'
		)!;
		expect( notice ).toHaveTextContent(
			'The move was not saved and has been undone. The outline changed after it was loaded.'
		);
		expect( saved().revision ).toBe( 'read-token' );
		expect(
			screen
				.getAllByRole( 'region' )
				.filter( ( region ) => region.hasAttribute( 'data-oml-drop' ) )
				.map( ( region ) => region.getAttribute( 'aria-label' ) )
		).toEqual( [ 'Algebra', 'Geometry' ] );
		await user.click(
			within( notice ).getByRole( 'button', {
				name: 'Reload saved outline',
			} )
		);
		expect( reload ).toHaveBeenCalledOnce();
	} );
	it( 'keeps a move in the unsaved draft when other edits are pending', async () => {
		const user = userEvent.setup();
		open();
		const title = screen.getAllByLabelText( 'Unit title' )[ 1 ]!;
		await user.type( title, ' II' );
		dragTo( 'activity-1', 'unit:9', true );
		expect( names( 'Geometry II' ) ).toEqual( [ 'Essay', 'First' ] );
		expect( api.saveOutline ).not.toHaveBeenCalled();
		expect( screen.getByText( 'Unsaved changes' ) ).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save outline draft' } )
		);
		await waitFor( () => expect( api.saveOutline ).toHaveBeenCalledOnce() );
		expect( saved().units[ 1 ]!.title ).toBe( 'Geometry II' );
		expect( saved().program.items[ 3 ]!.chapter_id ).toBe( 9 );
	} );
} );

describe( 'keyboard moves', () => {
	it( 'announces the new position and returns focus to the move button', async () => {
		const user = userEvent.setup();
		open();
		await user.click(
			screen.getByRole( 'button', { name: 'Move Review up' } )
		);
		expect(
			screen.getByText( 'Moved Review to position 2 in Algebra.' )
		).toBeInTheDocument();
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
		expect( names( 'Algebra' ) ).toEqual( [ 'First', 'Review', 'Check' ] );
		expect( document.activeElement ).toBe(
			screen.getByRole( 'button', { name: 'Move Review up' } )
		);
	} );
	it( 'falls back to the moved row when its button reaches the edge', async () => {
		const user = userEvent.setup();
		open();
		await user.click(
			screen.getByRole( 'button', { name: 'Move Check up' } )
		);
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
		expect( document.activeElement ).toBe( row( 'activity-2' ) );
	} );
	it( 'moves to another unit with the select and keeps focus on it', async () => {
		const user = userEvent.setup();
		open();
		await user.selectOptions(
			screen.getByLabelText( 'Unit for Check' ),
			'9'
		);
		expect(
			screen.getByText( 'Moved Check to position 2 in Geometry.' )
		).toBeInTheDocument();
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
		expect( names( 'Geometry' ) ).toEqual( [ 'Essay', 'Check' ] );
		expect( document.activeElement ).toBe(
			screen.getByLabelText( 'Unit for Check' )
		);
	} );
	it( 'moves units with their buttons', async () => {
		const user = userEvent.setup();
		open();
		await user.click(
			within(
				screen.getByRole( 'region', { name: 'Geometry' } )
			).getByRole( 'button', { name: 'Move unit up' } )
		);
		expect(
			screen.getByText( 'Moved unit Geometry to position 1.' )
		).toBeInTheDocument();
		await screen.findAllByText(
			'Outline order saved to the draft. Learner publication is unchanged.'
		);
		expect( saved().units.map( ( unit ) => unit.id ) ).toEqual( [ 9, 8 ] );
		expect( document.activeElement ).toBe( row( 'unit:9' ) );
		await user.click(
			within( row( 'unit:9' ) ).getByRole( 'button', {
				name: 'Move unit down',
			} )
		);
		await waitFor( () =>
			expect( api.saveOutline ).toHaveBeenCalledTimes( 2 )
		);
		expect( saved( 1 ).units.map( ( unit ) => unit.id ) ).toEqual( [
			8, 9,
		] );
		expect( saved( 1 ).revision ).toBe( 'saved' );
	} );
} );
