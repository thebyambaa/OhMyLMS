import { DatePicker } from '@wordpress/components';
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ButtonWP } from '../actions/ButtonWP.mjs';
import { CardWP } from '../layout/CardWP.mjs';
import { SpacerWP } from '../layout/SpacerWP.mjs';
import { watchCalendar } from './date-range-dom.mjs';
import { pickDay } from './date-range-model.mjs';

/**
 * Calendar of the date-range picker. The first click sets the start, the
 * second completes the range (ordered by time) and a click on a complete range
 * starts a new one. Days between the endpoints, and the days between the start
 * and the hovered day, are highlighted.
 *
 * @param {Object}                                                          props
 * @param {unknown}                                                         props.initialStartDate Range start the calendar opens with.
 * @param {unknown}                                                         props.initialEndDate   Range end the calendar opens with.
 * @param {( selection: { startDate: unknown, endDate: unknown } ) => void} props.onSelect         Called with `{ startDate, endDate }`
 *                                                                                                 once the range is complete, or with
 *                                                                                                 both set to `null` by "Clear".
 * @return {import('react').ReactElement} Calendar.
 */
export function DateRangeCalendar( {
	initialStartDate,
	initialEndDate,
	onSelect,
} ) {
	const [ start, setStart ] = useState( initialStartDate );
	const [ end, setEnd ] = useState( initialEndDate );
	const [ hover, setHover ] = useState( null );

	useEffect(
		() => watchCalendar( document, start, end, hover, setHover ),
		[ start, end, hover ]
	);

	return createElement(
		CardWP,
		{ isBorderless: true, padding: '16px' },
		createElement( DatePicker, {
			currentDate: start,
			onChange: ( picked ) => {
				const result = pickDay( start, end, picked );
				if ( 'start' === result.type ) {
					setStart( result.start );
					setEnd( null );
				} else {
					onSelect( {
						startDate: result.startDate,
						endDate: result.endDate,
					} );
				}
			},
		} ),
		createElement(
			SpacerWP,
			{ marginTop: 4, marginBottom: 0 },
			createElement(
				ButtonWP,
				{
					isSecondary: true,
					onClick: () =>
						onSelect( { startDate: null, endDate: null } ),
				},
				__( 'Clear', 'ohmylms' )
			)
		)
	);
}
