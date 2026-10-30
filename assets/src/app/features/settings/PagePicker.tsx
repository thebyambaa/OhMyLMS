import { Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { searchPages } from './api';
import { SelectField, TextField } from './fields';
import type { PageOption } from './types';

const SEARCH_DELAY_MS = 250;

/**
 * Choose one published page. Results are fetched on demand (at most 20 at a time, searched by
 * title), so a site with thousands of pages never loads them all. The saved choice is labelled
 * through a separate lookup by id, so it stays visible while searching.
 *
 * @param props          Component props.
 * @param props.label    Field label.
 * @param props.value    Selected page id, 0 for none.
 * @param props.onChange Called with the new id.
 * @param props.help     Help text.
 * @param props.error    Validation message.
 * @param props.disabled Disables both controls.
 */
export function PagePicker( {
	label,
	value,
	onChange,
	help,
	error,
	disabled,
}: {
	label: string;
	value: number;
	onChange: ( id: number ) => void;
	help?: string;
	error?: string;
	disabled?: boolean;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState< PageOption[] >( [] );
	const [ labelled, setLabelled ] = useState< PageOption | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ searching, setSearching ] = useState( false );

	useEffect( () => {
		if ( value <= 0 ) {
			return undefined;
		}
		const controller = new AbortController();
		searchPages( { include: [ value ] }, controller.signal ).then(
			( pages ) => {
				if ( ! controller.signal.aborted ) {
					setLabelled( pages[ 0 ] ?? null );
				}
			},
			( problem: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailure( normalizeApiError( problem ).message );
				}
			}
		);
		return () => controller.abort();
	}, [ value ] );

	useEffect( () => {
		const controller = new AbortController();
		const timer = setTimeout(
			() => {
				setSearching( true );
				setFailure( null );
				searchPages( { search: query }, controller.signal ).then(
					( pages ) => {
						if ( ! controller.signal.aborted ) {
							setResults( pages );
							setSearching( false );
						}
					},
					( problem: unknown ) => {
						if ( ! controller.signal.aborted ) {
							setFailure( normalizeApiError( problem ).message );
							setSearching( false );
						}
					}
				);
			},
			'' === query ? 0 : SEARCH_DELAY_MS
		);
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ query ] );

	const options = [
		{ value: '0', label: __( '— None —', 'ohmylms' ) },
		...( value > 0 && ! results.some( ( page ) => page.id === value )
			? [
					{
						value: String( value ),
						label:
							labelled && labelled.id === value
								? labelled.title
								: sprintf(
										/* translators: %d: page id. */
										__(
											'Page %d (not published or missing)',
											'ohmylms'
										),
										value
									),
					},
				]
			: [] ),
		...results.map( ( page ) => ( {
			value: String( page.id ),
			label:
				page.title ||
				sprintf(
					/* translators: %d: page id. */
					__( 'Untitled page %d', 'ohmylms' ),
					page.id
				),
		} ) ),
	];

	return (
		<fieldset>
			<legend className="screen-reader-text">{ label }</legend>
			<TextField
				label={ sprintf(
					/* translators: %s: setting name. */
					__( 'Search pages for %s', 'ohmylms' ),
					label
				) }
				value={ query }
				onChange={ setQuery }
				disabled={ disabled }
				maxLength={ 100 }
			/>
			<SelectField
				label={ label }
				value={ String( value ) }
				options={ options }
				onChange={ ( next ) => onChange( Number( next ) ) }
				help={ help }
				error={ error }
				disabled={ disabled }
			/>
			{ searching && (
				<p role="status">{ __( 'Searching pages…', 'ohmylms' ) }</p>
			) }
			{ failure && (
				<Notice status="warning" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
		</fieldset>
	);
}
