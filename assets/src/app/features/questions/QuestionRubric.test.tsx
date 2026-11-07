import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ExtensionsProvider } from '../../extensions/context';
import { createRegistry } from '../../extensions/registry.mjs';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as api from './api';
import { question, types, version } from './fixtures';
import QuestionEditorPage from './QuestionEditorPage';
import { rubricErrorPath, rubricFromSettings } from './rubric';
import type { QuestionDetail } from './types';

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

const essay: QuestionDetail = {
	...question,
	type: 'long-text',
	settings: {
		type: 'long-text',
		question_code: 'Q-TEST',
		score: { enabled: true, value: 3 },
	},
	options: [],
};

const marked: QuestionDetail = {
	...essay,
	settings: {
		...essay.settings,
		rubric: [
			{
				id: 'reasoning',
				title: 'Reasoning',
				description: 'Explains each step.',
				points: 3,
				levels: [ { points: 3, description: 'Complete' } ],
			},
		],
	},
};

function renderEditor() {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<ExtensionsProvider value={ createRegistry() }>
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

async function loaded() {
	await screen.findByRole( 'textbox', { name: 'Question' } );
	return screen.getByRole( 'group', { name: 'Marking rubric' } );
}

function save() {
	return screen.getByRole( 'button', { name: 'Save question' } );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getQuestion ).mockResolvedValue( essay );
	vi.mocked( api.listQuestionTypes ).mockResolvedValue( types );
	vi.mocked( api.listBanks ).mockResolvedValue( [] );
	vi.mocked( api.getSkills ).mockResolvedValue( [] );
	vi.mocked( api.listVersions ).mockResolvedValue( {
		items: [ version ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.updateQuestion ).mockImplementation( async () => marked );
} );

describe( 'marking rubric of short and long text questions', () => {
	it( 'adds criteria with levels whose total becomes the points', async () => {
		const user = userEvent.setup();
		renderEditor();
		const rubric = await loaded();
		expect( screen.getByLabelText( 'Points' ) ).toHaveValue( 3 );

		await user.click(
			within( rubric ).getByRole( 'button', { name: 'Add criterion' } )
		);
		await user.type(
			within( rubric ).getByRole( 'textbox', { name: 'Criterion 1' } ),
			'Reasoning'
		);
		const points = within( rubric ).getByRole( 'spinbutton', {
			name: 'Points of criterion 1',
		} );
		await user.clear( points );
		await user.type( points, '2' );
		await user.click(
			within( rubric ).getByRole( 'button', {
				name: 'Add a level to criterion 1',
			} )
		);
		await user.type(
			within( rubric ).getByRole( 'spinbutton', {
				name: 'Points for level 1 of criterion 1',
			} ),
			'2'
		);
		await user.type(
			within( rubric ).getByRole( 'textbox', {
				name: 'Description of level 1 of criterion 1',
			} ),
			'Complete'
		);
		await user.click(
			within( rubric ).getByRole( 'button', { name: 'Add criterion' } )
		);
		await user.type(
			within( rubric ).getByRole( 'textbox', { name: 'Criterion 2' } ),
			'Clarity'
		);

		expect( within( rubric ).getByRole( 'status' ) ).toHaveTextContent(
			'Rubric total: 3 points'
		);
		expect( screen.queryByLabelText( 'Points' ) ).not.toBeInTheDocument();
		expect(
			screen.getByText( /Points: 3 \(the total of the rubric/ )
		).toBeInTheDocument();

		await user.click( save() );
		await waitFor( () =>
			expect( api.updateQuestion ).toHaveBeenCalledTimes( 1 )
		);
		const input = vi.mocked( api.updateQuestion ).mock.calls[ 0 ]![ 1 ];
		expect( input.settings?.rubric ).toEqual( [
			{
				id: 'c1',
				title: 'Reasoning',
				description: '',
				points: 2,
				levels: [ { points: 2, description: 'Complete' } ],
			},
			{
				id: 'c2',
				title: 'Clarity',
				description: '',
				points: 1,
				levels: [],
			},
		] );
		expect( input.settings?.score ).toEqual( {
			enabled: true,
			value: 3,
		} );
	} );

	it( 'checks the rubric before saving', async () => {
		vi.mocked( api.getQuestion ).mockResolvedValue( marked );
		const user = userEvent.setup();
		renderEditor();
		const rubric = await loaded();
		const title = within( rubric ).getByRole( 'textbox', {
			name: 'Criterion 1',
		} );
		expect( title ).toHaveValue( 'Reasoning' );
		await user.clear( title );
		await user.type(
			within( rubric ).getByRole( 'spinbutton', {
				name: 'Points for level 1 of criterion 1',
			} ),
			'0'
		);
		await user.click( save() );

		await waitFor( () =>
			expect( title ).toHaveAccessibleDescription(
				'Enter a title of 1 to 200 characters.'
			)
		);
		expect(
			within( rubric ).getByRole( 'spinbutton', {
				name: 'Points for level 1 of criterion 1',
			} )
		).toHaveAccessibleDescription(
			'Enter different points from 0 up to the criterion’s points.'
		);
		expect( api.updateQuestion ).not.toHaveBeenCalled();
	} );

	it( 'shows a server rejection at the rubric field', async () => {
		vi.mocked( api.getQuestion ).mockResolvedValue( marked );
		vi.mocked( api.updateQuestion ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_question_invalid_settings',
				message: 'Describe each level in at most 1000 characters.',
				details: {
					status: 422,
					field: 'settings.rubric[0].levels[0].description',
				},
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		const rubric = await loaded();
		const description = within( rubric ).getByRole( 'textbox', {
			name: 'Description of level 1 of criterion 1',
		} );
		await user.type( description, '!' );
		await user.click( save() );
		await waitFor( () =>
			expect( description ).toHaveAccessibleDescription(
				'Describe each level in at most 1000 characters.'
			)
		);
	} );

	it( 'removing every criterion brings back the points and drops the rubric', async () => {
		vi.mocked( api.getQuestion ).mockResolvedValue( marked );
		const user = userEvent.setup();
		renderEditor();
		const rubric = await loaded();
		await user.click(
			within( rubric ).getByRole( 'button', {
				name: 'Remove criterion 1',
			} )
		);
		expect( screen.getByLabelText( 'Points' ) ).toHaveValue( 3 );
		await user.click( save() );
		await waitFor( () =>
			expect( api.updateQuestion ).toHaveBeenCalledTimes( 1 )
		);
		const input = vi.mocked( api.updateQuestion ).mock.calls[ 0 ]![ 1 ];
		expect( input.settings ).not.toHaveProperty( 'rubric' );
		expect( input.settings?.score ).toEqual( {
			enabled: true,
			value: 3,
		} );
	} );

	it( 'has no rubric for automatically marked types', async () => {
		vi.mocked( api.getQuestion ).mockResolvedValue( question );
		renderEditor();
		await screen.findByRole( 'textbox', { name: 'Question' } );
		expect(
			screen.queryByRole( 'group', { name: 'Marking rubric' } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'rubric model', () => {
	it( 'reads stored criteria with stable keys and maps server fields', () => {
		const rows = rubricFromSettings( marked.settings );
		expect( rows ).toEqual( rubricFromSettings( marked.settings ) );
		expect( rows[ 0 ] ).toMatchObject( {
			id: 'reasoning',
			points: '3',
			levels: [ { points: '3', description: 'Complete' } ],
		} );
		expect( rubricErrorPath( 'settings.rubric[2].title' ) ).toBe(
			'rubric.2.title'
		);
		expect( rubricErrorPath( 'settings.rubric' ) ).toBe( 'rubric' );
		expect( rubricErrorPath( 'settings.rubric[0].id' ) ).toBe( 'rubric' );
		expect( rubricErrorPath( 'settings.hint' ) ).toBeUndefined();
	} );
} );
