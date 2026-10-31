import { Button } from '@wordpress/components';
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { normalizeApiError } from '../lib/api';
import { parseMediaImage, type MediaImage } from '../lib/media';
import type { InvalidResponse } from '../lib/validation';
import './MediaPicker.css';

interface Attachment {
	toJSON: () => unknown;
}

interface MediaFrame {
	on: ( event: 'select' | 'close', callback: () => void ) => void;
	off: ( event: 'select' | 'close' ) => void;
	open: () => void;
	close: () => void;
	state: () => {
		get: ( key: 'selection' ) => { first: () => Attachment | undefined };
	};
}

type MediaFactory = ( options: {
	title: string;
	button: { text: string };
	library: { type: string | readonly string[] };
	multiple: false;
} ) => MediaFrame;

function mediaFactory(): MediaFactory | null {
	const media = ( window as unknown as { wp?: { media?: unknown } } ).wp
		?.media;
	return 'function' === typeof media ? ( media as MediaFactory ) : null;
}

function member( value: unknown, key: string ): unknown {
	return value && 'object' === typeof value
		? ( value as Record< string, unknown > )[ key ]
		: undefined;
}

/**
 * Rewrite a media frame attachment (`wp.media.model.Attachment#toJSON()`) as the `/wp/v2/media`
 * item `parseMediaImage` checks. The title is the attachment's plain-text title.
 *
 * @param attachment Attachment JSON.
 * @return The item in REST shape.
 */
function restItem( attachment: unknown ): Record< string, unknown > {
	const thumbnail = member( member( attachment, 'sizes' ), 'thumbnail' );
	return {
		id: member( attachment, 'id' ),
		source_url: member( attachment, 'url' ),
		mime_type: member( attachment, 'mime' ),
		title: { rendered: member( attachment, 'title' ) },
		media_details:
			undefined === thumbnail
				? {}
				: {
						sizes: {
							thumbnail: {
								source_url: member( thumbnail, 'url' ),
							},
						},
					},
	};
}

/** The stored image; an `id` of 0 (or `null`) means none. */
export interface MediaPickerValue {
	id: number;
	url: string | null;
}

export interface MediaPickerProps {
	/** Current image, or `null`. */
	value: MediaPickerValue | null;
	/** Receives the chosen image after `parseMediaImage` accepted it. */
	onChange: ( image: MediaImage ) => void;
	/** Clears the image; without it there is no remove button. */
	onRemove?: () => void;
	/** Title of the media frame. */
	label: string;
	/** Text of the choose button. */
	chooseText: string;
	/** Text of the choose button while an image is set; defaults to `chooseText`. */
	changeText?: string;
	/** Accessible name of the choose button when its text alone is ambiguous. */
	chooseLabel?: string;
	/** Text of the remove button. */
	removeText?: string;
	/** Accessible name of the remove button when its text alone is ambiguous. */
	removeLabel?: string;
	/** Style the remove button as destructive. */
	destructiveRemove?: boolean;
	/** Text beside the preview, such as "Image #12" or "No image selected.". */
	description?: ReactNode;
	/** Width and height of the preview in pixels; 0 shows none. */
	previewSize?: number;
	/** Preview `alt`; leave empty when `description` already names the image. */
	previewAlt?: string;
	/** Help below the buttons. */
	help?: ReactNode;
	disabled?: boolean;
	/** Shown instead of the choose button when the user may not pick (removing still works). */
	unavailable?: string;
	/** Accepted mime types; omit for any image. */
	accept?: readonly string[];
	/** Thumbnail fallback rule of `parseMediaImage`. */
	fallback?: 'unusable' | 'missing';
	/** The feature's message (or error factory) for an attachment `parseMediaImage` rejects. */
	invalid: InvalidResponse;
}

/**
 * An image field on the WordPress media frame (`wp.media`, which the app screen enqueues): preview,
 * choose or change, and remove. The choice is checked with `parseMediaImage` before `onChange`;
 * a rejected attachment shows the feature's `invalid` message. Focus returns to the choose button
 * when the frame closes or the image is removed.
 *
 * @param props Component props.
 */
