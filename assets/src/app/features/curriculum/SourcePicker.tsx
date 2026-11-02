import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { MAX_SOURCES, type CompareSource } from './compare';

/**
 * Source checkboxes, at most {@link MAX_SOURCES}.
 *
 * @param props          Component props.
 * @param props.legend   Group legend.
 * @param props.sources  Selectable syllabuses and roadmaps.
 * @param props.selected Selected IDs, in order.
 * @param props.onChange Receives the new selection.
 * @param props.exclude  An ID that may not be chosen (a roadmap cannot be its own source).
 */
export function SourcePicker( {
	legend,
	sources,
	selected,
	onChange,
	exclude = 0,
}: {
	legend: string;
	sources: readonly CompareSource[];
	selected: readonly number[];
	onChange: ( ids: number[] ) => void;
	exclude?: number;
} ) {
	return (
		<fieldset>
			<legend>{ legend }</legend>
			{ sources
				.filter( ( source ) => source.id !== exclude )
				.map( ( source ) => {
					const checked = selected.includes( source.id );
					return (
						<CheckboxControl
							key={ source.id }
							label={ [
								source.name,
								source.version,
								'roadmap' === source.item_type
									? __( '(roadmap)', 'ohmylms' )
									: '',
							]
								.filter( Boolean )
								.join( ' ' ) }
							checked={ checked }
							disabled={
								! checked && selected.length >= MAX_SOURCES
							}
							onChange={ ( value ) =>
								onChange(
									value
										? [ ...selected, source.id ]
										: selected.filter(
												( id ) => id !== source.id
											)
								)
							}
						/>
					);
				} ) }
		</fieldset>
	);
}
