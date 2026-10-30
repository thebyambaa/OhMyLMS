import { Button, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';

/**
 * Search form shared by the hub lists.
 *
 * @param props          Component props.
 * @param props.label    Accessible name of the search.
 * @param props.busy     Disable the submit button.
 * @param props.onSearch Called with the submitted text.
 * @param props.children Extra filters.
 */
export function SearchForm( {
	label,
	busy,
	onSearch,
	children,
}: {
	label: string;
	busy: boolean;
	onSearch: ( value: string ) => void;
	children?: ReactNode;
} ) {
	const [ value, setValue ] = useState( '' );
	return (
		<form
			role="search"
			aria-label={ label }
			onSubmit={ ( event ) => {
				event.preventDefault();
				onSearch( value );
			} }
		>
			<TextControl
				label={ label }
				value={ value }
				onChange={ setValue }
			/>
			{ children }
			<Button type="submit" variant="secondary" disabled={ busy }>
				{ __( 'Search', 'ohmylms' ) }
			</Button>
		</form>
	);
}

/**
 * Loading, error with retry, empty state and total for a list.
 *
 * @param props             Component props.
 * @param props.loading     Whether the list is loading.
 * @param props.error       Load error message.
 * @param props.total       Total rows.
 * @param props.loadingText Text while loading.
 * @param props.emptyText   Text when there are no rows.
 * @param props.onRetry     Reload the list.
 * @param props.children    Table and pagination, shown when rows exist.
 */
export function ListState( {
	loading,
	error,
	total,
	loadingText,
	emptyText,
	onRetry,
	children,
}: {
	loading: boolean;
	error: string;
	total: number;
	loadingText: string;
	emptyText: string;
	onRetry: () => void;
	children: ReactNode;
} ) {
	if ( loading ) {
		return <p role="status">{ loadingText }</p>;
	}
	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }{ ' ' }
				<Button variant="link" onClick={ onRetry }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	if ( ! total ) {
		return <p>{ emptyText }</p>;
	}
	return (
		<>
			<p role="status">
				{ sprintf(
					/* translators: %d: number of results. */
					_n( '%d result.', '%d results.', total, 'ohmylms' ),
					total
				) }
			</p>
			{ children }
		</>
	);
}
