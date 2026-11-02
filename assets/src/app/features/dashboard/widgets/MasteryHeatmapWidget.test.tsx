import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import * as api from '../shell/api';
import { algebraClass, context, envelope } from '../shell/fixtures';
import type { WidgetContext } from '../shell/model';
import * as masteryApi from './MasteryApi';
import MasteryHeatmapWidget, { SKILLS_PER_PAGE } from './MasteryHeatmapWidget';
import {
	levelRange,
	parseMasteryHeatmap,
	parseMasteryLearners,
} from './MasteryModel';
import type { MasteryHeatmap, MasteryLearners } from './MasteryModel';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );
vi.mock( './MasteryApi', () => ( {
	getMasteryLearners: vi.fn(),
	assignReviewSet: vi.fn(),
} ) );

const getWidget = vi.mocked( api.getWidget );
const getMasteryLearners = vi.mocked( masteryApi.getMasteryLearners );

const levels: MasteryHeatmap[ 'levels' ] = [
	{ id: 'mastered', label: 'Mastered', min: 95 },
	{ id: 'proficient', label: 'Proficient', min: 80 },
	{ id: 'familiar', label: 'Familiar', min: 50 },
	{ id: 'attempted', label: 'Attempted', min: null },
	{ id: 'not-started', label: 'Not started', min: null },
];

const rawHeatmap = {
	row_type: 'class',
	rows_total: 2,
	rows: [
		{ key: 'class:9', id: 9, label: 'Algebra 8A', learners: 6 },
		{ key: 'class:10', id: 10, label: 'Algebra 8B', learners: 4 },
	],
	skills: [
		{
			id: 31,
			name: 'Fractions',
			code: 'M1',
			subject: 'Mathematics',
			assessed: 9,
		},
		{ id: 32, name: 'Cells', code: '', subject: 'Science', assessed: 5 },
		{ id: 33, name: 'Grammar', code: '', subject: '', assessed: 3 },
	],
	subjects: [
		{ name: 'Mathematics', skills: 1 },
		{ name: 'Science', skills: 1 },
	],
	cells: [
		{
			row: 0,
			skill: 0,
			percent: 64,
			level: 'familiar',
			assessed: 5,
			below: 1,
		},
		{
			row: 1,
			skill: 0,
			percent: 95,
			level: 'mastered',
			assessed: 4,
			below: 0,
		},
		{
			row: 0,
			skill: 1,
			percent: 26,
			level: 'attempted',
			assessed: 5,
			below: 5,
		},
	],
	levels,
	limits: { rows: 20, skills: 40 },
	can_open_reports: true,
};

const heatmap = parseMasteryHeatmap( rawHeatmap );

const learners: MasteryLearners = {
	row: { key: 'class:9', label: 'Algebra 8A', learners: 6 },
	skill: { id: 32, name: 'Cells' },
	below: 2,
	assessed: 5,
	learners: [
		{
			id: 501,
			name: 'Ana Ortiz',
			percent: 10,
			level: 'attempted',
			route: '/students/501/report',
		},
		{
			id: 502,
			name: 'Bo Chen',
			percent: 49,
			level: 'attempted',
			route: null,
		},
	],
	limit: 50,
};

function show( current: WidgetContext = context( algebraClass ) ) {
	return render(
		<MemoryRouter>
			<MasteryHeatmapWidget context={ current } />
		</MemoryRouter>
	);
}

function withSkills( count: number ): MasteryHeatmap {
	const skills = Array.from( { length: count }, ( _, index ) => ( {
		id: 100 + index,
		name: `Skill ${ index + 1 }`,
		code: '',
		subject: index % 2 ? 'Science' : 'Mathematics',
		assessed: 1,
	} ) );
	return {
		...heatmap,
		skills,
		subjects: [
			{ name: 'Mathematics', skills: Math.ceil( count / 2 ) },
			{ name: 'Science', skills: Math.floor( count / 2 ) },
		],
		cells: skills.map( ( _, index ) => ( {
			row: 0,
			skill: index,
			percent: 70,
			level: 'familiar' as const,
			assessed: 1,
			below: 0,
		} ) ),
	};
}

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope ) =>
		envelope( widget, scope, heatmap )
	);
	getMasteryLearners.mockResolvedValue( learners );
} );

