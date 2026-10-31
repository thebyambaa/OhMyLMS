import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ExtensionsProvider } from '../../extensions/context';
import {
	createRegistry,
	type ExtensionRegistry,
} from '../../extensions/registry.mjs';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { options, plan } from './fixtures';
import MembershipEditorPage from './MembershipEditorPage';
import type { MembershipSettingsProps } from './MembershipExtensionSettings';

vi.mock( './api', () => ( {
	getMembership: vi.fn(),
	getOptions: vi.fn(),
	updateMembership: vi.fn(),
	listCourses: vi.fn(),
	listMembers: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

function BenefitPanel( {
	value,
	onChange,
	plan: edited,
}: MembershipSettingsProps ) {
	return (
		<label htmlFor="acme-benefit">
			{ `Member benefit for ${ edited.name }` }
			<input
				id="acme-benefit"
				value={ 'string' === typeof value.benefit ? value.benefit : '' }
				onChange={ ( event ) =>
					onChange( { ...value, benefit: event.target.value } )
				}
			/>
		</label>
	);
}

function renderEditor( registry: ExtensionRegistry ) {
	return render(
		<ExtensionsProvider value={ registry }>
			<MemoryRouter initialEntries={ [ '/memberships/7' ] }>
				<Routes>
					<Route
						path="/memberships/:id"
						element={ <MembershipEditorPage /> }
					/>
				</Routes>
			</MemoryRouter>
		</ExtensionsProvider>
	);
}

async function loaded() {
	await screen.findByRole( 'textbox', { name: 'Name' } );
}

function quietErrors() {
	vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
	const prevent = ( event: ErrorEvent ) => event.preventDefault();
	window.addEventListener( 'error', prevent );
	return () => window.removeEventListener( 'error', prevent );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getMembership ).mockResolvedValue( {
		...plan,
		extension_settings: { 'acme-benefit': { benefit: 'Office hours' } },
	} );
	vi.mocked( api.getOptions ).mockResolvedValue( {
		...options,
		extension_settings: [ 'acme-benefit', 'acme-broken' ],
	} );
	vi.mocked( api.listMembers ).mockResolvedValue( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	vi.mocked( api.updateMembership ).mockImplementation(
		async ( id, input ) => ( {
			...plan,
			id,
			name: input.name,
			extension_settings: {
				'acme-benefit': { benefit: 'Office hours' },
				...input.extension_settings,
			},
		} )
	);
} );

describe( 'membership settings extensions', () => {
	it( 'renders a registered panel with the plan and its saved values', async () => {
		const registry = createRegistry();
		registry.registerMembershipSettings( 'acme-benefit', {
			label: 'Acme benefit',
			render: BenefitPanel,
		} );
		registry.registerMembershipSettings( 'acme-client-only', {
			label: 'Client only',
			render: () => <p>Not accepted by the server</p>,
		} );
		registry.registerMembershipSettings( 'acme-hidden', {
			label: 'Hidden',
			when: () => false,
			render: () => <p>Hidden panel</p>,
		} );
		renderEditor( registry );
		await loaded();

		expect(
			screen.getByRole( 'group', { name: 'Acme benefit' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'textbox', {
				name: 'Member benefit for All access',
			} )
		).toHaveValue( 'Office hours' );
		expect(
			screen.queryByText( 'Not accepted by the server' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Hidden panel' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save plan' } )
		).toBeDisabled();
	} );

	it( 'renders extensions that register after the editor', async () => {
		const registry = createRegistry();
		renderEditor( registry );
		await loaded();
		expect(
			screen.queryByRole( 'group', { name: 'Acme benefit' } )
		).not.toBeInTheDocument();

		act( () => {
			registry.registerMembershipSettings( 'acme-benefit', {
				label: 'Acme benefit',
				render: BenefitPanel,
			} );
		} );
		expect(
			screen.getByRole( 'textbox', {
				name: 'Member benefit for All access',
			} )
		).toHaveValue( 'Office hours' );
	} );

	it( 'saves changed values with the plan through the unsaved-changes flow', async () => {
		const registry = createRegistry();
		registry.registerMembershipSettings( 'acme-benefit', {
			label: 'Acme benefit',
			render: BenefitPanel,
		} );
		const user = userEvent.setup();
		renderEditor( registry );
		await loaded();

		const field = screen.getByRole( 'textbox', {
			name: 'Member benefit for All access',
		} );
		await user.clear( field );
		await user.type( field, 'Weekly tutoring' );
		expect( screen.getByText( 'You have unsaved changes.' ) ).toBeVisible();
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );

		expect(
			await screen.findByText( 'Plan saved.', notice )
		).toBeInTheDocument();
		expect( api.updateMembership ).toHaveBeenCalledTimes( 1 );
		expect(
			vi.mocked( api.updateMembership ).mock.calls[ 0 ]![ 1 ]
		).toEqual(
			expect.objectContaining( {
				name: 'All access',
				extension_settings: {
					'acme-benefit': { benefit: 'Weekly tutoring' },
				},
			} )
		);
		expect( field ).toHaveValue( 'Weekly tutoring' );
		expect(
			screen.queryByText( 'You have unsaved changes.' )
		).not.toBeInTheDocument();
	} );

	it( 'sends no extension settings when none changed', async () => {
		const registry = createRegistry();
		registry.registerMembershipSettings( 'acme-benefit', {
			label: 'Acme benefit',
			render: BenefitPanel,
		} );
		const user = userEvent.setup();
		renderEditor( registry );
		await loaded();

		await user.type(
			screen.getByRole( 'textbox', { name: 'Name' } ),
			' plus'
		);
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		await screen.findByText( 'Plan saved.', notice );
		expect(
			vi.mocked( api.updateMembership ).mock.calls[ 0 ]![ 1 ]
		).not.toHaveProperty( 'extension_settings' );
	} );

	it( 'shows a server rejection inside the extension panel', async () => {
		vi.mocked( api.updateMembership ).mockRejectedValue(
			new ApiError( {
				status: 400,
				code: 'ohmylms_membership_invalid_extension_settings',
				message:
					'acme-benefit[benefit] must be at most 200 characters long.',
				details: {
					status: 400,
					field: 'extension_settings',
					namespace: 'acme-benefit',
				},
			} )
		);
		const registry = createRegistry();
		registry.registerMembershipSettings( 'acme-benefit', {
			label: 'Acme benefit',
			render: BenefitPanel,
		} );
		const user = userEvent.setup();
		renderEditor( registry );
		await loaded();

		await user.type(
			screen.getByRole( 'textbox', {
				name: 'Member benefit for All access',
			} ),
			'!'
		);
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );

		const group = screen.getByRole( 'group', { name: 'Acme benefit' } );
		await waitFor( () =>
			expect( group ).toHaveAccessibleDescription(
				'acme-benefit[benefit] must be at most 200 characters long.'
			)
		);
		expect(
			screen.getByRole( 'textbox', {
				name: 'Member benefit for All access',
			} )
		).toHaveValue( 'Office hours!' );
	} );

	it( 'isolates a failing extension and keeps the editor working', async () => {
		const restore = quietErrors();
		const reported = vi.fn();
		window.addEventListener( 'ohmylms:extension-error', reported );
		const registry = createRegistry();
		registry.registerMembershipSettings( 'acme-broken', {
			label: 'Acme broken',
			render: () => {
				throw new Error( 'Panel failure' );
			},
		} );
		registry.registerMembershipSettings( 'acme-benefit', {
			label: 'Acme benefit',
			render: BenefitPanel,
		} );
		const user = userEvent.setup();
		renderEditor( registry );
		await loaded();

		expect(
			screen.getByRole( 'group', { name: 'Acme broken' } )
		).toHaveTextContent( 'This extension could not be displayed.' );
		expect(
			( reported.mock.calls[ 0 ]?.[ 0 ] as CustomEvent ).detail
		).toEqual( { id: 'acme-broken', message: 'Panel failure' } );

		await user.type(
			screen.getByRole( 'textbox', {
				name: 'Member benefit for All access',
			} ),
			' and more'
		);
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		await screen.findByText( 'Plan saved.', notice );
		expect(
			vi.mocked( api.updateMembership ).mock.calls[ 0 ]![ 1 ]
				.extension_settings
		).toEqual( {
			'acme-benefit': { benefit: 'Office hours and more' },
		} );
		window.removeEventListener( 'ohmylms:extension-error', reported );
		restore();
	} );

	it( 'ignores values that are not plain objects', async () => {
		const registry = createRegistry();
		registry.registerMembershipSettings( 'acme-benefit', {
			label: 'Acme benefit',
			render: ( { onChange }: MembershipSettingsProps ) => (
				<button
					type="button"
					onClick={ () =>
						onChange( [ 'not', 'an', 'object' ] as never )
					}
				>
					Send a list
				</button>
			),
		} );
		const user = userEvent.setup();
		renderEditor( registry );
		await loaded();

		await user.click(
			screen.getByRole( 'button', { name: 'Send a list' } )
		);
		expect(
			screen.queryByText( 'You have unsaved changes.' )
		).not.toBeInTheDocument();
	} );
} );
