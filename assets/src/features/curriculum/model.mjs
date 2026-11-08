/**
 * Pure helpers for the curriculum accordion editor. The server owns every rule (it validates
 * parents, cycles, depth and types again on each write); these functions let the editor
 * disable invalid choices up front and keep UI state, such as which branches are open,
 * independent of the data so edits never collapse the tree.
 */

export const DEFAULT_MAX_DEPTH = 10;
export const NAME_LIMIT = 190;
export const DESCRIPTION_LIMIT = 2000;
export const SHORT_LIMIT = 60;
export const LINK_TYPES = [ 'course', 'skill', 'bank', 'quiz' ];

/**
 * @typedef {Object} CurriculumItem
 * @property {number}                 id            Item ID.
 * @property {number|null}            [parent_id]   Parent item ID, 0 or empty at the top level.
 * @property {number}                 position      Position among siblings.
 * @property {string}                 [name]        Display name.
 * @property {string}                 [item_type]   Type slug.
 * @property {string}                 [description] Description.
 * @property {string}                 [code]        Short code.
 * @property {string}                 [version]     Version label.
 * @property {boolean}                [is_syllabus] Whether the item is a syllabus.
 * @property {number}                 [child_count] Visible children.
 * @property {Record<string, number>} [links]       Link counts by type.
 */

/**
 * @typedef {Object} TreeNode
 * @property {CurriculumItem} item     Item.
 * @property {number}         depth    Depth with a root at 0.
 * @property {TreeNode[]}     children Nested children.
 */

/**
 * @typedef {Object} SearchState
 * @property {boolean}     active  Whether a query is set.
 * @property {Set<number>} matches Matching item IDs.
 * @property {Set<number>} reveal  Ancestors of the matches.
 */

/**
 * @typedef {Object} ViewState
 * @property {Set<number>}          expanded  Branches the administrator opened.
 * @property {Map<number, boolean>} overrides Open state during one search.
 */

/** @typedef {'name'|'item_type'|'description'|'code'|'version'} EditableField */

/**
 * @param {CurriculumItem} left
 * @param {CurriculumItem} right
 */
const byPosition = ( left, right ) =>
	left.position - right.position || left.id - right.id;

/**
 * @template {CurriculumItem} T
 * @param {T[]|null} [items]
 * @return {Map<number, T>} Items by ID.
 */
export function indexItems( items ) {
	return new Map( ( items || [] ).map( ( item ) => [ item.id, item ] ) );
}

/**
 * Hide topic rows and promote their children to the nearest visible ancestor.
 * @param {CurriculumItem[]} [items]
 */
export function withoutTopics( items = [] ) {
	const index = indexItems( items );
	const visible = items
		.filter( ( item ) => item.item_type !== 'topic' )
		.map( ( item ) => {
			let parent = item.parent_id || 0;
			const seen = new Set( [ item.id ] );
			while (
				index.get( parent )?.item_type === 'topic' &&
				! seen.has( parent )
			) {
				seen.add( parent );
				parent = index.get( parent )?.parent_id || 0;
			}
			return {
				...item,
				parent_id: seen.has( parent ) ? 0 : parent,
				child_count: 0,
			};
		} );
	const parents = indexItems( visible );
	for ( const item of visible ) {
		const parent = parents.get( item.parent_id );
		if ( parent ) {
			parent.child_count++;
		}
	}
	return visible;
}

/**
 * Parent ID => children ordered by sibling position.
 * @param {CurriculumItem[]|null} [items]
 * @return {Map<number, CurriculumItem[]>} Children by parent ID.
 */
export function childrenOf( items ) {
	/** @type {Map<number, CurriculumItem[]>} */
	const map = new Map();
	for ( const item of items || [] ) {
		const parent = item.parent_id || 0;
		if ( ! map.has( parent ) ) {
			map.set( parent, [] );
		}
		/** @type {CurriculumItem[]} */ ( map.get( parent ) ).push( item );
	}
	for ( const list of map.values() ) {
		list.sort( byPosition );
	}
	return map;
}

