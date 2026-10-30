import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import {
	createRegistry,
	type ExtensionRegistry,
} from '../../extensions/registry.mjs';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import {
	registerAppQuestionEditors,
	type EditorModuleLoader,
} from '../../extensions/coreEditors';
import { ExtensionsProvider } from '../../extensions/context';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as api from './api';
import { question, types, version } from './fixtures';
import QuestionEditorPage from './QuestionEditorPage';
import type { QuestionDetail, QuestionSettings } from './types';

vi.mock( './api', () => ( {
	getQuestion: vi.fn(),
	listQuestionTypes: vi.fn(),
	updateQuestion: vi.fn(),
	archiveQuestion: vi.fn(),
	restoreQuestion: vi.fn(),
	approveQuestion: vi.fn(),
	duplicateQuestion: vi.fn(),
	createQuestion: vi.fn(),
	listVersions: vi.fn(),
} ) );

const LEGACY =
	'http://example.test/wp-admin/admin.php?page=ohmylms#/content-hub/question-bank';

function renderEditor(
	route = '/content-hub/question-bank/812',
	extensions: ExtensionRegistry = createRegistry()
) {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<ExtensionsProvider value={ extensions }>
				<MemoryRouter initialEntries={ [ route ] }>
					<Link to="/content-hub/question-bank">Away</Link>
					<Routes>
						<Route
							path="/content-hub/question-bank/:id"
							element={ <QuestionEditorPage /> }
						/>
						<Route
							path="/content-hub/question-bank"
							element={ <p>List route reached</p> }
						/>
					</Routes>
				</MemoryRouter>
			</ExtensionsProvider>
		</BootstrapProvider>
	);
}

const notice = { selector: '.components-notice__content' };

function quietErrors() {
	vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
	const prevent = ( event: ErrorEvent ) => event.preventDefault();
	window.addEventListener( 'error', prevent );
	return () => window.removeEventListener( 'error', prevent );
}

function loadQuestion( value: QuestionDetail ) {
	vi.mocked( api.getQuestion ).mockResolvedValue( value );
}

async function loaded() {
	return screen.findByRole( 'textbox', { name: 'Name' } );
}

function saveButton() {
	return screen.getByRole( 'button', { name: 'Save question' } );
}

const numberQuestion: QuestionDetail = {
	...question,
	type: 'example-number',
	settings: {
		type: 'example-number',
		score: { enabled: true, value: 2 },
		expected: '7',
	},
	options: [],
};

