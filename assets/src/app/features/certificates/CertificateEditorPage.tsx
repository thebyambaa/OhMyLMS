import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { normalizeApiError } from '../../lib/api';
import { previewCertificate, updateCertificate } from './api';
import { BlockEditor } from './BlockEditor';
import { CourseSelector } from './CourseSelector';
import { MediaPicker } from './MediaPicker';
import {
	formFromCertificate,
	isDirty,
	isValid,
	moveBlock,
	newBlock,
	serverFieldError,
	toInput,
	validateForm,
} from './model';
import { useCertificateEditor } from './useCertificateEditor';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import type {
	BlockForm,
	FormErrors,
	FormState,
	MediaImage,
	Preview,
} from './types';

type PickerTarget = 'background' | 'signature';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * Certificate template editor (`#/certificates/<id>`): details, images, text blocks, courses and a
 * server-rendered preview. Edits survive failed saves, and leaving with unsaved changes asks first.
 */
export default function CertificateEditorPage() {
	const params = useParams();
	const id = routeId( params.id );
	const editor = useCertificateEditor( id );
	const [ form, setForm ] = useState< FormState | null >( null );
	const [ saved, setSaved ] = useState< FormState | null >( null );
	const [ errors, setErrors ] = useState< FormErrors >( {} );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ picker, setPicker ] = useState< PickerTarget | null >( null );
	const [ preview, setPreview ] = useState< Preview | null >( null );
	const [ previewBusy, setPreviewBusy ] = useState( false );
	const [ previewError, setPreviewError ] = useState< string | null >( null );
	const summary = useRef< HTMLDivElement >( null );
	const operation = useRef< AbortController | null >( null );
	const previewOperation = useRef< AbortController | null >( null );
	const dirty = Boolean( form && saved && isDirty( form, saved ) );
	const guard = useUnsavedChangesGuard( dirty );
	const certificate = editor.certificate;
	const options = editor.options;

	useEffect(
		() => () => {
			operation.current?.abort();
			previewOperation.current?.abort();
		},
		[]
	);

	useEffect( () => {
		if ( certificate && options ) {
			const next = formFromCertificate( certificate );
			setForm( next );
			setSaved( next );
			setErrors( {} );
			void refreshPreview( next, options, true );
		}
		// The initial preview runs only when a template (re)loads.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ certificate, options ] );

	async function refreshPreview(
		source: FormState,
		choices: NonNullable< typeof options >,
		quiet = false
	) {
		const problems = validateForm( source, choices );
		previewOperation.current?.abort();
		if ( ! isValid( problems ) ) {
			if ( ! quiet ) {
				setErrors( problems );
				setPreviewError(
					__(
						'Fix the highlighted fields to refresh the preview.',
						'ohmylms'
					)
				);
			}
			return;
		}
		const controller = new AbortController();
		previewOperation.current = controller;
		setPreviewBusy( true );
		setPreviewError( null );
		try {
			const next = await previewCertificate(
				toInput( source ),
				controller.signal
			);
			if ( ! controller.signal.aborted ) {
				setPreview( next );
			}
		} catch ( problem ) {
			if ( ! controller.signal.aborted ) {
				const error = normalizeApiError( problem );
				const mapped = serverFieldError( error );
				if ( mapped ) {
					setErrors( ( current ) => ( {
						...current,
						[ mapped.path ]: mapped.message,
					} ) );
				}
				setPreviewError( error.message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setPreviewBusy( false );
			}
		}
	}

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Certificate template', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __(
						'This certificate template link is invalid.',
						'ohmylms'
					) }
				</Notice>
				<Link to="/certificates">
					{ __( 'Back to certificate templates', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	if ( editor.loading && ! form ) {
		return (
			<>
				<h2>{ __( 'Certificate template', 'ohmylms' ) }</h2>
				<p role="status">
					{ __( 'Loading certificate template…', 'ohmylms' ) }
				</p>
			</>
		);
	}
	if ( editor.error || ! form || ! saved || ! options || ! certificate ) {
		return (
			<>
				<h2>{ __( 'Certificate template', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ editor.error?.message ??
						__(
							'The certificate template could not be loaded.',
							'ohmylms'
						) }
				</Notice>
				<p>
					{ ( ! editor.error ||
						( 403 !== editor.error.status &&
							404 !== editor.error.status ) ) && (
						<>
							<Button
								variant="secondary"
								onClick={ editor.reload }
							>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>{ ' ' }
						</>
					) }
					<Link to="/certificates">
						{ __( 'Back to certificate templates', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	const update = < K extends keyof FormState >(
		key: K,
		value: FormState[ K ]
	) => {
		setForm( ( current ) =>
			current ? { ...current, [ key ]: value } : current
		);
		setMessage( null );
	};
	const updateBlock = ( blockId: string, patch: Partial< BlockForm > ) => {
		setForm( ( current ) =>
			current
				? {
						...current,
						blocks: current.blocks.map( ( block ) =>
							block.id === blockId
								? { ...block, ...patch }
								: block
						),
					}
				: current
		);
		setMessage( null );
	};
	const selectImage = ( image: MediaImage ) => {
		if ( 'background' === picker ) {
			setForm( ( current ) =>
				current
					? {
							...current,
							background_id: image.id,
							background_url: image.url,
						}
					: current
			);
		} else if ( 'signature' === picker ) {
			setForm( ( current ) =>
				current
					? {
							...current,
							signature_image_id: image.id,
							signature_url: image.url,
						}
					: current
			);
		}
		setPicker( null );
	};

	async function save() {
		if ( ! form || ! options || operation.current ) {
			return;
		}
		const problems = validateForm( form, options );
		setErrors( problems );
		setMessage( null );
		setFailure( null );
		if ( ! isValid( problems ) ) {
			setFailure(
				__( 'Fix the highlighted fields before saving.', 'ohmylms' )
			);
			summary.current?.focus();
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		try {
			const result = await updateCertificate(
				certificate?.id ?? 0,
				toInput( form ),
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return;
			}
			editor.setCertificate( result );
			setMessage( __( 'Template saved.', 'ohmylms' ) );
		} catch ( problem ) {
			if ( ! controller.signal.aborted ) {
				const error = normalizeApiError( problem );
				const mapped = serverFieldError( error );
				if ( mapped ) {
					setErrors( { [ mapped.path ]: mapped.message } );
				}
				setFailure( error.message );
				summary.current?.focus();
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}

	const sizeLabel = (
		options.sizes.find( ( size ) => size.value === form.size )?.label ??
		form.size
	).toString();

	return (
		<>
			<p>
				<Link to="/certificates">
					{ __( 'Back to certificate templates', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: certificate template name. */
					__( 'Edit certificate template: %s', 'ohmylms' ),
					saved.name
				) }
			</h2>
			{ dirty && (
				<p role="status">
					{ __( 'You have unsaved changes.', 'ohmylms' ) }
				</p>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			<div ref={ summary } tabIndex={ -1 }>
				{ failure && (
					<Notice status="error" isDismissible={ false }>
						{ failure }
					</Notice>
				) }
			</div>
			<form
				aria-label={ __( 'Certificate template', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					void save();
				} }
			>
				<fieldset disabled={ busy }>
					<legend>{ __( 'Details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ form.name }
						maxLength={ 200 }
						required
						aria-invalid={ errors.name ? true : undefined }
						aria-describedby={
							errors.name ? 'oml-cert-name-error' : undefined
						}
						onChange={ ( next ) => update( 'name', next ) }
					/>
					{ errors.name && (
						<p id="oml-cert-name-error" role="alert">
							{ errors.name }
						</p>
					) }
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ form.status }
						options={ options.statuses }
						help={ __(
							'Draft templates are kept private; published templates can be issued to learners.',
							'ohmylms'
						) }
						onChange={ ( next ) =>
							update(
								'status',
								'publish' === next ? 'publish' : 'draft'
							)
						}
					/>
					<SelectControl
						label={ __( 'Orientation', 'ohmylms' ) }
						value={ form.orientation }
						options={ options.orientations }
						onChange={ ( next ) =>
							update(
								'orientation',
								'portrait' === next ? 'portrait' : 'landscape'
							)
						}
					/>
					<SelectControl
						label={ __( 'Page size', 'ohmylms' ) }
						value={ form.size }
						options={ options.sizes.map( ( size ) => ( {
							value: size.value,
							label: size.label,
						} ) ) }
						onChange={ ( next ) => update( 'size', next ) }
					/>
					<SelectControl
						label={ __( 'Font', 'ohmylms' ) }
						value={ form.font_family }
						options={ options.font_families }
						onChange={ ( next ) => update( 'font_family', next ) }
					/>
					<TextControl
						label={ __( 'Background color', 'ohmylms' ) }
						value={ form.background_color }
						maxLength={ 7 }
						aria-invalid={
							errors.background_color ? true : undefined
						}
						aria-describedby={
							errors.background_color
								? 'oml-cert-color-error'
								: undefined
						}
						onChange={ ( next ) =>
							update( 'background_color', next )
						}
					/>
					{ errors.background_color && (
						<p id="oml-cert-color-error" role="alert">
							{ errors.background_color }
						</p>
					) }
					{ errors.layout && <p role="alert">{ errors.layout }</p> }
				</fieldset>

				<fieldset disabled={ busy }>
					<legend>{ __( 'Images', 'ohmylms' ) }</legend>
					<p>
						{ form.background_id
							? sprintf(
									/* translators: %d: image id. */
									__( 'Background image: #%d', 'ohmylms' ),
									form.background_id
								)
							: __( 'No background image.', 'ohmylms' ) }{ ' ' }
						<Button
							variant="secondary"
							onClick={ () => setPicker( 'background' ) }
						>
							{ __( 'Choose background image', 'ohmylms' ) }
						</Button>{ ' ' }
						{ form.background_id > 0 && (
							<Button
								variant="tertiary"
								onClick={ () => {
									update( 'background_id', 0 );
									update( 'background_url', '' );
								} }
							>
								{ __( 'Remove background image', 'ohmylms' ) }
							</Button>
						) }
					</p>
					<p>
						{ form.signature_image_id
							? sprintf(
									/* translators: %d: image id. */
									__( 'Signature image: #%d', 'ohmylms' ),
									form.signature_image_id
								)
							: __( 'No signature image.', 'ohmylms' ) }{ ' ' }
						<Button
							variant="secondary"
							onClick={ () => setPicker( 'signature' ) }
						>
							{ __( 'Choose signature image', 'ohmylms' ) }
						</Button>{ ' ' }
						{ form.signature_image_id > 0 && (
							<Button
								variant="tertiary"
								onClick={ () => {
									update( 'signature_image_id', 0 );
									update( 'signature_url', '' );
								} }
							>
								{ __( 'Remove signature image', 'ohmylms' ) }
							</Button>
						) }
					</p>
					<TextControl
						label={ __( 'Signature left (% of page)', 'ohmylms' ) }
						type="number"
						step="0.01"
						value={ form.signature_x }
						aria-invalid={ errors.signature ? true : undefined }
						aria-describedby={
							errors.signature
								? 'oml-cert-signature-error'
								: undefined
						}
						onChange={ ( next ) => update( 'signature_x', next ) }
					/>
					<TextControl
						label={ __( 'Signature top (% of page)', 'ohmylms' ) }
						type="number"
						step="0.01"
						value={ form.signature_y }
						aria-invalid={ errors.signature ? true : undefined }
						aria-describedby={
							errors.signature
								? 'oml-cert-signature-error'
								: undefined
						}
						onChange={ ( next ) => update( 'signature_y', next ) }
					/>
					<TextControl
						label={ __( 'Signature width (% of page)', 'ohmylms' ) }
						type="number"
						step="0.01"
						value={ form.signature_width }
						aria-invalid={ errors.signature ? true : undefined }
						aria-describedby={
							errors.signature
								? 'oml-cert-signature-error'
								: undefined
						}
						onChange={ ( next ) =>
							update( 'signature_width', next )
						}
					/>
					{ errors.signature && (
						<p id="oml-cert-signature-error" role="alert">
							{ errors.signature }
						</p>
					) }
				</fieldset>

				<h3>{ __( 'Text blocks', 'ohmylms' ) }</h3>
				<p>
					{ sprintf(
						/* translators: 1: page size label, 2: placeholder list. */
						__(
							'Positions are percentages of the %1$s page. Available placeholders: %2$s.',
							'ohmylms'
						),
						sizeLabel,
						options.placeholders
							.map( ( item ) => item.value )
							.join( ', ' )
					) }
				</p>
				{ errors.blocks && <p role="alert">{ errors.blocks }</p> }
				{ form.blocks.map( ( block, index ) => (
					<BlockEditor
						key={ block.id }
						block={ block }
						index={ index }
						count={ form.blocks.length }
						errors={ errors }
						placeholders={ options.placeholders }
						alignments={ options.alignments }
						limits={ options.limits }
						disabled={ busy }
						onChange={ ( patch ) => updateBlock( block.id, patch ) }
						onMove={ ( delta ) =>
							update(
								'blocks',
								moveBlock( form.blocks, index, delta )
							)
						}
						onRemove={ () =>
							update(
								'blocks',
								form.blocks.filter(
									( item ) => item.id !== block.id
								)
							)
						}
					/>
				) ) }
				<p>
					<Button
						variant="secondary"
						disabled={
							busy ||
							form.blocks.length >= options.limits.max_blocks
						}
						onClick={ () =>
							update( 'blocks', [
								...form.blocks,
								newBlock( form.blocks ),
							] )
						}
					>
						{ __( 'Add text block', 'ohmylms' ) }
					</Button>
				</p>

				<CourseSelector
					selected={ form.courses }
					disabled={ busy }
					max={ options.limits.max_courses }
					error={ errors.courses }
					onChange={ ( courses ) => update( 'courses', courses ) }
				/>

				<p>
					<Button
						type="submit"
						variant="primary"
						disabled={ busy }
						isBusy={ busy }
					>
						{ __( 'Save template', 'ohmylms' ) }
					</Button>
				</p>
			</form>

			<h3>{ __( 'Preview', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'The preview is rendered by the server from the values above, with sample student, course and date.',
					'ohmylms'
				) }
			</p>
			<Button
				variant="secondary"
				disabled={ previewBusy }
				isBusy={ previewBusy }
				onClick={ () => void refreshPreview( form, options ) }
			>
				{ __( 'Refresh preview', 'ohmylms' ) }
			</Button>
			{ previewError && (
				<Notice status="error" isDismissible={ false }>
					{ previewError }
				</Notice>
			) }
			{ preview && (
				<iframe
					title={ __( 'Certificate preview', 'ohmylms' ) }
					sandbox=""
					srcDoc={ preview.html }
					className="ohmylms-certificate-preview"
					style={ {
						aspectRatio: `${ preview.width_mm } / ${ preview.height_mm }`,
					} }
				/>
			) }

			{ picker && (
				<MediaPicker
					title={
						'background' === picker
							? __( 'Choose background image', 'ohmylms' )
							: __( 'Choose signature image', 'ohmylms' )
					}
					onSelect={ selectImage }
					onClose={ () => setPicker( null ) }
				/>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Your changes to this certificate template have not been saved.',
							'ohmylms'
						) }
					</p>
					<Button variant="primary" onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						isDestructive
						onClick={ guard.leave }
					>
						{ __( 'Discard changes', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
