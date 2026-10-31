import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { nested, report } from './fixtures';
import { MindMap } from './MindMap';
import { parseReport } from './model';
import { SpreadsheetView } from './SpreadsheetView';

vi.mock( './api', async ( original ) => ( {
	...( await original< typeof import( './api' ) >() ),
	command: vi.fn(),
	importRows: vi.fn(),
	readSyllabus: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );

function inRouter( children: JSX.Element ) {
	return render( <MemoryRouter>{ children }</MemoryRouter> );
}

describe( 'Mind map', () => {
	it( 'draws the tree as SVG with a text version that expands and collapses', async () => {
		const user = userEvent.setup();
		inRouter( <MindMap outline={ nested } /> );
		expect(
			screen.getByRole( 'img', {
				name: /Mind map of Mathematics syllabus: 2 content items, 2 skill groups and 3 skills/,
			} )
		).toBeInTheDocument();
		const list = screen.getByRole( 'heading', { name: 'Text version' } )
			.parentElement as HTMLElement;
		expect( within( list ).queryByText( 'Measure angles' ) ).toBeNull();
		await user.click(
			within( list ).getByRole( 'button', { name: 'Expand U1 Geometry' } )
		);
		await user.click(
			within( list ).getByRole( 'button', { name: 'Expand G Angles' } )
		);
		expect(
			within( list ).getByRole( 'link', { name: 'G.1 Measure angles' } )
		).toHaveAttribute(
			'href',
			'/content-hub/curriculum/compare?tab=mappings&skill=12'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Collapse all' } )
		);
		expect( within( list ).queryByText( 'G.1 Measure angles' ) ).toBeNull();
		await user.type(
			screen.getByLabelText( 'Find in the map' ),
			'measure'
		);
		expect(
			within( list ).getByRole( 'link', { name: 'G.1 Measure angles' } )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Hide text version' } )
		);
		expect(
			screen.queryByRole( 'heading', { name: 'Text version' } )
		).toBeNull();
	} );
} );

describe( 'Spreadsheet', () => {
	it( 'edits cells with the keyboard and saves one batch with previous values', async () => {
		const user = userEvent.setup();
		const onSaved = vi.fn();
		vi.mocked( api.command ).mockResolvedValue( nested );
		inRouter( <SpreadsheetView outline={ nested } onSaved={ onSaved } /> );
		const grid = screen.getByRole( 'grid' );
		const groupCell = within( grid ).getAllByRole(
			'gridcell'
		)[ 3 ] as HTMLElement;
		groupCell.focus();
		await user.keyboard( '{Enter}' );
		const editor = within( grid ).getByRole( 'textbox', {
			name: 'Skill group, row 1',
		} );
		await user.clear( editor );
		await user.type( editor, 'Algebra 1{Enter}' );
		expect( within( grid ).getAllByText( 'Algebra 1' ) ).toHaveLength( 2 );
		await user.keyboard( '{ArrowRight}{ArrowRight}{ArrowRight}{Enter}' );
		const skill = within( grid ).getByRole( 'textbox', {
			name: 'Skill, row 2',
		} );
		await user.keyboard( '{Escape}' );
		expect( skill ).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save 1 changed cells' } )
		);
		expect( api.command ).toHaveBeenCalledWith(
			5,
			'sheet_edit',
			{
				changes: [
					{
						kind: 'group',
						id: 7,
						field: 'name',
						previous: 'Algebra',
						value: 'Algebra 1',
					},
				],
			},
			expect.any( AbortSignal )
		);
		await waitFor( () => expect( onSaved ).toHaveBeenCalledWith( nested ) );
	} );

	it( 'blocks invalid cells and shows conflicts with a reload that keeps edits', async () => {
		const user = userEvent.setup();
		vi.mocked( api.command ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_syllabus_sheet_stale',
				message: 'Changed.',
				details: {
					conflicts: [
						{ kind: 'skill', id: 10, field: 'code', current: 'Z' },
					],
				},
			} )
		);
		vi.mocked( api.readSyllabus ).mockResolvedValue( nested );
		inRouter( <SpreadsheetView outline={ nested } onSaved={ vi.fn() } /> );
		const grid = screen.getByRole( 'grid' );
		const codeCell = within( grid ).getAllByRole(
			'gridcell'
		)[ 5 ] as HTMLElement;
		await user.dblClick( codeCell );
		const editor = within( grid ).getByRole( 'textbox' );
		await user.clear( editor );
		await user.type( editor, 'A.2{Enter}' );
		expect(
			screen.getAllByText( /Two skills would share a code/ )[ 0 ]
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save 1 changed cells' } )
		).toBeDisabled();
		await user.dblClick(
			within( grid ).getAllByRole( 'gridcell' )[ 5 ] as HTMLElement
		);
		const again = within( grid ).getByRole( 'textbox' );
		await user.clear( again );
		await user.type( again, 'A.7{Enter}' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save 1 changed cells' } )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Reload syllabus and keep my edits',
			} )
		);
		expect( api.readSyllabus ).toHaveBeenCalledWith( 5 );
		expect(
			( await screen.findAllByText( /Your edits were kept/ ) )[ 0 ]
		).toBeInTheDocument();
		expect( within( grid ).getByText( 'A.7' ) ).toBeInTheDocument();
	} );

	it( 'checks pasted rows with a dry run before adding them', async () => {
		const user = userEvent.setup();
		const reviewed = parseReport( report );
		vi.mocked( api.importRows )
			.mockResolvedValueOnce( { report: reviewed, outline: null } )
			.mockResolvedValueOnce( {
				report: { ...reviewed, applied: true },
				outline: nested,
			} );
		const onSaved = vi.fn();
		inRouter( <SpreadsheetView outline={ nested } onSaved={ onSaved } /> );
		await user.click( screen.getByLabelText( 'Rows to paste' ) );
		await user.paste( 'Number\tCounting\tN.1\tCount to ten' );
		await user.click(
			screen.getByRole( 'button', { name: 'Check pasted rows' } )
		);
		expect( api.importRows ).toHaveBeenLastCalledWith(
			5,
			[
				{
					line: 1,
					content: 'Number',
					group: 'Counting',
					skill_code: 'N.1',
					skill: 'Count to ten',
				},
			],
			true,
			expect.any( AbortSignal )
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Add pasted rows' } )
		);
		expect( api.importRows ).toHaveBeenLastCalledWith(
			5,
			expect.any( Array ),
			false,
			expect.any( AbortSignal ),
			'review-token'
		);
		await waitFor( () => expect( onSaved ).toHaveBeenCalledWith( nested ) );
	} );
} );
