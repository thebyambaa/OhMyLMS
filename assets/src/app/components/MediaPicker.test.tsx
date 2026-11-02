import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../lib/api';
import { IMAGE_MIME_TYPES } from '../lib/media';
import { attachment, mockMediaFrame } from '../test/mediaFrame';
import { MediaPicker, type MediaPickerProps } from './MediaPicker';

const INVALID = 'The server returned invalid course data.';

let media: ReturnType< typeof mockMediaFrame >;

beforeEach( () => {
	media = mockMediaFrame();
} );

afterEach( () => media.restore() );

function renderPicker( props: Partial< MediaPickerProps > = {} ) {
	const onChange = vi.fn();
	const onRemove = vi.fn();
	const view = render(
		<MediaPicker
			value={ null }
			onChange={ onChange }
			onRemove={ onRemove }
			label="Choose a thumbnail"
			chooseText="Choose thumbnail"
			changeText="Change thumbnail"
			removeText="Remove thumbnail"
			invalid={ INVALID }
			{ ...props }
		/>
	);
	return { ...view, onChange, onRemove };
}

describe( 'MediaPicker', () => {
	it( 'shows the description, and the preview and remove button only with an image', () => {
		const { rerender, onChange, onRemove } = renderPicker( {
			description: 'No thumbnail selected.',
			previewSize: 96,
		} );
		expect(
			screen.getByText( 'No thumbnail selected.' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'img' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Remove thumbnail' } )
		).not.toBeInTheDocument();
		rerender(
			<MediaPicker
				value={ { id: 40, url: 'https://example.test/a-150.png' } }
				onChange={ onChange }
				onRemove={ onRemove }
				label="Choose a badge image"
				chooseText="Choose image"
				changeText="Change image"
				removeText="Remove image"
				previewSize={ 64 }
				previewAlt="Selected badge image"
				invalid={ INVALID }
			/>
		);
		const preview = screen.getByRole( 'img', {
			name: 'Selected badge image',
		} );
		expect( preview ).toHaveAttribute(
			'src',
			'https://example.test/a-150.png'
		);
		expect( preview ).toHaveAttribute( 'width', '64' );
		expect(
			screen.getByRole( 'button', { name: 'Change image' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Remove image' } )
		).toBeInTheDocument();
	} );

	it( 'shows no preview without a size or a URL, and no remove button without onRemove', () => {
		const { rerender, onChange } = renderPicker( {
			value: { id: 9, url: 'https://example.test/logo.png' },
		} );
		expect( screen.queryByRole( 'img' ) ).not.toBeInTheDocument();
		rerender(
			<MediaPicker
				value={ { id: 9, url: null } }
				onChange={ onChange }
				label="Choose a thumbnail"
				chooseText="Choose thumbnail"
				removeText="Remove thumbnail"
				previewSize={ 32 }
				invalid={ INVALID }
			/>
		);
		expect( screen.queryByRole( 'img' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Remove thumbnail' } )
		).not.toBeInTheDocument();
	} );

	it( 'opens one single-select media frame per field, limited to the accepted types', async () => {
		const user = userEvent.setup();
		renderPicker( { accept: IMAGE_MIME_TYPES } );
		const choose = screen.getByRole( 'button', {
			name: 'Choose thumbnail',
		} );
		await user.click( choose );
		expect( media.media ).toHaveBeenCalledWith( {
			title: 'Choose a thumbnail',
			button: { text: 'Use this image' },
			library: { type: IMAGE_MIME_TYPES },
			multiple: false,
		} );
		media.last().dismiss();
		await user.click( choose );
		expect( media.media ).toHaveBeenCalledTimes( 1 );
		expect( media.last().opened ).toBe( 2 );
	} );

	it( 'lists any image when no types are given', async () => {
		const user = userEvent.setup();
		renderPicker();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		expect( media.last().options.library ).toEqual( { type: 'image' } );
	} );

	it( 'opens from the keyboard and returns focus to the button when the frame closes', async () => {
		const user = userEvent.setup();
		renderPicker();
		const choose = screen.getByRole( 'button', {
			name: 'Choose thumbnail',
		} );
		choose.focus();
		await user.keyboard( '{Enter}' );
		expect( media.last().opened ).toBe( 1 );
		choose.blur();
		media.last().dismiss();
		await waitFor( () => expect( choose ).toHaveFocus() );
		await user.keyboard( ' ' );
		expect( media.last().opened ).toBe( 2 );
	} );

	it( 'passes the chosen attachment through parseMediaImage', async () => {
		const user = userEvent.setup();
		const { onChange } = renderPicker( { accept: IMAGE_MIME_TYPES } );
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		media.last().choose( attachment() );
		expect( onChange ).toHaveBeenCalledWith( {
			id: 40,
			url: 'https://example.test/a.png',
			thumbnail_url: 'https://example.test/a-150.png',
			title: 'Cover A',
		} );
		media.last().choose( attachment( { id: 41, sizes: {} } ) );
		expect( onChange ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				id: 41,
				thumbnail_url: 'https://example.test/a.png',
			} )
		);
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );

	it( 'ignores a frame closed without a choice', async () => {
		const user = userEvent.setup();
		const { onChange } = renderPicker();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		media.last().dismiss();
		media.last().trigger( 'select' );
		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( 'shows the feature message for a type it does not accept, and clears it on the next choice', async () => {
		const user = userEvent.setup();
		const { onChange } = renderPicker( { accept: IMAGE_MIME_TYPES } );
		const choose = screen.getByRole( 'button', {
			name: 'Choose thumbnail',
		} );
		await user.click( choose );
		media.last().choose( attachment( { mime: 'image/svg+xml' } ) );
		expect( onChange ).not.toHaveBeenCalled();
		const alert = screen.getByRole( 'alert' );
		expect( alert ).toHaveTextContent( INVALID );
		expect( choose ).toHaveAttribute(
			'aria-describedby',
			alert.getAttribute( 'id' )
		);
		media.last().choose( attachment() );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );

	it( 'uses an error factory for the message and rejects an attachment without an id or URL', async () => {
		const user = userEvent.setup();
		const { onChange } = renderPicker( {
			invalid: () =>
				new ApiError( {
					code: 'invalid_response',
					status: 502,
					message: 'The server returned invalid gamification data.',
				} ),
		} );
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		media.last().choose( attachment( { id: 0 } ) );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'The server returned invalid gamification data.'
		);
		media.last().choose( attachment( { url: undefined } ) );
		expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();
		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( "keeps a malformed thumbnail size out with fallback 'missing'", async () => {
		const user = userEvent.setup();
		const { onChange } = renderPicker( { fallback: 'missing' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		const malformed = attachment( { sizes: { thumbnail: { url: 5 } } } );
		media.last().choose( malformed );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( INVALID );
		media.last().choose( attachment( { sizes: undefined } ) );
		expect( onChange ).toHaveBeenCalledWith(
			expect.objectContaining( {
				thumbnail_url: 'https://example.test/a.png',
			} )
		);
	} );

	it( 'falls back to the full image for a malformed thumbnail size by default', async () => {
		const user = userEvent.setup();
		const { onChange } = renderPicker();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		media
			.last()
			.choose( attachment( { sizes: { thumbnail: { url: 5 } } } ) );
		expect( onChange ).toHaveBeenCalledWith(
			expect.objectContaining( {
				thumbnail_url: 'https://example.test/a.png',
			} )
		);
	} );

	it( 'removes the image and moves focus to the choose button', async () => {
		const user = userEvent.setup();
		const { onRemove } = renderPicker( {
			value: { id: 40, url: 'https://example.test/a-150.png' },
		} );
		await user.click(
			screen.getByRole( 'button', { name: 'Remove thumbnail' } )
		);
		expect( onRemove ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByRole( 'button', { name: 'Change thumbnail' } )
		).toHaveFocus();
	} );

	it( 'names ambiguous buttons and links the help text', () => {
		renderPicker( {
			value: { id: 3, url: 'https://site.test/book.png' },
			chooseText: 'Choose image',
			changeText: undefined,
			chooseLabel: 'Choose an image for Lesson',
			removeText: 'Use built-in',
			removeLabel: 'Use the built-in icon for Lesson',
			help: 'Square images work best.',
		} );
		const choose = screen.getByRole( 'button', {
			name: 'Choose an image for Lesson',
		} );
		expect( choose ).toHaveTextContent( 'Choose image' );
		expect( choose ).toHaveAccessibleDescription(
			'Square images work best.'
		);
		expect(
			screen.getByRole( 'button', {
				name: 'Use the built-in icon for Lesson',
			} )
		).toHaveTextContent( 'Use built-in' );
	} );

	it( 'does nothing while disabled', async () => {
		const user = userEvent.setup();
		const { onRemove } = renderPicker( {
			value: { id: 40, url: null },
			disabled: true,
		} );
		const change = screen.getByRole( 'button', {
			name: 'Change thumbnail',
		} );
		expect( change ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.click( change );
		await user.click(
			screen.getByRole( 'button', { name: 'Remove thumbnail' } )
		);
		expect( media.media ).not.toHaveBeenCalled();
		expect( onRemove ).not.toHaveBeenCalled();
	} );

	it( 'replaces the choose button with the unavailable text but still removes', async () => {
		const user = userEvent.setup();
		const { onRemove } = renderPicker( {
			value: { id: 40, url: null },
			unavailable:
				'You do not have permission to pick images from the media library.',
		} );
		expect(
			screen.queryByRole( 'button', { name: 'Change thumbnail' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByText( /do not have permission to pick images/ )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Remove thumbnail' } )
		);
		expect( onRemove ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'explains a missing media library and keeps the button disabled', () => {
		media.restore();
		renderPicker();
		const choose = screen.getByRole( 'button', {
			name: 'Choose thumbnail',
		} );
		expect( choose ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( choose ).toHaveAccessibleDescription(
			'The media library is not available on this screen.'
		);
	} );

	it( 'closes an open frame and drops its handlers on unmount', async () => {
		const user = userEvent.setup();
		const { unmount, onChange } = renderPicker();
		await user.click(
			screen.getByRole( 'button', { name: 'Choose thumbnail' } )
		);
		const frame = media.last();
		unmount();
		expect( frame.closed ).toBe( 1 );
		frame.selection = attachment();
		frame.trigger( 'select' );
		expect( onChange ).not.toHaveBeenCalled();
	} );
} );
