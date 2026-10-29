import { createElement, useEffect, useState } from '@wordpress/element';
import { DateRangeField } from './DateRangeField.mjs';

/**
 * Date-range picker: a button showing the range as `DD/MM/YYYY - DD/MM/YYYY`,
 * a menu of presets (today, this week, last 7 days, this month) and a calendar
 * popover. The range follows `initialStartDate` and `initialEndDate` whenever
 * they change.
 *
 * @param {Object}                                props
 * @param {unknown}                               [props.initialStartDate] Range start; a `YYYY-MM-DD` string, Date, or empty.
 * @param {unknown}                               [props.initialEndDate]   Range end.
 * @param {( range: Array<string|null> ) => void} [props.onChange]         Called with `[ start, end ]` as `YYYY-MM-DD`
 *                                                                         strings, or `[ null, null ]` when cleared.
 * @return {import('react').ReactElement} Date-range picker.
 */
export function DateRangePickerWP( {
	onChange,
	initialStartDate,
	initialEndDate,
} ) {
	const [ start, setStart ] = useState( initialStartDate );
	const [ end, setEnd ] = useState( initialEndDate );

	useEffect( () => {
		setStart( initialStartDate );
		setEnd( initialEndDate );
	}, [ initialStartDate, initialEndDate ] );

	return createElement( DateRangeField, {
		initialStartDate: start,
		initialEndDate: end,
		onChange: ( range ) => {
			setStart( range[ 0 ] );
			setEnd( range[ 1 ] );
			if ( onChange ) {
				onChange( range );
			}
		},
	} );
}
