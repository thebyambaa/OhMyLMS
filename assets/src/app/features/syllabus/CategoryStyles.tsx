import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import icons from '../../../features/curriculum/icons.json';
import { DEFAULT_CATEGORY_COLOR, HEX_COLOR, type CategoryStyle } from './model';

export type Styles = Record< string, CategoryStyle >;

/**
 * Icon and colour per skill category. Icons come from the catalogue `Curriculum\Icons` validates;
 * a category without a style uses the default icon.
 *
 * @param props            Control properties.
 * @param props.categories Categories being edited, in order.
 * @param props.styles     Styles by category.
 * @param props.onChange   Replace the styles.
 */
export function CategoryStyles( {
	categories,
	styles,
	onChange,
}: {
	categories: string[];
	styles: Styles;
	onChange: ( styles: Styles ) => void;
} ) {
	const set = ( category: string, style: Partial< CategoryStyle > ) =>
		onChange( {
			...styles,
			[ category ]: {
				icon: '',
				color: DEFAULT_CATEGORY_COLOR,
				...styles[ category ],
				...style,
			},
		} );
	if ( ! categories.length ) {
		return null;
	}
	return (
		<fieldset>
			<legend>{ __( 'Category styles', 'ohmylms' ) }</legend>
			{ categories.map( ( category ) => {
				const style = styles[ category ];
				const invalid = !! style && ! HEX_COLOR.test( style.color );
				return (
					<div key={ category }>
						<h4>
							{ style?.icon && (
								<span
									aria-hidden="true"
									className={ `dashicons dashicons-${ style.icon }` }
									style={
										invalid
											? undefined
											: { color: style.color }
									}
								/>
							) }{ ' ' }
							{ category }
						</h4>
						<SelectControl
							label={ sprintf(
								/* translators: %s: skill category. */
								__( 'Icon for %s', 'ohmylms' ),
								category
							) }
							value={ style?.icon ?? '' }
							options={ [
								{
									value: '',
									label: __( 'Default icon', 'ohmylms' ),
								},
								...icons.map( ( icon ) => ( {
									value: icon.id,
									label: icon.label,
								} ) ),
							] }
							onChange={ ( icon ) => set( category, { icon } ) }
						/>
						<TextControl
							label={ sprintf(
								/* translators: %s: skill category. */
								__( 'Colour for %s', 'ohmylms' ),
								category
							) }
							value={ style?.color ?? DEFAULT_CATEGORY_COLOR }
							maxLength={ 7 }
							aria-invalid={ invalid || undefined }
							onChange={ ( color ) => set( category, { color } ) }
						/>
						<input
							type="color"
							aria-label={ sprintf(
								/* translators: %s: skill category. */
								__( 'Pick the colour for %s', 'ohmylms' ),
								category
							) }
							value={
								style && ! invalid
									? style.color.toLowerCase()
									: DEFAULT_CATEGORY_COLOR
							}
							onChange={ ( event ) =>
								set( category, { color: event.target.value } )
							}
						/>
						{ invalid && (
							<p role="alert">
								{ __(
									'Enter # and six hexadecimal digits, or use the picker.',
									'ohmylms'
								) }
							</p>
						) }
						{ style && (
							<Button
								variant="link"
								onClick={ () => {
									const next = { ...styles };
									delete next[ category ];
									onChange( next );
								} }
							>
								{ sprintf(
									/* translators: %s: skill category. */
									__(
										'Use the default style for %s',
										'ohmylms'
									),
									category
								) }
							</Button>
						) }
					</div>
				);
			} ) }
		</fieldset>
	);
}