/**
 * Nest a flat list. An item whose parent is missing surfaces as a root and a loop is broken,
 * so corrupted data is shown rather than silently hidden.
 * @param {CurriculumItem[]|null} [items]
 * @return {TreeNode[]} Root nodes.
 */
export function buildTree( items ) {
	const byId = indexItems( items );
	const children = childrenOf( items );
	const seen = new Set();
	/** @type {( item: CurriculumItem, depth: number ) => TreeNode} */
	const build = ( item, depth ) => {
		seen.add( item.id );
		const nested = ( children.get( item.id ) || [] ).filter(
			( child ) => ! seen.has( child.id )
		);
		return {
			item,
			depth,
			children: nested.map( ( child ) => build( child, depth + 1 ) ),
		};
	};
	const roots = ( items || [] )
		.filter( ( item ) => ! item.parent_id || ! byId.has( item.parent_id ) )
		.sort( byPosition );
	const tree = roots.map( ( root ) => build( root, 0 ) );
	for ( const item of items || [] ) {
		if ( ! seen.has( item.id ) ) {
			tree.push( build( item, 0 ) );
		}
	}
	return tree;
}

/**
 * Depth-first node list.
 * @param {TreeNode[]} tree
 * @return {TreeNode[]} Nodes.
 */
export function flattenTree( tree ) {
	return tree.flatMap( ( node ) => [
		node,
		...flattenTree( node.children ),
	] );
}

/**
 * Ancestor IDs of an item, nearest parent first. Stops at a root, a gap or a loop.
 * @param {CurriculumItem[]|null}       items
 * @param {number}                      id
 * @param {Map<number, CurriculumItem>} [byId]
 * @return {number[]} Ancestor IDs.
 */
export function ancestorIds( items, id, byId = indexItems( items ) ) {
	const result = [];
	const seen = new Set( [ id ] );
	let current = byId.get( id )?.parent_id || 0;
	while ( current && byId.has( current ) && ! seen.has( current ) ) {
		result.push( current );
		seen.add( current );
		current = byId.get( current )?.parent_id || 0;
	}
	return result;
}

/**
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 * @return {number[]} Descendant IDs, breadth first.
 */
export function descendantIds( items, id ) {
	const children = childrenOf( items );
	const result = [];
	const seen = new Set( [ id ] );
	const queue = [ id ];
	while ( queue.length ) {
		for ( const child of children.get(
			/** @type {number} */ ( queue.shift() )
		) || [] ) {
			if ( seen.has( child.id ) ) {
				continue;
			}
			seen.add( child.id );
			result.push( child.id );
			queue.push( child.id );
		}
	}
	return result;
}

/**
 * Depth with a root at 1.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 */
export function depthOf( items, id ) {
	return 1 + ancestorIds( items, id ).length;
}

/**
 * Levels in the subtree under an item, counting the item itself as 1.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 */
export function heightOf( items, id ) {
	const children = childrenOf( items );
	const seen = new Set( [ id ] );
	let level = [ id ];
	let height = 1;
	while ( level.length ) {
		const next = [];
		for ( const current of level ) {
			for ( const child of children.get( current ) || [] ) {
				if ( seen.has( child.id ) ) {
					continue;
				}
				seen.add( child.id );
				next.push( child.id );
			}
		}
		if ( next.length ) {
			height++;
		}
		level = next;
	}
	return height;
}

/**
 * Names from the root down to the item's parent.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 */
export function pathNames( items, id ) {
	const byId = indexItems( items );
	return ancestorIds( items, id, byId )
		.reverse()
		.map( ( ancestor ) => byId.get( ancestor )?.name );
}

/**
 * Syllabus shortcuts in tree order, retaining each complete curriculum path.
 *
 * @param {CurriculumItem[]} items Stored curriculum items.
 * @param {TreeNode[]}       tree  Hidden descendant branches, optionally filtered by search.
 * @return {Array<{item: CurriculumItem, ancestors: Array<CurriculumItem|undefined>, path: Array<string|undefined>}>} Syllabus items and their breadcrumb names.
 */
