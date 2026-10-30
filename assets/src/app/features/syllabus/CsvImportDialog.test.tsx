import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import * as api from './api';
import { CsvImportDialog } from './CsvImportDialog';
import { outline, report } from './fixtures';
import { parseReport } from './model';

vi.mock( './api', () => ( { importRows: vi.fn() } ) );
beforeEach( () => vi.resetAllMocks() );
function open() {
	const onApplied = vi.fn();
	const onClose = vi.fn();
	render(
		<CsvImportDialog id={ 5 } onApplied={ onApplied } onClose={ onClose } />
	);
	return { onApplied, onClose };
}
async function source() {
	const user = userEvent.setup();
	await user.type(
		screen.getByLabelText( 'CSV text' ),
		'group,skill,skill_code\nAlgebra,Count,A.1'
	);
	await user.click( screen.getByRole( 'button', { name: 'Read columns' } ) );
	return user;
}
it( 'never writes before review and applies only the reviewed rows', async () => {
	const { onApplied } = open();
	const user = await source();
	expect( api.importRows ).not.toHaveBeenCalled();
	vi.mocked( api.importRows ).mockResolvedValue( {
		report: parseReport( report ),
		outline: null,
	} );
	await user.click( screen.getByRole( 'button', { name: 'Review import' } ) );
	await screen.findByRole( 'button', { name: 'Apply reviewed import' } );
	expect( api.importRows ).toHaveBeenLastCalledWith(
		5,
		expect.any( Array ),
		true,
		expect.any( AbortSignal )
	);
	const rows = vi.mocked( api.importRows ).mock.calls[ 0 ]![ 1 ];
	vi.mocked( api.importRows ).mockResolvedValue( {
		report: parseReport( { ...report, applied: true } ),
		outline,
	} );
	await user.click(
		screen.getByRole( 'button', { name: 'Apply reviewed import' } )
	);
	await waitFor( () => expect( onApplied ).toHaveBeenCalledWith( outline ) );
	expect( api.importRows ).toHaveBeenLastCalledWith(
		5,
		rows,
		false,
		expect.any( AbortSignal ),
		'review-token'
	);
} );
it( 'invalidates review when a mapping changes', async () => {
	open();
	const user = await source();
	vi.mocked( api.importRows ).mockResolvedValue( {
		report: parseReport( report ),
		outline: null,
	} );
	await user.click( screen.getByRole( 'button', { name: 'Review import' } ) );
	await screen.findByRole( 'button', { name: 'Apply reviewed import' } );
	await user.selectOptions( screen.getByLabelText( 'Column 1' ), '' );
	expect(
		screen.queryByRole( 'button', { name: 'Apply reviewed import' } )
	).not.toBeInTheDocument();
} );
it( 'retains CSV and mapping after an apply failure', async () => {
	open();
	const user = await source();
	vi.mocked( api.importRows ).mockResolvedValue( {
		report: parseReport( report ),
		outline: null,
	} );
	await user.click( screen.getByRole( 'button', { name: 'Review import' } ) );
	await screen.findByRole( 'button', { name: 'Apply reviewed import' } );
	vi.mocked( api.importRows ).mockRejectedValue(
		new Error( 'Try again later' )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Apply reviewed import' } )
	);
	await screen.findAllByText( 'Try again later' );
	expect( screen.getByLabelText( 'CSV text' ) ).toHaveValue(
		'group,skill,skill_code\nAlgebra,Count,A.1'
	);
	expect( screen.getByLabelText( 'Column 2' ) ).toHaveValue( 'skill' );
} );
