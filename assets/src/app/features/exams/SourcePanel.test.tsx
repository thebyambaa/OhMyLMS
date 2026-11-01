import { configure, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { source } from './fixtures';
import SourcePanel, { type SourceFocus } from './SourcePanel';
import type { SourceInfo } from './sourceModel';

configure( { asyncUtilTimeout: 5000 } );

const notice = '.components-notice__content';

const question: SourceFocus = {
	key: '0:',
	title: 'Question 1',
	page: 2,
	figure: null,
	bounds: null,
};

const figure: SourceFocus = {
	key: '0:f1',
	title: 'Question 1',
	page: 3,
	figure: 'f1',
	bounds: [ 0.25, 0.25, 0.75, 0.5 ],
};

function renderPanel(
	props: Partial< Parameters< typeof SourcePanel >[ 0 ] > = {}
) {
	const onCrop = vi.fn();
	const view = render(
		<SourcePanel
			source={ source }
			error={ null }
			focus={ question }
			regions={ [
				{
					id: 'f2',
					label: 'f2',
					page: 2,
					bounds: [ 0, 0, 0.5, 0.5 ],
				},
				{
					id: 'f9',
					label: 'f9',
					page: 5,
					bounds: [ 0, 0, 0.5, 0.5 ],
				},
			] }
			canCrop
			busy={ false }
			onCrop={ onCrop }
			{ ...props }
		/>
	);
	return { ...view, onCrop };
}

function sheet( container: HTMLElement ): HTMLElement {
	const element = container.querySelector(
		'.ohmylms-crop-page'
	) as HTMLElement;
	vi.spyOn( element, 'getBoundingClientRect' ).mockReturnValue( {
		left: 0,
		top: 0,
		width: 400,
		height: 200,
		right: 400,
		bottom: 200,
		x: 0,
		y: 0,
		toJSON: () => ( {} ),
	} );
	return element;
}

function fields(): string[] {
	return [ 'Left (%)', 'Top (%)', 'Right (%)', 'Bottom (%)' ].map(
		( name ) =>
			( screen.getByRole( 'textbox', { name } ) as HTMLInputElement )
				.value
	);
}

describe( 'SourcePanel', () => {
	it( "shows the focused question's page with its crops and renderer", () => {
		const { container } = renderPanel();
		expect(
			screen.getByRole( 'heading', {
				name: 'Source: Question 1, page 2',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'img', { name: 'Page 2 of the source paper' } )
		).toHaveAttribute(
			'src',
			'https://example.test/wp-json/ohmylms/v1/exam-imports/jobs/7/pages/2?_wpnonce=abc'
		);
		expect( screen.getByText( /Poppler 24\.08\.0/ ) ).toBeInTheDocument();
		expect( screen.getByText( 'Page 2 of 6' ) ).toBeInTheDocument();
		// Only crops on this page are drawn.
		expect(
			[ ...container.querySelectorAll( '.ohmylms-crop-region' ) ].map(
				( region ) => region.textContent
			)
		).toEqual( [ 'f2' ] );
		expect(
			screen.getByRole( 'link', { name: 'Open the mark scheme' } )
		).toHaveAttribute( 'href', source.mark_scheme );
		expect( fields() ).toEqual( [ '10', '10', '90', '50' ] );
	} );

	it( 'pages through the paper within its page count', async () => {
		const user = userEvent.setup();
		renderPanel( { focus: { ...question, page: 6 } } );
		expect(
			screen.getByRole( 'button', { name: 'Next page' } )
		).toBeDisabled();
		await user.click(
			screen.getByRole( 'button', { name: 'Previous page' } )
		);
		expect(
			screen.getByRole( 'img', { name: 'Page 5 of the source paper' } )
		).toHaveAttribute( 'src', expect.stringContaining( '/pages/5?' ) );
	} );

	it( 'draws a crop by dragging and adds it as a new figure', async () => {
		const user = userEvent.setup();
		const { container, onCrop } = renderPanel();
		const page = sheet( container );
		fireEvent.pointerDown( page, {
			pointerId: 1,
			pointerType: 'mouse',
			button: 0,
			clientX: 300,
			clientY: 150,
		} );
		fireEvent.pointerMove( page, {
			pointerId: 1,
			clientX: 200,
			clientY: 100,
		} );
		fireEvent.pointerUp( page, {
			pointerId: 1,
			clientX: 100,
			clientY: 20,
		} );
		expect(
			screen.getByRole( 'button', {
				name: 'Crop area: left 25%, top 10%, right 75%, bottom 75%',
			} )
		).toBeInTheDocument();
		expect( fields() ).toEqual( [ '25', '10', '75', '75' ] );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Add as a new figure of Question 1',
			} )
		);
		expect( onCrop ).toHaveBeenCalledWith(
			2,
			[ 0.25, 0.1, 0.75, 0.75 ],
			null
		);
	} );

	it( 'draws with touch and moves the crop by dragging inside it', () => {
		const { container } = renderPanel( { focus: figure } );
		const page = sheet( container );
		// Inside the figure crop (25-75 %, 25-50 %): move it.
		fireEvent.pointerDown( page, {
			pointerId: 2,
			pointerType: 'touch',
			clientX: 200,
			clientY: 70,
		} );
		fireEvent.pointerUp( page, {
			pointerId: 2,
			clientX: 240,
			clientY: 90,
		} );
		expect( fields() ).toEqual( [ '35', '35', '85', '60' ] );
		// Outside it: draw a new one.
		fireEvent.pointerDown( page, {
			pointerId: 3,
			pointerType: 'touch',
			clientX: 0,
			clientY: 180,
		} );
		fireEvent.pointerUp( page, {
			pointerId: 3,
			clientX: 40,
			clientY: 200,
		} );
		expect( fields() ).toEqual( [ '0', '90', '10', '100' ] );
		// A tap without a drag keeps the crop.
		fireEvent.pointerDown( page, {
			pointerId: 4,
			pointerType: 'touch',
			clientX: 300,
			clientY: 20,
		} );
		fireEvent.pointerUp( page, {
			pointerId: 4,
			clientX: 300,
			clientY: 20,
		} );
		expect( fields() ).toEqual( [ '0', '90', '10', '100' ] );
	} );

	it( 'moves and resizes the crop with the keyboard and replaces the figure crop', async () => {
		const user = userEvent.setup();
		const { onCrop } = renderPanel( { focus: figure } );
		expect(
			screen.getByRole( 'heading', {
				name: 'Source: Question 1, figure f1, page 3',
			} )
		).toBeInTheDocument();
		const crop = screen.getByRole( 'button', { name: /^Crop area/ } );
		crop.focus();
		await user.keyboard( '{ArrowRight}{ArrowRight}' );
		await user.keyboard( '{Shift>}{ArrowDown}{/Shift}' );
		await user.keyboard( '{Alt>}{ArrowLeft}{/Alt}' );
		expect( crop ).toHaveAccessibleName(
			'Crop area: left 26.9%, top 25%, right 76.9%, bottom 51%'
		);
		await user.click(
			screen.getByRole( 'button', {
				name: 'Use this area for figure f1',
			} )
		);
		expect( onCrop ).toHaveBeenCalledWith(
			3,
			[ 0.269, 0.25, 0.769, 0.51 ],
			'f1'
		);
	} );

	it( 'takes typed percentages and refuses invalid ones', async () => {
		const user = userEvent.setup();
		const { onCrop } = renderPanel();
		const right = screen.getByRole( 'textbox', { name: 'Right (%)' } );
		await user.clear( right );
		await user.type( right, '5' );
		expect(
			screen.getByText( /with left before right/, {
				selector: '[role="alert"]',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', {
				name: 'Add as a new figure of Question 1',
			} )
		).toBeDisabled();
		await user.type( right, '5.5' );
		expect(
			screen.getByRole( 'button', {
				name: 'Crop area: left 10%, top 10%, right 55.5%, bottom 50%',
			} )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Add as a new figure of Question 1',
			} )
		);
		expect( onCrop ).toHaveBeenCalledWith(
			2,
			[ 0.1, 0.1, 0.555, 0.5 ],
			null
		);
	} );

	it( 'follows a new focus to its page and crop', () => {
		const { rerender } = renderPanel();
		rerender(
			<SourcePanel
				source={ source }
				error={ null }
				focus={ figure }
				regions={ [] }
				canCrop
				busy={ false }
				onCrop={ vi.fn() }
			/>
		);
		expect(
			screen.getByRole( 'img', { name: 'Page 3 of the source paper' } )
		).toBeInTheDocument();
		expect( fields() ).toEqual( [ '25', '25', '75', '50' ] );
	} );

	it( 'is read only when crops cannot be saved', () => {
		renderPanel( { canCrop: false } );
		expect(
			screen.queryByRole( 'textbox', { name: 'Left (%)' } )
		).toBeNull();
		expect(
			screen.getByRole( 'button', { name: /^Crop area/ } )
		).toBeDisabled();
	} );

	it( 'falls back to the PDF viewer with guidance when the server cannot render', () => {
		const unavailable: SourceInfo = {
			...source,
			renderer: {
				...source.renderer,
				available: false,
				engine: '',
				version: '',
				reason: 'not_configured',
				crops: false,
			},
		};
		renderPanel( { source: unavailable } );
		expect(
			screen.getByText( /OHMYLMS_PDFTOPPM_BINARY/, { selector: notice } )
		).toBeInTheDocument();
		expect( screen.getByTitle( 'Source paper (PDF)' ) ).toHaveAttribute(
			'src',
			`${ source.document }#page=2`
		);
		expect( screen.queryByRole( 'img' ) ).toBeNull();
		expect(
			screen.queryByRole( 'button', { name: /Add as a new figure/ } )
		).toBeNull();
	} );

	it( 'switches to the PDF viewer and reports a page that failed to render', async () => {
		const user = userEvent.setup();
		renderPanel();
		fireEvent.error(
			screen.getByRole( 'img', { name: 'Page 2 of the source paper' } )
		);
		expect(
			screen.getByText( /could not be rendered/, { selector: notice } )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Open in the PDF viewer' } )
		);
		expect( screen.getByTitle( 'Source paper (PDF)' ) ).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Show the page image' } )
		);
		expect(
			screen.getByRole( 'img', { name: 'Page 2 of the source paper' } )
		).toBeInTheDocument();
	} );

	it( 'shows why the source could not be loaded', () => {
		renderPanel( { source: null, error: 'This import has no PDF paper.' } );
		expect(
			screen.getByText( 'This import has no PDF paper.', {
				selector: notice,
			} )
		).toBeInTheDocument();
	} );
} );