export function syllabusBreadcrumbs( items, tree ) {
	const byId = indexItems( items );
	return flattenTree( tree )
		.filter( ( node ) => node.item.is_syllabus )
		.map( ( { item } ) => ( {
			item,
			ancestors: ancestorIds( items, item.id, byId )
				.reverse()
				.map( ( id ) => byId.get( id ) ),
			path: [ ...pathNames( items, item.id ), item.name ],
		} ) );
}

/**
 * Open a breadcrumb parent and the ancestors needed to reveal it.
 *
 * @param {ViewState}        state  Expansion state.
 * @param {SearchState}      search Current search state.
 * @param {CurriculumItem[]} items  Visible curriculum items.
 * @param {number}           id     Parent to reveal.
 * @return {ViewState} Updated expansion state.
 */
export function revealBranch( state, search, items, id ) {
	const ids = [ ...ancestorIds( items, id ), id ];
	const expanded = new Set( state.expanded );
	const overrides = new Map( state.overrides );
	for ( const parent of ids ) {
		expanded.add( parent );
		if ( search.active ) {
			overrides.set( parent, true );
		}
	}
	return { ...state, expanded, overrides };
}

/**
 * May an item move under a parent (0 = top level)? Mirrors the server: never under itself or
 * one of its own descendants, and never beyond the maximum depth.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 * @param {number|null}           [parentId]
 * @param {number}                [maxDepth]
 * @return {{ok: boolean, reason?: string}} Result.
 */
export function validateMove(
	items,
	id,
	parentId,
	maxDepth = DEFAULT_MAX_DEPTH
) {
	const byId = indexItems( items );
	parentId = parentId || 0;
	if ( ! byId.has( id ) ) {
		return { ok: false, reason: 'missing' };
	}
	if ( parentId && ! byId.has( parentId ) ) {
		return { ok: false, reason: 'missing-parent' };
	}
	if ( parentId === id ) {
		return { ok: false, reason: 'self' };
	}
	if ( parentId && descendantIds( items, id ).includes( parentId ) ) {
		return { ok: false, reason: 'cycle' };
	}
	const base = parentId ? depthOf( items, parentId ) : 0;
	if ( base + heightOf( items, id ) > maxDepth ) {
		return { ok: false, reason: 'depth' };
	}
	return { ok: true };
}

/**
 * Parents an item can legally move under, in tree order with their depth for indented menus.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 * @param {number}                [maxDepth]
 */
export function moveTargets( items, id, maxDepth = DEFAULT_MAX_DEPTH ) {
	return flattenTree( buildTree( items ) )
		.filter(
			( node ) => validateMove( items, id, node.item.id, maxDepth ).ok
		)
		.map( ( node ) => ( {
			id: node.item.id,
			depth: node.depth,
			name: node.item.name,
		} ) );
}

/**
 * Where an item sits among its siblings.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 */
export function siblingInfo( items, id ) {
	const byId = indexItems( items );
	const parent = byId.get( id )?.parent_id || 0;
	const siblings = ( childrenOf( items ).get( parent ) || [] ).map(
		( sibling ) => sibling.id
	);
	return {
		parentId: parent,
		index: siblings.indexOf( id ),
		count: siblings.length,
	};
}

/**
 * Target for moving one step up (-1) or down (+1) among siblings, or null at the edge.
 * @param {CurriculumItem[]|null} items
 * @param {number}                id
 * @param {number}                direction
 */
export function siblingMove( items, id, direction ) {
	const { parentId, index, count } = siblingInfo( items, id );
	const target = index + direction;
	if ( index < 0 || target < 0 || target >= count ) {
		return null;
	}
	return { parentId, position: target };
}

/**
 * @param {unknown} value
 */
export function normalizeText( value ) {
	return String( value ?? '' )
		.normalize( 'NFKC' )
		.toLocaleLowerCase();
}

/**
 * Search names, codes, versions, types and descriptions. `matches` are the hits and `reveal`
 * their ancestors, which the tree shows open so each hit is visible inside its branch.
 * @param {CurriculumItem[]|null} items
 * @param {unknown}               query
 * @return {SearchState} Search state.
 */
