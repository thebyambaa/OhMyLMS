import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderShell } from '../../../test/render';
import { memberships } from '../../memberships';
import * as api from './api';
import { optionsBody, ruleRow } from './fixtures';
import { parseOptions, parseRule } from './model';

vi.mock( './api', () => ( {
	listRules: vi.fn(),
	getRule: vi.fn(),
	getRuleOptions: vi.fn(),
	createRule: vi.fn(),
	updateRule: vi.fn(),
	deleteRule: vi.fn(),
	listLog: vi.fn(),
	searchChoices: vi.fn(),
} ) );

const rule = parseRule( ruleRow );
const page = < T, >( items: T[] ) => ( {
	items,
	total: items.length,
	totalPages: 1,
} );
const show = ( route: string ) =>
	renderShell( [ memberships ], {
		route,
		capabilities: { manage_options: true },
	} );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.getRuleOptions ).mockResolvedValue(
		parseOptions( optionsBody )
	);
	vi.mocked( api.listRules ).mockResolvedValue( page( [ rule ] ) );
	vi.mocked( api.getRule ).mockResolvedValue( rule );
	vi.mocked( api.searchChoices ).mockResolvedValue( [
		{ id: 40, title: 'Intro to Algebra' },
		{ id: 41, title: 'Algebra II' },
	] );
	vi.mocked( api.listLog ).mockResolvedValue(
		page( [
			{
				id: 1,
				automation_id: 7,
				rule_name: 'Welcome',
				event: 'course_enrolled',
				user_name: 'Ada',
				object_title: 'Intro to Algebra',
				action: 'send_email',
				status: 'success',
				message: 'Email sent.',
				created_at: '2026-10-31T09:00:00',
			},
		] )
	);
} );

describe( 'automations panel', () => {
	it( 'lists rules as "when … then …" with their runs', async () => {
		show( '/memberships?panel=automations' );
		const table = await screen.findByRole( 'table', {
			name: 'Automation rules',
		} );
		expect( within( table ).getByText( 'Welcome' ) ).toBeInTheDocument();
		expect(
			within( table ).getByText( 'A learner is enrolled in a course' )
		).toBeInTheDocument();
		expect(
			within( table ).getByText( 'Send an email, Enrol in a course' )
		).toBeInTheDocument();
	} );

	it( 'pauses a rule with a status-only update', async () => {
		vi.mocked( api.updateRule ).mockResolvedValue( {
			...rule,
			status: 'paused',
		} );
		const user = userEvent.setup();
		show( '/memberships?panel=automations' );
		const table = await screen.findByRole( 'table', {
			name: 'Automation rules',
		} );
		await user.click(
			within( table ).getByRole( 'button', { name: 'Pause' } )
		);
		expect( api.updateRule ).toHaveBeenCalledWith(
			7,
			{ status: 'paused' },
			expect.anything()
		);
	} );

	it( 'creates a rule only from allow-listed choices and validates first', async () => {
		vi.mocked( api.createRule ).mockResolvedValue( rule );
		const user = userEvent.setup();
		show( '/memberships?panel=automations&rule=new' );
		const form = await screen.findByRole( 'form', { name: 'New rule' } );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Create rule' } )
		);
		expect(
			within( form ).getByText( /Enter a name of 1 to/ )
		).toBeInTheDocument();
		expect( api.createRule ).not.toHaveBeenCalled();

		await user.type( within( form ).getByLabelText( 'Name' ), 'Welcome' );
		await user.type( within( form ).getByLabelText( 'Subject' ), 'Hi' );
		await user.type(
			within( form ).getByLabelText( 'Message (plain text)' ),
			'Hello {{first_name}'
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Create rule' } )
		);
		expect( api.createRule ).toHaveBeenCalledWith(
			{
				name: 'Welcome',
				status: 'paused',
				event: 'course_enrolled',
				conditions: {},
				actions: [
					{
						type: 'send_email',
						to: 'learner',
						subject: 'Hi',
						body: 'Hello {first_name}',
					},
				],
			},
			expect.anything()
		);
	} );

	it( 'shows the run log', async () => {
		show( '/memberships?panel=automations&show=log' );
		const table = await screen.findByRole( 'table', {
			name: 'Automation run log',
		} );
		expect(
			within( table ).getByText( 'Done: Email sent.' )
		).toBeInTheDocument();
		expect( api.listLog ).toHaveBeenCalledWith(
			1,
			'any',
			0,
			expect.anything()
		);
	} );
} );
