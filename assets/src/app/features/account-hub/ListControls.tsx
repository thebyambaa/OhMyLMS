import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import {
	ListTable,
	type ListTableProps,
	type ListTableView,
} from '../../../ui/ListTable';

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

/** What `HubListTable` reads from `usePagedList`. */
interface HubList< Row > {
	page: number;
	search: string;
	loading: boolean;
	error: string;
	data: { items: Row[]; total: number; totalPages: number };
	setPage: ( page: number ) => void;
	pageHref?: ( page: number ) => string;
	setSearch: ( value: string ) => void;
	reload: () => void;
}

/**
 * A hub list as a WordPress list table: search box, optional views and filters in the table
 * navigation, loading and error states, and pagination over a `usePagedList` result.
 *
 * @param props             Component props.
 * @param props.caption     Table name.
 * @param props.searchLabel Accessible name of the search.
 * @param props.list        Paged list state.
 * @param props.loadingText Text while loading.
 * @param props.emptyText   Text when there are no rows.
 * @param props.views       Status views.
 * @param props.filters     Filters for the table navigation (applied on change).
 * @param props.table       Columns, row key and row actions.
 */
export function HubListTable< Row >( {
	caption,
	searchLabel,
	list,
	loadingText,
	emptyText,
	views,
	filters,
	table,
}: {
	caption: string;
	searchLabel: string;
	list: HubList< Row >;
	loadingText: string;
	emptyText: string;
	views?: ListTableView[];
	filters?: ReactNode;
	table: Pick<
		ListTableProps< Row >,
		'columns' | 'rowKey' | 'rowActions' | 'rowLabel'
	>;
} ) {
	const [ value, setValue ] = useState( list.search );
	useEffect( () => setValue( list.search ), [ list.search ] );
	if ( list.loading ) {
		return <p role="status">{ loadingText }</p>;
	}
	if ( list.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ list.error }{ ' ' }
				<Button variant="link" onClick={ list.reload }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	const total = list.data.total;
	return (
		<ListTable< Row >
			{ ...table }
			caption={ caption }
			heading={ caption }
			formLabel={ searchLabel }
			rows={ list.data.items }
			emptyMessage={ emptyText }
			views={ views }
			filters={ filters }
			filterLabel={ null }
			search={ {
				label: searchLabel,
				submitLabel: __( 'Search', 'ohmylms' ),
				value,
				onChange: setValue,
				onSubmit: () => list.setSearch( value ),
			} }
			pagination={ {
				page: list.page,
				totalPages: list.data.totalPages,
				totalItems: total,
				itemsLabel: sprintf(
					/* translators: %d: number of results. */
					_n( '%d result.', '%d results.', total, 'ohmylms' ),
					total
				),
				onChange: list.setPage,
				href: list.pageHref,
			} }
		/>
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
