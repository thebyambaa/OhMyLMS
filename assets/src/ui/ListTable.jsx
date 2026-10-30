import {
	createElement as h,
	Fragment,
	useEffect,
	useId,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

const classes = ( ...names ) => names.filter( Boolean ).join( ' ' );

const keyOf = ( rowKey, row, index ) =>
	String( rowKey ? rowKey( row, index ) : index );

/**
 * Screen-reader-only label for a native control, as WordPress prints it in list tables.
 *
 * @param {Object} props          Label properties.
 * @param {string} props.htmlFor  Control ID.
 * @param {string} props.children Label text.
 */
function ScreenReaderLabel( { htmlFor, children } ) {
	return h( 'label', { className: 'screen-reader-text', htmlFor }, children );
}

/**
 * Filter dropdown for the list-table navigation (`.tablenav .actions`): a native select with a
 * screen-reader label, like WordPress's "Filter by date" and category dropdowns.
 *
 * @param {Object}                  props          Select properties.
 * @param {string}                  props.label    Accessible label.
 * @param {string}                  props.value    Selected value.
 * @param {Array}                   props.options  `{ value, label }` choices.
 * @param {(value: string) => void} props.onChange Selection callback.
 * @param {string}                  props.id       Control ID; generated when empty.
 */
export function ListTableSelect( {
	label,
	value,
	options = [],
	onChange,
	id,
	...rest
} ) {
	const generated = useId();
	const controlId = id || `oml-filter-${ generated }`;
	return h(
		Fragment,
		null,
		h( ScreenReaderLabel, { htmlFor: controlId }, label ),
		h(
			'select',
			{
				...rest,
				id: controlId,
				value,
				onChange: ( event ) => onChange?.( event.target.value ),
			},
			options.map( ( option ) =>
				h(
					'option',
					{
						key: option.value,
						value: option.value,
						disabled: option.disabled,
					},
					option.label
				)
			)
		)
	);
}

/**
 * Text, number or date filter for the list-table navigation, with a screen-reader label.
 *
 * @param {Object}                  props          Input properties.
 * @param {string}                  props.label    Accessible label.
 * @param {string}                  props.value    Current value.
 * @param {(value: string) => void} props.onChange Change callback.
 * @param {string}                  props.id       Control ID; generated when empty.
 */
export function ListTableInput( { label, value, onChange, id, ...rest } ) {
	const generated = useId();
	const controlId = id || `oml-filter-${ generated }`;
	return h(
		Fragment,
		null,
		h( ScreenReaderLabel, { htmlFor: controlId }, label ),
		h( 'input', {
			type: 'text',
			...rest,
			id: controlId,
			value,
			onChange: ( event ) => onChange?.( event.target.value ),
		} )
	);
}

function RowAction( { action } ) {
	if ( action.render ) {
		return action.render;
	}
	if ( action.href ) {
		return h(
			'a',
			{
				href: action.href,
				className: action.destructive ? 'submitdelete' : undefined,
				'aria-label': action.ariaLabel,
				onClick: action.onClick
					? ( event ) => {
							event.preventDefault();
							action.onClick();
						}
					: undefined,
			},
			action.label
		);
	}
	return h(
		'button',
		{
			type: 'button',
			className: classes(
				'button-link',
				action.destructive && 'button-link-delete'
			),
			disabled: action.disabled,
			'aria-label': action.ariaLabel,
			onClick: action.onClick,
		},
		action.label
	);
}

function RowActions( { actions, visible } ) {
	const list = actions.filter( Boolean );
	if ( ! list.length ) {
		return null;
	}
	return h(
		'div',
		{ className: classes( 'row-actions', visible && 'visible' ) },
		list.map( ( action, index ) =>
			h(
				'span',
				{ key: action.key, className: action.key },
				h( RowAction, { action } ),
				index < list.length - 1 ? ' | ' : null
			)
		)
	);
}

function PageButton( { kind, label, symbol, disabled, onClick } ) {
	if ( disabled ) {
		return h(
			'span',
			{
				className: 'tablenav-pages-navspan button disabled',
				'aria-hidden': 'true',
			},
			symbol
		);
	}
	return h(
		'button',
		{ type: 'button', className: `${ kind } button`, onClick },
		h( 'span', { className: 'screen-reader-text' }, label ),
		h( 'span', { 'aria-hidden': 'true' }, symbol )
	);
}

function Pages( { pagination, position, baseId, busy } ) {
	const {
		page,
		totalPages,
		totalItems,
		itemsLabel,
		onChange,
		disabled = false,
	} = pagination;
	const pages = Math.max( 1, totalPages || 1 );
	const current = Math.min( Math.max( 1, page || 1 ), pages );
	const [ draft, setDraft ] = useState( String( current ) );
	useEffect( () => setDraft( String( current ) ), [ current ] );
	const locked = busy || disabled;
	const go = ( next ) => {
		if ( ! locked && next !== current && next >= 1 && next <= pages ) {
			onChange( next );
		}
	};
	const pagingId = `${ baseId }-paging`;
	const count =
		itemsLabel ??
		( 'number' === typeof totalItems
			? sprintf(
					/* translators: %s: number of items. */
					_n( '%s item', '%s items', totalItems, 'ohmylms' ),
					totalItems.toLocaleString()
				)
			: null );
	const total = h(
		'span',
		{ className: 'total-pages' },
		pages.toLocaleString()
	);
	return h(
		'div',
		{
			className: classes(
				'tablenav-pages',
				pages <= 1 && 'one-page',
				0 === totalItems && 'no-pages'
			),
		},
		count && h( 'span', { className: 'displaying-num' }, count ),
		h(
			'span',
			{ className: 'pagination-links' },
			h( PageButton, {
				kind: 'first-page',
				label: __( 'First page', 'ohmylms' ),
				symbol: '«',
				disabled: locked || current <= 1,
				onClick: () => go( 1 ),
			} ),
			' ',
			h( PageButton, {
				kind: 'prev-page',
				label: __( 'Previous page', 'ohmylms' ),
				symbol: '‹',
				disabled: locked || current <= 1,
				onClick: () => go( current - 1 ),
			} ),
			' ',
			'top' === position
				? h(
						'span',
						{ className: 'paging-input' },
						h(
							ScreenReaderLabel,
							{ htmlFor: `${ baseId }-current-page` },
							__( 'Current Page', 'ohmylms' )
						),
						h( 'input', {
							className: 'current-page',
							id: `${ baseId }-current-page`,
							type: 'text',
							inputMode: 'numeric',
							size: Math.max( 1, String( pages ).length ),
							value: draft,
							disabled: locked,
							'aria-describedby': pagingId,
							onChange: ( event ) =>
								setDraft(
									event.target.value.replace( /\D/g, '' )
								),
							onKeyDown: ( event ) => {
								if ( 'Enter' === event.key ) {
									event.preventDefault();
									const next = parseInt( draft, 10 );
									if ( Number.isFinite( next ) ) {
										go(
											Math.min(
												Math.max( 1, next ),
												pages
											)
										);
									}
									setDraft( String( current ) );
								}
							},
						} ),
						h(
							'span',
							{ className: 'tablenav-paging-text' },
							' ',
							/* translators: Separates the current page and the page count ("1 of 4"). */
							__( 'of', 'ohmylms' ),
							' ',
							total
						)
					)
				: h(
						'span',
						{ className: 'paging-input', id: pagingId },
						h(
							'span',
							{ className: 'tablenav-paging-text' },
							current.toLocaleString(),
							' ',
							__( 'of', 'ohmylms' ),
							' ',
							total
						)
					),
			' ',
			h( PageButton, {
				kind: 'next-page',
				label: __( 'Next page', 'ohmylms' ),
				symbol: '›',
				disabled: locked || current >= pages,
				onClick: () => go( current + 1 ),
			} ),
			' ',
			h( PageButton, {
				kind: 'last-page',
				label: __( 'Last page', 'ohmylms' ),
				symbol: '»',
				disabled: locked || current >= pages,
				onClick: () => go( pages ),
			} )
		)
	);
}

function Views( { views, busy } ) {
	return h(
		'ul',
		{ className: 'subsubsub' },
		views.map( ( view, index ) =>
			h(
				'li',
				{ key: view.key, className: view.key },
				h(
					'a',
					{
						href: view.href ?? '#',
						className: view.current ? 'current' : undefined,
						'aria-current': view.current ? 'page' : undefined,
						onClick: view.onSelect
							? ( event ) => {
									event.preventDefault();
									if ( ! busy ) {
										view.onSelect();
									}
								}
							: undefined,
					},
					view.label,
					'number' === typeof view.count &&
						h(
							Fragment,
							null,
							' ',
							h(
								'span',
								{ className: 'count' },
								`(${ view.count.toLocaleString() })`
							)
						)
				),
				index < views.length - 1 ? ' |' : null
			)
		)
	);
}

function SortLink( { column, sort, busy } ) {
	const sorted = sort.orderby === column.id;
	const first = 'desc' === column.sortable ? 'desc' : 'asc';
	let next = first;
	if ( sorted ) {
		next = 'asc' === sort.order ? 'desc' : 'asc';
	}
	return h(
		'a',
		{
			href: sort.href ? sort.href( column.id, next ) : '#',
			onClick: ( event ) => {
				event.preventDefault();
				if ( ! busy ) {
					sort.onChange( column.id, next );
				}
			},
		},
		h( 'span', null, column.heading ?? column.label ),
		h(
			'span',
			{ className: 'sorting-indicators' },
			h( 'span', {
				className: 'sorting-indicator asc',
				'aria-hidden': 'true',
			} ),
			h( 'span', {
				className: 'sorting-indicator desc',
				'aria-hidden': 'true',
			} )
		),
		' ',
		h(
			'span',
			{ className: 'screen-reader-text' },
			'asc' === next
				? __( 'Sort ascending.', 'ohmylms' )
				: __( 'Sort descending.', 'ohmylms' )
		)
	);
}

/**
 * WordPress list table rendered over REST data: `.subsubsub` views, search box, `.tablenav` with bulk
 * actions, filters and pagination, `table.wp-list-table.widefat.fixed.striped` with sortable headers,
 * check column, row actions on the primary column and the responsive `.toggle-row`. Markup and class
 * names follow `WP_List_Table`, so wp-admin's own list-table styles apply. See the typed props in
 * ListTable.d.ts.
 *
 * @param {Object} props List table properties.
 */
export function ListTable( props ) {
	const {
		caption,
		columns,
		rows = [],
		rowKey,
		rowLabel,
		rowActions,
		rowClassName,
		isSelectable,
		emptyMessage = __( 'No items found.', 'ohmylms' ),
		views,
		search,
		filters,
		filterLabel = __( 'Filter', 'ohmylms' ),
		onFilter,
		bulkActions,
		selected: controlledSelected,
		onSelectedChange,
		sort,
		pagination,
		busy = false,
		fixed = true,
		striped = true,
		footer = true,
		className = '',
		formLabel,
		tablenavExtra,
	} = props;
	const baseId = `oml-list-${ useId().replace( /[^a-zA-Z0-9_-]/g, '' ) }`;
	const visibleColumns = columns.filter( ( column ) => ! column.hidden );
	const primaryId = (
		visibleColumns.find( ( column ) => column.primary ) ??
		visibleColumns[ 0 ]
	)?.id;
	const selectable = Boolean( bulkActions ) || Boolean( onSelectedChange );
	const keys = useMemo(
		() => rows.map( ( row, index ) => keyOf( rowKey, row, index ) ),
		[ rows, rowKey ]
	);
	const [ ownSelected, setOwnSelected ] = useState( () => [] );
	const selected = controlledSelected
		? controlledSelected.map( String )
		: ownSelected;
	const setSelected = ( next ) => {
		if ( ! controlledSelected ) {
			setOwnSelected( next );
		}
		onSelectedChange?.( next );
	};
	useEffect( () => {
		if ( ! controlledSelected ) {
			setOwnSelected( ( current ) =>
				current.filter( ( key ) => keys.includes( key ) )
			);
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ keys.join( '\u0000' ) ] );
	const [ expanded, setExpanded ] = useState( () => [] );
	const [ focused, setFocused ] = useState( null );
	const [ bulk, setBulk ] = useState( { top: '-1', bottom: '-1' } );
	const [ applying, setApplying ] = useState( false );
	const locked = busy || applying;
	const selectableKeys = keys.filter(
		( key, index ) => ! isSelectable || isSelectable( rows[ index ] )
	);
	const allSelected =
		selectableKeys.length > 0 &&
		selectableKeys.every( ( key ) => selected.includes( key ) );
	const columnCount = visibleColumns.length + ( selectable ? 1 : 0 );
	const sortedColumn =
		sort && visibleColumns.find( ( column ) => column.id === sort.orderby );

	async function applyBulk( position ) {
		const action = bulk[ position ];
		if ( ! bulkActions || '-1' === action || ! selected.length ) {
			return;
		}
		const chosen = rows.filter( ( row, index ) =>
			selected.includes( keys[ index ] )
		);
		setApplying( true );
		try {
			const result = await bulkActions.onApply(
				action,
				[ ...selected ],
				chosen
			);
			if ( false !== result ) {
				setSelected( [] );
				setBulk( { top: '-1', bottom: '-1' } );
			}
		} finally {
			setApplying( false );
		}
	}

	function headerRow( position ) {
		const cells = [];
		if ( selectable ) {
			const id = `${ baseId }-select-all-${ position }`;
			cells.push(
				h(
					'td',
					{
						key: 'cb',
						className: 'manage-column column-cb check-column',
					},
					h( 'input', {
						id,
						type: 'checkbox',
						checked: allSelected,
						disabled: ! selectableKeys.length || locked,
						onChange: () =>
							setSelected(
								allSelected ? [] : [ ...selectableKeys ]
							),
					} ),
					h(
						'label',
						{ htmlFor: id },
						h(
							'span',
							{ className: 'screen-reader-text' },
							__( 'Select All', 'ohmylms' )
						)
					)
				)
			);
		}
		for ( const column of visibleColumns ) {
			const sorted = Boolean( sort && sort.orderby === column.id );
			const sortable = Boolean( sort && column.sortable );
			let order = null;
			if ( sorted ) {
				order = sort.order;
			} else if ( sortable ) {
				order = 'desc' === column.sortable ? 'asc' : 'desc';
			}
			cells.push(
				h(
					'th',
					{
						key: column.id,
						scope: 'col',
						className: classes(
							'manage-column',
							`column-${ column.id }`,
							column.id === primaryId && 'column-primary',
							sorted && 'sorted',
							! sorted && sortable && 'sortable',
							( sorted || sortable ) && order,
							column.className
						),
						'aria-sort':
							'top' === position && sorted
								? `${ sort.order === 'asc' ? 'ascending' : 'descending' }`
								: undefined,
					},
					sortable
						? h( SortLink, { column, sort, busy: locked } )
						: ( column.heading ?? column.label )
				)
			);
		}
		return h( 'tr', null, cells );
	}

	function bodyRow( row, index ) {
		const key = keys[ index ];
		const isExpanded = expanded.includes( key );
		const label = rowLabel ? rowLabel( row ) : key;
		const actions = rowActions ? rowActions( row ) || [] : [];
		const cells = [];
		if ( selectable && isSelectable && ! isSelectable( row ) ) {
			cells.push(
				h( 'th', {
					key: 'cb',
					scope: 'row',
					className: 'check-column',
				} )
			);
		} else if ( selectable ) {
			const id = `${ baseId }-cb-${ index }`;
			cells.push(
				h(
					'th',
					{ key: 'cb', scope: 'row', className: 'check-column' },
					h( 'input', {
						id,
						type: 'checkbox',
						value: key,
						checked: selected.includes( key ),
						disabled: locked,
						onChange: () =>
							setSelected(
								selected.includes( key )
									? selected.filter(
											( item ) => item !== key
										)
									: [ ...selected, key ]
							),
					} ),
					h(
						'label',
						{ htmlFor: id },
						h(
							'span',
							{ className: 'screen-reader-text' },
							sprintf(
								/* translators: %s: row title. */
								__( 'Select %s', 'ohmylms' ),
								label
							)
						)
					)
				)
			);
		}
		for ( const column of visibleColumns ) {
			const primary = column.id === primaryId;
			cells.push(
				h(
					'td',
					{
						key: column.id,
						className: classes(
							column.id,
							`column-${ column.id }`,
							primary && 'has-row-actions column-primary',
							column.className
						),
						'data-colname': column.label,
						onFocus: primary ? () => setFocused( key ) : undefined,
						onBlur: primary
							? ( event ) => {
									if (
										! event.currentTarget.contains(
											event.relatedTarget
										)
									) {
										setFocused( ( current ) =>
											current === key ? null : current
										);
									}
								}
							: undefined,
					},
					column.render( row, index ),
					primary &&
						h( RowActions, { actions, visible: focused === key } ),
					primary &&
						h(
							'button',
							{
								type: 'button',
								className: 'toggle-row',
								'aria-expanded': isExpanded,
								onClick: () =>
									setExpanded(
										isExpanded
											? expanded.filter(
													( item ) => item !== key
												)
											: [ ...expanded, key ]
									),
							},
							h(
								'span',
								{ className: 'screen-reader-text' },
								__( 'Show more details', 'ohmylms' )
							)
						)
				)
			);
		}
		return h(
			'tr',
			{
				key,
				className:
					classes(
						rowClassName?.( row ),
						isExpanded && 'is-expanded'
					) || undefined,
			},
			cells
		);
	}

	function tablenav( position ) {
		const showBulk = Boolean( bulkActions );
		const showFilters = 'top' === position && ( filters || tablenavExtra );
		if ( ! showBulk && ! showFilters && ! pagination ) {
			return null;
		}
		const selectId = `${ baseId }-bulk-action-${ position }`;
		return h(
			'div',
			{ className: `tablenav ${ position }` },
			showBulk &&
				h(
					'div',
					{ className: 'alignleft actions bulkactions' },
					h(
						ScreenReaderLabel,
						{ htmlFor: selectId },
						__( 'Select bulk action', 'ohmylms' )
					),
					h(
						'select',
						{
							id: selectId,
							value: bulk[ position ],
							disabled: locked,
							onChange: ( event ) =>
								setBulk( {
									...bulk,
									[ position ]: event.target.value,
								} ),
						},
						h(
							'option',
							{ value: '-1' },
							bulkActions.label ?? __( 'Bulk actions', 'ohmylms' )
						),
						bulkActions.actions.map( ( action ) =>
							h(
								'option',
								{ key: action.value, value: action.value },
								action.label
							)
						)
					),
					' ',
					h(
						'button',
						{
							type: 'button',
							className: 'button action',
							disabled: locked,
							onClick: () => void applyBulk( position ),
						},
						__( 'Apply', 'ohmylms' )
					)
				),
			'top' === position &&
				filters &&
				h(
					'div',
					{ className: 'alignleft actions' },
					filters,
					onFilter &&
						filterLabel &&
						h(
							'button',
							{
								type: 'submit',
								className: 'button',
								disabled: locked,
							},
							filterLabel
						)
				),
			'top' === position && tablenavExtra,
			pagination &&
				h( Pages, { pagination, position, baseId, busy: locked } ),
			h( 'br', { className: 'clear' } )
		);
	}

	const searchId = `${ baseId }-search-input`;
	const table = h(
		Fragment,
		null,
		search &&
			h(
				'p',
				{ className: 'search-box' },
				h( ScreenReaderLabel, { htmlFor: searchId }, search.label ),
				h( 'input', {
					type: 'search',
					id: searchId,
					value: search.value,
					placeholder: search.placeholder,
					maxLength: search.maxLength,
					onChange: ( event ) =>
						search.onChange( event.target.value ),
				} ),
				' ',
				h(
					'button',
					{ type: 'submit', className: 'button', disabled: locked },
					search.submitLabel ?? search.label
				)
			),
		tablenav( 'top' ),
		h(
			'table',
			{
				className: classes(
					'wp-list-table widefat',
					fixed && 'fixed',
					striped && 'striped',
					'table-view-list',
					className
				),
				'aria-describedby': sortedColumn
					? `${ baseId }-order`
					: undefined,
			},
			h( 'caption', { className: 'screen-reader-text' }, caption ),
			h( 'thead', null, headerRow( 'top' ) ),
			h(
				'tbody',
				null,
				rows.length
					? rows.map( bodyRow )
					: h(
							'tr',
							{ className: 'no-items' },
							h(
								'td',
								{
									className: 'colspanchange',
									colSpan: columnCount,
								},
								emptyMessage
							)
						)
			),
			footer && h( 'tfoot', null, headerRow( 'bottom' ) )
		),
		sortedColumn &&
			h(
				'span',
				{ id: `${ baseId }-order`, className: 'screen-reader-text' },
				'asc' === sort.order
					? sprintf(
							/* translators: %s: column name. */
							__( 'Table ordered by %s. Ascending.', 'ohmylms' ),
							sortedColumn.label
						)
					: sprintf(
							/* translators: %s: column name. */
							__( 'Table ordered by %s. Descending.', 'ohmylms' ),
							sortedColumn.label
						)
			),
		tablenav( 'bottom' )
	);
	const content =
		search || onFilter
			? h(
					'form',
					{
						className: 'ohmylms-list-table',
						'aria-label': formLabel,
						onSubmit: ( event ) => {
							event.preventDefault();
							if ( ! locked ) {
								( onFilter ?? search?.onSubmit )?.();
							}
						},
						noValidate: true,
					},
					table
				)
			: h( 'div', { className: 'ohmylms-list-table' }, table );
	// A block wrapper keeps the floated views and search box off the line of preceding inline buttons.
	return h(
		'div',
		{ className: 'ohmylms-list-table-wrap' },
		views && views.length > 0 && h( Views, { views, busy: locked } ),
		content
	);
}
