import { DropdownMenu, Popover } from '@wordpress/components';
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ButtonWP } from '../actions/ButtonWP.mjs';
import { FlexWP } from '../layout/FlexWP.mjs';
import { DateRangeCalendar } from './DateRangeCalendar.mjs';
import {
	getPresetRange,
	getRangeLabel,
	resolveSelection,
} from './date-range-model.mjs';

/**
 * Range button with a preset menu and a calendar popover.
 *
 * @param {Object}   props
 * @param {*}        props.initialStartDate Range start.
 * @param {*}        props.initialEndDate   Range end.
 * @param {Function} props.onChange         Called with `[ start, end ]` as `YYYY-MM-DD`, or `[ null, null ]` when cleared.
 * @return {import('react').ReactElement} Field.
 */
export function DateRangeField( {
	initialStartDate,
	initialEndDate,
	onChange,
} ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ start, setStart ] = useState( initialStartDate );
	const [ end, setEnd ] = useState( initialEndDate );

	useEffect( () => {
		setStart( initialStartDate );
		setEnd( initialEndDate );
	}, [ initialStartDate, initialEndDate ] );

	const applyPreset = ( preset ) => {
		const range = getPresetRange( preset );
		setStart( range[ 0 ] );
		setEnd( range[ 1 ] );
		onChange( range );
	};

	const presets = [
		[ __( 'Today', 'ohmylms' ), 'today' ],
		[ __( 'This Week', 'ohmylms' ), 'this_week' ],
		[ __( 'Last 7 Days', 'ohmylms' ), 'last_7_days' ],
		[ __( 'This Month', 'ohmylms' ), 'this_month' ],
	].map( ( [ title, preset ] ) => ( {
		title,
		onClick: () => applyPreset( preset ),
	} ) );

	return createElement(
		FlexWP,
		{ align: 'center', justify: 'flex-start', gap: 0 },
		createElement(
			ButtonWP,
			{
				className: 'ohmylms-date-range-input',
				onClick: () => setIsOpen( ! isOpen ),
			},
			getRangeLabel( start, end, __( 'Select Date Range', 'ohmylms' ) )
		),
		createElement( DropdownMenu, {
			icon: 'calendar-alt',
			label: __( 'Select a Preset', 'ohmylms' ),
			controls: [
				...presets,
				{
					title: __( 'Custom', 'ohmylms' ),
					onClick: () => setIsOpen( true ),
				},
			],
		} ),
		isOpen &&
			createElement(
				Popover,
				{ position: 'bottom', onClose: () => setIsOpen( false ) },
				createElement( DateRangeCalendar, {
					initialStartDate: start,
					initialEndDate: end,
					onSelect: ( selection ) => {
						const result = resolveSelection( selection );
						if ( result.dates ) {
							setStart( result.dates[ 0 ] );
							setEnd( result.dates[ 1 ] );
						}
						if ( result.close ) {
							setIsOpen( false );
						}
						onChange( result.value );
					},
				} )
			)
	);
}
