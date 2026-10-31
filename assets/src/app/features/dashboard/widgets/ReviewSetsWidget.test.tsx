import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../../lib/api';
import * as api from '../shell/api';
import { context, envelope, northScope } from '../shell/fixtures';
import * as masteryApi from './MasteryApi';
import { parseAssignResult, parseReviewSets } from './ReviewSetsModel';
import type { ReviewSets } from './ReviewSetsModel';
import ReviewSetsWidget from './ReviewSetsWidget';

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
const assignReviewSet = vi.mocked( masteryApi.assignReviewSet );
const getMasteryLearners = vi.mocked( masteryApi.getMasteryLearners );

const raw = {
	row_type: 'class',
	min_group: 3,
	total: 3,
	can_assign: true,
	groups: [
		{
			key: 'class:9|skill:32',
			class: { id: 9, key: 'class:9', label: 'Algebra 8A', learners: 6 },
			skill: { id: 32, name: 'Cells', code: '' },
			learners: 5,
			questions: { available: 5, needed: 3, draw: 5, enough: true },
			review_set: null,
		},
		{
			key: 'class:9|skill:34',
			class: { id: 9, key: 'class:9', label: 'Algebra 8A', learners: 6 },
			skill: { id: 34, name: 'Orbits', code: '' },
			learners: 3,
			questions: { available: 1, needed: 3, draw: 1, enough: false },
			review_set: null,
		},
		{
			key: 'class:10|skill:31',
			class: {
				id: 10,
				key: 'class:10',
				label: 'Algebra 8B',
				learners: 4,
			},
			skill: { id: 31, name: 'Fractions', code: '' },
			learners: 3,
			questions: { available: 8, needed: 3, draw: 5, enough: true },
			review_set: {
				id: 70,
				title: 'Fractions review set (Algebra 8B)',
				status: 'draft',
				route: '/quizzes/70',
			},
		},
	],
};
const sets: ReviewSets = parseReviewSets( raw );

function show( data: ReviewSets = sets ) {
	getWidget.mockImplementation( async ( widget, scope ) =>
		envelope( widget, scope, data )
	);
	return render(
		<MemoryRouter>
			<ReviewSetsWidget context={ context( northScope ) } />
		</MemoryRouter>
	);
}

function item( text: string ) {
	return screen.getByText( text ).closest( 'li' ) as HTMLElement;
}

beforeEach( () => {
	vi.clearAllMocks();
	assignReviewSet.mockResolvedValue( {
		created: true,
		reviewSet: {
			id: 77,
			title: 'Cells review set (Algebra 8A)',
			status: 'draft',
			route: '/quizzes/77',
		},
	} );
	getMasteryLearners.mockResolvedValue( {
		row: { key: 'class:9', label: 'Algebra 8A', learners: 6 },
		skill: { id: 32, name: 'Cells' },
		below: 1,
		assessed: 5,
		learners: [
			{
				id: 501,
				name: 'Ana Ortiz',
				percent: 10,
				level: 'attempted',
				route: null,
			},
		],
		limit: 50,
	} );
} );

describe( 'review set data', () => {
	it( 'validates groups and links', () => {
		expect( sets.groups ).toHaveLength( 3 );
		expect( sets.groups[ 2 ]?.reviewSet?.route ).toBe( '/quizzes/70' );
		expect( () =>
			parseReviewSets( {
				...raw,
				groups: [ { ...raw.groups[ 0 ], key: 'Cells' } ],
			} )
		).toThrow();
		expect( () =>
			parseAssignResult( {
				created: true,
				review_set: {
					id: 1,
					title: 'x',
					status: 'draft',
					route: '//evil',
				},
			} )
		).toThrow();
	} );
} );

