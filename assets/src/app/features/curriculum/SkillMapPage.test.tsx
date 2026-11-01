import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as skillMap from './skill-map';
import {
	map,
	rawClassOverlay,
	rawLearnerOverlay,
	rawTargets,
} from './skill-map-fixtures';
import * as overlays from './skill-map-overlay';
import SkillMapPage from './SkillMapPage';

vi.mock( './skill-map', async ( original ) => ( {
	...( await original< typeof import( './skill-map' ) >() ),
	readSubjects: vi.fn(),
	readMap: vi.fn(),
} ) );
vi.mock( './skill-map-overlay', async ( original ) => ( {
	...( await original< typeof import( './skill-map-overlay' ) >() ),
	readOverlay: vi.fn(),
	readTargets: vi.fn(),
} ) );

const PATH = '/content-hub/curriculum/skill-map';

function Address() {
	const location = useLocation();
	return (
		<output aria-label="address">
			{ location.pathname + location.search }
		</output>
	);
}

function open( search = '?subject=Mathematics' ) {
	return render(
		<MemoryRouter initialEntries={ [ PATH + search ] }>
			<Routes>
				<Route
					path={ PATH }
					element={
						<>
							<SkillMapPage />
							<Address />
						</>
					}
				/>
				<Route path="*" element={ <Address /> } />
			</Routes>
		</MemoryRouter>
	);
}

const chip = ( name: string ) =>
	screen.getByRole( 'button', { name: new RegExp( `^${ name }` ) } );

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( skillMap.readSubjects ).mockResolvedValue( [
		{ subject: 'Mathematics', skills: 4, syllabuses: [] },
	] );
	vi.mocked( skillMap.readMap ).mockResolvedValue( map );
	vi.mocked( overlays.readTargets ).mockImplementation( async ( search ) => {
		const raw = rawTargets();
		return overlays.parseTargets(
			search.length < 2 ? { ...raw, learners: [] } : raw
		);
	} );
	vi.mocked( overlays.readOverlay ).mockImplementation( async ( _, target ) =>
		overlays.parseOverlay(
			target.startsWith( 'class:' )
				? rawClassOverlay()
				: rawLearnerOverlay()
		)
	);
} );

