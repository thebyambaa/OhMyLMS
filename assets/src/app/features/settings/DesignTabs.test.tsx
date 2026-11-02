import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	ADMIN_FONTS,
	changedValues,
	LEARNER_FONTS,
	parseArchive,
	parseFonts,
	validateArchive,
	validateFonts,
	validateQuizColors,
	type AdminFont,
} from './designSettings';
import SettingsPage from './SettingsPage';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
	post: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );
vi.mock( './api', () => ( {
	getSettings: vi.fn( () => new Promise( () => undefined ) ),
	saveSettings: vi.fn(),
	searchPages: vi.fn(),
	listCurrencies: vi.fn(),
	listCountries: vi.fn(),
	listStates: vi.fn(),
	restoreDefaultPages: vi.fn(),
	clearCache: vi.fn(),
} ) );

const archive = {
	group: 'course-archive',
	changed: [],
	values: {
		courses_per_page: 10,
		layout: 'grid',
		layout_style: 'grid-style1',
		columns_per_row: 4,
		show_filters: true,
		filters: [ 'category', 'tag' ],
		show_sorting: false,
		show_search: false,
		show_category: false,
		rows: [ { heading: 'Popular', criteria: 'best_selling' } ],
	},
};
const quiz = {
	group: 'quiz-design',
	changed: [],
	values: Object.fromEntries(
		[
			'stage',
			'prompt',
			'canvas',
			'toolbar',
			'toolbar_text',
			'accent',
			'text',
			'answer_1',
			'answer_2',
			'answer_3',
			'answer_4',
			'correct',
			'player_stage',
			'player_prompt',
			'player_toolbar',
			'player_toolbar_text',
			'player_answer_1',
			'player_answer_2',
			'player_answer_3',
			'player_answer_4',
		].map( ( name ) => [ name, '#123456' ] )
	),
};
const fonts = {
	group: 'fonts',
	changed: [],
	values: { font_family: 'inherit', admin_font_family: 'system' },
};

function show( tab: string ) {
	return render(
		<MemoryRouter initialEntries={ [ `/settings?tab=${ tab }` ] }>
			<SettingsPage />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( get ).mockImplementation( async ( path: string ) => {
		if ( path.endsWith( '/course-archive' ) ) {
			return archive;
		}
		if ( path.endsWith( '/quiz-design' ) ) {
			return quiz;
		}
		if ( path.endsWith( '/settings/fonts' ) ) {
			return fonts;
		}
		if ( path.endsWith( '/migrations' ) ) {
			return [
				{
					id: 'tutorLMS',
					title: 'Tutor LMS',
					description: '',
					available: true,
				},
				{
					id: 'learnDash',
					title: 'LearnDash LMS',
					description: '',
					available: false,
				},
			];
		}
		throw new Error( `Unexpected ${ path }` );
	} );
} );

describe( 'design settings model', () => {
	it( 'parses, diffs and validates', () => {
		const parsed = parseArchive( archive ).values;
		expect(
			changedValues( parsed, { ...parsed, columns_per_row: 3 } )
		).toEqual( { columns_per_row: 3 } );
		expect(
			validateArchive( {
				...parsed,
				filters: [],
				rows: [ { heading: ' ', criteria: 'all' } ],
			} )
		).toEqual( {
			filters: expect.any( String ),
			'rows.0': expect.any( String ),
		} );
		expect( validateQuizColors( { stage: 'red' } ) ).toHaveProperty(
			'stage'
		);
		expect( () =>
			parseArchive( {
				...archive,
				values: { ...archive.values, layout: 'masonry' },
			} )
		).toThrow();
	} );

	it( 'keeps the font allow-lists: theme font for learners only', () => {
		expect( LEARNER_FONTS ).toEqual( [ 'inherit', ...ADMIN_FONTS ] );
		expect( ADMIN_FONTS ).toEqual( [
			'system',
			'Inter',
			'Roboto',
			'Open Sans',
			'Noto Sans',
			'Montserrat',
			'Nunito',
			'PT Sans',
			'Fira Sans',
			'Rubik',
		] );
		expect( parseFonts( fonts ).values ).toEqual( fonts.values );
		for ( const values of [
			{ ...fonts.values, font_family: 'Comic Sans' },
			{ ...fonts.values, admin_font_family: 'inherit' },
			{ font_family: 'inherit' },
		] ) {
			expect( () => parseFonts( { ...fonts, values } ) ).toThrow();
		}
		expect( () =>
			parseFonts( { ...fonts, group: 'quiz-design' } )
		).toThrow();
		expect(
			validateFonts( {
				font_family: 'inherit',
				admin_font_family: 'inherit' as AdminFont,
			} )
		).toEqual( { admin_font_family: expect.any( String ) } );
		expect(
			validateFonts( {
				font_family: 'Rubik',
				admin_font_family: 'Inter',
			} )
		).toEqual( {} );
	} );
} );

