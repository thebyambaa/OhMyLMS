import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ExtensionsProvider } from '../../extensions/context';
import { createRegistry } from '../../extensions/registry.mjs';
import { renderShell } from '../../test/render';
import * as api from './api';
import { outline, preview } from './fixtures';
import * as generation from './generation';
import { syllabusEditor } from './index';
import type { SyllabusOutline } from './model';
import SyllabusPage, { SyllabusWorkspace } from './SyllabusPage';

vi.mock( './api', () => ( {
	command: vi.fn(),
	readSyllabus: vi.fn(),
	readMappings: vi.fn(),
} ) );
vi.mock( '../course-structure/public', () => ( {
	PublicationPanel: ( {
		course,
		disabled,
		onPublished,
	}: {
		course: number;
		disabled: boolean;
		onPublished: () => void;
	} ) => (
		<section aria-label="Course publication">
			<p>{ `Course ${ course } publication${
				disabled ? ' disabled' : ''
			}` }</p>
			<button onClick={ onPublished }>Finish publication</button>
		</section>
	),
} ) );
vi.mock( './generation', async ( original ) => ( {
	...( await original< typeof import( './generation' ) >() ),
	readSource: vi.fn(),
	createFromSyllabus: vi.fn(),
} ) );
vi.mock( '../curriculum/public', async ( original ) => ( {
	...( await original< typeof import( '../curriculum/public' ) >() ),
	searchSharedSkills: vi.fn(),
} ) );
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.command ).mockResolvedValue( outline );
} );
function open( initial: SyllabusOutline = outline ) {
	return render(
		<MemoryRouter>
			<SyllabusWorkspace initial={ initial } />
		</MemoryRouter>
	);
}
const notice = { selector: '.components-notice__content' };
const algebra = () => screen.getByRole( 'region', { name: 'Algebra' } );
it( 'makes no syllabus requests without administrator capability', () => {
	renderShell( [ { ...syllabusEditor, component: SyllabusPage } ], {
		route: '/content-hub/curriculum/syllabus/5',
		capabilities: {},
	} );
	expect( api.readSyllabus ).not.toHaveBeenCalled();
} );
it( 'opens old layout bookmarks in the outline workspace', () => {
	render(
		<MemoryRouter
			initialEntries={ [
				'/content-hub/curriculum/syllabus/5?layout=spreadsheet',
			] }
		>
			<SyllabusWorkspace initial={ outline } />
		</MemoryRouter>
	);
	expect(
		screen.queryByRole( 'navigation', { name: 'Syllabus layouts' } )
	).not.toBeInTheDocument();
	expect( screen.queryByRole( 'grid' ) ).not.toBeInTheDocument();
	expect(
		screen.getAllByRole( 'button', { name: 'Move skill down' } ).length
	).toBeGreaterThan( 0 );
} );
it( 'sends group ordering without altering skill identities', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getAllByRole( 'button', { name: 'Move skill down' } )[ 0 ]!
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'move_skill',
		{ group_id: 7, term_id: 10, destination: 7, position: 1 },
		expect.any( AbortSignal )
	);
} );
it( 'keeps edited details after a rejected write and sends only writable fields', async () => {
	const user = userEvent.setup();
	open();
	await user.click( screen.getByRole( 'button', { name: 'Edit group' } ) );
	const dialog = within( screen.getByRole( 'dialog' ) );
	await user.clear( dialog.getByLabelText( 'Name' ) );
	await user.type( dialog.getByLabelText( 'Name' ), 'New group name' );
	vi.mocked( api.command ).mockRejectedValue( new Error( 'Save rejected' ) );
	await user.click( dialog.getByRole( 'button', { name: 'Save details' } ) );
	await dialog.findAllByText( 'Save rejected' );
	expect( dialog.getByLabelText( 'Name' ) ).toHaveValue( 'New group name' );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'edit_group',
		{ group_id: 7, name: 'New group name', code: 'A', description: '' },
		expect.any( AbortSignal )
	);
} );
it( 'confirms detachment before removing only the chosen placement', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getAllByRole( 'button', { name: 'Detach skill' } )[ 0 ]!
	);
	expect( api.command ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Detach placement' } )
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'detach_skill',
		{ group_id: 7, term_id: 10 },
		expect.any( AbortSignal )
	);
} );
it( 'creates/synchronizes the same course through an explicit command', async () => {
	const user = userEvent.setup();
	const { unmount } = open();
	vi.mocked( api.command ).mockImplementation(
		() => new Promise( () => {} )
	);
	await user.dblClick(
		screen.getByRole( 'button', { name: 'Update course from syllabus' } )
	);
	expect( api.command ).toHaveBeenCalledOnce();
	const signal = vi.mocked( api.command ).mock.calls[ 0 ]![ 3 ]!;
	unmount();
	expect( signal.aborted ).toBe( true );
} );
it( 'confirms deleting a group that holds skills and keeps the skills in the library', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		within( algebra() ).getByRole( 'button', { name: 'Delete group' } )
	);
	const dialog = within( screen.getByRole( 'dialog' ) );
	expect(
		dialog.getByText(
			/This group holds 2 skills\. The skills stay in the skill library/
		)
	).toBeInTheDocument();
	await user.click( dialog.getByRole( 'button', { name: 'Keep group' } ) );
	expect( api.command ).not.toHaveBeenCalled();
	await user.click(
		within( algebra() ).getByRole( 'button', { name: 'Delete group' } )
	);
	await user.click(
		within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
			name: 'Delete group',
		} )
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'delete_group',
		{ group_id: 7, confirm: true },
		expect.any( AbortSignal )
	);
	await waitFor( () =>
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
	);
} );
it( 'deletes an empty group without the skill confirmation and shows a refusal', async () => {
	const user = userEvent.setup();
	const root = outline.contents[ 0 ]!;
	open( {
		...outline,
		contents: [
			{
				...root,
				groups: [
					...root.groups,
					{
						id: 9,
						item_id: 5,
						position: 1,
						name: 'Empty',
						code: '',
						description: '',
						skills: [],
					},
				],
			},
		],
	} );
	await user.click(
		within( screen.getByRole( 'region', { name: 'Empty' } ) ).getByRole(
			'button',
			{ name: 'Delete group' }
		)
	);
	const dialog = within( screen.getByRole( 'dialog' ) );
	expect(
		dialog.getByText( 'This group holds no skills.' )
	).toBeInTheDocument();
	vi.mocked( api.command ).mockRejectedValue(
		new Error( 'This group holds 1 skill.' )
	);
	await user.click( dialog.getByRole( 'button', { name: 'Delete group' } ) );
	expect(
		await dialog.findByText( 'This group holds 1 skill.' )
	).toBeInTheDocument();
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'delete_group',
		{ group_id: 9, confirm: false },
		expect.any( AbortSignal )
	);
} );
it( 'sets course requirements and targets per skill and per group', async () => {
	const user = userEvent.setup();
	open();
	expect(
		screen.getByText( '0 of 2 skills are required for the course.' )
	).toBeInTheDocument();
	expect(
		screen.getByText(
			'The course cannot be published until at least one skill is required.',
			notice
		)
	).toBeInTheDocument();
	const group = within( algebra() );
	await user.click(
		group.getAllByLabelText( 'Required for the course' )[ 0 ]!
	);
	expect( api.command ).toHaveBeenLastCalledWith(
		5,
		'set_requirements',
		{ skills: [ { term_id: 10, required: true } ] },
		expect.any( AbortSignal )
	);
	await waitFor( () =>
		expect( group.getAllByLabelText( 'Target' )[ 1 ] ).toBeEnabled()
	);
	await user.selectOptions(
		group.getAllByLabelText( 'Target' )[ 1 ]!,
		'mastered'
	);
	expect( api.command ).toHaveBeenLastCalledWith(
		5,
		'set_requirements',
		{ skills: [ { term_id: 11, target: 'mastered' } ] },
		expect.any( AbortSignal )
	);
	await waitFor( () =>
		expect(
			group.getByRole( 'button', {
				name: 'Require every skill in this group',
			} )
		).toBeEnabled()
	);
	await user.click(
		group.getByRole( 'button', {
			name: 'Require every skill in this group',
		} )
	);
	expect( api.command ).toHaveBeenLastCalledWith(
		5,
		'set_requirements',
		{
			skills: [
				{ term_id: 10, required: true },
				{ term_id: 11, required: true },
			],
		},
		expect.any( AbortSignal )
	);
} );
it( 'shows the saved requirement count and hides requirement controls without a course', () => {
	const { unmount } = open( {
		...outline,
		requirements: [
			{ term_id: 10, required: true, target: 'mastered' },
			{ term_id: 11, required: false, target: 'proficient' },
		],
	} );
	expect(
		screen.getByText( '1 of 2 skills are required for the course.' )
	).toBeInTheDocument();
	expect(
		screen.queryByText( /cannot be published/, notice )
	).not.toBeInTheDocument();
	expect(
		within( algebra() ).getByRole( 'button', {
			name: 'Make every skill in this group optional',
		} )
	).toBeEnabled();
	unmount();
	open( { ...outline, course: null, requirements: [] } );
	expect(
		screen.queryByLabelText( 'Required for the course' )
	).not.toBeInTheDocument();
	expect(
		screen.getByRole( 'button', {
			name: 'Create mirrored syllabus course',
		} )
	).toBeInTheDocument();
} );
it( 'edits the source details and profile, sending only changed fields', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'Source and profile' } )
	);
	const dialog = within( screen.getByRole( 'dialog' ) );
	expect( dialog.getByLabelText( 'Syllabus code' ) ).toHaveValue( '0580' );
	await user.clear( dialog.getByLabelText( 'Version or edition' ) );
	await user.type( dialog.getByLabelText( 'Version or edition' ), '2027' );
	await user.type(
		dialog.getByLabelText( 'Skill categories' ),
		'\n Advanced \n\n'
	);
	await user.click(
		dialog.getByRole( 'button', { name: 'Save source and profile' } )
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'edit_source',
		{ version: '2027', categories: [ 'Core', 'Extended', 'Advanced' ] },
		expect.any( AbortSignal )
	);
	await waitFor( () =>
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
	);
} );
it( 'keeps source edits after a refusal and locks the profile without a course', async () => {
	const user = userEvent.setup();
	const { unmount } = open();
	await user.click(
		screen.getByRole( 'button', { name: 'Source and profile' } )
	);
	let dialog = within( screen.getByRole( 'dialog' ) );
	await user.clear( dialog.getByLabelText( 'Skill categories' ) );
	await user.type( dialog.getByLabelText( 'Skill categories' ), 'Core' );
	vi.mocked( api.command ).mockRejectedValue(
		new Error( 'The category “Extended” is used by skills.' )
	);
	await user.click(
		dialog.getByRole( 'button', { name: 'Save source and profile' } )
	);
	expect(
		await dialog.findByText( 'The category “Extended” is used by skills.' )
	).toBeInTheDocument();
	expect( dialog.getByLabelText( 'Skill categories' ) ).toHaveValue( 'Core' );
	unmount();
	open( {
		...outline,
		course: null,
		requirements: [],
		syllabus: { ...outline.syllabus, item_type: 'roadmap' },
		roadmap_sources: [ { id: 3, name: 'Digital SAT', version: '2025' } ],
	} );
	await user.click(
		screen.getByRole( 'button', { name: 'Source and profile' } )
	);
	dialog = within( screen.getByRole( 'dialog' ) );
	expect( dialog.getByLabelText( 'Subject' ) ).toBeDisabled();
	expect( dialog.getByText( 'Digital SAT 2025' ) ).toBeInTheDocument();
	expect(
		dialog.getByRole( 'link', {
			name: 'Change roadmap sources in Compare',
		} )
	).toHaveAttribute( 'href', '/content-hub/curriculum/compare' );
} );
it( 'opens the independent course workflow for this syllabus', async () => {
	const user = userEvent.setup();
	vi.mocked( generation.readSource ).mockResolvedValue( preview );
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'New course from this syllabus…' } )
	);
	expect(
		await screen.findByLabelText( 'Every skill in Geometry' )
	).toBeInTheDocument();
	expect( generation.readSource ).toHaveBeenCalledWith(
		5,
		expect.any( AbortSignal )
	);
	expect( api.command ).not.toHaveBeenCalled();
} );
it( 'previews and publishes the mirrored course through the course publication panel', async () => {
	const user = userEvent.setup();
	vi.mocked( api.readSyllabus ).mockResolvedValue( {
		...outline,
		course: { ...outline.course!, published: true },
	} );
	open();
	const panel = await screen.findByRole( 'region', {
		name: 'Course publication',
	} );
	expect(
		within( panel ).getByText( 'Course 12 publication' )
	).toBeInTheDocument();
	expect(
		screen.getByText( 'The course is a draft: learners cannot enroll yet.' )
	).toBeInTheDocument();
	await user.click(
		within( panel ).getByRole( 'button', { name: 'Finish publication' } )
	);
	expect(
		await screen.findByText(
			'A learning program of this course is published to learners.'
		)
	).toBeInTheDocument();
	expect( api.readSyllabus ).toHaveBeenCalledWith(
		5,
		expect.any( AbortSignal )
	);
	expect( api.command ).not.toHaveBeenCalled();
} );
it( 'holds publication while the course has not synchronized and without a course', async () => {
	const { unmount } = open( { ...outline, course_error: 'Sync failed' } );
	expect(
		await screen.findByText( 'Course 12 publication disabled' )
	).toBeInTheDocument();
	expect(
		screen.getByText(
			'Update the course from the syllabus before publishing it.'
		)
	).toBeInTheDocument();
	unmount();
	open( { ...outline, course: null, requirements: [] } );
	expect(
		screen.queryByRole( 'region', { name: 'Course publication' } )
	).not.toBeInTheDocument();
} );
it( 'edits category icons and colours and sends them with edit_source', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'Source and profile' } )
	);
	const dialog = within( screen.getByRole( 'dialog' ) );
	expect( dialog.getByLabelText( 'Icon for Core' ) ).toHaveValue(
		'calculator'
	);
	await user.selectOptions(
		dialog.getByLabelText( 'Icon for Extended' ),
		'book'
	);
	const colour = dialog.getByLabelText( 'Colour for Core' );
	await user.clear( colour );
	await user.type( colour, '#12' );
	expect( dialog.getByRole( 'alert' ) ).toHaveTextContent(
		'Enter # and six hexadecimal digits, or use the picker.'
	);
	expect(
		dialog.getByRole( 'button', { name: 'Save source and profile' } )
	).toBeDisabled();
	await user.type( colour, 'AB56' );
	await user.click(
		dialog.getByRole( 'button', { name: 'Save source and profile' } )
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'edit_source',
		{
			category_styles: {
				Core: { icon: 'calculator', color: '#12ab56' },
				Extended: { icon: 'book', color: '#6e42d3' },
			},
		},
		expect.any( AbortSignal )
	);
} );
it( 'restores a category to the default style', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'Source and profile' } )
	);
	const dialog = within( screen.getByRole( 'dialog' ) );
	await user.click(
		dialog.getByRole( 'button', { name: 'Use the default style for Core' } )
	);
	expect( dialog.getByLabelText( 'Icon for Core' ) ).toHaveValue( '' );
	await user.click(
		dialog.getByRole( 'button', { name: 'Save source and profile' } )
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'edit_source',
		{ category_styles: {} },
		expect.any( AbortSignal )
	);
} );
it( 'renders the syllabus.skill.resources extension slot under each skill', () => {
	const registry = createRegistry();
	registry.registerSlot( 'acme-games', {
		label: 'Games',
		slot: 'syllabus.skill.resources',
		render: ( {
			skill,
			syllabus,
		}: {
			skill: { name: string };
			syllabus: { name: string };
		} ) => <p>{ `Games for ${ skill.name } in ${ syllabus.name }` }</p>,
	} );
	registry.registerSlot( 'acme-banner', {
		label: 'Banner',
		slot: 'admin.screen.before',
		render: () => <p>Screen banner</p>,
	} );
	render(
		<ExtensionsProvider value={ registry }>
			<MemoryRouter>
				<SyllabusWorkspace initial={ outline } />
			</MemoryRouter>
		</ExtensionsProvider>
	);
	expect(
		within( algebra() ).getByText(
			'Games for Solve equations in Mathematics syllabus'
		)
	).toBeInTheDocument();
	expect(
		within( algebra() ).getByText(
			'Games for Use expressions in Mathematics syllabus'
		)
	).toBeInTheDocument();
	expect( screen.queryByText( 'Screen banner' ) ).not.toBeInTheDocument();
} );
