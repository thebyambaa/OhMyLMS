import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { GradeGrid, type GridRow } from './GradeGrid';

const columns = [
	{ key: 'a', label: 'Quiz', header: 'Quiz' },
	{ key: 'b', label: 'Essay', header: 'Essay' },
	{ key: 'c', label: 'Test', header: 'Test' },
];

function rows( count: number ): GridRow[] {
	return Array.from( { length: count }, ( _, index ) => ( {
		key: String( index ),
		header:
			index === 0 ? (
				<a href="#learner-0">Learner 0</a>
			) : (
				`Learner ${ index }`
			),
		cells: [
			{
				content: `${ index }-a`,
				activatable: true,
				label: `Quiz of ${ index }: 80%`,
			},
			{ content: `${ index }-b`, activatable: true },
			{ content: '—', label: 'Not assigned' },
		],
	} ) );
}

function show( count = 3, onActivate = vi.fn() ) {
	render(
		<GradeGrid
			label="Grades for Algebra"
			corner="Learner"
			columns={ columns }
			rows={ rows( count ) }
			footer={ {
				key: 'sum',
				header: 'Average',
				cells: [
					{ content: '70%' },
					{ content: '—' },
					{ content: '' },
				],
			} }
			rowCount={ 40 }
			rowOffset={ 10 }
			onActivate={ onActivate }
		/>
	);
	return onActivate;
}

describe( 'GradeGrid', () => {
	it( 'is a labelled grid with row and column headers and counts', () => {
		show();
		const grid = screen.getByRole( 'grid', { name: 'Grades for Algebra' } );
		expect( grid ).toHaveAttribute( 'aria-rowcount', '42' );
		expect( grid ).toHaveAttribute( 'aria-colcount', '4' );
		expect( within( grid ).getAllByRole( 'columnheader' ) ).toHaveLength(
			4
		);
		expect( within( grid ).getAllByRole( 'rowheader' ) ).toHaveLength( 4 );
		expect( within( grid ).getAllByRole( 'row' )[ 1 ] ).toHaveAttribute(
			'aria-rowindex',
			'12'
		);
		expect(
			screen.getByRole( 'gridcell', { name: 'Quiz of 1: 80%' } )
		).toBeInTheDocument();
		expect( grid ).toHaveClass( 'widefat' );
	} );

	it( 'keeps exactly one cell in the tab order and moves it with the keyboard', async () => {
		show();
		const user = userEvent.setup();
		const focusable = () =>
			document.querySelectorAll( '[data-row][tabindex="0"]' );
		expect( focusable() ).toHaveLength( 1 );
		await user.tab();
		expect( document.activeElement ).toHaveTextContent( 'Learner' );
		await user.keyboard( '{ArrowDown}{ArrowRight}' );
		expect( document.activeElement ).toHaveTextContent( '0-a' );
		await user.keyboard( '{ArrowRight}{ArrowRight}{ArrowRight}' );
		expect( document.activeElement ).toHaveTextContent( '—' );
		await user.keyboard( '{Home}' );
		expect( document.activeElement ).toHaveTextContent( 'Learner 0' );
		await user.keyboard( '{Control>}{End}{/Control}' );
		expect( document.activeElement?.closest( 'tfoot' ) ).not.toBeNull();
		await user.keyboard( '{Control>}{Home}{/Control}' );
		expect( document.activeElement ).toHaveTextContent( 'Learner' );
		expect( focusable() ).toHaveLength( 1 );
	} );

	it( 'pages with Page Down and Page Up', async () => {
		show( 25 );
		const user = userEvent.setup();
		await user.tab();
		await user.keyboard( '{PageDown}' );
		expect( document.activeElement ).toHaveTextContent( 'Learner 9' );
		await user.keyboard( '{PageDown}{PageDown}' );
		expect( document.activeElement ).toHaveTextContent( 'Average' );
		await user.keyboard( '{PageUp}' );
		expect( document.activeElement ).toHaveTextContent( 'Learner 15' );
	} );

	it( 'opens activatable cells with Enter, Space, F2 and a click, and nothing else', async () => {
		const onActivate = show();
		const user = userEvent.setup();
		await user.tab();
		await user.keyboard( '{ArrowDown}{ArrowDown}{ArrowRight}{Enter}' );
		expect( onActivate ).toHaveBeenLastCalledWith( 1, 0 );
		await user.keyboard( '{ArrowRight} ' );
		expect( onActivate ).toHaveBeenLastCalledWith( 1, 1 );
		await user.keyboard( '{F2}' );
		expect( onActivate ).toHaveBeenCalledTimes( 3 );
		await user.keyboard( '{ArrowRight}{Enter}' );
		expect( onActivate ).toHaveBeenCalledTimes( 3 );
		await user.click( screen.getByText( '2-a' ) );
		expect( onActivate ).toHaveBeenLastCalledWith( 2, 0 );
		await user.click( screen.getByText( '70%' ) );
		expect( onActivate ).toHaveBeenCalledTimes( 4 );
	} );

	it( 'follows the link inside a header cell on Enter', async () => {
		show();
		const user = userEvent.setup();
		const link = screen.getByRole( 'link', { name: 'Learner 0' } );
		const clicked = vi.fn( ( event: Event ) => event.preventDefault() );
		link.addEventListener( 'click', clicked );
		await user.tab();
		await user.keyboard( '{ArrowDown}' );
		fireEvent.keyDown( document.activeElement as Element, {
			key: 'Enter',
		} );
		expect( clicked ).toHaveBeenCalled();
	} );
} );