export function searchItems( items, query ) {
	const terms = normalizeText( query )
		.trim()
		.split( /\s+/ )
		.filter( Boolean );
	if ( ! terms.length ) {
		return { active: false, matches: new Set(), reveal: new Set() };
	}
	const byId = indexItems( items );
	/** @type {Set<number>} */
	const matches = new Set();
	/** @type {Set<number>} */
	const reveal = new Set();
	for ( const item of items || [] ) {
		const haystack = normalizeText(
			[
				item.name,
				item.code,
				item.version,
				item.item_type,
				item.description,
			].join( ' ' )
		);
		if ( ! terms.every( ( term ) => haystack.includes( term ) ) ) {
			continue;
		}
		matches.add( item.id );
		for ( const ancestor of ancestorIds( items, item.id, byId ) ) {
			reveal.add( ancestor );
		}
	}
	return { active: true, matches, reveal };
}

/**
 * Keep only hits and their ancestors while a search is active.
 * @param {TreeNode[]}  tree
 * @param {SearchState} search
 * @return {TreeNode[]} Visible nodes.
 */
export function filterTree( tree, search ) {
	if ( ! search.active ) {
		return tree;
	}
	return tree
		.filter(
			( node ) =>
				search.matches.has( node.item.id ) ||
				search.reveal.has( node.item.id )
		)
		.map( ( node ) => ( {
			...node,
			children: filterTree( node.children, search ),
		} ) );
}

/**
 * Open/closed state lives outside the data: `expanded` is the administrator's own choice and
 * survives edits, reloads and searches; `overrides` only adjusts branches during one search.
 * @param {Iterable<number>} [expanded]
 * @return {ViewState} View state.
 */
export function createViewState( expanded = [] ) {
	return { expanded: new Set( expanded ), overrides: new Map() };
}

/**
 * @param {ViewState}   state
 * @param {SearchState} search
 * @param {number}      id
 */
export function isOpen( state, search, id ) {
	if ( search.active ) {
		return state.overrides.has( id )
			? state.overrides.get( id )
			: search.reveal.has( id );
	}
	return state.expanded.has( id );
}

/**
 * @param {ViewState}   state
 * @param {SearchState} search
 * @param {number}      id
 * @return {ViewState} Updated state.
 */
export function toggleOpen( state, search, id ) {
	const open = isOpen( state, search, id );
	if ( search.active ) {
		const overrides = new Map( state.overrides );
		overrides.set( id, ! open );
		return { ...state, overrides };
	}
	const expanded = new Set( state.expanded );
	if ( open ) {
		expanded.delete( id );
	} else {
		expanded.add( id );
	}
	return { ...state, expanded };
}

/**
 * Open a branch (for example the parent of a newly added child) without toggling it shut.
 * @param {ViewState} state
 * @param {number}    id
 * @return {ViewState} Updated state.
 */
export function ensureOpen( state, id ) {
	if ( state.expanded.has( id ) ) {
		return state;
	}
	return { ...state, expanded: new Set( [ ...state.expanded, id ] ) };
}

/**
 * @param {ViewState} state
 * @return {ViewState} State without search overrides.
 */
export function resetOverrides( state ) {
	return state.overrides.size ? { ...state, overrides: new Map() } : state;
}

/**
 * @param {CurriculumItem[]|null} [items]
 * @return {ViewState} State with every branch open.
 */
export function expandAll( items ) {
	const children = childrenOf( items );
	return {
		expanded: new Set(
			( items || [] )
				.filter( ( item ) => children.has( item.id ) )
				.map( ( item ) => item.id )
		),
		overrides: new Map(),
	};
}

export function collapseAll() {
	return createViewState();
}

/**
 * Forget items that no longer exist; every surviving branch keeps its state.
 * @param {ViewState}             state
 * @param {CurriculumItem[]|null} [items]
 * @return {ViewState} Pruned state.
 */