describe( 'review sets widget', () => {
	it( 'lists groups with their question figures', async () => {
		show();
		expect(
			await screen.findByText(
				'5 learners in Algebra 8A are below Familiar on Cells'
			)
		).toBeInTheDocument();
		expect( getWidget ).toHaveBeenCalledWith(
			'review-sets',
			'school:3',
			30,
			parseReviewSets,
			expect.any( AbortSignal )
		);
		const cells = item(
			'5 learners in Algebra 8A are below Familiar on Cells'
		);
		expect( cells ).toHaveTextContent(
			'A review set draws 5 of 5 approved questions.'
		);
		const orbits = item(
			'3 learners in Algebra 8A are below Familiar on Orbits'
		);
		expect( orbits ).toHaveTextContent(
			'Not enough questions: 1 approved, 3 needed.'
		);
		expect(
			within( orbits ).queryByRole( 'button', {
				name: /Assign review set/,
			} )
		).toBeNull();
		const fractions = item(
			'3 learners in Algebra 8B are below Familiar on Fractions'
		);
		expect(
			within( fractions ).getByRole( 'link', { name: 'Open draft' } )
		).toHaveAttribute( 'href', '/quizzes/70' );
		expect(
			within( fractions ).queryByRole( 'button', { name: /Assign/ } )
		).toBeNull();
	} );

	it( 'creates a draft once, even on a double click, and links to it', async () => {
		const user = userEvent.setup();
		let finish: ( () => void ) | undefined;
		assignReviewSet.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					finish = () =>
						resolve( {
							created: true,
							reviewSet: {
								id: 77,
								title: 'Cells review set (Algebra 8A)',
								status: 'draft',
								route: '/quizzes/77',
							},
						} );
				} )
		);
		show();
		const assign = await screen.findByRole( 'button', {
			name: 'Assign review set: Cells, Algebra 8A',
		} );
		await user.click( assign );
		await user.click( assign );
		expect( assignReviewSet ).toHaveBeenCalledTimes( 1 );
		expect( assignReviewSet ).toHaveBeenCalledWith( 'school:3', 9, 32 );
		expect( assign ).toHaveAttribute( 'aria-disabled', 'true' );
		finish?.();
		const status = await screen.findByRole( 'status' );
		expect( status ).toHaveTextContent(
			'Draft review set ready: Cells review set (Algebra 8A). It is not published'
		);
		expect(
			within( status ).getByRole( 'link', { name: 'Open the draft' } )
		).toHaveAttribute( 'href', '/quizzes/77' );
		const cells = item(
			'5 learners in Algebra 8A are below Familiar on Cells'
		);
		expect(
			within( cells ).getByRole( 'link', { name: 'Open draft' } )
		).toHaveAttribute( 'href', '/quizzes/77' );
		expect(
			within( cells ).queryByRole( 'button', { name: /Assign/ } )
		).toBeNull();
	} );

	it( 'tells a teacher who cannot open the quiz editor that an administrator publishes the draft', async () => {
		const user = userEvent.setup();
		assignReviewSet.mockResolvedValueOnce( {
			created: true,
			reviewSet: {
				id: 78,
				title: 'Cells review set (Algebra 8A)',
				status: 'draft',
				route: null,
			},
		} );
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Assign review set: Cells, Algebra 8A',
			} )
		);
		const status = await screen.findByRole( 'status' );
		expect( status ).toHaveTextContent(
			'Draft review set created: Cells review set (Algebra 8A). It is not published; an administrator can review and publish it under Content › Quizzes.'
		);
		expect( status ).not.toHaveTextContent( 'review it, then publish' );
		expect( within( status ).queryByRole( 'link' ) ).toBeNull();
		const cells = item(
			'5 learners in Algebra 8A are below Familiar on Cells'
		);
		expect( cells ).toHaveTextContent( 'Draft review set created' );
		expect( within( cells ).queryByRole( 'link' ) ).toBeNull();
		expect(
			within( cells ).queryByRole( 'button', { name: /Assign/ } )
		).toBeNull();
	} );

	it( 'shows why a review set could not be created', async () => {
		const user = userEvent.setup();
		assignReviewSet.mockRejectedValueOnce(
			new ApiError( {
				status: 409,
				code: 'ohmylms_dashboard_review_set_not_enough_questions',
				message:
					'Not enough questions: the bank has 2 approved questions for this skill and a review set needs 3.',
			} )
		);
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Assign review set: Cells, Algebra 8A',
			} )
		);
		expect(
			await screen.findByText(
				'Not enough questions: the bank has 2 approved questions for this skill and a review set needs 3.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', {
					name: 'Assign review set: Cells, Algebra 8A',
				} )
			).not.toHaveAttribute( 'aria-disabled', 'true' )
		);
	} );

	it( 'tells users who cannot create quizzes instead of offering the button', async () => {
		show( { ...sets, canAssign: false } );
		await screen.findByText(
			'5 learners in Algebra 8A are below Familiar on Cells'
		);
		expect(
			screen.queryByRole( 'button', { name: /Assign review set/ } )
		).toBeNull();
		expect(
			screen.getAllByText( 'Creating quizzes needs an author account.' )
		).toHaveLength( 1 );
	} );

	it( 'lists the group', async () => {
		const user = userEvent.setup();
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'See group: Cells, Algebra 8A',
			} )
		);
		expect( getMasteryLearners ).toHaveBeenCalledWith(
			'school:3',
			'class:9',
			32,
			expect.any( AbortSignal )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Below Familiar on Cells · Algebra 8A',
		} );
		expect(
			await within( dialog ).findByText( 'Ana Ortiz' )
		).toBeInTheDocument();
	} );

	it( 'explains views without classes or groups', async () => {
		const { unmount } = show( { ...sets, rowType: 'course', groups: [] } );
		expect(
			await screen.findByText(
				'Recommendations are made for classes, and this view has none. Choose a school or class with classes.'
			)
		).toBeInTheDocument();
		unmount();
		show( { ...sets, groups: [] } );
		expect(
			await screen.findByText(
				'No group of 3 or more learners in a class is below Familiar on a skill. Nothing to review right now.'
			)
		).toBeInTheDocument();
	} );
} );