describe( 'mastery heatmap data', () => {
	it( 'validates the heatmap and rejects malformed data', () => {
		expect( heatmap.rowType ).toBe( 'class' );
		expect( heatmap.cells[ 0 ]?.level ).toBe( 'familiar' );
		expect( heatmap.canOpenReports ).toBe( true );
		const bad = ( patch: Record< string, unknown > ) => () =>
			parseMasteryHeatmap( { ...rawHeatmap, ...patch } );
		expect( bad( { row_type: 'school' } ) ).toThrow();
		expect(
			bad( {
				cells: [ { ...rawHeatmap.cells[ 0 ], level: 'expert' } ],
			} )
		).toThrow();
		expect(
			bad( { cells: [ { ...rawHeatmap.cells[ 0 ], skill: 3 } ] } )
		).toThrow();
		expect(
			bad( { cells: [ { ...rawHeatmap.cells[ 0 ], percent: 101 } ] } )
		).toThrow();
		expect(
			bad( { rows: [ { ...rawHeatmap.rows[ 0 ], key: 'school:3' } ] } )
		).toThrow();
	} );

	it( 'only accepts student report links for learners', () => {
		const raw = {
			...learners,
			row: { ...learners.row, type: 'class', id: 9 },
		};
		expect( parseMasteryLearners( raw ).learners[ 0 ]?.route ).toBe(
			'/students/501/report'
		);
		expect( () =>
			parseMasteryLearners( {
				...raw,
				learners: [
					{ ...learners.learners[ 0 ], route: 'https://evil.test' },
				],
			} )
		).toThrow();
	} );

	it( 'describes each level range in words', () => {
		expect(
			levels.map( ( _, index ) => levelRange( levels, index ) )
		).toEqual( [
			'95% and above',
			'80–94%',
			'50–79%',
			'below 50%',
			'no evidence yet',
		] );
	} );
} );

