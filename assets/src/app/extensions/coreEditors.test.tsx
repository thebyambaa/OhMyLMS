import { act, render, screen } from '@testing-library/react';
import { Suspense } from '@wordpress/element';
import type { ComponentType } from 'react';
import { createRegistry } from '../../extensions/registry.mjs';
import { CORE_QUESTION_EDITOR_IDS } from '../../features/question-editor/registerCoreEditors';
import {
	CORE_EDITOR_COMPONENTS,
	registerAppQuestionEditors,
	type EditorModuleLoader,
} from './coreEditors';
import { ExtensionBoundary } from './ExtensionBoundary';

type Module = Awaited< ReturnType< EditorModuleLoader > >;

function fakeModule(): Module {
	return Object.fromEntries(
		CORE_EDITOR_COMPONENTS.map( ( name ) => [
			name,
			( { value }: { value?: { type?: string } } ) => (
				<p>
					{ name } for { value?.type ?? 'part' }
				</p>
			),
		] )
	);
}

function deferred() {
	let resolve: ( module: Module ) => void = () => undefined;
	let reject: ( error: Error ) => void = () => undefined;
	const promise = new Promise< Module >( ( done, fail ) => {
		resolve = done;
		reject = fail;
	} );
	return { promise, resolve, reject };
}

function renderEditor(
	registry: ReturnType< typeof createRegistry >,
	id: string
) {
	const Editor = registry.get( 'question-editor', id )?.render as
		ComponentType< Record< string, unknown > > | undefined;
	if ( ! Editor ) {
		throw new Error( `No editor ${ id }` );
	}
	return render(
		<ExtensionBoundary id={ id }>
			<Suspense fallback={ <p role="status">Loading editor…</p> }>
				<Editor
					value={ { type: id } }
					onChange={ () => undefined }
					question={ {} }
				/>
			</Suspense>
		</ExtensionBoundary>
	);
}

describe( 'built-in question editors', () => {
	it( 'registers the fifteen editors without loading them', () => {
		const registry = createRegistry();
		const load = vi.fn< EditorModuleLoader >();

		const registered = registerAppQuestionEditors( registry, load );

		expect( registered ).toEqual( [ ...CORE_QUESTION_EDITOR_IDS ] );
		expect(
			registry
				.list( 'question-editor' )
				.map( ( entry ) => entry.id )
				.sort()
		).toEqual( [ ...CORE_QUESTION_EDITOR_IDS ].sort() );
		expect( registry.get( 'question-editor', 'set-clock' )?.label ).toBe(
			'Set the clock'
		);
		expect( load ).not.toHaveBeenCalled();
	} );

	it( 'keeps editors that an extension registered first', () => {
		const registry = createRegistry();
		const addOn = () => <p>Add-on editor</p>;
		registry.registerQuestionEditor( 'expression', {
			label: 'Add-on expression',
			render: addOn,
		} );

		const registered = registerAppQuestionEditors( registry, vi.fn() );

		expect( registered ).toHaveLength( 14 );
		expect( registered ).not.toContain( 'expression' );
		expect( registry.get( 'question-editor', 'expression' )?.render ).toBe(
			addOn
		);
	} );

	it( 'shows a fallback until the editor module loads, then the editor', async () => {
		const registry = createRegistry();
		const module = deferred();
		const load = vi.fn( () => module.promise );
		registerAppQuestionEditors( registry, load );

		renderEditor( registry, 'number-line' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading editor…'
		);

		await act( async () => module.resolve( fakeModule() ) );

		expect(
			await screen.findByText( 'NumberLineEditor for number-line' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument();
		expect( load ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders structured parts with the loaded answer fields without suspending again', async () => {
		const registry = createRegistry();
		const parts = fakeModule();
		parts.StructuredEditor = ( {
			renderQuestion: Part,
		}: {
			renderQuestion: ComponentType< Record< string, unknown > >;
		} ) => (
			<div>
				Parts: <Part value={ { type: 'numerical' } } />
			</div>
		);
		const load = vi.fn( async () => parts );
		registerAppQuestionEditors( registry, load );

		renderEditor( registry, 'structured' );

		expect(
			await screen.findByText( 'BankAnswerFields for numerical' )
		).toBeInTheDocument();
		expect( load ).toHaveBeenCalledTimes( 1 );

		renderEditor( registry, 'categorize' );
		expect(
			screen.getByText( 'CategorizeEditor for categorize' )
		).toBeInTheDocument();
	} );

	it( 'reports a failed load through the extension boundary', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const prevent = ( event: ErrorEvent ) => event.preventDefault();
		window.addEventListener( 'error', prevent );
		const registry = createRegistry();
		const module = deferred();
		registerAppQuestionEditors( registry, () => module.promise );

		renderEditor( registry, 'fill-level' );
		await act( async () => module.reject( new Error( 'Chunk failed' ) ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'This extension could not be displayed.'
		);
		window.removeEventListener( 'error', prevent );
	} );
} );
