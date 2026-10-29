import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useId } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { appendPlaceholder } from './model';
import type {
	BlockForm,
	FormErrors,
	Limits,
	Option,
	Placeholder,
	TextAlign,
} from './types';

interface Props {
	block: BlockForm;
	index: number;
	count: number;
	errors: FormErrors;
	placeholders: Placeholder[];
	alignments: Option[];
	limits: Limits;
	disabled: boolean;
	onChange: ( patch: Partial< BlockForm > ) => void;
	onMove: ( delta: -1 | 1 ) => void;
	onRemove: () => void;
}

const ALIGNMENTS: readonly TextAlign[] = [ 'left', 'center', 'right' ];

// Editor for one text block. Positions are numeric fields (percent of the page), not a canvas.
export function BlockEditor( {
	block,
	index,
	count,
	errors,
	placeholders,
	alignments,
	limits,
	disabled,
	onChange,
	onMove,
	onRemove,
}: Props ) {
	const instance = useId();
	const number = index + 1;
	const base = `blocks.${ block.id }`;
	const textError = errors[ `${ base }.text` ];
	const textErrorId = `${ instance }-text-error`;
	const layoutError = errors[ `${ base }.layout` ];
	const layoutErrorId = `${ instance }-layout-error`;
	const sizeError = errors[ `${ base }.font_size` ];
	const sizeErrorId = `${ instance }-size-error`;
	const colorError = errors[ `${ base }.color` ];
	const colorErrorId = `${ instance }-color-error`;
	const numberField = ( key: 'x' | 'y' | 'width', label: string ) => (
		<TextControl
			label={ label }
			type="number"
			step="0.01"
			value={ block[ key ] }
			aria-invalid={ layoutError ? true : undefined }
			aria-describedby={ layoutError ? layoutErrorId : undefined }
			onChange={ ( next ) => onChange( { [ key ]: next } ) }
		/>
	);
	return (
		<fieldset disabled={ disabled }>
			<legend>
				{ sprintf(
					/* translators: %d: text block number. */
					__( 'Text block %d', 'ohmylms' ),
					number
				) }
			</legend>
			<TextareaControl
				label={ __( 'Text', 'ohmylms' ) }
				value={ block.text }
				rows={ 2 }
				aria-invalid={ textError ? true : undefined }
				aria-describedby={ textError ? textErrorId : undefined }
				onChange={ ( next ) => onChange( { text: next } ) }
				help={ sprintf(
					/* translators: %d: maximum characters. */
					__(
						'Plain text, up to %d characters. Placeholders are replaced when a certificate is issued.',
						'ohmylms'
					),
					limits.max_text
				) }
			/>
			{ textError && (
				<p id={ textErrorId } role="alert">
					{ textError }
				</p>
			) }
			<div
				role="group"
				aria-label={ sprintf(
					/* translators: %d: text block number. */
					__( 'Insert placeholder into text block %d', 'ohmylms' ),
					number
				) }
			>
				{ placeholders.map( ( item ) => (
					<Button
						key={ item.value }
						variant="tertiary"
						size="small"
						onClick={ () =>
							onChange( {
								text: appendPlaceholder(
									block.text,
									item.value
								),
							} )
						}
						aria-label={ sprintf(
							/* translators: 1: placeholder label, 2: text block number. */
							__( 'Insert %1$s into text block %2$d', 'ohmylms' ),
							item.label,
							number
						) }
					>
						{ item.value }
					</Button>
				) ) }
			</div>
			{ numberField( 'x', __( 'Left (% of page)', 'ohmylms' ) ) }
			{ numberField( 'y', __( 'Top (% of page)', 'ohmylms' ) ) }
			{ numberField( 'width', __( 'Width (% of page)', 'ohmylms' ) ) }
			{ layoutError && (
				<p id={ layoutErrorId } role="alert">
					{ layoutError }
				</p>
			) }
			<TextControl
				label={ __( 'Font size (pt)', 'ohmylms' ) }
				type="number"
				step="0.5"
				value={ block.font_size }
				aria-invalid={ sizeError ? true : undefined }
				aria-describedby={ sizeError ? sizeErrorId : undefined }
				onChange={ ( next ) => onChange( { font_size: next } ) }
			/>
			{ sizeError && (
				<p id={ sizeErrorId } role="alert">
					{ sizeError }
				</p>
			) }
			<SelectControl
				label={ __( 'Alignment', 'ohmylms' ) }
				value={ block.align }
				options={ alignments }
				onChange={ ( next ) => {
					const align = ALIGNMENTS.find( ( item ) => item === next );
					if ( align ) {
						onChange( { align } );
					}
				} }
			/>
			<TextControl
				label={ __( 'Color', 'ohmylms' ) }
				value={ block.color }
				maxLength={ 7 }
				aria-invalid={ colorError ? true : undefined }
				aria-describedby={ colorError ? colorErrorId : undefined }
				onChange={ ( next ) => onChange( { color: next } ) }
			/>
			{ colorError && (
				<p id={ colorErrorId } role="alert">
					{ colorError }
				</p>
			) }
			<CheckboxControl
				label={ __( 'Bold', 'ohmylms' ) }
				checked={ block.bold }
				onChange={ ( next ) => onChange( { bold: next } ) }
			/>
			<p>
				<Button
					variant="tertiary"
					disabled={ 0 === index }
					onClick={ () => onMove( -1 ) }
					aria-label={ sprintf(
						/* translators: %d: text block number. */
						__( 'Move text block %d up', 'ohmylms' ),
						number
					) }
				>
					{ __( 'Move up', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ index === count - 1 }
					onClick={ () => onMove( 1 ) }
					aria-label={ sprintf(
						/* translators: %d: text block number. */
						__( 'Move text block %d down', 'ohmylms' ),
						number
					) }
				>
					{ __( 'Move down', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					isDestructive
					onClick={ onRemove }
					aria-label={ sprintf(
						/* translators: %d: text block number. */
						__( 'Remove text block %d', 'ohmylms' ),
						number
					) }
				>
					{ __( 'Remove', 'ohmylms' ) }
				</Button>
			</p>
		</fieldset>
	);
}
