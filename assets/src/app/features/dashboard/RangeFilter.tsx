import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	MAX_SPAN_DAYS,
	RANGE_PRESETS,
	presetLabel,
	validateCustomRange,
} from './model';
import type { RangePreset, RangeSelection } from './types';

/**
 * Date range filter: a preset select that applies at once and, for a custom range, two date fields
 * with an explicit Apply button (Enter in a field applies too). Everything is reachable by keyboard.
 *
 * @param props          Component props.
 * @param props.value    Applied range.
 * @param props.onChange Called with a valid range.
 */
export function RangeFilter( {
	value,
	onChange,
}: {
	value: RangeSelection;
	onChange: ( range: RangeSelection ) => void;
} ) {
	const [ preset, setPreset ] = useState< RangePreset >( value.preset );
	const [ start, setStart ] = useState( value.start );
	const [ end, setEnd ] = useState( value.end );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		setPreset( value.preset );
		setStart( value.start );
		setEnd( value.end );
		setError( null );
	}, [ value.preset, value.start, value.end ] );

	const choosePreset = ( next: string ) => {
		const found = RANGE_PRESETS.find( ( option ) => option === next );
		if ( ! found ) {
			return;
		}
		setPreset( found );
		setError( null );
		if ( 'custom' !== found ) {
			onChange( { preset: found, start: '', end: '' } );
		}
	};

	return (
		<form
			aria-label={ __( 'Date range', 'ohmylms' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( 'custom' !== preset ) {
					return;
				}
				const problem = validateCustomRange( start, end );
				setError( problem );
				if ( ! problem ) {
					onChange( { preset: 'custom', start, end } );
				}
			} }
		>
			<SelectControl
				label={ __( 'Date range', 'ohmylms' ) }
				value={ preset }
				options={ RANGE_PRESETS.map( ( option ) => ( {
					value: option,
					label: presetLabel( option ),
				} ) ) }
				onChange={ choosePreset }
			/>
			{ 'custom' === preset && (
				<fieldset>
					<legend>{ __( 'Custom range', 'ohmylms' ) }</legend>
					<TextControl
						type="date"
						label={ __( 'Start date', 'ohmylms' ) }
						value={ start }
						onChange={ setStart }
					/>
					<TextControl
						type="date"
						label={ __( 'End date', 'ohmylms' ) }
						value={ end }
						help={ sprintf(
							/* translators: %d: maximum number of days. */
							__( 'At most %d days.', 'ohmylms' ),
							MAX_SPAN_DAYS
						) }
						onChange={ setEnd }
					/>
					{ error && <p role="alert">{ error }</p> }
					<Button type="submit" variant="secondary">
						{ __( 'Apply range', 'ohmylms' ) }
					</Button>
				</fieldset>
			) }
		</form>
	);
}
