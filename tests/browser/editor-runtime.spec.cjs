const { test, expect } = require( '@playwright/test' );
const { harness } = require( './helpers/recovered-runtime.cjs' );

test( 'rich text and custom table cells preserve the original JSON and HTML', async ( {
	page,
} ) => {
	async function sample( baseline ) {
		const { errors } = await harness( page, false, baseline, baseline );
		const result = await page.evaluate( () => {
			const require = window.testRequire;
			const { Editor } = require( 99248 );
			const extensions = [
				require( 71075 ).A,
				require( 19721 ).default,
				require( 81531 ).default,
				require( 43420 ).default,
				require( 70034 ).default,
				require( 81993 ).default,
				require( 52516 ).default.configure( {
					types: [ 'heading', 'paragraph' ],
				} ),
				require( 4805 ).default,
				require( 58526 ).default.configure( { nested: true } ),
				require( 10601 ).default,
				require( 55340 ).default,
				require( 17729 ).default,
				require( 11491 ).TableCell,
				require( 6268 ).default,
			];
			const editor = new Editor( {
				element: document.getElementById( 'app' ),
				extensions,
				content:
					'<h2 style="text-align: center">Монгол хэл</h2><p><strong>Bold</strong> <em>italic</em> <u>underlined</u> <s>strike</s> <code>code</code> <a href="https://example.com">link</a> <span style="color: rgb(255, 0, 0); font-family: serif">styled</span></p><ul><li><p>One</p></li></ul><ol start="3"><li><p>Three</p></li></ol><blockquote><p>Quote</p></blockquote><ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>Done</p></li></ul><table><tbody><tr><th><p>Header</p></th></tr><tr><td><p>Cell</p></td></tr></tbody></table><img src="https://example.com/test.png" alt="Test"><p>Last</p>',
			} );
			editor.commands.setTextSelection( 2 );
			editor.commands.toggleBold();
			editor.commands.insertContent( 'Edited ' );
			const json = editor.getJSON();
			const html = editor.getHTML();
			const model = require( 61106 );
			const singleton =
				editor.state.doc instanceof model.Node &&
				editor.state instanceof require( 56614 ).EditorState;
			editor.destroy();
			const reopened = new Editor( {
				element: document.getElementById( 'app' ),
				extensions,
				content: JSON.parse( JSON.stringify( json ) ),
			} );
			const result = {
				json,
				html,
				reopened: reopened.getJSON(),
				reopenedHtml: reopened.getHTML(),
				singleton,
			};
			reopened.destroy();
			return result;
		} );
		expect( errors ).toEqual( [] );
		expect( result.reopened ).toEqual( result.json );
		expect( result.reopenedHtml ).toBe( result.html );
		expect( result.singleton ).toBe( true );
		return result;
	}
	const migrated = await sample( false );
	const original = await sample( true );
	expect( migrated.json ).toEqual( original.json );
	expect( migrated.html ).toBe( original.html );
} );

test( 'React editor hooks update toolbar state and dynamic icons retain custom precedence', async ( {
	page,
} ) => {
	const { errors } = await harness( page );
	await page.evaluate( () => {
		const { createElement, useState } = window.React;
		const { useEditor, EditorContent } = window.testRequire( 83574 );
		const Icon = window.testRequire( 94608 ).Icon;
		const custom = window.testRequire( 66481 ).customIcons;
		custom.BookOpen = () =>
			createElement( 'svg', { 'data-test': 'custom-icon' } );
		function App() {
			const [ count, setCount ] = useState( 0 );
			const editor = useEditor( {
				extensions: [ window.testRequire( 71075 ).A ],
				content: '<p>Hello</p>',
				onUpdate: () => setCount( ( value ) => value + 1 ),
			} );
			return createElement(
				'div',
				null,
				createElement(
					'button',
					{
						onClick: () =>
							editor.chain().focus().toggleBold().run(),
						'aria-pressed': editor?.isActive( 'bold' ) || false,
					},
					'Bold'
				),
				createElement( 'span', { id: 'updates' }, String( count ) ),
				createElement( Icon, { name: 'BookOpen' } ),
				createElement( Icon, { name: 'ArrowRight' } ),
				createElement( EditorContent, { editor } )
			);
		}
		window.ReactDOM.createRoot( document.getElementById( 'app' ) ).render(
			createElement( App )
		);
	} );
	await expect( page.locator( '[data-test="custom-icon"]' ) ).toHaveCount(
		1
	);
	await expect( page.locator( 'svg.lucide-arrow-right' ) ).toHaveCount( 1 );
	await page.getByRole( 'button', { name: 'Bold', exact: true } ).click();
	await expect(
		page.getByRole( 'button', { name: 'Bold', exact: true } )
	).toHaveAttribute( 'aria-pressed', 'true' );
	await page.keyboard.type( ' world' );
	await expect( page.locator( '.ProseMirror strong' ) ).toContainText(
		'world'
	);
	await expect( page.locator( '#updates' ) ).not.toHaveText( '0' );
	expect( errors ).toEqual( [] );
} );