describe( 'mastery heatmap widget', () => {
	it( 'asks for the scope and renders class rows and skill columns', async () => {
		show( context( algebraClass, 7 ) );
		const table = await screen.findByRole( 'table', {
			name: 'Average mastery by class and skill',
		} );
		expect( getWidget ).toHaveBeenCalledWith(
			'mastery-heatmap',
			'class:9',
			7,
			parseMasteryHeatmap,
			expect.any( AbortSignal )
		);
		expect(
			within( table )
				.getAllByRole( 'columnheader' )
				.map( ( th ) => th.textContent )
		).toEqual( [ 'Class', 'Fractions', 'Cells', 'Grammar' ] );
		expect(
			within( table ).getByRole( 'rowheader', { name: /Algebra 8A/ } )
		).toHaveTextContent( 'Algebra 8A6 learners' );
	} );

	it( 'names every cell with its level and percentage, not only a colour', async () => {
		show();
		const cell = await screen.findByRole( 'button', {
			name: 'Algebra 8A, Fractions: Familiar, average 64%, 1 learner below Familiar',
		} );
		expect( cell ).toHaveClass( 'ohmylms-heatmap__cell', 'is-familiar' );
		expect( cell ).toHaveTextContent( '64%1 below' );
		expect(
			screen.getByRole( 'button', {
				name: 'Algebra 8B, Fractions: Mastered, average 95%, 0 learners below Familiar',
			} )
		).toHaveTextContent( '95%' );
		const row = screen
			.getByRole( 'rowheader', { name: /Algebra 8B/ } )
			.closest( 'tr' ) as HTMLElement;
		const notStarted = within( row ).getAllByText( 'Not started' );
		expect( notStarted ).toHaveLength( 2 );
		expect( notStarted[ 0 ] ).toHaveClass( 'screen-reader-text' );
		expect( within( row ).getAllByRole( 'button' ) ).toHaveLength( 1 );
	} );

	it( 'has an accessible legend with names, ranges and hidden swatches', async () => {
		show();
		const legend = await screen.findByRole( 'list', {
			name: 'Mastery levels',
		} );
		const items = within( legend ).getAllByRole( 'listitem' );
		expect( items.map( ( item ) => item.textContent ) ).toEqual( [
			'Mastered 95% and above',
			'Proficient 80–94%',
			'Familiar 50–79%',
			'Attempted below 50%',
			'Not started no evidence yet',
		] );
		items.forEach( ( item ) => {
			const swatch = item.querySelector( '.ohmylms-mastery-swatch' );
			expect( swatch ).toHaveAttribute( 'aria-hidden', 'true' );
		} );
		expect(
			items[ 3 ]?.querySelector( '.ohmylms-mastery-swatch' )
		).toHaveClass( 'is-attempted' );
	} );

	it( 'opens the learners below Familiar with report links where allowed', async () => {
		const user = userEvent.setup();
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: /^Algebra 8A, Cells: Attempted, average 26%/,
			} )
		);
		expect( getMasteryLearners ).toHaveBeenCalledWith(
			'class:9',
			'class:9',
			32,
			expect.any( AbortSignal )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Below Familiar on Cells · Algebra 8A',
		} );
		expect(
			await within( dialog ).findByText(
				'2 of 5 learners with evidence are below Familiar (6 in all).'
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'link', { name: 'Ana Ortiz' } )
		).toHaveAttribute( 'href', '/students/501/report' );
		expect(
			within( dialog ).queryByRole( 'link', { name: 'Bo Chen' } )
		).toBeNull();
		expect( within( dialog ).getByText( 'Bo Chen' ) ).toBeInTheDocument();
		expect( within( dialog ).getAllByText( 'Attempted' ) ).toHaveLength(
			2
		);
		await user.keyboard( '{Escape}' );
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
	} );

	it( 'pages skills and narrows them by subject', async () => {
		const user = userEvent.setup();
		getWidget.mockImplementation( async ( widget, scope ) =>
			envelope( widget, scope, withSkills( 10 ) )
		);
		show();
		const table = await screen.findByRole( 'table' );
		expect( within( table ).getAllByRole( 'columnheader' ) ).toHaveLength(
			SKILLS_PER_PAGE + 1
		);
		expect( screen.getByText( 'Skills 1–8 of 10' ) ).toBeInTheDocument();
		const previous = screen.getByRole( 'button', {
			name: 'Previous skills',
		} );
		expect( previous ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.click(
			screen.getByRole( 'button', { name: 'Next skills' } )
		);
		expect( screen.getByText( 'Skills 9–10 of 10' ) ).toBeInTheDocument();
		expect(
			within( screen.getByRole( 'table' ) )
				.getAllByRole( 'columnheader' )
				.map( ( th ) => th.textContent )
		).toEqual( [ 'Class', 'Skill 9', 'Skill 10' ] );

		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Subject' } ),
			'Science'
		);
		expect( screen.getByText( 'Skills 1–5 of 5' ) ).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Next skills' } )
		).toBeNull();
		expect(
			within( screen.getByRole( 'table' ) )
				.getAllByRole( 'columnheader' )
				.map( ( th ) => th.textContent )
		).toEqual( [
			'Class',
			'Skill 2',
			'Skill 4',
			'Skill 6',
			'Skill 8',
			'Skill 10',
		] );
	} );

	it( 'offers skills without a subject', async () => {
		const user = userEvent.setup();
		show();
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Subject' } ),
			'__none'
		);
		expect(
			within( screen.getByRole( 'table' ) )
				.getAllByRole( 'columnheader' )
				.map( ( th ) => th.textContent )
		).toEqual( [ 'Class', 'Grammar' ] );
	} );

	it( 'uses courses as rows and says when rows are cut', async () => {
		getWidget.mockImplementation( async ( widget, scope ) =>
			envelope( widget, scope, {
				...heatmap,
				rowType: 'course' as const,
				rowsTotal: 24,
			} )
		);
		show();
		expect(
			await screen.findByRole( 'table', {
				name: 'Average mastery by course and skill',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'columnheader', { name: 'Course' } )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Showing 2 of 24 classes. Choose a school or class above to see the others.'
			)
		).toBeInTheDocument();
	} );

	it( 'explains an empty view', async () => {
		getWidget.mockImplementation( async ( widget, scope ) =>
			envelope( widget, scope, { ...heatmap, skills: [], cells: [] } )
		);
		show();
		expect(
			await screen.findByText(
				'No skill evidence yet: levels appear once learners answer questions linked to skills.'
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
} );
