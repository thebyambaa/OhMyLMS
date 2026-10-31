import { ComboboxControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

export interface PickerChoice {
	id: number;
	title: string;
}

/**
 * Single choice from a server search (courses, plans, learners, coupons). Typing searches after a
 * short pause; obsolete requests are cancelled.
 *
 * @param props            Component props.
 * @param props.label      Field label.
 * @param props.value      Selected id, 0 for none.
 * @param props.valueLabel Title of the selected id (shown before the first search).
 * @param props.search     Searches the server.
 * @param props.onChange   Receives the id (0 when cleared) and its title.
 * @param props.help       Help text.
 * @param props.error      Validation message.
 */
export function SearchPicker( {
	label,
	value,
	valueLabel,
	search,
	onChange,
	help,
	error,
}: {
	label: string;
	value: number;
	valueLabel: string;
	search: ( term: string, signal: AbortSignal ) => Promise< PickerChoice[] >;
	onChange: ( id: number, title: string ) => void;
	help?: string;
	error?: string;
} ) {
	const searcher = useRef( search );
	searcher.current = search;
	const [ term, setTerm ] = useState( '' );
	const [ options, setOptions ] = useState< PickerChoice[] >( [] );

	useEffect( () => {
		const controller = new AbortController();
		const timer = setTimeout( () => {
			searcher.current( term, controller.signal ).then(
				( found ) => {
					if ( ! controller.signal.aborted ) {
						setOptions( found );
					}
				},
				() => undefined
			);
		}, 250 );
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ term ] );

	const merged =
		value && ! options.some( ( option ) => option.id === value )
			? [ { id: value, title: valueLabel }, ...options ]
			: options;
	return (
		<>
			<ComboboxControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				help={ error ? undefined : help }
				value={ value ? String( value ) : null }
				options={ merged.map( ( option ) => ( {
					value: String( option.id ),
					label: option.title,
				} ) ) }
				onFilterValueChange={ setTerm }
				onChange={ ( next ) => {
					const id = Number( next ?? 0 );
					const found = merged.find( ( option ) => option.id === id );
					onChange( found ? id : 0, found?.title ?? '' );
				} }
			/>
			{ error && <p role="alert">{ error }</p> }
		</>
	);
}
