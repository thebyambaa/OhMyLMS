import { Button, TextControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge } from '../../../ui/Badge';
import type { CurriculumItem, ItemCounts } from './api';

/**
 * Items in the tree order, and each item's children.
 *
 * @param items All curriculum items.
 * @return Children by parent ID (0 for the top level), in position order.
 */
export function childrenOf(
	items: readonly CurriculumItem[]
): Map< number, CurriculumItem[] > {
	const map = new Map< number, CurriculumItem[] >();
	for ( const item of items ) {
		const list = map.get( item.parent_id ) ?? [];
		list.push( item );
		map.set( item.parent_id, list );
	}
	for ( const list of map.values() ) {
		list.sort( ( a, b ) => a.position - b.position || a.id - b.id );
	}
	return map;
}

/**
 * IDs that must stay visible for a search: the matches and everything above them.
 *
 * @param items  All curriculum items.
 * @param search What the author typed.
 * @return The IDs to show, or null for no search.
 */
export function visibleIds(
	items: readonly CurriculumItem[],
	search: string
): Set< number > | null {
	const needle = search.trim().toLowerCase();
	if ( ! needle ) {
		return null;
	}
	const byId = new Map( items.map( ( item ) => [ item.id, item ] ) );
	const shown = new Set< number >();
	for ( const item of items ) {
		if (
			item.name.toLowerCase().includes( needle ) ||
			item.code.toLowerCase().includes( needle )
		) {
			let current: CurriculumItem | undefined = item;
			while ( current && ! shown.has( current.id ) ) {
				shown.add( current.id );
				current = byId.get( current.parent_id );
			}
		}
	}
	return shown;
}

/**
 * The ancestors of an item, nearest last.
 * @param items
 * @param id
 */
export function ancestorsOf(
	items: readonly CurriculumItem[],
	id: number
): CurriculumItem[] {
	const byId = new Map( items.map( ( item ) => [ item.id, item ] ) );
	const chain: CurriculumItem[] = [];
	let current = byId.get( byId.get( id )?.parent_id ?? 0 );
	while ( current && ! chain.includes( current ) ) {
		chain.unshift( current );
		current = byId.get( current.parent_id );
	}
	return chain;
}

function summary( counts: ItemCounts | undefined ) {
	if ( ! counts ) {
		return '';
	}
	const parts: string[] = [];
	if ( counts.skills > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: number of skills. */
				_n( '%d skill', '%d skills', counts.skills, 'ohmylms' ),
				counts.skills
			)
		);
	}
	if ( counts.courses > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: number of courses. */
				_n( '%d course', '%d courses', counts.courses, 'ohmylms' ),
				counts.courses
			)
		);
	}
	return parts.join( ' · ' );
}

/**
 * The curriculum as one tree: curricula, their topics and groups, with what each holds. Searching keeps the
 * matches and their parents on screen.
 *
 * @param props          Component props.
 * @param props.items    All curriculum items.
 * @param props.counts   What each item holds.
 * @param props.selected The open item.
 * @param props.onSelect Opens an item.
 * @param props.onAdd    Starts a new top-level item.
 */
export function WorkspaceTree( {
	items,
	counts,
	selected,
	onSelect,
	onAdd,
}: {
	items: readonly CurriculumItem[];
	counts: Record< number, ItemCounts >;
	selected: number | null;
	onSelect: ( id: number ) => void;
	onAdd: () => void;
} ) {
	const [ search, setSearch ] = useState( '' );
	// What the author opened or closed by hand; everything else opens by the rules in `branch`.
	const [ overrides, setOverrides ] = useState<
		ReadonlyMap< number, boolean >
	>( () => new Map() );
	const children = useMemo( () => childrenOf( items ), [ items ] );
	const shown = useMemo(
		() => visibleIds( items, search ),
		[ items, search ]
	);
	const open = useMemo(
		() =>
			new Set(
				selected
					? ancestorsOf( items, selected ).map( ( row ) => row.id )
					: []
			),
		[ items, selected ]
	);
	const toggle = ( id: number, expanded: boolean ) =>
		setOverrides( ( current ) => new Map( current ).set( id, ! expanded ) );
	function branch( item: CurriculumItem, depth: number ) {
		if ( shown && ! shown.has( item.id ) ) {
			return null;
		}
		const kids = children.get( item.id ) ?? [];
		const hasKids = kids.some( ( kid ) => ! shown || shown.has( kid.id ) );
		// The top level starts open and deeper levels closed; a search or the open item's parents open the way.
		const expanded =
			hasKids &&
			( overrides.get( item.id ) ??
				( Boolean( shown ) || open.has( item.id ) || 0 === depth ) );
		return (
			<li
				key={ item.id }
				role="treeitem"
				aria-expanded={ hasKids ? expanded : undefined }
				aria-selected={ selected === item.id }
				className="oml-cw-node"
			>
				<div
					className={ `oml-cw-row${
						selected === item.id ? ' is-selected' : ''
					}` }
					style={ { paddingInlineStart: `${ depth * 16 }px` } }
				>
					{ hasKids ? (
						<Button
							size="small"
							icon={
								expanded
									? 'arrow-down-alt2'
									: 'arrow-right-alt2'
							}
							label={
								expanded
									? sprintf(
											/* translators: %s: item name. */
											__( 'Collapse %s', 'ohmylms' ),
											item.name
										)
									: sprintf(
											/* translators: %s: item name. */
											__( 'Expand %s', 'ohmylms' ),
											item.name
										)
							}
							onClick={ () => toggle( item.id, expanded ) }
						/>
					) : (
						<span className="oml-cw-spacer" aria-hidden="true" />
					) }
					<button
						type="button"
						className="oml-cw-name"
						onClick={ () => onSelect( item.id ) }
					>
						<span>{ item.name }</span>
						<Badge>{ item.item_type }</Badge>
						{ summary( counts[ item.id ] ) && (
							<span className="oml-cw-counts">
								{ summary( counts[ item.id ] ) }
							</span>
						) }
					</button>
				</div>
				{ expanded && (
					<ul role="group" className="oml-cw-children">
						{ kids.map( ( kid ) => branch( kid, depth + 1 ) ) }
					</ul>
				) }
			</li>
		);
	}
	const roots = children.get( 0 ) ?? [];
	return (
		<nav
			className="oml-cw-tree"
			aria-label={ __( 'Curriculum', 'ohmylms' ) }
		>
			<div className="oml-cw-tree-head">
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Search curriculum', 'ohmylms' ) }
					hideLabelFromVision
					placeholder={ __( 'Search curriculum', 'ohmylms' ) }
					type="search"
					value={ search }
					onChange={ setSearch }
				/>
				<Button
					variant="primary"
					size="small"
					icon="plus"
					onClick={ onAdd }
				>
					{ __( 'Add', 'ohmylms' ) }
				</Button>
			</div>
			{ shown && 0 === shown.size && (
				<p className="oml-cw-empty">
					{ __( 'Nothing matches that search.', 'ohmylms' ) }
				</p>
			) }
			<ul role="tree" className="oml-cw-list">
				{ roots.map( ( item ) => branch( item, 0 ) ) }
			</ul>
		</nav>
	);
}
