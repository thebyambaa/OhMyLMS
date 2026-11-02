import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { CourseConnections, useConnectionTarget } from './CourseConnections';

vi.mock( '../curriculum/public', () => ( {
	ContentSkillConnections: ( {
		kind,
		id,
		onDirtyChange,
	}: {
		kind: string;
		id: number;
		onDirtyChange: ( dirty: boolean ) => void;
	} ) => (
		<div>
			<span>{ `${ kind }:${ id }` }</span>
			<button onClick={ () => onDirtyChange( true ) }>
				Stage skill link
			</button>
		</div>
	),
} ) );

function Workspace() {
	const connections = useConnectionTarget( 12 );
	return (
		<MemoryRouter>
			<button
				onClick={ () =>
					connections.choose( { kind: 'lesson', id: 20 } )
				}
			>
				Select lesson
			</button>
			<CourseConnections
				course={ 12 }
				target={ connections.target }
				dirty={ connections.dirty }
				onDirtyChange={ connections.setDirty }
				onCourse={ () =>
					connections.choose( { kind: 'course', id: 12 } )
				}
			/>
			{ connections.confirmation }
		</MemoryRouter>
	);
}

it( 'keeps staged links when switching is cancelled and switches after explicit discard', async () => {
	const user = userEvent.setup();
	render( <Workspace /> );
	await user.click(
		screen.getByRole( 'button', { name: 'Stage skill link' } )
	);
	await user.click( screen.getByRole( 'button', { name: 'Select lesson' } ) );
	expect( screen.getByRole( 'dialog' ) ).toHaveTextContent(
		'Discard unsaved skill connections?'
	);
	expect( screen.getByText( 'course:12' ) ).toBeInTheDocument();
	await user.click( screen.getByRole( 'button', { name: 'Keep editing' } ) );
	expect(
		screen.getByText( 'Unsaved skill connections' )
	).toBeInTheDocument();
	await user.click( screen.getByRole( 'button', { name: 'Select lesson' } ) );
	await user.click(
		screen.getByRole( 'button', { name: 'Discard and switch' } )
	);
	expect( screen.getByText( 'lesson:20' ) ).toBeInTheDocument();
	expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
} );
