import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { SortOrder } from './types';

/**
 * A sortable column header: a button inside a `th` that carries `aria-sort`.
 *
 * @param props         Component props.
 * @param props.label   Column title.
 * @param props.field   Sort key of this column.
 * @param props.orderby Active sort key.
 * @param props.order   Active direction.
 * @param props.onSort  Called with the key and the direction to apply.
 * @param props.first   Direction used when the column is first chosen.
 */
export function SortHeader< K extends string >( {
	label,
	field,
	orderby,
	order,
	onSort,
	first = 'desc',
}: {
	label: string;
	field: K;
	orderby: K;
	order: SortOrder;
	onSort: ( field: K, order: SortOrder ) => void;
	first?: SortOrder;
} ) {
	const active = field === orderby;
	const reversed: SortOrder = 'asc' === order ? 'desc' : 'asc';
	const next: SortOrder = active ? reversed : first;
	let ariaSort: 'ascending' | 'descending' | 'none' = 'none';
	if ( active ) {
		ariaSort = 'asc' === order ? 'ascending' : 'descending';
	}
	return (
		<th scope="col" aria-sort={ ariaSort }>
			<Button
				variant="link"
				onClick={ () => onSort( field, next ) }
				aria-label={ sprintf(
					/* translators: %s: column title. */
					__( 'Sort by %s', 'ohmylms' ),
					label
				) }
			>
				{ label }
			</Button>
		</th>
	);
}
