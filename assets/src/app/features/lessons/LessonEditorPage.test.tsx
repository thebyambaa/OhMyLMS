import { createElement } from '@wordpress/element';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { createRegistry } from '../../extensions/registry.mjs';
import { ExtensionsProvider } from '../../extensions/context';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { lesson } from './fixtures';
import { LessonWorkspace } from './LessonEditorPage';
import type { Lesson } from './model';

vi.mock( './api', () => ( {
	readLesson: vi.fn(),
	saveLesson: vi.fn(),
	publishLesson: vi.fn(),
	listLessons: vi.fn(),
	readObjectives: vi.fn(),
	saveObjectives: vi.fn(),
	listSyllabuses: vi.fn(),
	searchObjectives: vi.fn(),
	searchSkills: vi.fn(),
} ) );
vi.mock( './LessonBlocks', () => ( {
	default: ( {
		initial,
		onChange,
		disabled,
	}: {
		initial: string;
		onChange: ( value: string ) => void;
		disabled: boolean;
	} ) => (
		<textarea
			aria-label="Visual block content"
			value={ initial }
			disabled={ disabled }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
} ) );
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.readObjectives ).mockResolvedValue( {
		id: 12,
		revision: 'objectives-token',
		skills: [],
		mappings: [],
		course_targets: [],
	} );
} );
function open( initial: Lesson = lesson, registry = createRegistry() ) {
	const reload = vi.fn();
	return {
		reload,
		...render(
			<ExtensionsProvider value={ registry }>
				<MemoryRouter>
					<LessonWorkspace initial={ initial } reload={ reload } />
				</MemoryRouter>
			</ExtensionsProvider>
		),
	};
}
async function savedPayload( user: ReturnType< typeof userEvent.setup > ) {
	vi.mocked( api.saveLesson ).mockResolvedValue( {
		...lesson,
		revision: 'saved',
	} );
	await user.click( screen.getByRole( 'button', { name: 'Save lesson' } ) );
	await waitFor( () => expect( api.saveLesson ).toHaveBeenCalledOnce() );
	return vi.mocked( api.saveLesson ).mock.calls[ 0 ]![ 0 ];
}

