import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ExtensionsProvider } from '../../extensions/context';
import {
	createRegistry,
	type ExtensionRegistry,
} from '../../extensions/registry.mjs';
import { settingsDefinitions, settingsFields } from '../../extensions/settings';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as api from './api';
import { question, types, version } from './fixtures';
import QuestionEditorPage from './QuestionEditorPage';
import type { QuestionSettingsProps } from './QuestionExtensionSettings';

vi.mock( './api', () => ( {
	getQuestion: vi.fn(),
	listQuestionTypes: vi.fn(),
	updateQuestion: vi.fn(),
	listVersions: vi.fn(),
	listBanks: vi.fn(),
	searchSkills: vi.fn(),
	getSkills: vi.fn(),
	previewQuestion: vi.fn(),
} ) );

const manifest = {
	question: {},
	settings: {
		'acme-review': {
			contexts: [ 'question', 'quiz' ],
			label: 'Acme review',
			schema: {
				type: 'object',
				required: [ 'level' ],
				properties: {
					note: {
						type: 'string',
						title: 'Reviewer note',
						maxLength: 50,
					},
					level: {
						type: 'integer',
						title: 'Level',
						minimum: 1,
						maximum: 5,
					},
					audience: {
						type: 'string',
						title: 'Audience',
						enum: [ 'teachers', 'students' ],
					},
					flagged: { type: 'boolean', title: 'Flagged' },
					tags: { type: 'array', items: { type: 'string' } },
				},
				additionalProperties: false,
			},
		},
		'acme-plan': {
			contexts: [ 'membership' ],
			schema: { type: 'object', properties: {} },
		},
	},
};

function renderEditor( registry: ExtensionRegistry = createRegistry() ) {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<ExtensionsProvider value={ registry }>
				<MemoryRouter
					initialEntries={ [ '/content-hub/question-bank/812' ] }
				>
					<Routes>
						<Route
							path="/content-hub/question-bank/:id"
							element={ <QuestionEditorPage /> }
						/>
					</Routes>
				</MemoryRouter>
			</ExtensionsProvider>
		</BootstrapProvider>
	);
}

async function section() {
	await screen.findByRole( 'textbox', { name: 'Question' } );
	return screen.getByRole( 'group', { name: 'Acme review' } );
}

function save() {
	return screen.getByRole( 'button', { name: 'Save question' } );
}

function sent() {
	return vi.mocked( api.updateQuestion ).mock.calls[ 0 ]![ 1 ];
}

