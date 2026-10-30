import type { ListTableView } from '../../ui/ListTable';

/**
 * `.subsubsub` views from a status filter's options: the current view shows the total the list
 * already knows; the REST collections do not report per-status counts.
 *
 * @param options  Status options, `all` first.
 * @param current  Selected value.
 * @param total    Items in the current view.
 * @param onSelect Switches the view.
 * @return Views for `ListTable`.
 */
export function statusViews(
	options: ReadonlyArray< { value: string; label: string } >,
	current: string,
	total: number | null,
	onSelect: ( value: string ) => void
): ListTableView[] {
	return options.map( ( option ) => ( {
		key: option.value,
		label: option.label,
		current: option.value === current,
		count: option.value === current && null !== total ? total : undefined,
		onSelect: () => onSelect( option.value ),
	} ) );
}
