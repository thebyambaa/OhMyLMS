import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import * as api from './api';
import {
	context,
	detail,
	mastery,
	rollup,
	roster,
	transcript,
} from './fixtures';
import MasteryPage from './MasteryPage';
import RollupPage from './RollupPage';
import RosterPage from './RosterPage';
import TranscriptPage from './TranscriptPage';

vi.mock( './api', () => ( {
	getContext: vi.fn(),
	getRoster: vi.fn(),
	findLearners: vi.fn(),
	getTranscript: vi.fn(),
	getMastery: vi.fn(),
	getRollup: vi.fn(),
	getCell: vi.fn(),
	changeCell: vi.fn(),
	listViews: vi.fn(),
	createView: vi.fn(),
	deleteView: vi.fn(),
} ) );

const mocked = vi.mocked( api );

function Search() {
	return (
		<output aria-label="address">
			{ useLocation().pathname + useLocation().search }
		</output>
	);
}

function show( element: JSX.Element, route: string ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			{ element }
			<Search />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	mocked.getContext.mockResolvedValue( context );
	mocked.getRoster.mockResolvedValue( roster );
	mocked.findLearners.mockResolvedValue( [ { id: 1, name: 'Ana Example' } ] );
	mocked.getTranscript.mockResolvedValue( transcript );
	mocked.getMastery.mockResolvedValue( mastery );
	mocked.getRollup.mockResolvedValue( rollup );
	mocked.getCell.mockResolvedValue( detail );
	mocked.listViews.mockResolvedValue( [] );
} );

describe( 'Roster × Assignments', () => {
	it( 'chooses the first class and shows its courses, totals and not-enrolled cells', async () => {
		show( <RosterPage />, '/content-hub/gradebook/roster' );
		const grid = await screen.findByRole( 'grid', {
			name: 'Grades of Grade 8A across its courses',
		} );
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			'class=7'
		);
		expect( mocked.getRoster ).toHaveBeenCalledWith(
			'',
			7,
			expect.anything()
		);
		expect(
			within( grid ).getByRole( 'gridcell', { name: 'Total 32% F' } )
		).toBeInTheDocument();
		expect(
			within( grid ).getByRole( 'gridcell', {
				name: 'Not enrolled in this course',
			} )
		).toBeInTheDocument();
		expect(
			within( grid ).getAllByRole( 'gridcell', {
				name: /Not enrolled in this course/,
			} )
		).toHaveLength( 3 );
		expect(
			screen.getByText( /Grade 8A · Alpha · 2 learners · 1 courses/ )
		).toBeInTheDocument();
	} );

	it( 'opens a cell of a course', async () => {
		show( <RosterPage />, '/content-hub/gradebook/roster?class=7' );
		const user = userEvent.setup();
		const grid = await screen.findByRole( 'grid' );
		await user.click(
			within( grid ).getByRole( 'gridcell', {
				name: 'Algebra: Practice set: Missing',
			} )
		);
		await screen.findByRole( 'dialog' );
		expect( mocked.getCell ).toHaveBeenCalledWith(
			'',
			12,
			102,
			1,
			expect.anything()
		);
	} );
} );

describe( 'Student transcript', () => {
	it( 'shows enrolments with weights, totals, items and mastery', async () => {
		show( <TranscriptPage />, '/content-hub/gradebook/student?learner=1' );
		expect(
			await screen.findByRole( 'heading', {
				name: 'Ana Example — transcript',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Weights (class override): homework 20% · quiz 30% · test 50%'
			)
		).toBeInTheDocument();
		const items = screen.getByRole( 'table', { name: 'Items of Algebra' } );
		expect(
			within( items ).getByRole( 'button', {
				name: 'Practice set: Missing',
			} )
		).toBeInTheDocument();
		expect(
			within( items ).getByLabelText( 'Not assigned' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Linear equations' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'img', { name: '88%' } )
		).toBeInTheDocument();
	} );

	it( 'finds learners in the scope and opens one', async () => {
		show( <TranscriptPage />, '/content-hub/gradebook/student' );
		const user = userEvent.setup();
		expect(
			await screen.findByText(
				'Choose a learner to see their transcript.'
			)
		).toBeInTheDocument();
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Find a learner' } ),
			'ana{Enter}'
		);
		expect( mocked.findLearners ).toHaveBeenLastCalledWith(
			'',
			'ana',
			expect.anything()
		);
		await user.selectOptions( screen.getByLabelText( 'Learner' ), '1' );
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			'learner=1'
		);
	} );
} );

describe( 'Skill mastery', () => {
	it( 'asks for a course in all schools, then shows levels in words', async () => {
		show( <MasteryPage />, '/content-hub/gradebook/mastery' );
		expect(
			await screen.findByText(
				'Choose a course, or a school or class, to compare skills.'
			)
		).toBeInTheDocument();
		expect( mocked.getMastery ).not.toHaveBeenCalled();
	} );

	it( 'shows learners × skills for a course', async () => {
		show( <MasteryPage />, '/content-hub/gradebook/mastery?course=12' );
		const grid = await screen.findByRole( 'grid', {
			name: 'Skill mastery of learners',
		} );
		expect(
			within( grid ).getByRole( 'gridcell', {
				name: 'Linear equations: Proficient, 88%',
			} )
		).toBeInTheDocument();
		expect(
			within( grid ).getByRole( 'gridcell', {
				name: 'Linear equations: Not started',
			} )
		).toBeInTheDocument();
	} );
} );

describe( 'Org roll-up', () => {
	it( 'shows schools, classes and individual learners with roster links', async () => {
		show( <RollupPage />, '/content-hub/gradebook/rollup' );
		const table = await screen.findByRole( 'table', {
			name: 'Schools, classes and individual learners',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		expect(
			within( rows[ 2 ]! ).getByRole( 'rowheader' )
		).toHaveTextContent( 'Grade 8A' );
		expect( within( rows[ 1 ]! ).getByText( '55.5%' ) ).toBeInTheDocument();
		await userEvent
			.setup()
			.click(
				within( table ).getByRole( 'link', { name: 'Open roster' } )
			);
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			'/content-hub/gradebook/roster?class=7'
		);
	} );
} );
