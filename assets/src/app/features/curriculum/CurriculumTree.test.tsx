import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { CurriculumTree } from './CurriculumTree';
import type { CurriculumItem } from './api';

const items: CurriculumItem[] = [
	{ id: 1, parent_id: 0, name: 'Mathematics' },
	{ id: 2, parent_id: 1, name: 'IGCSE' },
	{ id: 3, parent_id: 2, name: 'Algebra' },
].map( ( item, position ) => ( {
	...item,
	position,
	item_type: 'topic',
	description: '',
	code: '',
	version: '',
	is_syllabus: false,
	course_id: 0,
	icon: '',
	updated_at: '',
} ) );

it( 'expands branches, collapses all, and offers contextual child creation', async () => {
	const user = userEvent.setup();
	const onAddChild = vi.fn();
	render(
		<MemoryRouter>
			<CurriculumTree
				items={ items }
				search=""
				selected={ null }
				disabled={ false }
				onSelect={ vi.fn() }
				onAddChild={ onAddChild }
			/>
		</MemoryRouter>
	);
	expect(
		screen.queryByRole( 'button', { name: 'IGCSE' } )
	).not.toBeInTheDocument();
	await user.click(
		screen.getByRole( 'button', { name: 'Expand or collapse Mathematics' } )
	);
	expect( screen.getByRole( 'button', { name: 'IGCSE' } ) ).toBeVisible();
	await user.click( screen.getByRole( 'button', { name: 'Expand all' } ) );
	expect( screen.getByRole( 'button', { name: 'Algebra' } ) ).toBeVisible();
	await user.click(
		screen.getByRole( 'button', { name: 'Add child to Algebra' } )
	);
	expect( onAddChild ).toHaveBeenCalledWith( items[ 2 ] );
	await user.click( screen.getByRole( 'button', { name: 'Collapse all' } ) );
	expect(
		screen.queryByRole( 'button', { name: 'Algebra' } )
	).not.toBeInTheDocument();
} );

it( 'search reveals a matching descendant and its ancestors without manual expansion', () => {
	render(
		<MemoryRouter>
			<CurriculumTree
				items={ items }
				search="algebra"
				selected={ 3 }
				disabled={ false }
				onSelect={ vi.fn() }
				onAddChild={ vi.fn() }
			/>
		</MemoryRouter>
	);
	for ( const name of [ 'Mathematics', 'IGCSE', 'Algebra' ] ) {
		expect( screen.getByRole( 'button', { name } ) ).toBeVisible();
	}
	expect( screen.getByRole( 'button', { name: 'Algebra' } ) ).toHaveAttribute(
		'aria-pressed',
		'true'
	);
} );
