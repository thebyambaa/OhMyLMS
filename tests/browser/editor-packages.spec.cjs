const { test, expect } = require( '@playwright/test' );
const { harness } = require( './helpers/recovered-runtime.cjs' );

test( 'the recovered code editor highlights with npm Lowlight and preserves saved content', async ( {
	page,
} ) => {
	const { errors } = await harness( page );
	await page.evaluate( () => {
		const core = window.testRequire( 99248 );
		const block = window.testRequire( 48263 ).CodeBlock;
		const Document = core.Node.create( {
			name: 'doc',
			topNode: true,
			content: 'block+',
		} );
		const Text = core.Node.create( { name: 'text', group: 'inline' } );
		const Paragraph = core.Node.create( {
			name: 'paragraph',
			group: 'block',
			content: 'inline*',
			parseHTML: () => [ { tag: 'p' } ],
			renderHTML: () => [ 'p', 0 ],
		} );
		const extensions = [ Document, Text, Paragraph, block ];
		window.createTestEditor = ( content ) =>
			new core.Editor( {
				element: document.getElementById( 'app' ),
				extensions,
				content,
			} );
		window.testEditor = window.createTestEditor( {
			type: 'doc',
			content: [
				{
					type: 'codeBlock',
					attrs: { language: 'javascript' },
					content: [ { type: 'text', text: 'const count = 1;' } ],
				},
			],
		} );
	} );
	await expect(
		page.locator( '.ProseMirror code .hljs-keyword' )
	).toContainText( 'const' );
	await page.locator( '.ProseMirror' ).click();
	await page.keyboard.press( 'Control+End' );
	await page.keyboard.type( '\nconst next = 2;' );
	const saved = await page.evaluate( () => {
		const json = window.testEditor.getJSON();
		const html = window.testEditor.getHTML();
		window.testEditor.destroy();
		window.testEditor = window.createTestEditor(
			JSON.parse( JSON.stringify( json ) )
		);
		return {
			json,
			html,
			reopened: window.testEditor.getJSON(),
			reopenedHtml: window.testEditor.getHTML(),
		};
	} );
	expect( saved.json.content[ 0 ].content[ 0 ].text ).toContain(
		'const next = 2;'
	);
	expect( saved.reopened ).toEqual( saved.json );
	expect( saved.reopenedHtml ).toBe( saved.html );
	for ( const language of [ 'python', 'xml', 'not-a-language' ] ) {
		const result = await page.evaluate( ( language ) => {
			const code =
				'<script>window.unexpectedCodeExecution=true</script>\n# Монгол хэл\nreturn 42';
			const json = {
				type: 'doc',
				content: [
					{
						type: 'codeBlock',
						attrs: { language },
						content: [ { type: 'text', text: code } ],
					},
				],
			};
			window.testEditor.commands.setContent( json );
			return {
				json: window.testEditor.getJSON(),
				code,
				html: window.testEditor.getHTML(),
				executed: !! window.unexpectedCodeExecution,
			};
		}, language );
		expect( result.json.content[ 0 ].attrs.language ).toBe( language );
		expect( result.json.content[ 0 ].content[ 0 ].text ).toBe(
			result.code
		);
		expect( result.html ).toContain( '&lt;script&gt;' );
		expect( result.executed ).toBe( false );
	}
	expect( errors ).toEqual( [] );
} );