export function pruneState( state, items ) {
	const ids = new Set( ( items || [] ).map( ( item ) => item.id ) );
	const expanded = new Set(
		[ ...state.expanded ].filter( ( id ) => ids.has( id ) )
	);
	const overrides = new Map(
		[ ...state.overrides ].filter( ( [ id ] ) => ids.has( id ) )
	);
	if (
		expanded.size === state.expanded.size &&
		overrides.size === state.overrides.size
	) {
		return state;
	}
	return { expanded, overrides };
}

/**
 * Type slugs are lowercase letters, numbers, hyphens and underscores; spaces become hyphens.
 * @param {unknown} value
 */
export function normalizeType( value ) {
	return String( value ?? '' )
		.trim()
		.toLowerCase()
		.replace( /\s+/g, '-' )
		.replace( /[^a-z0-9_-]/g, '' );
}

/**
 * @param {unknown} type
 */
export function typeLabel( type ) {
	const text = String( type || '' )
		.replace( /[-_]+/g, ' ' )
		.trim();
	return text ? text.charAt( 0 ).toUpperCase() + text.slice( 1 ) : '';
}

/**
 * Client-side hints that mirror the server's field rules. Keys with a problem map to a message code.
 * @param {Record<string, unknown>|null} [draft]
 * @return {Record<string, string>} Message codes by field.
 */
export function validateDraft( draft ) {
	/** @type {Record<string, string>} */
	const errors = {};
	const name = String( draft?.name ?? '' ).trim();
	if ( ! name ) {
		errors.name = 'required';
	} else if ( name.length > NAME_LIMIT ) {
		errors.name = 'too-long';
	}
	const type = normalizeType( draft?.item_type );
	if ( type && ! /^[a-z]/.test( type ) ) {
		errors.item_type = 'invalid';
	} else if ( type.length > 40 ) {
		errors.item_type = 'too-long';
	}
	if ( String( draft?.description ?? '' ).length > DESCRIPTION_LIMIT ) {
		errors.description = 'too-long';
	}
	for ( const key of [ 'code', 'version' ] ) {
		if ( String( draft?.[ key ] ?? '' ).trim().length > SHORT_LIMIT ) {
			errors[ key ] = 'too-long';
		}
	}
	return errors;
}

/**
 * Fields the editor can change.
 * @type {EditableField[]}
 */
export const EDITABLE_FIELDS = [
	'name',
	'item_type',
	'description',
	'code',
	'version',
];

/**
 * @param {Partial<CurriculumItem>|null} [item]
 */
export function draftFrom( item ) {
	return Object.fromEntries(
		EDITABLE_FIELDS.map( ( key ) => [ key, item?.[ key ] ?? '' ] )
	);
}

/**
 * Payload for a save: trimmed, with the type normalized.
 * @param {Record<string, unknown>} draft
 */
export function payloadFrom( draft ) {
	return {
		name: String( draft.name ?? '' ).trim(),
		item_type: normalizeType( draft.item_type ) || 'custom',
		description: String( draft.description ?? '' ),
		code: String( draft.code ?? '' ).trim(),
		version: String( draft.version ?? '' ).trim(),
	};
}

/**
 * @param {Record<string, unknown>}      draft
 * @param {Partial<CurriculumItem>|null} [item]
 */
export function isDirty( draft, item ) {
	const clean = payloadFrom( draft );
	return EDITABLE_FIELDS.some(
		( key ) => clean[ key ] !== String( item?.[ key ] ?? '' )
	);
}

/**
 * @param {Partial<CurriculumItem>|null} [item]
 */
export function linkTotal( item ) {
	return LINK_TYPES.reduce(
		( sum, type ) => sum + ( item?.links?.[ type ] || 0 ),
		0
	);
}

/**
 * What deleting this item would affect, from the dependents the server reports.
 * @param {Record<string, number>|null} [dependents]
 * @param {string}                      [strategy]
 */
export function needsConfirmation( dependents, strategy ) {
	if ( ! dependents ) {
		return false;
	}
	const branch = strategy === 'delete';
	return Boolean(
		dependents.children ||
		( branch ? dependents.links : dependents.own_links ) ||
		( branch ? dependents.groups : dependents.own_groups )
	);
}
