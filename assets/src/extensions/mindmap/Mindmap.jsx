import { createElement, Fragment, useState, useId } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { buildMindmapTree } from './tree.mjs';

/**
 * Controlled tree mindmap. Data, selection, inheritance and node details belong to the caller.
 * @param root0
 * @param root0.items
 * @param root0.selected
 * @param root0.onSelectionChange
 * @param root0.includeDescendants
 * @param root0.title
 * @param root0.rootLabel
 * @param root0.description
 * @param root0.emptyMessage
 * @param root0.nodeHint
 * @param root0.renderNodeDetails
 * @param root0.hideNode
 */
export function Mindmap( {
	items,
	selected = [],
	onSelectionChange,
	includeDescendants = false,
	title,
	rootLabel,
	description,
	emptyMessage,
	nodeHint,
	renderNodeDetails,
	hideNode,
} ) {
	const _controlId = useId();
	const [ collapsed, setCollapsed ] = useState( new Set() );
	const selectedIds = new Set( selected.map( String ) );
	const roots = buildMindmapTree( items );
	const hasVisibleNode = ( node ) =>
		! hideNode?.( node ) || node.children.some( hasVisibleNode );
	const toggleBranch = ( id ) =>
		setCollapsed( ( current ) => {
			const next = new Set( current );
			if ( next.has( String( id ) ) ) {
				next.delete( String( id ) );
			} else {
				next.add( String( id ) );
			}
			return next;
		} );
	const renderBranch = ( node, inherited = false ) => {
		const explicit = selectedIds.has( String( node.id ) );
		const included = explicit || inherited;
		if ( hideNode?.( node ) ) {
			return node.children.map( ( child ) =>
				renderBranch( child, includeDescendants && included )
			);
		}
		const expanded = ! collapsed.has( String( node.id ) );
		const hasChildren = node.children.some( hasVisibleNode );
		return (
			<li key={ node.id }>
				<div
					className={ `ohmylms-mindmap-node${ included ? ' is-included' : '' }${ hasChildren && expanded ? ' has-children' : '' }` }
				>
					<div className="ohmylms-mindmap-node-heading">
						{ onSelectionChange ? (
							<label
								htmlFor={
									_controlId + '-field-1' + '-' + node.id
								}
							>
								{ onSelectionChange && (
									<input
										type="checkbox"
										checked={ included }
										disabled={ inherited && ! explicit }
										onChange={ () =>
											onSelectionChange(
												explicit
													? selected.filter(
															( id ) =>
																String( id ) !==
																String(
																	node.id
																)
														)
													: [ ...selected, node.id ]
											)
										}
										id={
											_controlId +
											'-field-1' +
											'-' +
											node.id
										}
									/>
								) }
								<span>{ node.label }</span>
							</label>
						) : (
							<span>{ node.label }</span>
						) }
						{ hasChildren && (
							<button
								type="button"
								aria-expanded={ expanded }
								aria-label={
									expanded
										? sprintf(
												/* translators: %s: item label. */ __(
													'Collapse %s',
													'ohmylms'
												),
												node.label
											)
										: sprintf(
												/* translators: %s: item label. */ __(
													'Expand %s',
													'ohmylms'
												),
												node.label
											)
								}
								onClick={ () => toggleBranch( node.id ) }
							>
								{ expanded ? '−' : '+' }
							</button>
						) }
					</div>
					{ nodeHint && (
						<small>
							{ nodeHint( node, {
								explicit,
								inherited,
								included,
							} ) }
						</small>
					) }
					{ renderNodeDetails?.( node, {
						explicit,
						inherited,
						included,
					} ) }
				</div>
				{ hasChildren && expanded && (
					<ul className="ohmylms-mindmap-branches">
						{ node.children.map( ( child ) =>
							renderBranch(
								child,
								includeDescendants && included
							)
						) }
					</ul>
				) }
			</li>
		);
	};
	return (
		<details className="ohmylms-mindmap" open>
			<summary>{ title }</summary>
			{ description && <p>{ description }</p> }
			{ roots.some( hasVisibleNode ) ? (
				<Fragment>
					<div className="ohmylms-mindmap-actions">
						<button
							type="button"
							className="button"
							onClick={ () => setCollapsed( new Set() ) }
						>
							{ __( 'Expand all', 'ohmylms' ) }
						</button>
						<button
							type="button"
							className="button"
							onClick={ () =>
								setCollapsed(
									new Set(
										items.map( ( item ) =>
											String( item.id )
										)
									)
								)
							}
						>
							{ __( 'Collapse all', 'ohmylms' ) }
						</button>
					</div>
					<div
						className="ohmylms-mindmap-scroll"
						tabIndex={ 0 }
						role="region"
						aria-label={ title }
					>
						<div className="ohmylms-mindmap-stage">
							<div className="ohmylms-mindmap-root">
								{ rootLabel }
							</div>
							<ul className="ohmylms-mindmap-branches">
								{ roots.map( ( node ) =>
									renderBranch( node )
								) }
							</ul>
						</div>
					</div>
				</Fragment>
			) : (
				<p>{ emptyMessage || __( 'No items yet.', 'ohmylms' ) }</p>
			) }
		</details>
	);
}