it( 'saves content and resource links without changing publication or placement', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.saveLesson ).mockResolvedValue( {
		...lesson,
		revision: 'saved',
	} );
	await user.type( screen.getByLabelText( 'Lesson title' ), ' revised' );
	await user.click(
		screen.getByRole( 'button', { name: 'Add resource link' } )
	);
	await user.type( screen.getByLabelText( 'Resource name' ), 'Worksheet' );
	await user.type(
		screen.getByLabelText( 'Resource URL' ),
		'https://example.com/file.pdf'
	);
	await user.click( screen.getByRole( 'button', { name: 'Save lesson' } ) );
	await screen.findAllByText(
		'Lesson saved. Publication status is unchanged.'
	);
	expect( api.saveLesson ).toHaveBeenCalledWith(
		{
			...lesson,
			title: 'Algebra lesson revised',
			resources: [
				{
					name: 'Worksheet',
					url: 'https://example.com/file.pdf',
					size: '',
				},
			],
		},
		expect.any( AbortSignal )
	);
	expect( api.publishLesson ).not.toHaveBeenCalled();
} );
it( 'keeps edits after conflicts and confirms before replacing dirty local state', async () => {
	const user = userEvent.setup();
	const { reload } = open();
	vi.mocked( api.saveLesson ).mockRejectedValue(
		new ApiError( {
			code: 'stale',
			status: 409,
			message: 'Changed in another session',
		} )
	);
	await user.type( screen.getByLabelText( 'Lesson title' ), ' unsaved' );
	await user.click( screen.getByRole( 'button', { name: 'Save lesson' } ) );
	await screen.findAllByText( 'Changed in another session' );
	expect( screen.getByLabelText( 'Lesson title' ) ).toHaveValue(
		'Algebra lesson unsaved'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Reload saved lesson' } )
	);
	expect( reload ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Discard and reload' } )
	);
	expect( reload ).toHaveBeenCalledOnce();
} );
it( 'requires review and confirmation before publishing the saved revision', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.publishLesson ).mockResolvedValue( {
		...lesson,
		status: 'publish',
		revision: 'published',
	} );
	await user.click(
		screen.getByRole( 'button', { name: 'Review lesson publication' } )
	);
	expect( api.publishLesson ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Confirm lesson publication' } )
	);
	await screen.findAllByText( 'Lesson published.' );
	expect( api.publishLesson ).toHaveBeenCalledWith(
		lesson,
		expect.any( AbortSignal )
	);
} );
it( 'prevents duplicate writes and aborts the owned request on unmount', async () => {
	const user = userEvent.setup();
	const { unmount } = open();
	vi.mocked( api.saveLesson ).mockImplementation(
		() => new Promise( () => {} )
	);
	await user.type( screen.getByLabelText( 'Lesson title' ), ' revised' );
	expect(
		screen.getByRole( 'button', { name: 'Review lesson publication' } )
	).toBeDisabled();
	await user.dblClick(
		screen.getByRole( 'button', { name: 'Save lesson' } )
	);
	await waitFor( () => expect( api.saveLesson ).toHaveBeenCalledOnce() );
	const signal = vi.mocked( api.saveLesson ).mock.calls[ 0 ]![ 1 ];
	unmount();
	expect( signal.aborted ).toBe( true );
} );
it( 'supports block source edits without dropping opaque custom markup', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'Edit block source' } )
	);
	expect( screen.getByLabelText( 'Block content source' ) ).toHaveValue(
		lesson.content
	);
	const source = '<!-- wp:vendor/reference {"reference":7} /-->';
	await user.clear( screen.getByLabelText( 'Block content source' ) );
	await user.paste( source );
	await user.click(
		screen.getByRole( 'button', { name: 'Use visual block editor' } )
	);
	expect( screen.getByLabelText( 'Visual block content' ) ).toHaveValue(
		source
	);
} );