describe( 'SkillMapPage learner overlay', () => {
	it( 'loads nothing until the overlay is turned on, then lays a chosen learner over the map', async () => {
		const user = userEvent.setup();
		open();
		const toggle = await screen.findByRole( 'checkbox', {
			name: 'Show learner mastery',
		} );
		expect( toggle ).not.toBeChecked();
		expect( overlays.readTargets ).not.toHaveBeenCalled();
		expect( overlays.readOverlay ).not.toHaveBeenCalled();

		await user.click( toggle );
		expect(
			await screen.findByRole( 'button', {
				name: 'Class A1 (Alpha School)',
			} )
		).toBeVisible();
		expect(
			screen.getByText( 'Type at least 2 characters to find a learner.' )
		).toBeVisible();

		await user.type(
			screen.getByRole( 'searchbox', {
				name: 'Find a learner, class or school',
			} ),
			'an'
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Ana Example' } )
		);
		await waitFor( () =>
			expect( overlays.readOverlay ).toHaveBeenCalledWith(
				'Mathematics',
				'learner:12',
				expect.anything()
			)
		);
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			`${ PATH }?subject=Mathematics&overlay=learner%3A12`
		);
		expect(
			await screen.findByText( 'Showing Ana Example' )
		).toBeVisible();

		expect( chip( 'Count to ten' ) ).toHaveTextContent(
			'Count to tenMastered · 100%'
		);
		expect( chip( 'Count to ten' ) ).toHaveClass( 'is-mastered' );
		expect( chip( 'Spot patterns' ) ).toHaveTextContent(
			'Proficient · 80%'
		);
		expect( chip( 'Simple equations' ) ).toHaveTextContent( 'Not started' );
		expect( chip( 'Simple equations' ) ).toHaveClass( 'is-not-started' );
		expect(
			screen.getAllByText( '1 of 2 Proficient or above' )
		).toHaveLength( 2 );

		const legend = screen.getByRole( 'list', { name: 'Mastery levels' } );
		const items = within( legend ).getAllByRole( 'listitem' );
		expect( items.map( ( item ) => item.textContent ) ).toEqual( [
			'Mastered 95% and above1 skill',
			'Proficient 80–94%1 skill',
			'Familiar 50–79%0 skills',
			'Attempted below 50%1 skill',
			'Not started no evidence yet1 skill',
		] );
		expect(
			screen.getByRole( 'link', { name: 'Open the transcript' } )
		).toHaveAttribute(
			'href',
			'/content-hub/gradebook/student?scope=all&learner=12'
		);
		expect(
			screen.getByRole( 'link', { name: 'Open the student report' } )
		).toHaveAttribute( 'href', '/students/12/report' );

		await user.click( chip( 'Add within twenty' ) );
		expect(
			within(
				screen.getByRole( 'region', { name: 'Selected skill' } )
			).getByText( 'Ana Example: Attempted · 30%.' )
		).toBeVisible();
	} );

	it( 'opens a class from the address and shows averages and learners per level', async () => {
		const user = userEvent.setup();
		open( '?subject=Mathematics&overlay=class%3A9' );
		expect(
			await screen.findByRole( 'checkbox', {
				name: 'Show learner mastery',
			} )
		).toBeChecked();
		expect(
			await screen.findByText( 'Showing Class A1 (Alpha School)' )
		).toBeVisible();
		expect( overlays.readTargets ).not.toHaveBeenCalled();
		expect( chip( 'Count to ten' ) ).toHaveTextContent(
			'Familiar · average 64%'
		);
		expect(
			screen.getByText(
				'Each skill shows the average of 6 learners; learners without evidence count as 0%.'
			)
		).toBeVisible();
		expect(
			screen.getByRole( 'link', {
				name: 'Open skill mastery in the gradebook',
			} )
		).toHaveAttribute(
			'href',
			'/content-hub/gradebook/mastery?scope=class%3A9'
		);
		await user.click( chip( 'Count to ten' ) );
		expect(
			screen.getByText(
				'Class A1: Familiar · average 64%. Learners: Mastered 2, Proficient 1, Familiar 1, Attempted 1, Not started 1 (of 6).'
			)
		).toBeVisible();

		await user.click( screen.getByRole( 'button', { name: 'Change' } ) );
		await user.click(
			await screen.findByRole( 'button', { name: 'Alpha School' } )
		);
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			'overlay=school%3A3'
		);
	} );

	it( 'reports a failed load, retries, and clears the overlay when turned off', async () => {
		const user = userEvent.setup();
		vi.mocked( overlays.readOverlay ).mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_skill_overlay_forbidden',
				message:
					'Sorry, this learner is not in your schools or classes.',
			} )
		);
		open( '?subject=Mathematics&overlay=learner%3A12' );
		expect(
			await screen.findByText(
				'Sorry, this learner is not in your schools or classes.'
			)
		).toBeVisible();
		expect( chip( 'Count to ten' ) ).not.toHaveClass(
			'ohmylms-skill-overlay__chip'
		);

		await user.click( screen.getByRole( 'button', { name: 'Retry' } ) );
		expect(
			await screen.findByText( 'Showing Ana Example' )
		).toBeVisible();
		expect( overlays.readOverlay ).toHaveBeenCalledTimes( 2 );

		await user.click(
			screen.getByRole( 'checkbox', { name: 'Show learner mastery' } )
		);
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			`${ PATH }?subject=Mathematics`
		);
		expect( screen.getByLabelText( 'address' ) ).not.toHaveTextContent(
			'overlay'
		);
		expect( chip( 'Count to ten' ) ).toHaveTextContent( /^Count to ten$/ );
		expect(
			screen.queryByRole( 'list', { name: 'Mastery levels' } )
		).not.toBeInTheDocument();
	} );

	it( 'ignores an overlay address value that is not a target', async () => {
		open( '?subject=Mathematics&overlay=all' );
		expect(
			await screen.findByRole( 'checkbox', {
				name: 'Show learner mastery',
			} )
		).not.toBeChecked();
		expect( overlays.readOverlay ).not.toHaveBeenCalled();
	} );
} );
