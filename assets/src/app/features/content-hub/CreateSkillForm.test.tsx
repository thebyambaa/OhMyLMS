import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CreateSkillForm } from './CreateSkillForm';
import * as api from './api';

vi.mock( './api', () => ( { saveSkill: vi.fn() } ) );

it( 'keeps failed creation editable and returns a new skill for explicit linking', async () => {
	const user = userEvent.setup();
	const onCreated = vi.fn();
	const onDirtyChange = vi.fn();
	vi.mocked( api.saveSkill )
		.mockRejectedValueOnce( new Error( 'Save failed.' ) )
		.mockResolvedValueOnce( 42 );
	const { container } = render(
		<CreateSkillForm
			disabled={ false }
			onCreated={ onCreated }
			onDirtyChange={ onDirtyChange }
		/>
	);
	await user.click( screen.getByText( 'Create a new skill' ) );
	await user.type(
		screen.getByRole( 'textbox', { name: 'Skill name' } ),
		'Linear equations'
	);
	await user.type(
		screen.getByRole( 'textbox', { name: 'Skill code (optional)' } ),
		'ALG.1'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Create skill and select' } )
	);
	await waitFor( () =>
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toHaveTextContent( 'Save failed.' )
	);
	expect( screen.getByRole( 'textbox', { name: 'Skill name' } ) ).toHaveValue(
		'Linear equations'
	);
	expect( onCreated ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Create skill and select' } )
	);
	await waitFor( () =>
		expect( onCreated ).toHaveBeenCalledWith( {
			id: 42,
			name: 'Linear equations',
			code: 'ALG.1',
		} )
	);
	expect( api.saveSkill ).toHaveBeenLastCalledWith(
		{
			name: 'Linear equations',
			code: 'ALG.1',
			description: '',
			parent: 0,
			prerequisites: [],
		},
		null,
		expect.any( AbortSignal )
	);
	expect( screen.getByRole( 'textbox', { name: 'Skill name' } ) ).toHaveValue(
		''
	);
} );
