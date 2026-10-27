import { SkillConnections } from './SkillConnections';
import {
	createElement,
	useEffect,
	useMemo,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import { Button, SearchControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useWorkspace } from './context';
import { groupLabel, skillLabel } from './syllabus.mjs';
import { skillText } from './skillTranslations.mjs';
import {
	ancestorKeys,
	containerOf,
	outlineDropPosition,
	topicLabel,
} from './workspace.mjs';
import { KindIcon, QuickAdd } from './WorkspaceParts';
import { layoutSyllabusMap, MAP_CARD } from './syllabusMap.mjs';
import { MAP_GUTTER, useMapNavigation } from './useMapNavigation';

/**
 * Display the same labels as the syllabus outline.
 *
 * @param {Object} node     Outline node.
 * @param {string} language Selected skill language.
 * @return {string} Display label.
 */
function mapLabel( node, language ) {
	if ( node.kind === 'content' ) {
		return topicLabel( node.content );
	}
	if ( node.kind === 'group' ) {
		return groupLabel( node.group );
	}
	return skillLabel( {
		...node.skill,
		name: skillText( node.skill, language ),
	} );
}

/**
 * Syllabus planning canvas; edits use the workspace's existing server actions.
 *
 * @return {Object} Interactive syllabus map.
 */
export function SyllabusMindmap() {
	const w = useWorkspace();
	const [ collapsed, setCollapsed ] = useState( new Set() );
	const [ query, setQuery ] = useState( '' );
	const [ adding, setAdding ] = useState( null );
	const [ moving, setMoving ] = useState( '' );
	const [ moveTarget, setMoveTarget ] = useState( '' );
	const [ movePosition, setMovePosition ] = useState( 'before' );
	const [ dragged, setDragged ] = useState( '' );
	const [ target, setTarget ] = useState( null );
	const nodeDrag = useRef( null );
	const controlId = useId();
	const label = ( node ) => mapLabel( node, w.skillLanguage );
	const layout = useMemo(
		() =>
			layoutSyllabusMap( w.tree.root, collapsed, query, ( node ) =>
				mapLabel( node, w.skillLanguage )
			),
		[ w.tree, w.skillLanguage, collapsed, query ]
	);
	const navigation = useMapNavigation( layout );
	const { viewport, zoom, center } = navigation;
	const endDrag = () => {
		nodeDrag.current = null;
		setDragged( '' );
		setTarget( null );
	};
	const focusSelected = () => {
		setQuery( '' );
		setCollapsed( ( previous ) => {
			const next = new Set( previous );
			for ( const key of ancestorKeys( w.tree.index, w.selected ) ) {
				next.delete( key );
			}
			return next;
		} );
		const selected = layout.nodes.find(
			( entry ) => entry.node.key === w.selected
		);
		if ( selected ) {
			center(
				selected.x + MAP_CARD.width / 2,
				selected.y + ( layout.cardHeight || MAP_CARD.height ) / 2
			);
		}
	};
	useEffect( () => {
		const needle = query.trim().toLocaleLowerCase();
		const selected =
			layout.nodes.find( ( entry ) =>
				needle
					? mapLabel( entry.node, w.skillLanguage )
							.toLocaleLowerCase()
							.includes( needle )
					: entry.node.key === w.selected
			) || layout.nodes[ 0 ];
		if ( selected ) {
			center(
				selected.x + MAP_CARD.width / 2,
				selected.y + ( layout.cardHeight || MAP_CARD.height ) / 2
			);
		}
	}, [ layout, w.selected, query, w.skillLanguage, center ] );
	useEffect( () => {
		setCollapsed( ( previous ) => {
			const next = new Set( previous );
			next.delete( w.selected );
			for ( const key of ancestorKeys( w.tree.index, w.selected ) ) {
				next.delete( key );
			}
			return next;
		} );
	}, [ w.selected, w.tree ] );
	const placement = ( event, node ) => {
		const source = w.tree.index.get( nodeDrag.current?.key || dragged );
		const box = event.currentTarget.getBoundingClientRect();
		const fraction = ( event.clientY - box.top ) / box.height;
		if (
			node === w.tree.root ||
			( node.kind === 'content' &&
				source?.kind !== 'skill' &&
				fraction > 0.25 &&
				fraction < 0.75 )
		) {
			return 'inside';
		}
		return fraction > 0.5;
	};
	const openMove = ( node ) => {
		w.select( node.key );
		setMoving( node.key );
		setMoveTarget( '' );
		setMovePosition( node.kind === 'skill' ? 'before' : 'inside' );
	};
	const dragMove = ( event ) => {
		const drag = nodeDrag.current;
		if ( ! drag || drag.id !== event.pointerId || w.pending ) {
			return;
		}
		event.stopPropagation();
		if (
			! drag.started &&
			Math.hypot( event.clientX - drag.x, event.clientY - drag.y ) < 6
		) {
			return;
		}
		drag.started = true;
		setDragged( drag.key );
		const element = viewport.current;
		const box = element.getBoundingClientRect();
		let left = 0;
		let top = 0;
		if ( event.clientX < box.left + 48 ) {
			left = -24;
		} else if ( event.clientX > box.right - 48 ) {
			left = 24;
		}
		if ( event.clientY < box.top + 48 ) {
			top = -24;
		} else if ( event.clientY > box.bottom - 48 ) {
			top = 24;
		}
		element.scrollBy( { left, top } );
		const card = document
			.elementFromPoint( event.clientX, event.clientY )
			?.closest( '[data-node-key]' );
		const node =
			card && element.contains( card )
				? w.tree.index.get( card.dataset.nodeKey )
				: null;
		const after = node
			? placement( { currentTarget: card, clientY: event.clientY }, node )
			: false;
		drag.target =
			node &&
			outlineDropPosition( w.tree, drag.key, node.key, after ) !== null
				? { key: node.key, after }
				: null;
		setTarget( drag.target );
	};
	const startAdding = ( node, kind ) => {
		w.select( node.key );
		setAdding( { node, kind } );
	};
	const moveAfter =
		movePosition === 'inside' ? 'inside' : movePosition === 'after';
	const moveOptions = moving
		? [ ...w.tree.index.values() ].filter(
				( node ) =>
					outlineDropPosition(
						w.tree,
						moving,
						node.key,
						moveAfter
					) !== null
			)
		: [];
	const destination = moveOptions.some( ( node ) => node.key === moveTarget )
		? moveTarget
		: moveOptions[ 0 ]?.key || '';
	const add = async ( data ) => {
		const { kind } = adding;
		const node = w.tree.index.get( adding.node.key );
		if ( ! node ) {
			return false;
		}
		const folder = containerOf( w.tree, node.key );
		let ok;
		if ( kind === 'folder' ) {
			ok = await w.actions.addTopic( folder.id, data );
		} else if ( node.kind === 'group' || node.kind === 'skill' ) {
			ok = await w.actions.addSkill(
				node.kind === 'group' ? node.id : node.groupId,
				data
			);
		} else {
			ok = await w.actions.addSkillInTopic( folder.id, data );
		}
		if ( ok ) {
			setCollapsed( ( previous ) => {
				const next = new Set( previous );
				next.delete( node.key );
				return next;
			} );
			setQuery( '' );
		}
		return ok;
	};
	return (
		<section
			className="ohmylms-syllabus-map"
			aria-label={ __( 'Syllabus mind map', 'ohmylms' ) }
		>
			<div className="ohmylms-syllabus-map-toolbar">
				<SearchControl
					label={ __( 'Find a folder or skill', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<Button
					variant="secondary"
					onClick={ () => setCollapsed( new Set() ) }
				>
					{ __( 'Expand all', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () =>
						setCollapsed(
							new Set(
								[ ...w.tree.index.keys() ].filter(
									( key ) => key !== w.tree.root.key
								)
							)
						)
					}
				>
					{ __( 'Collapse all', 'ohmylms' ) }
				</Button>
				<Button
					label={ __( 'Zoom out', 'ohmylms' ) }
					disabled={ zoom <= navigation.minZoom }
					onClick={ navigation.zoomOut }
				>
					−
				</Button>
				<Button
					label={ __( 'Reset zoom', 'ohmylms' ) }
					onClick={ navigation.resetZoom }
				>
					{ Math.round( zoom * 100 ) }%
				</Button>
				<Button
					label={ __( 'Zoom in', 'ohmylms' ) }
					disabled={ zoom >= navigation.maxZoom }
					onClick={ navigation.zoomIn }
				>
					+
				</Button>
				<Button variant="secondary" onClick={ navigation.fit }>
					{ __( 'Fit map', 'ohmylms' ) }
				</Button>
				<Button variant="secondary" onClick={ focusSelected }>
					{ __( 'Find selected', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ w.pending || w.selected === w.tree.root.key }
					onClick={ () => {
						setMoving( w.selected );
						setMoveTarget( '' );
						setMovePosition(
							w.tree.index.get( w.selected )?.kind === 'skill'
								? 'before'
								: 'inside'
						);
					} }
				>
					{ __( 'Move selected', 'ohmylms' ) }
				</Button>
			</div>
			<p className="ohmylms-syllabus-map-help">
				{ __(
					'Drag the background to pan. Scroll with two fingers; pinch on a trackpad or Ctrl + scroll to zoom. Use a node’s Move handle to drag it, or select it and choose Move selected.',
					'ohmylms'
				) }
			</p>
			{ moving && (
				<form
					className="ohmylms-syllabus-map-move"
					onSubmit={ async ( event ) => {
						event.preventDefault();
						if (
							destination &&
							! w.pending &&
							( await w.actions.reorderOutline(
								moving,
								destination,
								moveAfter
							) )
						) {
							setMoving( '' );
						}
					} }
				>
					<strong>
						{ sprintf(
							/* translators: %s: node name. */ __(
								'Move %s',
								'ohmylms'
							),
							label( w.tree.index.get( moving ) || w.tree.root )
						) }
					</strong>
					<label htmlFor={ `${ controlId }-position` }>
						{ __( 'Placement', 'ohmylms' ) }
					</label>
					<select
						id={ `${ controlId }-position` }
						value={ movePosition }
						onChange={ ( event ) =>
							setMovePosition( event.target.value )
						}
						disabled={ w.pending }
					>
						<option value="inside">
							{ __( 'Inside folder', 'ohmylms' ) }
						</option>
						<option value="before">
							{ __( 'Before node', 'ohmylms' ) }
						</option>
						<option value="after">
							{ __( 'After node', 'ohmylms' ) }
						</option>
					</select>
					<label htmlFor={ `${ controlId }-destination` }>
						{ __( 'Destination', 'ohmylms' ) }
					</label>
					<select
						id={ `${ controlId }-destination` }
						value={ destination }
						onChange={ ( event ) =>
							setMoveTarget( event.target.value )
						}
						disabled={ w.pending || ! destination }
					>
						{ ! destination && (
							<option value="">
								{ __( 'No available destinations', 'ohmylms' ) }
							</option>
						) }
						{ moveOptions.map( ( node ) => (
							<option key={ node.key } value={ node.key }>
								{ [
									...ancestorKeys( w.tree.index, node.key )
										.reverse()
										.map( ( key ) =>
											label( w.tree.index.get( key ) )
										),
									label( node ),
								].join( ' / ' ) }
							</option>
						) ) }
					</select>
					<Button
						type="submit"
						variant="primary"
						disabled={ w.pending || ! destination }
					>
						{ __( 'Move node', 'ohmylms' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => setMoving( '' ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</form>
			) }
			{ adding && (
				<QuickAdd
					key={ `${ adding.node.key }:${ adding.kind }` }
					nameLabel={
						adding.kind === 'folder'
							? __( 'Folder name', 'ohmylms' )
							: __( 'Skill name', 'ohmylms' )
					}
					codeLabel={ __( 'Code', 'ohmylms' ) }
					submitLabel={
						adding.kind === 'folder'
							? __( 'Add folder', 'ohmylms' )
							: __( 'Add skill', 'ohmylms' )
					}
					help={ sprintf(
						/* translators: %s: destination folder name. */ __(
							'Adding inside %s.',
							'ohmylms'
						),
						label( adding.node )
					) }
					pending={ w.pending }
					onAdd={ add }
					onCancel={ () => setAdding( null ) }
				/>
			) }
			<div
				className={ `ohmylms-syllabus-map-viewport${ navigation.panning ? ' is-panning' : '' }${ dragged ? ' is-moving-node' : '' }` }
				ref={ viewport }
				tabIndex={ 0 }
				role="region"
				aria-label={ __( 'Scrollable syllabus map', 'ohmylms' ) }
				{ ...navigation.pointerHandlers }
			>
				{ ! layout.nodes.length && (
					<p role="status">
						{ __( 'No matching folders or skills.', 'ohmylms' ) }
					</p>
				) }
				<div
					style={ {
						width: layout.width * zoom + MAP_GUTTER * 2,
						height: layout.height * zoom + MAP_GUTTER * 2,
					} }
				>
					<div
						className="ohmylms-syllabus-map-stage"
						style={ {
							width: layout.width,
							height: layout.height,
							transform: `scale(${ zoom })`,
							left: MAP_GUTTER,
							top: MAP_GUTTER,
						} }
					>
						<svg
							className="ohmylms-syllabus-map-lines"
							width={ layout.width }
							height={ layout.height }
							aria-hidden="true"
							focusable="false"
						>
							{ layout.edges.map( ( { from, to } ) => {
								const x = from.x + MAP_CARD.width;
								const y =
									from.y +
									( layout.cardHeight || MAP_CARD.height ) /
										2;
								const endY =
									to.y +
									( layout.cardHeight || MAP_CARD.height ) /
										2;
								return (
									<path
										key={ to.node.key }
										d={ `M ${ x } ${ y } C ${ x + 30 } ${ y }, ${ to.x - 30 } ${ endY }, ${ to.x } ${ endY }` }
									/>
								);
							} ) }
						</svg>
						{ layout.nodes.map( ( { node, x, y } ) => (
							<div
								key={ node.key }
								data-node-key={ node.key }
								data-drop-position={
									target?.key === node.key
										? String( target.after )
										: undefined
								}
								className={ `ohmylms-syllabus-map-card is-${ node.kind }${ w.selected === node.key ? ' is-selected' : '' }${ target?.key === node.key ? ' is-drop-target' : '' }` }
								style={ {
									left: x,
									top: y,
									width: MAP_CARD.width,
									height:
										layout.cardHeight || MAP_CARD.height,
								} }
							>
								{ target?.key === node.key && (
									<span className="ohmylms-syllabus-map-drop-label">
										{
											{
												inside: __(
													'Place inside',
													'ohmylms'
												),
												true: __(
													'Place after',
													'ohmylms'
												),
												false: __(
													'Place before',
													'ohmylms'
												),
											}[ String( target.after ) ]
										}
									</span>
								) }
								<button
									type="button"
									className="ohmylms-syllabus-map-select"
									title={ label( node ) }
									aria-current={
										w.selected === node.key
											? 'true'
											: undefined
									}
									onClick={ () => w.select( node.key ) }
								>
									<KindIcon
										kind={
											{
												content: 'topic',
												group: 'chapter',
												skill: 'skill',
											}[ node.kind ]
										}
									/>
									<span>{ label( node ) }</span>
								</button>
								{ node.kind === 'skill' && (
									<SkillConnections skill={ node.skill } />
								) }
								<div className="ohmylms-syllabus-map-node-actions">
									{ node.parentKey && (
										<button
											type="button"
											className="ohmylms-syllabus-map-move-handle"
											disabled={ w.pending }
											aria-label={ sprintf(
												/* translators: %s: node name. */ __(
													'Drag to move %s',
													'ohmylms'
												),
												label( node )
											) }
											title={ __(
												'Drag to move, or click for move options',
												'ohmylms'
											) }
											onPointerDown={ ( event ) => {
												if (
													w.pending ||
													event.button !== 0
												) {
													return;
												}
												event.preventDefault();
												event.stopPropagation();
												event.currentTarget.setPointerCapture(
													event.pointerId
												);
												nodeDrag.current = {
													key: node.key,
													id: event.pointerId,
													x: event.clientX,
													y: event.clientY,
													started: false,
													target: null,
												};
											} }
											onPointerMove={ dragMove }
											onPointerUp={ ( event ) => {
												event.stopPropagation();
												const drag = nodeDrag.current;
												if (
													! drag ||
													drag.id !== event.pointerId
												) {
													return;
												}
												if (
													event.currentTarget.hasPointerCapture(
														event.pointerId
													)
												) {
													event.currentTarget.releasePointerCapture(
														event.pointerId
													);
												}
												if (
													! w.pending &&
													drag.started &&
													drag.target
												) {
													w.actions.reorderOutline(
														drag.key,
														drag.target.key,
														drag.target.after
													);
												}
												if ( ! drag.started ) {
													openMove( node );
												}
												endDrag();
											} }
											onPointerCancel={ endDrag }
											onLostPointerCapture={ endDrag }
											onClick={ ( event ) => {
												if ( event.detail === 0 ) {
													openMove( node );
												}
											} }
										>
											{ __( '↕ Move', 'ohmylms' ) }
										</button>
									) }
									{ node.kind !== 'skill' && (
										<Button
											size="small"
											disabled={ w.pending }
											onClick={ () =>
												startAdding( node, 'skill' )
											}
										>
											{ __( '+ Skill', 'ohmylms' ) }
										</Button>
									) }
									{ node.kind === 'content' && (
										<Button
											size="small"
											disabled={ w.pending }
											onClick={ () =>
												startAdding( node, 'folder' )
											}
										>
											{ __( '+ Folder', 'ohmylms' ) }
										</Button>
									) }
									{ node.children.length > 0 && (
										<Button
											size="small"
											aria-expanded={
												Boolean( query.trim() ) ||
												! collapsed.has( node.key )
											}
											label={ sprintf(
												/* translators: %s: node name. */ __(
													'Toggle branch for %s',
													'ohmylms'
												),
												label( node )
											) }
											disabled={ Boolean( query.trim() ) }
											onClick={ () =>
												setCollapsed( ( previous ) => {
													const next = new Set(
														previous
													);
													if (
														next.has( node.key )
													) {
														next.delete( node.key );
													} else {
														next.add( node.key );
													}
													return next;
												} )
											}
										>
											{ collapsed.has( node.key ) &&
											! query.trim()
												? '+'
												: '−' }{ ' ' }
											{ node.children.length }
										</Button>
									) }
								</div>
							</div>
						) ) }
					</div>
				</div>
			</div>
		</section>
	);
}