export function MediaPicker( props: MediaPickerProps ) {
	const {
		value,
		onChange,
		onRemove,
		label,
		chooseText,
		changeText,
		chooseLabel,
		removeText,
		removeLabel,
		destructiveRemove = false,
		description,
		previewSize = 0,
		previewAlt = '',
		help,
		disabled = false,
		unavailable,
		accept,
		fallback,
		invalid,
	} = props;
	const id = useId();
	const chooseButton = useRef< HTMLButtonElement >( null );
	const frame = useRef< { key: string; frame: MediaFrame } | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const parse = useRef( { onChange, accept, fallback, invalid } );
	parse.current = { onChange, accept, fallback, invalid };
	const missing = ! unavailable && null === mediaFactory();
	const chosen = Boolean( value && value.id > 0 );
	const missingId = `${ id }-missing`;
	const helpId = `${ id }-help`;
	const errorId = `${ id }-error`;
	const describedBy =
		[ missing ? missingId : '', help ? helpId : '', error ? errorId : '' ]
			.filter( Boolean )
			.join( ' ' ) || undefined;

	useEffect(
		() => () => {
			const current = frame.current?.frame;
			if ( current ) {
				current.off( 'select' );
				current.off( 'close' );
				current.close();
			}
		},
		[]
	);

	function select( current: MediaFrame ) {
		const attachment = current.state().get( 'selection' ).first();
		if ( ! attachment ) {
			return;
		}
		const options = parse.current;
		let image: MediaImage;
		try {
			image = parseMediaImage( restItem( attachment.toJSON() ), {
				invalid: options.invalid,
				accept: options.accept,
				fallback: options.fallback,
			} );
		} catch ( failure ) {
			setError( normalizeApiError( failure ).message );
			return;
		}
		setError( null );
		options.onChange( image );
	}

	function open() {
		const create = mediaFactory();
		if ( ! create ) {
			return;
		}
		const type = accept ?? 'image';
		const key = JSON.stringify( [ label, type ] );
		if ( frame.current?.key !== key ) {
			const created = create( {
				title: label,
				button: { text: __( 'Use this image', 'ohmylms' ) },
				library: { type },
				multiple: false,
			} );
			created.on( 'select', () => select( created ) );
			created.on( 'close', () => {
				// The frame closes before it triggers `select`; focus the button after both.
				window.setTimeout( () => chooseButton.current?.focus(), 0 );
			} );
			frame.current = { key, frame: created };
		}
		frame.current.frame.open();
	}

	function remove() {
		setError( null );
		onRemove?.();
		chooseButton.current?.focus();
	}

	return (
		<div className="ohmylms-media-picker">
			{ chosen && value?.url && previewSize > 0 && (
				<img
					className="ohmylms-media-picker-preview"
					src={ value.url }
					alt={ previewAlt }
					width={ previewSize }
					height={ previewSize }
				/>
			) }
			{ description && (
				<span className="ohmylms-media-picker-description">
					{ description }
				</span>
			) }
			<span className="ohmylms-media-picker-actions">
				{ unavailable ? (
					<span>{ unavailable }</span>
				) : (
					<Button
						ref={ chooseButton }
						variant="secondary"
						disabled={ disabled || missing }
						accessibleWhenDisabled
						aria-label={ chooseLabel }
						aria-describedby={ describedBy }
						onClick={ open }
					>
						{ chosen ? ( changeText ?? chooseText ) : chooseText }
					</Button>
				) }
				{ chosen && onRemove && removeText && (
					<Button
						variant="tertiary"
						isDestructive={ destructiveRemove }
						disabled={ disabled }
						accessibleWhenDisabled
						aria-label={ removeLabel }
						onClick={ remove }
					>
						{ removeText }
					</Button>
				) }
			</span>
			{ missing && (
				<span id={ missingId } className="ohmylms-media-picker-help">
					{ __(
						'The media library is not available on this screen.',
						'ohmylms'
					) }
				</span>
			) }
			{ help && (
				<span id={ helpId } className="ohmylms-media-picker-help">
					{ help }
				</span>
			) }
			{ error && (
				<span
					id={ errorId }
					role="alert"
					className="ohmylms-media-picker-error"
				>
					{ error }
				</span>
			) }
		</div>
	);
}
