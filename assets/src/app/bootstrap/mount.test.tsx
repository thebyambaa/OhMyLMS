import { act } from '@testing-library/react';
import type { ExtensionRegistry } from '../../extensions/registry.mjs';
import { CORE_QUESTION_EDITOR_IDS } from '../../features/question-editor/registerCoreEditors';
import { installExtensionHost } from '../extensions/host';
import { createBootstrap } from '../test/render';
import { mountApp, MOUNT_ID } from './mount';

const rendered = vi.hoisted( () => ( {
	extensions: undefined as ExtensionRegistry | undefined,
} ) );

vi.mock( '../App', () => ( {
	App: ( { extensions }: { extensions?: ExtensionRegistry } ) => {
		rendered.extensions = extensions;
		return <p>App</p>;
	},
} ) );
vi.mock( './configure-api-fetch', () => ( { configureApiFetch: vi.fn() } ) );

function container() {
	const element = document.createElement( 'div' );
	element.id = MOUNT_ID;
	document.body.append( element );
	return element;
}

function editorIds( registry: ExtensionRegistry | undefined ) {
	return ( registry?.list( 'question-editor' ) ?? [] )
		.map( ( entry ) => entry.id )
		.sort();
}

describe( 'mountApp', () => {
	afterEach( () => {
		delete window.ohmylms;
		document.body.replaceChildren();
		rendered.extensions = undefined;
	} );

	it( 'registers the built-in question editors in the published host before rendering', async () => {
		const host = installExtensionHost( window );
		const addOn = () => null;
		host.registerQuestionEditor( 'numerical', {
			label: 'Add-on numerical',
			render: addOn,
		} );
		const element = container();

		await act( async () => {
			expect( mountApp( document, createBootstrap() ) ).toBe( true );
		} );

		expect( element ).toHaveTextContent( 'App' );
		expect( rendered.extensions ).toBe( host );
		expect( editorIds( host ) ).toEqual(
			[ ...CORE_QUESTION_EDITOR_IDS ].sort()
		);
		expect( host.get( 'question-editor', 'numerical' )?.render ).toBe(
			addOn
		);
	} );

	it( 'uses its own registry when the host is not loaded', async () => {
		container();

		await act( async () => {
			mountApp( document, createBootstrap() );
		} );

		expect( editorIds( rendered.extensions ) ).toEqual(
			[ ...CORE_QUESTION_EDITOR_IDS ].sort()
		);
	} );
} );