describe( 'advanced lesson settings', () => {
	afterEach( () => {
		delete ( window as unknown as { wp?: unknown } ).wp;
	} );
	it( 'saves a dated drip schedule', async () => {
		const user = userEvent.setup();
		open();
		await user.click(
			screen.getByRole( 'checkbox', {
				name: 'Release this lesson on a schedule',
			} )
		);
		await user.selectOptions(
			screen.getByLabelText( 'Release lesson' ),
			'specific-date'
		);
		await user.type(
			screen.getByLabelText( 'Release date' ),
			'2026-12-01'
		);
		await user.type( screen.getByLabelText( 'Release time' ), '08:30' );
		const payload = await savedPayload( user );
		expect( payload.drip ).toEqual( {
			enable: true,
			type: 'specific-date',
			days: 0,
			date: '2026-12-01',
			time: '08:30',
		} );
	} );
	it( 'saves days after enrollment', async () => {
		const user = userEvent.setup();
		open();
		await user.click(
			screen.getByRole( 'checkbox', {
				name: 'Release this lesson on a schedule',
			} )
		);
		await user.clear( screen.getByLabelText( 'Days' ) );
		await user.type( screen.getByLabelText( 'Days' ), '7' );
		const payload = await savedPayload( user );
		expect( payload.drip ).toMatchObject( {
			enable: true,
			type: 'enrollment-from-x-days',
			days: 7,
		} );
	} );
	it( 'adds and removes prerequisites found in the lesson library', async () => {
		const user = userEvent.setup();
		vi.mocked( api.listLessons ).mockResolvedValue( {
			pages: 1,
			items: [
				{ id: 12, title: 'Algebra lesson', status: 'draft' },
				{ id: 30, title: 'Numbers', status: 'publish' },
			],
		} );
		open( {
			...lesson,
			prerequisites: {
				enable: true,
				items: [ { id: 44, title: 'Entry quiz' } ],
			},
		} );
		await user.type(
			screen.getByLabelText( 'Find a lesson to require' ),
			'Num'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Search lessons' } )
		);
		const results = await screen.findByRole( 'list', {
			name: 'Matching lessons',
		} );
		expect(
			within( results ).queryByText( /Algebra lesson/ )
		).not.toBeInTheDocument();
		await user.click(
			within( results ).getByRole( 'button', { name: 'Require Numbers' } )
		);
		const payload = await savedPayload( user );
		expect( payload.prerequisites ).toEqual( {
			enable: true,
			items: [
				{ id: 44, title: 'Entry quiz' },
				{ id: 30, title: 'Numbers' },
			],
		} );
		expect( api.listLessons ).toHaveBeenCalledWith(
			1,
			'Num',
			expect.any( AbortSignal )
		);
	} );
	it( 'chooses a cover image through the WordPress media library', async () => {
		const handlers: Record< string, () => void > = {};
		const frame = {
			on: ( event: string, callback: () => void ) => {
				handlers[ event ] = callback;
			},
			open: () => {
				handlers.close?.();
				handlers.select?.();
			},
			state: () => ( {
				get: () => ( {
					first: () => ( {
						toJSON: () => ( {
							id: 77,
							url: 'https://example.test/cover.png',
						} ),
					} ),
				} ),
			} ),
		};
		const media = vi.fn( () => frame );
		( window as unknown as { wp: unknown } ).wp = { media };
		const user = userEvent.setup();
		open();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose Cover image' } )
		);
		expect(
			await screen.findByRole( 'link', {
				name: 'https://example.test/cover.png',
			} )
		).toBeInTheDocument();
		expect( media ).toHaveBeenCalledWith(
			expect.objectContaining( { library: { type: 'image' } } )
		);
		const payload = await savedPayload( user );
		expect( payload.cover_image_id ).toBe( 77 );
	} );
	it( 'explains when the media library is not loaded', () => {
		open();
		expect(
			screen.getAllByText( /media library is not available/ )
		).not.toHaveLength( 0 );
		expect(
			screen.getByRole( 'button', { name: 'Choose Cover image' } )
		).toBeDisabled();
	} );
} );

