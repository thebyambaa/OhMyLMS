import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import LessonRedirect from './LessonRedirect';

function renderAt( path: string ) {
	return render(
		<MemoryRouter initialEntries={ [ path ] }>
			<Routes>
				<Route
					path="/content-hub/lessons/:id"
					element={ <LessonRedirect /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

describe( 'LessonRedirect', () => {
	const original = window.location;
	afterEach( () => {
		Object.defineProperty( window, 'location', {
			value: original,
			configurable: true,
		} );
	} );

	it( 'sends a lesson address to the WordPress block editor', () => {
		const replace = vi.fn();
		Object.defineProperty( window, 'location', {
			value: { ...original, replace },
			configurable: true,
		} );
		renderAt( '/content-hub/lessons/13874' );
		expect( replace ).toHaveBeenCalledWith(
			'post.php?post=13874&action=edit'
		);
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Opening the block editor'
		);
	} );

	it( 'rejects an invalid lesson id without redirecting', () => {
		const replace = vi.fn();
		Object.defineProperty( window, 'location', {
			value: { ...original, replace },
			configurable: true,
		} );
		renderAt( '/content-hub/lessons/abc' );
		expect( replace ).not.toHaveBeenCalled();
		expect(
			screen.getByText( 'This lesson link is invalid.', {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
	} );
} );
