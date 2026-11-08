import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	BUILDER_AUTOSAVE_DELAY,
	BuilderSaveProvider,
	useBuilderSave,
} from './useBuilderSave';
import type { StepSave } from './useBuilderSave';

function Step( { id, step }: { id: string; step: StepSave } ) {
	useBuilderSave( step, id );
	return null;
}

function Bar( { step }: { step: StepSave | null } ) {
	return (
		<div>
			<span data-testid="dirty">
				{ String( Boolean( step?.dirty ) ) }
			</span>
			<span data-testid="busy">{ String( Boolean( step?.busy ) ) }</span>
			<span data-testid="course">{ step?.course?.status ?? 'none' }</span>
			<button onClick={ () => step?.save() }>Save</button>
		</div>
	);
}

const step = ( over: Partial< StepSave > = {} ): StepSave => ( {
	save: vi.fn(),
	dirty: false,
	busy: false,
	...over,
} );

function builder( steps: Record< string, StepSave > ) {
	return (
		<BuilderSaveProvider
			render={ ( registered ) => (
				<>
					<Bar step={ registered } />
					{ Object.entries( steps ).map( ( [ id, one ] ) => (
						<Step key={ id } id={ id } step={ one } />
					) ) }
				</>
			) }
		/>
	);
}

describe( 'builder save', () => {
	it( 'has no step until one registers', () => {
		render( builder( {} ) );
		expect( screen.getByTestId( 'dirty' ) ).toHaveTextContent( 'false' );
		expect( screen.getByTestId( 'course' ) ).toHaveTextContent( 'none' );
	} );

	it( 'is unsaved while any registered step is', () => {
		render(
			builder( {
				basics: step(),
				outline: step( { dirty: true } ),
			} )
		);
		expect( screen.getByTestId( 'dirty' ) ).toHaveTextContent( 'true' );
	} );

	it( 'is busy while any step is saving', () => {
		render(
			builder( { basics: step( { busy: true } ), outline: step() } )
		);
		expect( screen.getByTestId( 'busy' ) ).toHaveTextContent( 'true' );
	} );

	it( 'saves every step that has changes, and no other', async () => {
		const user = userEvent.setup();
		const basics = step( { dirty: true } );
		const outline = step( { dirty: true } );
		const settled = step();
		const saving = step( { dirty: true, busy: true } );
		render( builder( { basics, outline, settled, saving } ) );
		await user.click( screen.getByRole( 'button', { name: 'Save' } ) );
		expect( basics.save ).toHaveBeenCalledOnce();
		expect( outline.save ).toHaveBeenCalledOnce();
		expect( settled.save ).not.toHaveBeenCalled();
		expect( saving.save ).not.toHaveBeenCalled();
	} );

	it( 'takes the course link from whichever step has loaded it', () => {
		render(
			builder( {
				outline: step(),
				basics: step( {
					course: {
						status: 'draft',
						permalink: '',
						preview_url: '',
					},
				} ),
			} )
		);
		expect( screen.getByTestId( 'course' ) ).toHaveTextContent( 'draft' );
	} );

	it( 'forgets a step that unmounts', () => {
		const { rerender } = render(
			builder( { basics: step( { dirty: true } ) } )
		);
		expect( screen.getByTestId( 'dirty' ) ).toHaveTextContent( 'true' );
		act( () => {
			rerender( builder( {} ) );
		} );
		expect( screen.getByTestId( 'dirty' ) ).toHaveTextContent( 'false' );
	} );

	it( 'saves by itself shortly after the first unsaved change, once', () => {
		vi.useFakeTimers();
		try {
			const basics = step( { dirty: true } );
			const settled = step();
			render( builder( { basics, settled } ) );
			expect( basics.save ).not.toHaveBeenCalled();
			act( () => {
				vi.advanceTimersByTime( BUILDER_AUTOSAVE_DELAY + 10 );
			} );
			expect( basics.save ).toHaveBeenCalledOnce();
			expect( settled.save ).not.toHaveBeenCalled();
			// A save that failed leaves the step unsaved: it is not retried on its own.
			act( () => {
				vi.advanceTimersByTime( BUILDER_AUTOSAVE_DELAY * 4 );
			} );
			expect( basics.save ).toHaveBeenCalledOnce();
		} finally {
			vi.useRealTimers();
		}
	} );

	it( 'does not save by itself while a save runs or when nothing changed', () => {
		vi.useFakeTimers();
		try {
			const saving = step( { dirty: true, busy: true } );
			const clean = step();
			render( builder( { saving, clean } ) );
			act( () => {
				vi.advanceTimersByTime( BUILDER_AUTOSAVE_DELAY * 2 );
			} );
			expect( saving.save ).not.toHaveBeenCalled();
			expect( clean.save ).not.toHaveBeenCalled();
		} finally {
			vi.useRealTimers();
		}
	} );
} );