describe( 'extension lesson types', () => {
	const custom: Lesson = {
		...lesson,
		type: 'example-reading',
		custom_type: true,
		content: 'Old description',
		native_editor_url: '',
	};
	it( 'renders the registered lesson editor and saves its name and description', async () => {
		const registry = createRegistry();
		const editor = vi.fn(
			( props: {
				lesson: { type: string };
				value: { name: string; description: string };
				onChange: ( patch: Record< string, string > ) => void;
			} ) =>
				createElement(
					'div',
					null,
					createElement( 'input', {
						'aria-label': 'Reading title',
						value: props.value.name,
						onChange: ( event: { target: { value: string } } ) =>
							props.onChange( { name: event.target.value } ),
					} ),
					createElement( 'textarea', {
						'aria-label': 'Reading content',
						value: props.value.description,
						onChange: ( event: { target: { value: string } } ) =>
							props.onChange( {
								description: event.target.value,
							} ),
					} )
				)
		);
		registry.registerLessonEditor( 'example-reading', {
			label: 'Example reading',
			render: editor,
		} );
		const user = userEvent.setup();
		open( custom, registry );
		expect( screen.queryByLabelText( 'Lesson format' ) ).toBeNull();
		expect( screen.queryByLabelText( 'Lesson title' ) ).toBeNull();
		expect( editor.mock.calls[ 0 ]![ 0 ] ).toMatchObject( {
			lesson: { id: 12, type: 'example-reading' },
			value: { name: 'Algebra lesson', description: 'Old description' },
		} );
		await user.type( screen.getByLabelText( 'Reading title' ), ' two' );
		await user.clear( screen.getByLabelText( 'Reading content' ) );
		await user.type(
			screen.getByLabelText( 'Reading content' ),
			'New text'
		);
		const payload = await savedPayload( user );
		expect( payload ).toMatchObject( {
			type: 'example-reading',
			title: 'Algebra lesson two',
			content: 'New text',
		} );
	} );
	it( 'isolates a failing extension editor', () => {
		const registry = createRegistry();
		registry.registerLessonEditor( 'example-reading', {
			label: 'Broken',
			render: () => {
				throw new Error( 'broken' );
			},
		} );
		const error = vi
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		open( custom, registry );
		error.mockRestore();
		expect(
			screen.getByText( 'This extension could not be displayed.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Reload saved lesson' } )
		).toBeInTheDocument();
	} );
	it( 'falls back to name and source fields without a registered editor', () => {
		open( custom );
		expect(
			screen.getByText( /No editor is registered for this lesson type/ )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Lesson title' ) ).toHaveValue(
			'Algebra lesson'
		);
		expect( screen.getByLabelText( 'Lesson source' ) ).toHaveValue(
			'Old description'
		);
	} );
} );
it( 'edits the video settings and completion rule of a video lesson', async () => {
	const user = userEvent.setup();
	open( {
		...lesson,
		type: 'video',
		external_url: 'https://youtu.be/abcdefghijk',
	} );
	await user.selectOptions(
		screen.getByRole( 'combobox', { name: 'Provider' } ),
		'youtube'
	);
	await user.tripleClick(
		screen.getByRole( 'spinbutton', { name: 'Minutes' } )
	);
	await user.keyboard( '12' );
	await user.tripleClick(
		screen.getByRole( 'spinbutton', { name: 'Seconds' } )
	);
	await user.keyboard( '30' );
	expect( screen.getByText( 'Duration (12:30)' ) ).toBeInTheDocument();
	await user.selectOptions(
		screen.getByRole( 'combobox', { name: 'Completion' } ),
		'watch_percent'
	);
	const share = screen.getByRole( 'spinbutton', {
		name: 'Share to watch (%)',
	} );
	await user.tripleClick( share );
	await user.keyboard( '75' );
	await user.click(
		screen.getByRole( 'checkbox', { name: 'Show player controls' } )
	);
	const payload = await savedPayload( user );
	expect( payload.video ).toEqual( {
		...lesson.video,
		provider: 'youtube',
		duration: 750,
		completion: 'watch_percent',
		watch_percent: 75,
		controls: false,
	} );
} );
it( 'lists, reorders, removes and inserts inline questions in the lesson content', async () => {
	const user = userEvent.setup();
	const first =
		'<!-- wp:ohmylms/shared-multiple-choice {"questionId":5,"uuid":"aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa"} /-->';
	const second =
		'<!-- wp:ohmylms/shared-multiple-choice {"questionId":6,"uuid":"bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb"} /-->';
	const content = `<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n${ first }\n\n${ second }\n`;
	open( {
		...lesson,
		content,
		inline_questions: [
			{
				position: 1,
				kind: 'block',
				question_id: 5,
				uuid: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
				title: 'Slope check',
				status: 'approved',
			},
			{
				position: 2,
				kind: 'block',
				question_id: 6,
				uuid: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
				title: 'Intercept check',
				status: 'not_approved',
			},
		],
	} );
	const table = screen.getByRole( 'table', {
		name: 'Inline questions in this lesson',
	} );
	expect(
		within( table ).getByRole( 'link', { name: 'Slope check' } )
	).toHaveAttribute( 'href', '/content-hub/question-bank/5' );
	expect( within( table ).getByText( /Not approved/ ) ).toBeInTheDocument();
	expect(
		screen.getByRole( 'button', { name: 'Move Slope check up' } )
	).toBeDisabled();
	await user.click(
		screen.getByRole( 'button', { name: 'Move Slope check down' } )
	);
	expect( screen.getByLabelText( 'Visual block content' ) ).toHaveValue(
		`<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n${ second }\n\n${ first }\n`
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Remove Intercept check' } )
	);
	expect(
		screen.queryByRole( 'link', { name: 'Intercept check' } )
	).not.toBeInTheDocument();
	const payload = await savedPayload( user );
	expect( payload.content ).toBe(
		`<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n${ first }\n`
	);
} );
