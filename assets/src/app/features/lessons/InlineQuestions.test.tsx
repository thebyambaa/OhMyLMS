import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import * as questions from '../questions/public';
import InlineQuestions from './InlineQuestions';

vi.mock( '../questions/public', () => ( {
	listQuestions: vi.fn(),
	questionEditorPath: ( id: number ) => `/content-hub/question-bank/${ id }`,
} ) );

const summary = {
	id: 8,
	uuid: 'DDDDDDDD-dddd-4ddd-8ddd-dddddddddddd',
	name: 'Gradient check',
	type: 'single-choice',
	type_label: 'Single choice',
	secure: false,
};

beforeEach( () => vi.resetAllMocks() );

it( 'inserts an approved practice question as a block and links it to the question editor', async () => {
	vi.mocked( questions.listQuestions ).mockResolvedValue( {
		items: [
			summary,
			{
				...summary,
				id: 9,
				uuid: 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
				name: 'Exam item',
				secure: true,
			},
		],
		total: 2,
		totalPages: 1,
	} as unknown as Awaited< ReturnType< typeof questions.listQuestions > > );
	const onChange = vi.fn();
	const user = userEvent.setup();
	const { rerender } = render(
		<MemoryRouter>
			<InlineQuestions
				content="<p>x</p>"
				saved={ [] }
				disabled={ false }
				onChange={ onChange }
			/>
		</MemoryRouter>
	);
	expect(
		screen.getByText( 'This lesson has no inline questions.' )
	).toBeInTheDocument();
	await user.type(
		screen.getByRole( 'textbox', {
			name: 'Find an approved practice question',
		} ),
		'gradient'
	);
	await user.click(
		screen.getByRole( 'button', { name: 'Search questions' } )
	);
	expect( questions.listQuestions ).toHaveBeenCalledWith(
		expect.objectContaining( { search: 'gradient', status: 'approved' } ),
		expect.any( AbortSignal )
	);
	expect(
		await screen.findByRole( 'button', { name: 'Insert “Gradient check”' } )
	).toBeInTheDocument();
	expect(
		screen.queryByRole( 'button', { name: 'Insert “Exam item”' } )
	).not.toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Insert “Gradient check”' } )
	);
	const next = onChange.mock.calls[ 0 ]![ 0 ] as string;
	expect( next ).toBe(
		'<p>x</p>\n\n<!-- wp:ohmylms/shared-multiple-choice {"questionId":8,"uuid":"dddddddd-dddd-4ddd-8ddd-dddddddddddd"} /-->\n'
	);
	rerender(
		<MemoryRouter>
			<InlineQuestions
				content={ next }
				saved={ [] }
				disabled={ false }
				onChange={ onChange }
			/>
		</MemoryRouter>
	);
	expect(
		screen.getByRole( 'link', { name: 'Gradient check' } )
	).toHaveAttribute( 'href', '/content-hub/question-bank/8' );
	expect( screen.getByText( 'Approved' ) ).toBeInTheDocument();
} );

it( 'keeps shortcodes in place and locks editing when disabled', () => {
	render(
		<MemoryRouter>
			<InlineQuestions
				content={
					'<p>[ohmylms_question uuid="ffffffff-ffff-4fff-8fff-ffffffffffff"]</p>'
				}
				saved={ [] }
				disabled
				onChange={ vi.fn() }
			/>
		</MemoryRouter>
	);
	expect( screen.getByText( 'Unknown question' ) ).toBeInTheDocument();
	expect( screen.getByText( /Text shortcode/ ) ).toBeInTheDocument();
	expect(
		screen.getByRole( 'button', { name: 'Remove Unknown question' } )
	).toBeDisabled();
	expect(
		screen.getByRole( 'button', { name: 'Search questions' } )
	).toBeDisabled();
} );
