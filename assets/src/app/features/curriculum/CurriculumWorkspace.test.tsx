import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import * as syllabus from '../syllabus/public';
import * as api from './api';
import CurriculumWorkspace from './CurriculumWorkspace';
import { contentsUnder } from './SkillsPane';
import { syllabusOf } from './ItemDetail';
import { ancestorsOf, childrenOf, visibleIds } from './WorkspaceTree';

vi.mock( './api', async ( importOriginal ) => ( {
	...( await importOriginal< typeof import( './api' ) >() ),
	readWorkspace: vi.fn(),
	readItemLinks: vi.fn(),
	searchLinkTargets: vi.fn(),
	addItemLink: vi.fn(),
	removeItemLink: vi.fn(),
	saveItem: vi.fn(),
	readDeletion: vi.fn(),
	deleteItem: vi.fn(),
} ) );
vi.mock( '../syllabus/public', () => ( {
	readSyllabus: vi.fn(),
	syllabusCommand: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

const row = ( over: Partial< api.CurriculumItem > ): api.CurriculumItem => ( {
	id: 1,
	parent_id: 0,
	position: 1,
	name: 'Item',
	item_type: 'topic',
	description: '',
	code: '',
	version: '',
	is_syllabus: false,
	course_id: 0,
	icon: '',
	updated_at: '2026-11-01 10:00:00',
	...over,
} );

const items = [
	row( {
		id: 1,
		name: 'Digital SAT',
		item_type: 'syllabus',
		is_syllabus: true,
		code: 'DSAT',
		description: 'Test prep.',
	} ),
	row( { id: 2, parent_id: 1, name: 'Algebra', code: 'A' } ),
	row( { id: 3, parent_id: 1, position: 2, name: 'Geometry' } ),
	row( { id: 4, position: 2, name: 'Mathematics', item_type: 'subject' } ),
];

const counts = {
	1: { children: 2, courses: 1, skills: 1 },
	2: { children: 0, courses: 0, skills: 1 },
	3: { children: 0, courses: 0, skills: 0 },
	4: { children: 0, courses: 0, skills: 0 },
};

const outline = ( groups = true ) =>
	( {
		syllabus: {
			id: 1,
			name: 'Digital SAT',
			code: 'DSAT',
			version: '',
			description: '',
			item_type: 'syllabus',
		},
		contents: [
			{ id: 1, parent_id: 0, name: 'Digital SAT', depth: 0, groups: [] },
			{
				id: 2,
				parent_id: 1,
				name: 'Algebra',
				code: 'A',
				depth: 1,
				groups: groups
					? [
							{
								id: 10,
								item_id: 2,
								position: 1,
								name: 'Linear',
								code: 'L',
								description: '',
								skills: [
									{
										term_id: 5,
										name: 'Solve one-step equations',
										code: 'A.1',
										description: '',
									},
								],
							},
						]
					: [],
			},
			{ id: 3, parent_id: 1, name: 'Geometry', depth: 1, groups: [] },
		],
		course: null,
		settings: {
			subject: '',
			grade: '',
			language: '',
			categories: [],
			category_styles: {},
		},
		roadmap_sources: [],
		requirements: [],
	} ) as unknown as syllabus.SyllabusOutline;

const links = ( over: Partial< api.ItemLinks > = {} ): api.ItemLinks => ( {
	course: [],
	skill: [],
	bank: [],
	quiz: [],
	...over,
} );

const linkRow = (
	id: number,
	title: string,
	status = 'publish'
): api.LinkRow => ( {
	id,
	title,
	status,
	code: '',
	available: true,
} );

function Where() {
	return <p data-testid="where">{ useLocation().search }</p>;
}

function open( route = '/curriculum' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Where />
			<Routes>
				<Route path="/curriculum" element={ <CurriculumWorkspace /> } />
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.readWorkspace ).mockResolvedValue( {
		items,
		icons: [],
		types: [ 'subject', 'syllabus', 'topic' ],
		counts,
	} );
	vi.mocked( api.readItemLinks ).mockResolvedValue( links() );
	vi.mocked( syllabus.readSyllabus ).mockResolvedValue( outline() );
} );

describe( 'tree helpers', () => {
	it( 'orders children by position', () => {
		const shuffled = [ items[ 2 ]!, items[ 1 ]!, items[ 0 ]!, items[ 3 ]! ];
		expect(
			childrenOf( shuffled )
				.get( 1 )!
				.map( ( r ) => r.name )
		).toEqual( [ 'Algebra', 'Geometry' ] );
		expect(
			childrenOf( shuffled )
				.get( 0 )!
				.map( ( r ) => r.name )
		).toEqual( [ 'Digital SAT', 'Mathematics' ] );
	} );

	it( 'keeps matches and their parents for a search, and nothing for no match', () => {
		expect( visibleIds( items, '' ) ).toBeNull();
		expect( [ ...visibleIds( items, 'alg' )! ].sort() ).toEqual( [ 1, 2 ] );
		expect( [ ...visibleIds( items, 'dsat' )! ] ).toEqual( [ 1 ] );
		expect( visibleIds( items, 'zzz' )!.size ).toBe( 0 );
	} );

	it( 'finds the ancestors of an item, nearest last, and the syllabus it is in', () => {
		expect( ancestorsOf( items, 2 ).map( ( r ) => r.id ) ).toEqual( [ 1 ] );
		expect( ancestorsOf( items, 1 ) ).toEqual( [] );
		expect( syllabusOf( items, items[ 1 ]! )?.id ).toBe( 1 );
		expect( syllabusOf( items, items[ 0 ]! )?.id ).toBe( 1 );
		expect( syllabusOf( items, items[ 3 ]! ) ).toBeNull();
	} );

	it( 'takes the item and everything under it from a syllabus outline', () => {
		expect( contentsUnder( outline(), 2 ).map( ( c ) => c.id ) ).toEqual( [
			2,
		] );
		expect( contentsUnder( outline(), 1 ).map( ( c ) => c.id ) ).toEqual( [
			1, 2, 3,
		] );
	} );
} );

describe( 'the Curriculum workspace', () => {
	it( 'shows the tree with what each item holds, and asks to pick one', async () => {
		open();
		const tree = await screen.findByRole( 'tree' );
		expect( within( tree ).getByText( 'Digital SAT' ) ).toBeInTheDocument();
		expect(
			within( tree ).getByText( '1 skill · 1 course' )
		).toBeInTheDocument();
		expect( within( tree ).getByText( 'Mathematics' ) ).toBeInTheDocument();
		// The top level starts open, so a curriculum's topics are visible.
		expect( within( tree ).getByText( 'Algebra' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Pick something from the tree' )
		).toBeInTheDocument();
	} );

	it( 'opens an item, keeps it in the address and shows its details', async () => {
		const user = userEvent.setup();
		open();
		await user.click(
			await screen.findByRole( 'button', { name: /^Digital SAT/ } )
		);
		expect(
			await screen.findByRole( 'heading', { name: 'Digital SAT' } )
		).toBeInTheDocument();
		expect( screen.getByTestId( 'where' ) ).toHaveTextContent( 'item=1' );
		expect( screen.getByText( 'Test prep.' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'tab', { name: 'Skills' } ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		expect(
			await screen.findByText( 'Solve one-step equations' )
		).toBeInTheDocument();
	} );

	it( 'opens on the item and pane named in the address, with its parents open', async () => {
		vi.mocked( api.readItemLinks ).mockResolvedValue(
			links( { course: [ linkRow( 7, 'Algebra 1' ) ] } )
		);
		open( '/curriculum?item=2&pane=courses' );
		expect(
			await screen.findByRole( 'heading', { name: 'Algebra' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: /Courses/ } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect(
			await screen.findByRole( 'link', { name: 'Algebra 1' } )
		).toHaveAttribute( 'href', '/courses/7' );
		// The open item's curriculum is open so it can be seen in the tree.
		expect(
			within( screen.getByRole( 'tree' ) ).getAllByText( 'Algebra' )
				.length
		).toBeGreaterThan( 0 );
	} );

	it( 'filters the tree by what is typed, opening the way to a match', async () => {
		const user = userEvent.setup();
		open();
		await screen.findByRole( 'tree' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search curriculum' } ),
			'geo'
		);
		const tree = screen.getByRole( 'tree' );
		expect( within( tree ).getByText( 'Geometry' ) ).toBeInTheDocument();
		expect( within( tree ).getByText( 'Digital SAT' ) ).toBeInTheDocument();
		expect(
			within( tree ).queryByText( 'Mathematics' )
		).not.toBeInTheDocument();
		await user.clear(
			screen.getByRole( 'searchbox', { name: 'Search curriculum' } )
		);
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search curriculum' } ),
			'nothing like it'
		);
		expect(
			screen.getByText( 'Nothing matches that search.' )
		).toBeInTheDocument();
	} );

	it( 'offers to start a curriculum when there is none', async () => {
		vi.mocked( api.readWorkspace ).mockResolvedValue( {
			items: [],
			icons: [],
			types: [],
			counts: {},
		} );
		open();
		expect(
			await screen.findByText( 'No curriculum yet' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Add the first item' } )
		).toBeInTheDocument();
	} );

	it( 'adds an item inside the open one and opens it', async () => {
		const saved = row( { id: 9, parent_id: 2, name: 'Quadratics' } );
		vi.mocked( api.saveItem ).mockResolvedValue( saved );
		const user = userEvent.setup();
		open( '/curriculum?item=2' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add inside' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add curriculum item',
		} );
		expect(
			within( dialog ).getByText( 'Inside Algebra' )
		).toBeInTheDocument();
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
			'Quadratics'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add item' } )
		);
		await waitFor( () =>
			expect( api.saveItem ).toHaveBeenCalledWith(
				expect.objectContaining( {
					name: 'Quadratics',
					item_type: 'topic',
				} ),
				null,
				2,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( 'Curriculum item added.', notice )
		).toBeInTheDocument();
		await waitFor( () =>
			expect( screen.getByTestId( 'where' ) ).toHaveTextContent(
				'item=9'
			)
		);
	} );

	it( 'edits the open item with its current values', async () => {
		vi.mocked( api.saveItem ).mockResolvedValue( items[ 1 ]! );
		const user = userEvent.setup();
		open( '/curriculum?item=2' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Edit curriculum item',
		} );
		expect(
			within( dialog ).getByRole( 'textbox', { name: 'Name' } )
		).toHaveValue( 'Algebra' );
		expect(
			within( dialog ).getByRole( 'textbox', { name: 'Code' } )
		).toHaveValue( 'A' );
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
			' 2'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save' } )
		);
		await waitFor( () =>
			expect( api.saveItem ).toHaveBeenCalledWith(
				expect.objectContaining( { name: 'Algebra 2' } ),
				items[ 1 ],
				0,
				expect.any( AbortSignal )
			)
		);
	} );
} );

describe( 'the Skills pane', () => {
	it( 'lists the groups and skills of the item and what is under it', async () => {
		open( '/curriculum?item=1' );
		expect(
			await screen.findByText( 'Solve one-step equations' )
		).toBeInTheDocument();
		expect( screen.getByText( 'A · Algebra' ) ).toBeInTheDocument();
		expect( screen.getByText( 'L · Linear' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '1 skill under this item.' )
		).toBeInTheDocument();
		expect( syllabus.readSyllabus ).toHaveBeenCalledWith(
			1,
			expect.any( AbortSignal )
		);
	} );

	it( 'adds a skill to a group', async () => {
		vi.mocked( syllabus.syllabusCommand ).mockResolvedValue( outline() );
		const user = userEvent.setup();
		open( '/curriculum?item=2' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add skill' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add a skill to Linear',
		} );
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
			'Solve two-step equations'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Code (optional)',
			} ),
			'A.2'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add skill' } )
		);
		await waitFor( () =>
			expect( syllabus.syllabusCommand ).toHaveBeenCalledWith(
				1,
				'create_skill',
				{ group_id: 10, name: 'Solve two-step equations', code: 'A.2' },
				expect.any( AbortSignal )
			)
		);
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
	} );

	it( 'adds a skill group to the open item', async () => {
		vi.mocked( syllabus.syllabusCommand ).mockResolvedValue( outline() );
		const user = userEvent.setup();
		open( '/curriculum?item=2' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add skill group' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add skill group',
		} );
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
			'Quadratic'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add group' } )
		);
		await waitFor( () =>
			expect( syllabus.syllabusCommand ).toHaveBeenCalledWith(
				1,
				'add_group',
				{ item_id: 2, name: 'Quadratic', code: '' },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'takes a skill out of its group without deleting the skill', async () => {
		vi.mocked( syllabus.syllabusCommand ).mockResolvedValue(
			outline( false )
		);
		const user = userEvent.setup();
		open( '/curriculum?item=2' );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Take Solve one-step equations out of this group',
			} )
		);
		await waitFor( () =>
			expect( syllabus.syllabusCommand ).toHaveBeenCalledWith(
				1,
				'detach_skill',
				{ group_id: 10, term_id: 5 },
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText(
				'No skill groups here yet. Add a group, then add skills to it.'
			)
		).toBeInTheDocument();
	} );

	it( 'says skills live in a syllabus and offers to make the item one', async () => {
		vi.mocked( api.saveItem ).mockResolvedValue( items[ 3 ]! );
		const user = userEvent.setup();
		open( '/curriculum?item=4' );
		expect(
			await screen.findByText( /Skills are kept in a syllabus/ )
		).toBeInTheDocument();
		expect( syllabus.readSyllabus ).not.toHaveBeenCalled();
		await user.click(
			screen.getByRole( 'button', { name: 'Make this item a syllabus' } )
		);
		await waitFor( () =>
			expect( api.saveItem ).toHaveBeenCalledWith(
				expect.objectContaining( {
					name: 'Mathematics',
					is_syllabus: true,
				} ),
				items[ 3 ],
				0,
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'shows a failed command and keeps the dialog', async () => {
		vi.mocked( syllabus.syllabusCommand ).mockRejectedValue(
			new Error( 'Another skill already uses that code.' )
		);
		const user = userEvent.setup();
		open( '/curriculum?item=2' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add skill' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
			'X'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add skill' } )
		);
		expect(
			await within( dialog ).findByText(
				/Another skill already uses that code/
			)
		).toBeInTheDocument();
		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
	} );
} );

describe( 'the Courses and content panes', () => {
	it( 'places a course on the item and takes it off again', async () => {
		vi.mocked( api.searchLinkTargets ).mockResolvedValue( [
			{ id: 7, title: 'Algebra 1', status: 'publish' },
			{ id: 8, title: 'Algebra 2', status: 'draft' },
		] );
		vi.mocked( api.addItemLink ).mockResolvedValue(
			links( { course: [ linkRow( 7, 'Algebra 1' ) ] } )
		);
		vi.mocked( api.removeItemLink ).mockResolvedValue( links() );
		const user = userEvent.setup();
		open( '/curriculum?item=2&pane=courses' );
		expect(
			await screen.findByText( /No courses are placed here/ )
		).toBeInTheDocument();
		await user.type(
			screen.getByRole( 'searchbox', {
				name: 'Find a course to place here',
			} ),
			'alg'
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Add Algebra 1' } )
		);
		expect( api.addItemLink ).toHaveBeenCalledWith(
			2,
			'course',
			7,
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByRole( 'link', { name: 'Algebra 1' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: 'Courses (1)' } )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Remove Algebra 1 from this item',
			} )
		);
		expect( api.removeItemLink ).toHaveBeenCalledWith(
			2,
			'course',
			7,
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByText( /No courses are placed here/ )
		).toBeInTheDocument();
	} );

	it( 'does not offer a course that is already placed', async () => {
		vi.mocked( api.readItemLinks ).mockResolvedValue(
			links( { course: [ linkRow( 7, 'Algebra 1' ) ] } )
		);
		vi.mocked( api.searchLinkTargets ).mockResolvedValue( [
			{ id: 7, title: 'Algebra 1', status: 'publish' },
			{ id: 8, title: 'Algebra 2', status: 'draft' },
		] );
		const user = userEvent.setup();
		open( '/curriculum?item=2&pane=courses' );
		await screen.findByRole( 'link', { name: 'Algebra 1' } );
		await user.type(
			screen.getByRole( 'searchbox', {
				name: 'Find a course to place here',
			} ),
			'alg'
		);
		expect(
			await screen.findByRole( 'button', { name: 'Add Algebra 2' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Add Algebra 1' } )
		).not.toBeInTheDocument();
	} );

	it( 'lists the question banks and quizzes linked to the item', async () => {
		vi.mocked( api.readItemLinks ).mockResolvedValue(
			links( {
				bank: [ linkRow( 3, 'Algebra bank', 'private' ) ],
				quiz: [ linkRow( 12, 'Algebra quiz' ) ],
			} )
		);
		open( '/curriculum?item=2&pane=content' );
		expect( await screen.findByText( 'Algebra bank' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Algebra quiz' } )
		).toHaveAttribute( 'href', '/quizzes/12' );
		expect(
			screen.getByRole( 'tab', {
				name: 'Question banks and quizzes (2)',
			} )
		).toBeInTheDocument();
	} );

	it( 'shows a failed link change', async () => {
		vi.mocked( api.readItemLinks ).mockResolvedValue(
			links( { course: [ linkRow( 7, 'Algebra 1' ) ] } )
		);
		vi.mocked( api.removeItemLink ).mockRejectedValue(
			new Error( 'That link was already removed.' )
		);
		const user = userEvent.setup();
		open( '/curriculum?item=2&pane=courses' );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove Algebra 1 from this item',
			} )
		);
		expect(
			await screen.findByText( 'That link was already removed.', notice )
		).toBeInTheDocument();
	} );
} );
