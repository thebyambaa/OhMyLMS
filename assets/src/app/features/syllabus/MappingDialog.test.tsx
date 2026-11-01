import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import * as curriculum from '../curriculum/public';
import * as api from './api';
import { MappingDialog } from './MappingDialog';

vi.mock( './api', () => ( { readMappings: vi.fn() } ) );
vi.mock( '../curriculum/public', () => ( {
	searchSharedSkills: vi.fn(),
	saveSkillMapping: vi.fn(),
	removeSkillMapping: vi.fn(),
} ) );
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.readMappings ).mockResolvedValue( [
		{
			shared_id: 20,
			name: 'Shared number sense',
			relation: 'related',
			note: 'Old note',
			revision: 'read-revision',
			combined: false,
		},
	] );
	vi.mocked( curriculum.searchSharedSkills ).mockResolvedValue( {
		total: 1,
		items: [ { id: 20, name: 'Shared number sense' } ],
	} );
} );
function open() {
	render(
		<MappingDialog
			skill={ {
				term_id: 10,
				name: 'Count',
				code: 'A.1',
				description: '',
			} }
			onClose={ vi.fn() }
		/>
	);
}
it( 'requires confirmation and submits the read revision, not an unconditional overwrite', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Edit mapping' } )
	);
	await user.selectOptions(
		screen.getByLabelText( 'Relationship' ),
		'equivalent'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Review mapping change' } )
	);
	expect( curriculum.saveSkillMapping ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Confirm mapping change' } )
	);
	await waitFor( () =>
		expect( curriculum.saveSkillMapping ).toHaveBeenCalledOnce()
	);
	expect( curriculum.saveSkillMapping ).toHaveBeenCalledWith(
		10,
		20,
		'equivalent',
		'Old note',
		'read-revision',
		expect.any( AbortSignal )
	);
} );
it( 'removes a mapping at the revision it was read at', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Remove mapping' } )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Confirm mapping change' } )
	);
	await waitFor( () =>
		expect( curriculum.removeSkillMapping ).toHaveBeenCalledWith(
			10,
			20,
			'read-revision',
			expect.any( AbortSignal )
		)
	);
} );
it( 'keeps notes after a stale-write failure and allows explicit reload', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Edit mapping' } )
	);
	await user.type( screen.getByLabelText( 'Mapping note' ), ' revised' );
	vi.mocked( curriculum.saveSkillMapping ).mockRejectedValue(
		new ApiError( {
			code: 'stale',
			status: 409,
			message: 'Mapping changed',
		} )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Review mapping change' } )
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Confirm mapping change' } )
	);
	await screen.findAllByText( 'Mapping changed' );
	expect( screen.getByLabelText( 'Mapping note' ) ).toHaveValue(
		'Old note revised'
	);
	expect( curriculum.saveSkillMapping ).toHaveBeenCalledOnce();
	await user.click(
		screen.getByRole( 'button', { name: 'Reload mappings' } )
	);
	await waitFor( () =>
		expect( api.readMappings ).toHaveBeenCalledTimes( 2 )
	);
	expect( screen.getByLabelText( 'Mapping note' ) ).toHaveValue(
		'Old note revised'
	);
} );