beforeEach( () => {
	vi.resetAllMocks();
	loadQuestion( question );
	vi.mocked( api.listQuestionTypes ).mockResolvedValue( types );
	vi.mocked( api.listVersions ).mockResolvedValue( {
		items: [ version ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.updateQuestion ).mockImplementation(
		async ( id, input ) => ( {
			...question,
			id,
			name: input.name ?? question.name,
			version: question.version + 1,
			modified: '2026-10-30T14:00:00Z',
		} )
	);
} );

describe( 'QuestionEditorPage', () => {
	it( 'rejects an invalid route id without requesting anything', () => {
		renderEditor( '/content-hub/question-bank/abc' );
		expect(
			screen.getByText( 'This question link is invalid.', notice )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to question bank' } )
		).toBeInTheDocument();
		expect( api.getQuestion ).not.toHaveBeenCalled();
	} );

	it( 'loads the question and the types in parallel', async () => {
		renderEditor();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading question'
		);
		expect( await loaded() ).toHaveValue( 'Solve 2x = 6' );
		expect( api.getQuestion ).toHaveBeenCalledWith(
			812,
			expect.any( AbortSignal )
		);
		expect( api.listQuestionTypes ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByRole( 'heading', {
				name: 'Edit question: Solve 2x = 6',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( /Type: Single Choice/ ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Question text' ) ).toHaveValue(
			'<p>Find x.</p>'
		);
		expect( screen.getByLabelText( 'Points' ) ).toHaveValue( 2 );
		expect(
			screen.getByRole( 'combobox', { name: 'Difficulty' } )
		).toHaveValue( 'easy' );
		expect(
			screen.getByRole( 'textbox', { name: 'Option 1' } )
		).toHaveValue( '3' );
		expect(
			screen.getByRole( 'checkbox', { name: 'Option 1 is correct' } )
		).toBeChecked();
		expect( saveButton() ).toBeDisabled();
		const versions = await screen.findByRole( 'table', {
			name: 'Versions',
		} );
		expect( within( versions ).getByText( 'v3' ) ).toBeInTheDocument();
		expect( within( versions ).getByText( 'Current' ) ).toBeInTheDocument();
	} );

	it( 'shows a load error with a way back', async () => {
		vi.mocked( api.getQuestion ).mockRejectedValue(
			new ApiError( {
				status: 404,
				code: 'ohmylms_question_not_found',
				message: 'Question not found.',
			} )
		);
		renderEditor();
		expect(
			await screen.findByText( 'Question not found.', notice )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );

	it( 'edits options and saves only them with base_modified', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.type(
			screen.getByRole( 'textbox', { name: 'Option 2' } ),
			'0'
		);
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Option 2 is correct' } )
		);
		expect(
			screen.getByRole( 'checkbox', { name: 'Option 1 is correct' } )
		).not.toBeChecked();
		await user.click(
			screen.getByRole( 'button', { name: 'Add option' } )
		);
		await user.type(
			screen.getByRole( 'textbox', { name: 'Option 3' } ),
			'6'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Move option 3 up' } )
		);
		await user.click( saveButton() );
		expect(
			await screen.findByText( 'Question saved.', notice )
		).toBeInTheDocument();
		expect( api.updateQuestion ).toHaveBeenCalledWith(
			812,
			{
				base_modified: '2026-10-30T13:05:12Z',
				options: [
					{
						id: 3301,
						answer: '3',
						is_correct: false,
						order_number: 0,
					},
					{ answer: '6', is_correct: false, order_number: 1 },
					{
						id: 3302,
						answer: '120',
						is_correct: true,
						order_number: 2,
					},
				],
			},
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect( api.listVersions ).toHaveBeenCalledTimes( 2 )
		);
		expect( saveButton() ).toBeDisabled();
	} );

	it( 'checks the option rules before saving', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Option 1 is correct' } )
		);
		await user.click( saveButton() );
		expect(
			await screen.findByText( 'Mark exactly one option as correct.' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Fix the highlighted fields before saving.',
				notice
			)
		).toBeInTheDocument();
		expect( api.updateQuestion ).not.toHaveBeenCalled();
	} );

	it( 'maps a server field error to its field', async () => {
		vi.mocked( api.updateQuestion ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_question_invalid_name',
				message: 'Enter a question name.',
				details: { status: 422, field: 'name' },
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		const name = await loaded();
		await user.type( name, '!' );
		await user.click( saveButton() );
		const alert = await screen.findByRole( 'alert' );
		expect( alert ).toHaveTextContent( 'Enter a question name.' );
		expect( name ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( name ).toHaveAttribute( 'aria-describedby', alert.id );
	} );

	it( 'keeps edits on a conflict and reloads on request', async () => {
		vi.mocked( api.updateQuestion ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_question_conflict',
				message:
					'This question was changed by someone else. Reload it before saving.',
				details: { status: 409, modified: '2026-10-30T13:07:40Z' },
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		const name = await loaded();
		await user.clear( name );
		await user.type( name, 'Solve 2x = 8' );
		await user.click( saveButton() );
		expect(
			await screen.findByText( /changed by someone else/, notice )
		).toBeInTheDocument();
		expect( api.updateQuestion ).toHaveBeenCalledWith(
			812,
			{ base_modified: '2026-10-30T13:05:12Z', name: 'Solve 2x = 8' },
			expect.any( AbortSignal )
		);
		expect( name ).toHaveValue( 'Solve 2x = 8' );
		loadQuestion( {
			...question,
			name: 'Changed elsewhere',
			modified: '2026-10-30T13:07:40Z',
		} );
		await user.click( screen.getByRole( 'button', { name: 'Reload' } ) );
		await waitFor( () =>
			expect(
				screen.getByRole( 'textbox', { name: 'Name' } )
			).toHaveValue( 'Changed elsewhere' )
		);
		expect( api.getQuestion ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'asks before leaving with unsaved changes', async () => {
		const user = userEvent.setup();
		renderEditor();
		await user.type( await loaded(), '?' );
		await user.click( screen.getByRole( 'link', { name: 'Away' } ) );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Discard unsaved changes?',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		);
		expect( screen.queryByText( 'List route reached' ) ).toBeNull();
		await user.click( screen.getByRole( 'link', { name: 'Away' } ) );
		await user.click(
			await screen.findByRole( 'button', { name: 'Discard changes' } )
		);
		expect(
			await screen.findByText( 'List route reached' )
		).toBeInTheDocument();
	} );

	it( 'archives the question', async () => {
		vi.mocked( api.archiveQuestion ).mockResolvedValue( {
			...question,
			status: 'archived',
			post_status: 'ohmylms_archived',
		} );
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'button', { name: 'Archive question' } )
		);
		expect(
			await screen.findByText( 'Question archived.', notice )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Restore question' } )
		).toBeInTheDocument();
	} );

	it( 'is read only without edit permission', async () => {
		loadQuestion( {
			...question,
			permissions: {
				can_edit: false,
				can_approve: false,
				can_delete: false,
			},
		} );
		renderEditor();
		expect( await loaded() ).toBeDisabled();
		expect(
			screen.getByText(
				'You can view this question but not change it.',
				notice
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Save question' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Archive question' } )
		).not.toBeInTheDocument();
	} );

	it( 'renders a registered question editor and merges its patches with the type', async () => {
		const registry = createRegistry();
		const seen: QuestionSettings[] = [];
		registry.registerQuestionEditor( 'example-number', {
			label: 'Example number',
			render: ( {
				question: current,
				value,
				onChange,
			}: {
				question: QuestionDetail;
				value: QuestionSettings;
				onChange: ( patch: QuestionSettings ) => void;
			} ) => {
				seen.push( value );
				return (
					<>
						<label htmlFor="oml-test-expected">
							Expected number for { current.name }
						</label>
						<input
							id="oml-test-expected"
							value={ String( value.expected ?? '' ) }
							onChange={ ( event ) =>
								onChange( {
									expected: event.target.value,
									type: 'something-else',
								} )
							}
						/>
					</>
				);
			},
		} );
		loadQuestion( numberQuestion );
		const user = userEvent.setup();
		renderEditor( undefined, registry );
		const field = await screen.findByRole( 'textbox', {
			name: 'Expected number for Solve 2x = 6',
		} );
		expect( field ).toHaveValue( '7' );
		await user.clear( field );
		await user.type( field, '42' );
		expect( seen.at( -1 ) ).toEqual( {
			type: 'example-number',
			score: { enabled: true, value: 2 },
			expected: '42',
		} );
		await user.click( saveButton() );
		await screen.findByText( 'Question saved.', notice );
		expect( api.updateQuestion ).toHaveBeenCalledWith(
			812,
			{
				base_modified: '2026-10-30T13:05:12Z',
				settings: {
					type: 'example-number',
					score: { enabled: true, value: 2 },
					expected: '42',
				},
			},
			expect.any( AbortSignal )
		);
	} );

	it( 'isolates a question editor that throws', async () => {
		const restore = quietErrors();
		const registry = createRegistry();
		registry.registerQuestionEditor( 'example-number', {
			label: 'Example number',
			render: () => {
				throw new Error( 'Broken editor' );
			},
		} );
		loadQuestion( numberQuestion );
		const user = userEvent.setup();
		renderEditor( undefined, registry );
		const name = await loaded();
		expect(
			screen.getByText( 'This extension could not be displayed.' )
		).toBeInTheDocument();
		await user.type( name, '!' );
		expect( saveButton() ).toBeEnabled();
		restore();
	} );

	it( 'points to the full editor for types without an editor here', async () => {
		loadQuestion( {
			...question,
			type: 'long-text',
			settings: { type: 'long-text' },
			options: [],
		} );
		const { unmount } = renderEditor();
		await loaded();
		expect(
			screen.getByText(
				/does not have an editor in the new admin yet/,
				notice
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', {
				name: 'Open the full question editor',
			} )
		).toHaveAttribute( 'href', LEGACY );
		expect(
			screen.queryByRole( 'button', { name: 'Add option' } )
		).not.toBeInTheDocument();
		unmount();

		loadQuestion( {
			...question,
			type: 'fill-in-the-blank',
			settings: { type: 'fill-in-the-blank' },
		} );
		renderEditor();
		await loaded();
		expect(
			screen.getByText( /edited in the full editor/, notice )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'textbox', { name: 'Option 1' } )
		).not.toBeInTheDocument();
	} );

	it( 'edits matching labels and hides correct answers for matching', async () => {
		loadQuestion( {
			...question,
			type: 'matching',
			settings: { type: 'matching' },
			options: question.options.map( ( item, index ) => ( {
				...item,
				matching_data: { label: `M${ index + 1 }` },
			} ) ),
		} );
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		expect( screen.queryByRole( 'checkbox' ) ).not.toBeInTheDocument();
		const label = screen.getByRole( 'textbox', {
			name: 'Match for option 2',
		} );
		await user.clear( label );
		await user.click( saveButton() );
		expect(
			await screen.findByText(
				'Enter the matching answer for this option.'
			)
		).toBeInTheDocument();
		expect( label ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( api.updateQuestion ).not.toHaveBeenCalled();
	} );

	it( 'loads a built-in editor lazily behind a fallback and saves its settings', async () => {
		let finish: (
			module: Awaited< ReturnType< EditorModuleLoader > >
		) => void = () => undefined;
		const load = vi.fn(
			() =>
				new Promise< Awaited< ReturnType< EditorModuleLoader > > >(
					( resolve ) => {
						finish = resolve;
					}
				)
		);
		const registry = createRegistry();
		registerAppQuestionEditors( registry, load );
		loadQuestion( {
			...question,
			type: 'numerical',
			settings: {
				type: 'numerical',
				score: { enabled: true, value: 2 },
				answer: 3,
			},
			options: [],
		} );
		const user = userEvent.setup();
		renderEditor( undefined, registry );
		await loaded();
		expect( screen.getByText( 'Loading editor…' ) ).toHaveAttribute(
			'role',
			'status'
		);
		expect(
			screen.getByRole( 'group', { name: 'Numerical' } )
		).toBeInTheDocument();
		expect( load ).toHaveBeenCalledTimes( 1 );

		finish( {
			NumericalEditor: ( {
				value,
				onChange,
			}: {
				value: QuestionSettings;
				onChange: ( patch: QuestionSettings ) => void;
			} ) => (
				<>
					<label htmlFor="oml-test-answer">Answer</label>
					<input
						id="oml-test-answer"
						value={ String( value.answer ?? '' ) }
						onChange={ ( event ) =>
							onChange( { answer: Number( event.target.value ) } )
						}
					/>
				</>
			),
		} );
		const field = await screen.findByRole( 'textbox', { name: 'Answer' } );
		expect(
			screen.queryByText( 'Loading editor…' )
		).not.toBeInTheDocument();
		expect( field ).toHaveValue( '3' );
		await user.clear( field );
		await user.type( field, '6' );
		await user.click( saveButton() );
		await screen.findByText( 'Question saved.', notice );
		expect( api.updateQuestion ).toHaveBeenCalledWith(
			812,
			{
				base_modified: '2026-10-30T13:05:12Z',
				settings: {
					type: 'numerical',
					score: { enabled: true, value: 2 },
					answer: 6,
				},
			},
			expect.any( AbortSignal )
		);
	} );

	describe( 'approval', () => {
		it( 'approves the current version', async () => {
			vi.mocked( api.approveQuestion ).mockResolvedValue( {
				...question,
				status: 'approved',
				approved_version_id: 95,
				approved_is_current: true,
			} );
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			expect(
				screen.getByText(
					'No version is approved for shared use and practice yet.'
				)
			).toBeInTheDocument();
			await user.click(
				screen.getByRole( 'button', { name: 'Approve this version' } )
			);
			expect(
				await screen.findByText( 'Version 3 approved.', notice )
			).toBeInTheDocument();
			expect( api.approveQuestion ).toHaveBeenCalledWith(
				812,
				expect.any( AbortSignal )
			);
			expect(
				screen.getByText(
					'This version is approved for shared use and practice.'
				)
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'button', { name: 'Approve this version' } )
			).not.toBeInTheDocument();
			await waitFor( () =>
				expect( api.listVersions ).toHaveBeenCalledTimes( 2 )
			);
		} );

		it( 'asks to save first while there are unsaved changes', async () => {
			const user = userEvent.setup();
			renderEditor();
			await user.type( await loaded(), '!' );
			expect(
				screen.getByRole( 'button', { name: 'Approve this version' } )
			).toBeDisabled();
			expect(
				screen.getByRole( 'button', { name: 'Duplicate' } )
			).toBeDisabled();
			expect( api.approveQuestion ).not.toHaveBeenCalled();
		} );

		it( 'offers approval of a newer version only to approvers', async () => {
			loadQuestion( {
				...question,
				status: 'approved',
				approved_version_id: 94,
				approved_is_current: false,
			} );
			const { unmount } = renderEditor();
			await loaded();
			expect(
				screen.getByText( /An earlier version is approved/ )
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', { name: 'Approve this version' } )
			).toBeEnabled();
			unmount();

			loadQuestion( {
				...question,
				permissions: {
					can_edit: true,
					can_approve: false,
					can_delete: true,
				},
			} );
			renderEditor();
			await loaded();
			expect(
				screen.queryByRole( 'heading', { name: 'Approval' } )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'button', { name: 'Approve this version' } )
			).not.toBeInTheDocument();
		} );

		it( 'shows an approval failure', async () => {
			vi.mocked( api.approveQuestion ).mockRejectedValue(
				new ApiError( {
					status: 409,
					code: 'quiz_type_unversioned',
					message:
						'This question type cannot be approved for versioned use.',
				} )
			);
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Approve this version' } )
			);
			expect(
				await screen.findByText(
					'This question type cannot be approved for versioned use.',
					notice
				)
			).toBeInTheDocument();
		} );
	} );

	describe( 'duplicate', () => {
		const copy: QuestionDetail = {
			...question,
			id: 900,
			uuid: '1b0c5c1e-0000-4000-8000-000000000900',
			name: 'Solve 2x = 6 (copy)',
			version: 1,
			current_version_id: 120,
			usage: { quizzes: 0, responses: 0 },
			quizzes: [],
			modified: '2026-10-30T14:10:00Z',
		};

		it( 'opens the editor of the copy', async () => {
			vi.mocked( api.getQuestion ).mockImplementation( async ( id ) =>
				900 === id ? copy : question
			);
			vi.mocked( api.duplicateQuestion ).mockResolvedValue( copy );
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Duplicate' } )
			);
			expect(
				await screen.findByRole( 'heading', {
					name: 'Edit question: Solve 2x = 6 (copy)',
				} )
			).toBeInTheDocument();
			expect(
				screen.getByText(
					'Question duplicated. You are now editing the copy.',
					notice
				)
			).toBeInTheDocument();
			expect( api.duplicateQuestion ).toHaveBeenCalledWith(
				812,
				expect.any( AbortSignal )
			);
			expect(
				screen.getByRole( 'textbox', { name: 'Name' } )
			).toHaveValue( 'Solve 2x = 6 (copy)' );
			expect( api.getQuestion ).not.toHaveBeenCalledWith(
				900,
				expect.anything()
			);
		} );

		it( 'stays on the question when duplicating fails', async () => {
			vi.mocked( api.duplicateQuestion ).mockRejectedValue(
				new ApiError( {
					status: 403,
					code: 'ohmylms_question_cannot_duplicate',
					message:
						'Sorry, you are not allowed to duplicate this question.',
				} )
			);
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Duplicate' } )
			);
			expect(
				await screen.findByText(
					'Sorry, you are not allowed to duplicate this question.',
					notice
				)
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'heading', {
					name: 'Edit question: Solve 2x = 6',
				} )
			).toBeInTheDocument();
		} );
	} );

	describe( 'new question draft', () => {
		const draftRoute = ( type: string, name = 'Draft question' ) =>
			`/content-hub/question-bank/new?${ new URLSearchParams( {
				type,
				name,
			} ) }`;

		function seenEditor( registry: ExtensionRegistry ) {
			const seen: QuestionSettings[] = [];
			const Editor = ( {
				value,
				onChange,
			}: {
				value: QuestionSettings;
				onChange: ( patch: QuestionSettings ) => void;
			} ) => {
				seen.push( value );
				return (
					<>
						<label htmlFor="oml-test-hour">Hour</label>
						<input
							id="oml-test-hour"
							value={ String( value.hour ?? '' ) }
							onChange={ ( event ) =>
								onChange( {
									hour: Number( event.target.value ),
								} )
							}
						/>
					</>
				);
			};
			for ( const id of [
				'set-clock',
				'categorize',
				'numerical',
				'graded-reader',
			] ) {
				registry.registerQuestionEditor( id, {
					label: `Editor ${ id }`,
					render: Editor,
				} );
			}
			return seen;
		}

		async function draftLoaded( name = 'Draft question' ) {
			return screen.findByRole( 'heading', {
				name: `New question: ${ name }`,
			} );
		}

		it.each( [
			[
				'set-clock',
				{
					hour: 3,
					minute: 45,
					tolerance: 1,
					snap: 1,
					show_digital: false,
				},
			],
			[
				'categorize',
				{
					buckets: [
						{ id: 'b1', label: '' },
						{ id: 'b2', label: '' },
					],
					items: [
						{ id: 'i1', text: '' },
						{ id: 'i2', text: '' },
					],
					key: { i1: 'b1', i2: 'b2' },
				},
			],
			[ 'numerical', {} ],
			[ 'graded-reader', {} ],
		] )(
			'starts a %s draft from the authored defaults without requesting it',
			async ( type, defaults ) => {
				const registry = createRegistry();
				const seen = seenEditor( registry );
				renderEditor( draftRoute( type ), registry );
				await draftLoaded();
				expect( seen.at( -1 ) ).toEqual( {
					type,
					score: { enabled: true, value: 1 },
					...defaults,
				} );
				expect(
					screen.getByRole( 'group', { name: `Editor ${ type }` } )
				).toBeInTheDocument();
				expect( saveButton() ).toBeEnabled();
				expect( api.getQuestion ).not.toHaveBeenCalled();
				expect( api.listVersions ).not.toHaveBeenCalled();
				for ( const name of [
					'Duplicate',
					'Approve this version',
					'Archive question',
				] ) {
					expect(
						screen.queryByRole( 'button', { name } )
					).not.toBeInTheDocument();
				}
				expect(
					screen.queryByRole( 'table', { name: 'Versions' } )
				).not.toBeInTheDocument();
			}
		);

		it( 'creates the question on the first save and opens it from the response', async () => {
			const created: QuestionDetail = {
				...question,
				id: 901,
				name: 'Draft question',
				type: 'set-clock',
				settings: {
					type: 'set-clock',
					score: { enabled: true, value: 2 },
					hour: 7,
					minute: 45,
					tolerance: 1,
					snap: 1,
					show_digital: false,
				},
				options: [],
				version: 1,
			};
			vi.mocked( api.createQuestion ).mockResolvedValue( created );
			const registry = createRegistry();
			seenEditor( registry );
			const user = userEvent.setup();
			renderEditor( draftRoute( 'set-clock' ), registry );
			await draftLoaded();
			const hour = screen.getByRole( 'textbox', { name: 'Hour' } );
			await user.clear( hour );
			await user.type( hour, '7' );
			const points = screen.getByLabelText( 'Points' );
			await user.clear( points );
			await user.type( points, '2' );
			await user.click( saveButton() );
			expect(
				await screen.findByRole( 'heading', {
					name: 'Edit question: Draft question',
				} )
			).toBeInTheDocument();
			expect(
				screen.getByText( 'Question created.', notice )
			).toBeInTheDocument();
			expect( api.createQuestion ).toHaveBeenCalledWith(
				{
					name: 'Draft question',
					settings: created.settings,
					bank: { difficulty: 'standard' },
				},
				expect.any( AbortSignal )
			);
			expect( api.getQuestion ).not.toHaveBeenCalled();
			expect(
				screen.getByRole( 'textbox', { name: 'Hour' } )
			).toHaveValue( '7' );
			expect( saveButton() ).toBeDisabled();
			await screen.findByRole( 'table', { name: 'Versions' } );
			expect( api.listVersions ).toHaveBeenCalledWith(
				901,
				1,
				expect.any( AbortSignal )
			);
		} );

		it( 'creates an option question with its seeded options', async () => {
			vi.mocked( api.createQuestion ).mockResolvedValue( {
				...question,
				id: 902,
			} );
			const user = userEvent.setup();
			renderEditor( draftRoute( 'single-choice', 'Pick one' ) );
			await draftLoaded( 'Pick one' );
			expect(
				screen.getByRole( 'textbox', { name: 'Option 1' } )
			).toHaveValue( 'Option 1' );
			await user.click( saveButton() );
			await screen.findByText( 'Question created.', notice );
			expect( api.createQuestion ).toHaveBeenCalledWith(
				{
					name: 'Pick one',
					settings: {
						type: 'single-choice',
						score: { enabled: true, value: 1 },
					},
					bank: { difficulty: 'standard' },
					options: [
						{
							answer: 'Option 1',
							is_correct: true,
							order_number: 0,
						},
						{
							answer: 'Option 2',
							is_correct: false,
							order_number: 1,
						},
					],
				},
				expect.any( AbortSignal )
			);
		} );

		it( 'keeps the draft and shows a settings rejection next to the type editor', async () => {
			vi.mocked( api.createQuestion ).mockRejectedValue(
				new ApiError( {
					status: 400,
					code: 'ohmylms_invalid_question_settings',
					message:
						'Add at least two labelled groups with unique IDs.',
				} )
			);
			const registry = createRegistry();
			seenEditor( registry );
			const user = userEvent.setup();
			renderEditor( draftRoute( 'categorize' ), registry );
			await draftLoaded();
			await user.click( saveButton() );
			expect(
				await screen.findByText(
					'Add at least two labelled groups with unique IDs.',
					notice
				)
			).toBeInTheDocument();
			const typeEditor = screen.getByRole( 'group', {
				name: 'Editor categorize',
			} );
			expect( typeEditor.nextElementSibling ).toHaveAttribute(
				'role',
				'alert'
			);
			expect( typeEditor.nextElementSibling ).toHaveTextContent(
				'Add at least two labelled groups with unique IDs.'
			);
			expect(
				screen.getByRole( 'heading', {
					name: 'New question: Draft question',
				} )
			).toBeInTheDocument();
			expect( saveButton() ).toBeEnabled();
		} );

		it( 'asks for the expected number before creating a numerical question', async () => {
			const registry = createRegistry();
			seenEditor( registry );
			const user = userEvent.setup();
			renderEditor( draftRoute( 'numerical' ), registry );
			await draftLoaded();
			await user.click( saveButton() );
			expect(
				await screen.findByText( 'Enter the expected number.' )
			).toBeInTheDocument();
			expect( api.createQuestion ).not.toHaveBeenCalled();
		} );

		it( 'asks before leaving an unsaved draft', async () => {
			const user = userEvent.setup();
			renderEditor( draftRoute( 'single-choice' ) );
			await draftLoaded();
			await user.click( screen.getByRole( 'link', { name: 'Away' } ) );
			expect(
				await screen.findByRole( 'dialog', {
					name: 'Discard unsaved changes?',
				} )
			).toBeInTheDocument();
		} );

		it( 'refuses an unknown type', async () => {
			renderEditor( draftRoute( 'no-such-type' ) );
			expect(
				await screen.findByText(
					/This question type is not available/,
					notice
				)
			).toBeInTheDocument();
			expect( api.createQuestion ).not.toHaveBeenCalled();
		} );
	} );
} );
