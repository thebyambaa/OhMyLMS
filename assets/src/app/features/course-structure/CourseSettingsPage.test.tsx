import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './settings-api';
import { SettingsWorkspace } from './CourseSettingsPage';

vi.mock( './settings-api', () => ( {
	readSettings: vi.fn(),
	saveSettings: vi.fn(),
} ) );

const settings: api.CourseSettings = {
	course_id: 12,
	revision: 'settings-token',
	title: 'Algebra',
	description: '<p>Math</p>',
	author_id: 1,
	image_id: 0,
	status: 'draft',
	price_type: 'free',
	regular_price: '0',
	sale_price: '',
	access_type: 'public',
	has_capacity: false,
	capacity: 0,
};

beforeEach( () => vi.resetAllMocks() );

function open() {
	const reload = vi.fn();
	render(
		<MemoryRouter>
			<SettingsWorkspace initial={ settings } reload={ reload } />
		</MemoryRouter>
	);
	return reload;
}

it( 'saves the complete settings without publishing and clears the entered password', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.saveSettings ).mockResolvedValue( {
		...settings,
		revision: 'new-token',
		access_type: 'password_protected',
	} );
	await user.selectOptions(
		screen.getByLabelText( 'Course access' ),
		'password_protected'
	);
	await user.type(
		screen.getByLabelText( 'New course password' ),
		'test-password'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Save course settings' } )
	);
	await screen.findAllByText( 'Course settings saved.' );
	expect( api.saveSettings ).toHaveBeenCalledWith(
		{ ...settings, access_type: 'password_protected' },
		'test-password',
		expect.any( AbortSignal )
	);
	expect( screen.getByLabelText( 'New course password' ) ).toHaveValue( '' );
	expect(
		screen.queryByRole( 'option', { name: 'Published' } )
	).not.toBeInTheDocument();
	expect(
		screen.getByRole( 'button', { name: 'Save course settings' } )
	).toBeDisabled();
} );

it( 'keeps edits after a conflict and requires a decision before reloading', async () => {
	const user = userEvent.setup();
	const reload = open();
	vi.mocked( api.saveSettings ).mockRejectedValue(
		new ApiError( {
			code: 'stale',
			status: 409,
			message: 'Changed in another session',
		} )
	);
	await user.type( screen.getByLabelText( 'Course title' ), ' edits' );
	await user.click(
		screen.getByRole( 'button', { name: 'Save course settings' } )
	);
	await screen.findAllByText( 'Changed in another session' );
	expect( screen.getByLabelText( 'Course title' ) ).toHaveValue(
		'Algebra edits'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Reload saved settings' } )
	);
	expect( reload ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Discard and reload' } )
	);
	expect( reload ).toHaveBeenCalledOnce();
} );
