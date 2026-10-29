import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { listSkills } from './api';
import { SkillPicker } from './SkillPicker';

vi.mock( './api', () => ( { listSkills: vi.fn() } ) );
const base = {
	id: 7,
	name: 'Self',
	code: '',
	description: '',
	parent: 0,
	prerequisites: [],
	archived: false,
};
it( 'searches on Enter without submitting the editor, excludes self and retains selected ids', async () => {
	const user = userEvent.setup();
	const changed = vi.fn();
	const submitted = vi.fn( ( event ) => event.preventDefault() );
	vi.mocked( listSkills )
		.mockResolvedValueOnce( {
			items: [ { ...base, id: 8, name: 'Existing' } ],
			total: 1,
			totalPages: 1,
		} )
		.mockResolvedValueOnce( {
			items: [
				base,
				{ ...base, id: 9, name: 'New prerequisite' },
				{ ...base, id: 10, name: 'Archived', archived: true },
			],
			total: 3,
			totalPages: 1,
		} );
	render(
		<form onSubmit={ submitted }>
			<SkillPicker
				label="Prerequisites"
				selected={ [ 8 ] }
				exclude={ 7 }
				multiple
				disabled={ false }
				onChange={ changed }
			/>
		</form>
	);
	await screen.findByText( 'Existing' );
	await user.type(
		screen.getByLabelText( 'Search Prerequisites by name' ),
		'New{Enter}'
	);
	await screen.findByRole( 'button', { name: 'New prerequisite' } );
	expect( submitted ).not.toHaveBeenCalled();
	expect(
		screen.queryByRole( 'button', { name: 'Self' } )
	).not.toBeInTheDocument();
	expect(
		screen.queryByRole( 'button', { name: 'Archived' } )
	).not.toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'New prerequisite' } )
	);
	expect( changed ).toHaveBeenCalledWith( [ 8, 9 ] );
} );
it( 'cancels obsolete searches and ignores their late results', async () => {
	const user = userEvent.setup();
	let finish:
		| ( ( value: Awaited< ReturnType< typeof listSkills > > ) => void )
		| undefined;
	vi.mocked( listSkills ).mockImplementationOnce(
		() =>
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
	);
	const view = render(
		<SkillPicker
			label="Parent"
			selected={ [] }
			exclude={ null }
			multiple={ false }
			disabled={ false }
			onChange={ vi.fn() }
		/>
	);
	await user.click( screen.getByRole( 'button', { name: 'Find skills' } ) );
	await waitFor( () => expect( listSkills ).toHaveBeenCalled() );
	const signal = vi.mocked( listSkills ).mock.calls.at( -1 )?.[ 2 ];
	view.unmount();
	expect( signal?.aborted ).toBe( true );
	finish?.( { items: [ base ], total: 1, totalPages: 1 } );
} );