describe( 'design tabs', () => {
	it( 'saves only the changed archive fields', async () => {
		vi.mocked( patch ).mockResolvedValue( {
			...archive,
			changed: [ 'columns_per_row', 'rows' ],
			values: {
				...archive.values,
				columns_per_row: 3,
				rows: [
					{ heading: 'Popular', criteria: 'best_selling' },
					{ heading: 'Courses', criteria: 'all' },
				],
			},
		} );
		show( 'course-archive' );
		const columns = await screen.findByLabelText( 'Columns per row' );
		await userEvent.clear( columns );
		await userEvent.type( columns, '3' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Add row' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () => expect( patch ).toHaveBeenCalled() );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/course-archive',
			{
				columns_per_row: 3,
				rows: [
					{ heading: 'Popular', criteria: 'best_selling' },
					{ heading: 'Courses', criteria: 'all' },
				],
			},
			expect.anything()
		);
		expect(
			await screen.findByText( 'Settings saved.', {
				selector: '.components-notice__content',
			} )
		).toBeVisible();
	} );

	it( 'blocks invalid quiz colours before saving', async () => {
		show( 'quiz-design' );
		const stage = await screen.findByLabelText( 'Question stage' );
		await userEvent.clear( stage );
		await userEvent.type( stage, 'red' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText( 'Enter a hex colour such as #6e42d3.' )
		).toBeVisible();
		expect( patch ).not.toHaveBeenCalled();
	} );

	it( 'offers both fonts and saves only the changed one', async () => {
		vi.mocked( patch ).mockResolvedValue( {
			...fonts,
			changed: [ 'admin_font_family' ],
			values: { ...fonts.values, admin_font_family: 'Noto Sans' },
		} );
		show( 'fonts' );
		const learner = await screen.findByLabelText( 'Learner font' );
		const admin = screen.getByLabelText( 'Admin font' );
		expect( learner ).toHaveValue( 'inherit' );
		expect(
			within( learner ).getByRole( 'option', { name: 'Theme font' } )
		).toBeInTheDocument();
		expect(
			within( admin ).queryByRole( 'option', { name: 'Theme font' } )
		).not.toBeInTheDocument();
		expect( within( admin ).getAllByRole( 'option' ) ).toHaveLength(
			ADMIN_FONTS.length
		);
		expect( admin ).toHaveValue( 'system' );
		await userEvent.selectOptions( admin, 'Noto Sans' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( patch ).toHaveBeenCalledWith(
				'/ohmylms/v1/settings/fonts',
				{ admin_font_family: 'Noto Sans' },
				expect.anything()
			)
		);
		expect(
			await screen.findByText(
				'Settings saved. Reload the page to see the new admin font.',
				{ selector: '.components-notice__content' }
			)
		).toBeVisible();
		expect( screen.getByLabelText( 'Admin font' ) ).toHaveValue(
			'Noto Sans'
		);
	} );

	it( 'shows a font the server rejects on its field', async () => {
		vi.mocked( patch ).mockRejectedValue(
			new ApiError( {
				status: 400,
				code: 'rest_invalid_param',
				message: 'Invalid parameter(s): font_family',
				details: {
					status: 400,
					params: {
						font_family:
							'font_family is not one of inherit, system, Inter.',
					},
				},
			} )
		);
		show( 'fonts' );
		await userEvent.selectOptions(
			await screen.findByLabelText( 'Learner font' ),
			'Montserrat'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText(
				'font_family is not one of inherit, system, Inter.'
			)
		).toBeVisible();
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/fonts',
			{ font_family: 'Montserrat' },
			expect.anything()
		);
	} );

	it( 'migrates a course from an active source after confirmation', async () => {
		vi.mocked( getCollection ).mockResolvedValue( {
			items: [
				{
					id: 7,
					title: 'Old algebra',
					status: 'publish',
					migrated_to: null,
				},
				{
					id: 8,
					title: 'Old geometry',
					status: 'draft',
					migrated_to: 90,
				},
			],
			total: 2,
			totalPages: 1,
		} );
		vi.mocked( post ).mockResolvedValue( {
			source: 'tutorLMS',
			course_id: 7,
			migrated_to: 91,
		} );
		show( 'migration' );
		const table = await screen.findByRole( 'table', {
			name: 'Source courses',
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/migrations/tutorLMS/courses',
			expect.any( Function ),
			expect.objectContaining( { query: { page: 1, per_page: 20 } } )
		);
		expect(
			within( table ).getByRole( 'link', {
				name: 'Migrated: open the copy',
			} )
		).toHaveAttribute( 'href', '/courses/90' );
		expect(
			screen.queryByRole( 'option', { name: 'LearnDash LMS' } )
		).not.toBeInTheDocument();
		await userEvent.click(
			within( table ).getByRole( 'button', { name: 'Migrate' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect( post ).not.toHaveBeenCalled();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Migrate' } )
		);
		await waitFor( () =>
			expect( post ).toHaveBeenCalledWith(
				'/ohmylms/v1/migrations/tutorLMS',
				{ course_id: 7 },
				expect.anything()
			)
		);
		expect(
			await screen.findByText(
				'Old algebra was copied into a new draft course.',
				{ selector: '.components-notice__content' }
			)
		).toBeVisible();
	} );
} );
