import type {
	InputHTMLAttributes,
	ReactElement,
	ReactNode,
	SelectHTMLAttributes,
} from 'react';

export type SortOrder = 'asc' | 'desc';

export interface ListTableColumn< Row > {
	/** Column key: becomes `column-<id>` and the sort `orderby` value. */
	id: string;
	/** Plain-text name: header text, `data-colname` for the responsive view and the sort description. */
	label: string;
	/** Header content when it differs from the label. */
	heading?: ReactNode;
	/** The primary column carries the row actions and the responsive toggle (default: the first column). */
	primary?: boolean;
	/** Sortable by this column; `'desc'` sorts descending on the first click (WordPress `desc_first`). */
	sortable?: boolean | SortOrder;
	/** Extra header and cell classes, for example `num`. */
	className?: string;
	hidden?: boolean;
	render: ( row: Row, index: number ) => ReactNode;
}

interface RowActionBase {
	/** Becomes the `<span>` class (`edit`, `trash`, `view`…), as in WordPress. */
	key: string;
	label: ReactNode;
	ariaLabel?: string;
	destructive?: boolean;
}

/** A row action is a button (`onClick`), a link (`href`) or a ready element such as a router `Link`. */
export type ListTableRowAction = RowActionBase &
	(
		| {
				onClick: () => void;
				disabled?: boolean;
				href?: undefined;
				render?: undefined;
		  }
		| { href: string; onClick?: () => void; render?: undefined }
		| { render: ReactElement; onClick?: undefined; href?: undefined }
	);

export interface ListTableView {
	key: string;
	label: string;
	count?: number;
	current?: boolean;
	/** Address of the view; with `onSelect`, plain clicks select in place and modified clicks open it. */
	href?: string;
	onSelect?: () => void;
}

export interface ListTableSearch {
	/** Screen-reader label of the search field (WordPress: "Search Posts:"). */
	label: string;
	value: string;
	onChange: ( value: string ) => void;
	/** Visible submit text; defaults to the label. */
	submitLabel?: string;
	placeholder?: string;
	maxLength?: number;
	/** Called on submit when the table has no `onFilter`. */
	onSubmit?: () => void;
}

export interface ListTableBulkActions< Row > {
	actions: ReadonlyArray< { value: string; label: string } >;
	/** First option text; defaults to "Bulk actions". */
	label?: string;
	/** Return `false` (or a promise of it) to keep the selection. */
	onApply: (
		action: string,
		keys: string[],
		rows: Row[]
	) => void | boolean | Promise< void | boolean >;
}

export interface ListTablePagination {
	page: number;
	totalPages: number;
	totalItems?: number;
	/** Replaces the default "N items" text. */
	itemsLabel?: string;
	disabled?: boolean;
	onChange: ( page: number ) => void;
	/** Address of a page: the page links become real links (plain clicks still call `onChange`). */
	href?: ( page: number ) => string;
}

export interface ListTableSort {
	orderby: string;
	order: SortOrder;
	onChange: ( orderby: string, order: SortOrder ) => void;
	href?: ( orderby: string, order: SortOrder ) => string;
}

export interface ListTableProps< Row > {
	/** Accessible table name (a screen-reader caption). */
	caption: string;
	/**
	 * Screen-reader `h2` before the list, as WordPress prints on list screens ("Posts list"). Give it on
	 * screens whose title is the page heading and tab, so the list still has a heading.
	 */
	heading?: string;
	columns: ReadonlyArray< ListTableColumn< Row > >;
	rows: readonly Row[];
	rowKey?: ( row: Row, index: number ) => string | number;
	/** Name used by the row checkbox label ("Select %s"). */
	rowLabel?: ( row: Row ) => string;
	rowActions?: (
		row: Row
	) => ReadonlyArray< ListTableRowAction | false | null | undefined >;
	rowClassName?: ( row: Row ) => string | undefined;
	/** Rows that get no checkbox (for example items the user cannot edit). */
	isSelectable?: ( row: Row ) => boolean;
	emptyMessage?: ReactNode;
	views?: readonly ListTableView[];
	search?: ListTableSearch;
	/** Filter controls for the top `.tablenav` (use ListTableSelect / ListTableInput). */
	filters?: ReactNode;
	/** Filter button text (default "Filter"); `null` hides it when the filters apply on change. */
	filterLabel?: string | null;
	/** Search and Filter submit the same form, as on WordPress list screens. */
	onFilter?: () => void;
	formLabel?: string;
	bulkActions?: ListTableBulkActions< Row >;
	/** Controlled selection (row keys as strings). */
	selected?: readonly ( string | number )[];
	onSelectedChange?: ( keys: string[] ) => void;
	sort?: ListTableSort;
	pagination?: ListTablePagination;
	/** Extra content in the top `.tablenav`, after the filters. */
	tablenavExtra?: ReactNode;
	busy?: boolean;
	fixed?: boolean;
	striped?: boolean;
	footer?: boolean;
	className?: string;
}

/** WordPress list table (`WP_List_Table` markup) over REST data. Runtime is ListTable.jsx. */
export function ListTable< Row >( props: ListTableProps< Row > ): ReactElement;

export interface ListTableSelectProps extends Omit<
	SelectHTMLAttributes< HTMLSelectElement >,
	'onChange'
> {
	label: string;
	value: string;
	options: ReadonlyArray< {
		value: string;
		label: string;
		disabled?: boolean;
	} >;
	onChange: ( value: string ) => void;
}

export function ListTableSelect( props: ListTableSelectProps ): ReactElement;

export interface ListTableInputProps extends Omit<
	InputHTMLAttributes< HTMLInputElement >,
	'onChange'
> {
	label: string;
	value: string;
	onChange: ( value: string ) => void;
}

export function ListTableInput( props: ListTableInputProps ): ReactElement;