function NotePanel( {
	value,
	onChange,
	question: edited,
}: QuestionSettingsProps ) {
	return (
		<label htmlFor="acme-note">
			{ `Note for ${ edited.type }` }
			<input
				id="acme-note"
				value={ 'string' === typeof value.note ? value.note : '' }
				onChange={ ( event ) =>
					onChange( { ...value, note: event.target.value } )
				}
			/>
		</label>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	(
		window as { ohmylmsExtensionManifest?: unknown }
	 ).ohmylmsExtensionManifest = manifest;
	vi.mocked( api.getQuestion ).mockResolvedValue( {
		...question,
		extension_settings: { 'acme-review': { level: 2, tags: [ 'x' ] } },
	} );
	vi.mocked( api.listQuestionTypes ).mockResolvedValue( types );
	vi.mocked( api.listBanks ).mockResolvedValue( [] );
	vi.mocked( api.getSkills ).mockResolvedValue( [] );
	vi.mocked( api.listVersions ).mockResolvedValue( {
		items: [ version ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.updateQuestion ).mockImplementation(
		async ( id, input ) => ( {
			...question,
			id,
			extension_settings: {
				'acme-review': { level: 2, tags: [ 'x' ] },
				...input.extension_settings,
			},
			modified: '2026-10-30T14:00:00Z',
		} )
	);
} );

afterEach( () => {
	delete ( window as { ohmylmsExtensionManifest?: unknown } )
		.ohmylmsExtensionManifest;
} );

describe( 'question extension settings', () => {
	it( 'edits the simple properties of a schema and saves them with the question', async () => {
		const user = userEvent.setup();
		renderEditor();
		const group = await section();
		expect(
			screen.queryByRole( 'group', { name: 'acme-plan' } )
		).not.toBeInTheDocument();
		expect(
			within( group ).getByRole( 'spinbutton', { name: 'Level' } )
		).toHaveValue( 2 );
		expect( group ).toHaveTextContent(
			'These settings can only be changed by the add-on that registered them and are kept as they are: tags.'
		);

		await user.type(
			within( group ).getByRole( 'textbox', { name: 'Reviewer note' } ),
			'Check units'
		);
		const level = within( group ).getByRole( 'spinbutton', {
			name: 'Level',
		} );
		await user.clear( level );
		await user.type( level, '4' );
		await user.selectOptions(
			within( group ).getByRole( 'combobox', { name: 'Audience' } ),
			'teachers'
		);
		await user.click(
			within( group ).getByRole( 'checkbox', { name: 'Flagged' } )
		);
		expect( screen.getByText( 'You have unsaved changes.' ) ).toBeVisible();
		await user.click( save() );

		await waitFor( () =>
			expect( api.updateQuestion ).toHaveBeenCalledTimes( 1 )
		);
		expect( sent().extension_settings ).toEqual( {
			'acme-review': {
				level: 4,
				tags: [ 'x' ],
				note: 'Check units',
				audience: 'teachers',
				flagged: true,
			},
		} );
	} );

	it( 'checks values against the schema before saving', async () => {
		const user = userEvent.setup();
		renderEditor();
		const group = await section();
		const level = within( group ).getByRole( 'spinbutton', {
			name: 'Level',
		} );
		await user.clear( level );
		await user.click( save() );
		expect( level ).toHaveAccessibleDescription(
			'This field is required.'
		);
		await user.type( level, '9' );
		await user.click( save() );
		expect( level ).toHaveAccessibleDescription(
			'Enter a value from 1 to 5.'
		);
		expect( api.updateQuestion ).not.toHaveBeenCalled();
	} );

	it( 'sends no extension settings when none changed', async () => {
		const user = userEvent.setup();
		renderEditor();
		await section();
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Learners must answer' } )
		);
		await user.click( save() );
		await waitFor( () =>
			expect( api.updateQuestion ).toHaveBeenCalledTimes( 1 )
		);
		expect( sent() ).not.toHaveProperty( 'extension_settings' );
	} );

	it( 'uses a registered question-settings panel instead of the generic fields', async () => {
		const registry = createRegistry();
		registry.registerQuestionSettings( 'acme-review', {
			label: 'Acme review',
			render: NotePanel,
		} );
		registry.registerQuestionSettings( 'acme-unknown', {
			label: 'Not accepted by the server',
			render: () => <p>Unknown panel</p>,
		} );
		const user = userEvent.setup();
		renderEditor( registry );
		const group = await section();
		expect(
			within( group ).queryByRole( 'spinbutton', { name: 'Level' } )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Unknown panel' ) ).not.toBeInTheDocument();
		await user.type(
			within( group ).getByRole( 'textbox', {
				name: 'Note for single-choice',
			} ),
			'Hi'
		);
		await user.click( save() );
		await waitFor( () =>
			expect( api.updateQuestion ).toHaveBeenCalledTimes( 1 )
		);
		expect( sent().extension_settings ).toEqual( {
			'acme-review': { level: 2, tags: [ 'x' ], note: 'Hi' },
		} );
	} );

	it( 'renders panels that register after the editor', async () => {
		const registry = createRegistry();
		renderEditor( registry );
		await section();
		act( () => {
			registry.registerQuestionSettings( 'acme-review', {
				label: 'Acme review',
				render: NotePanel,
			} );
		} );
		expect(
			screen.getByRole( 'textbox', { name: 'Note for single-choice' } )
		).toBeInTheDocument();
	} );

	it( 'shows a server rejection inside the namespace', async () => {
		vi.mocked( api.updateQuestion ).mockRejectedValue(
			new ApiError( {
				status: 400,
				code: 'ohmylms_question_invalid_extension_settings',
				message:
					'acme-review[note] must be at most 50 characters long.',
				details: {
					status: 400,
					field: 'extension_settings',
					namespace: 'acme-review',
				},
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		const group = await section();
		await user.type(
			within( group ).getByRole( 'textbox', { name: 'Reviewer note' } ),
			'x'
		);
		await user.click( save() );
		await waitFor( () =>
			expect( group ).toHaveAccessibleDescription(
				'acme-review[note] must be at most 50 characters long.'
			)
		);
	} );
} );

describe( 'extension settings model', () => {
	it( 'reads definitions of a context and the editable fields of a schema', () => {
		expect(
			settingsDefinitions( 'question', manifest ).map( ( item ) => [
				item.id,
				item.label,
			] )
		).toEqual( [ [ 'acme-review', 'Acme review' ] ] );
		expect( settingsDefinitions( 'question', null ) ).toEqual( [] );
		expect( settingsDefinitions( 'question', { settings: [] } ) ).toEqual(
			[]
		);
		const { fields, unsupported } = settingsFields(
			manifest.settings[ 'acme-review' ].schema
		);
		expect( fields.map( ( field ) => [ field.name, field.kind ] ) ).toEqual(
			[
				[ 'note', 'text' ],
				[ 'level', 'integer' ],
				[ 'audience', 'choice' ],
				[ 'flagged', 'boolean' ],
			]
		);
		expect( fields[ 1 ]?.required ).toBe( true );
		expect( unsupported ).toEqual( [ 'tags' ] );
	} );
} );
