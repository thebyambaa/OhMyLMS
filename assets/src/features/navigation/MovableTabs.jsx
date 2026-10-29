import {
	createElement,
	useEffect,
	useRef,
	useState,
	useId,
} from '@wordpress/element';
import { Modal } from '../../ui/Modal';
import { __, sprintf } from '@wordpress/i18n';
import {
	assignTab,
	layoutBlocks,
	moveBlock,
	moveTab,
	normalizeLayout,
} from './tabLayout.mjs';
import { tabPreferences } from './tabPreferences';
const textLabel = ( node ) =>
	( () => {
		if ( typeof node === 'string' || typeof node === 'number' ) {
			return String( node );
		}
		if ( Array.isArray( node ) ) {
			return node.map( textLabel ).join( '' );
		}
		return textLabel( node?.props?.children || '' );
	} )();
export function MovableTabs( {
	scope,
	tabs,
	labels,
	active,
	label,
	onChange,
	className = '',
	orientation = 'horizontal',
} ) {
	const _controlId = useId();
	const ids = tabs.map( ( tab ) => tab.id );
	const store = tabPreferences( scope, ids );
	const [ state, setState ] = useState( store.read );
	const [ editing, setEditing ] = useState( false );
	const [ dragging, setDragging ] = useState( null );
	const [ over, setOver ] = useState( null );
	const drag = useRef( null );
	const suppressClick = useRef( false );
	const layout = normalizeLayout( state.layout, ids );
	const { status } = state;
	useEffect( () => {
		setState( store.read() );
		return store.subscribe( setState );
	}, [ store ] );
	const save = ( next ) => store.save( next );
	const updateGroup = ( id, patch ) =>
		save( {
			...layout,
			groups: layout.groups.map( ( group ) =>
				group.id === id
					? {
							...group,
							...patch,
						}
					: group
			),
		} );
	const startDrag = ( event, item ) => {
		drag.current = item;
		suppressClick.current = true;
		setDragging( item );
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData( 'text/plain', item.id );
	};
	const endDrag = () => {
		drag.current = null;
		setDragging( null );
		setOver( null );
	};
	const drop = ( event, target, blockId ) => {
		event.preventDefault();
		event.stopPropagation();
		const item = drag.current;
		if ( ! item ) {
			return;
		}
		const box = event.currentTarget.getBoundingClientRect();
		const after =
			orientation === 'vertical'
				? event.clientY > box.top + box.height / 2
				: event.clientX > box.left + box.width / 2;
		save(
			item.type === 'group'
				? moveBlock( layout, item.id, blockId, after )
				: moveTab( layout, item.id, target, after )
		);
		endDrag();
	};
	const dropProps = ( target, blockId ) => ( {
		onDragOver( event ) {
			if ( drag.current ) {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';
				setOver( target );
			}
		},
		onDrop: ( event ) => drop( event, target, blockId ),
	} );
	const renderTab = ( id, block ) => {
		const tab = tabs.find( ( item ) => item.id === id );
		return (
			<a
				key={ id }
				href={ tab.href || `#${ tab.path || '' }` }
				draggable
				aria-current={ active === id ? 'page' : undefined }
				className={ [
					'oml-tab',
					active === id ? 'is-active' : '',
					over === id ? 'is-drop-target' : '',
					dragging?.id === id ? 'is-dragging' : '',
				].join( ' ' ) }
				title={ __(
					'Drag to move; right-click to organize tabs',
					'ohmylms'
				) }
				onPointerDown={ () => {
					suppressClick.current = false;
				} }
				onClick={ ( event ) => {
					if ( suppressClick.current ) {
						event.preventDefault();
						suppressClick.current = false;
					} else if ( onChange ) {
						event.preventDefault();
						onChange( id );
					}
				} }
				onContextMenu={ ( event ) => {
					event.preventDefault();
					setEditing( true );
				} }
				onDragStart={ ( event ) =>
					startDrag( event, {
						type: 'tab',
						id,
					} )
				}
				onDragEnd={ endDrag }
				onKeyDown={ ( event ) => {
					if (
						event.altKey &&
						[ 'ArrowLeft', 'ArrowRight' ].includes( event.key )
					) {
						event.preventDefault();
						const direction = event.key === 'ArrowRight' ? 1 : -1;
						const neighbor =
							layout.order[
								layout.order.indexOf( id ) + direction
							];
						if ( neighbor ) {
							save(
								moveTab( layout, id, neighbor, direction > 0 )
							);
						}
					}
				} }
				{ ...dropProps( id, block.id ) }
			>
				{ labels[ id ] }
			</a>
		);
	};
	return (
		<>
			<nav
				className={ `oml-tab-list ohmylms-content-hub-nav ohmylms-movable-tabs ${ className }` }
				aria-label={ label }
			>
				{ layoutBlocks( layout ).map( ( block ) =>
					block.group ? (
						<div
							key={ block.id }
							className="ohmylms-tab-group"
							style={ {
								backgroundColor: block.group.background,
								color: block.group.text,
							} }
						>
							<button
								type="button"
								className={ `ohmylms-tab-group-label ${ over === block.id ? 'is-drop-target' : '' } ${ block.tabs.includes( active ) ? 'is-active' : '' }` }
								draggable
								aria-expanded={ ! block.group.collapsed }
								title={ __(
									'Click to collapse; drag to move group; right-click to edit',
									'ohmylms'
								) }
								onPointerDown={ () => {
									suppressClick.current = false;
								} }
								onClick={ () => {
									if ( ! suppressClick.current ) {
										updateGroup( block.group.id, {
											collapsed: ! block.group.collapsed,
										} );
									}
									suppressClick.current = false;
								} }
								onContextMenu={ ( event ) => {
									event.preventDefault();
									setEditing( true );
								} }
								onDragStart={ ( event ) =>
									startDrag( event, {
										type: 'group',
										id: block.id,
									} )
								}
								onDragEnd={ endDrag }
								{ ...dropProps( block.id, block.id ) }
							>
								<span aria-hidden="true">
									{ block.group.collapsed ? '▸' : '▾' }
								</span>{ ' ' }
								{ block.group.name || __( 'Group', 'ohmylms' ) }
							</button>
							{ ! block.group.collapsed &&
								block.tabs.map( ( id ) =>
									renderTab( id, block )
								) }
						</div>
					) : (
						renderTab( block.tabs[ 0 ], block )
					)
				) }
				{ dragging?.type === 'tab' && (
					<span
						className="ohmylms-tab-ungroup"
						{ ...dropProps( null, null ) }
					>
						{ __( 'Move outside group', 'ohmylms' ) }
					</span>
				) }
				<button
					type="button"
					className="ohmylms-tab-organize"
					onClick={ () => setEditing( true ) }
				>
					{ __( 'Organize tabs', 'ohmylms' ) }
				</button>
			</nav>
			{ status !== 'saved' && (
				<div className="ohmylms-tab-save-status" role="status">
					{ status === 'saving' ? (
						__( 'Saving tab layout…', 'ohmylms' )
					) : (
						<>
							{ __(
								'Tab layout could not be saved.',
								'ohmylms'
							) }{ ' ' }
							<button
								type="button"
								onClick={ () => save( layout ) }
							>
								{ __( 'Retry', 'ohmylms' ) }
							</button>
						</>
					) }
				</div>
			) }
			{ editing && (
				<Modal
					title={ __( 'Organize tabs', 'ohmylms' ) }
					onRequestClose={ () => setEditing( false ) }
					className="ohmylms-tab-modal"
				>
					<p>
						{ __(
							'Drag tabs or group labels to reorder them. Drop a tab onto a group to add it. Changes save to your account automatically.',
							'ohmylms'
						) }
					</p>
					<p>
						{ __(
							'You can also focus a tab and press Alt + Left or Right to move it.',
							'ohmylms'
						) }
					</p>
					<div className="ohmylms-tab-group-editor">
						{ layout.groups.map( ( group ) => (
							<fieldset key={ group.id }>
								<legend>{ group.name }</legend>
								<label
									htmlFor={
										_controlId + '-field-1' + '-' + group.id
									}
								>
									{ __( 'Group name', 'ohmylms' ) }
									<input
										type="text"
										maxLength={ 80 }
										value={ group.name }
										onChange={ ( event ) =>
											updateGroup( group.id, {
												name: event.target.value,
											} )
										}
										id={
											_controlId +
											'-field-1' +
											'-' +
											group.id
										}
									/>
								</label>
								<label
									htmlFor={
										_controlId + '-field-2' + '-' + group.id
									}
								>
									{ __( 'Background color', 'ohmylms' ) }
									<input
										type="color"
										value={ group.background }
										onChange={ ( event ) =>
											updateGroup( group.id, {
												background: event.target.value,
											} )
										}
										id={
											_controlId +
											'-field-2' +
											'-' +
											group.id
										}
									/>
								</label>
								<label
									htmlFor={
										_controlId + '-field-3' + '-' + group.id
									}
								>
									{ __( 'Text color', 'ohmylms' ) }
									<input
										type="color"
										value={ group.text }
										onChange={ ( event ) =>
											updateGroup( group.id, {
												text: event.target.value,
											} )
										}
										id={
											_controlId +
											'-field-3' +
											'-' +
											group.id
										}
									/>
								</label>
								<button
									type="button"
									className="button"
									onClick={ () =>
										save(
											normalizeLayout(
												{
													...layout,
													groups: layout.groups.filter(
														( item ) =>
															item.id !== group.id
													),
												},
												ids
											)
										)
									}
								>
									{ __( 'Remove group', 'ohmylms' ) }
								</button>
							</fieldset>
						) ) }
					</div>
					<button
						type="button"
						className="button"
						disabled={ layout.groups.length >= 30 }
						onClick={ () => {
							const id = `g_${ Date.now().toString( 36 ) }_${ Math.random().toString( 36 ).slice( 2, 8 ) }`;
							save( {
								...layout,
								groups: [
									...layout.groups,
									{
										id,
										name: __( 'New group', 'ohmylms' ),
										background: '#ede7f6',
										text: '#45278b',
										collapsed: false,
									},
								],
							} );
						} }
					>
						{ __( 'New group', 'ohmylms' ) }
					</button>
					<div className="ohmylms-tab-assignments">
						{ layout.order.map( ( id, index ) => (
							<div key={ id }>
								<label
									htmlFor={
										_controlId + '-field-4' + '-' + id
									}
								>
									{ labels[ id ] }
									<select
										value={ layout.assignments[ id ] || '' }
										onChange={ ( event ) =>
											save(
												assignTab(
													layout,
													id,
													event.target.value
												)
											)
										}
										id={
											_controlId + '-field-4' + '-' + id
										}
									>
										<option value="">
											{ __( 'No group', 'ohmylms' ) }
										</option>
										{ layout.groups.map( ( group ) => (
											<option
												key={ group.id }
												value={ group.id }
											>
												{ group.name }
											</option>
										) ) }
									</select>
								</label>
								<button
									type="button"
									className="button"
									disabled={ index === 0 }
									aria-label={ sprintf(
										/* translators: %s: tab label. */ __(
											'Move %s left',
											'ohmylms'
										),
										textLabel( labels[ id ] )
									) }
									onClick={ () =>
										save(
											moveTab(
												layout,
												id,
												layout.order[ index - 1 ]
											)
										)
									}
								>
									←
								</button>
								<button
									type="button"
									className="button"
									disabled={
										index === layout.order.length - 1
									}
									aria-label={ sprintf(
										/* translators: %s: tab label. */ __(
											'Move %s right',
											'ohmylms'
										),
										textLabel( labels[ id ] )
									) }
									onClick={ () =>
										save(
											moveTab(
												layout,
												id,
												layout.order[ index + 1 ],
												true
											)
										)
									}
								>
									→
								</button>
							</div>
						) ) }
					</div>
					<div className="ohmylms-tab-modal-footer">
						<button
							type="button"
							className="button"
							onClick={ () =>
								save( normalizeLayout( null, ids ) )
							}
						>
							{ __( 'Reset layout', 'ohmylms' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ () => setEditing( false ) }
						>
							{ __( 'Done', 'ohmylms' ) }
						</button>
					</div>
				</Modal>
			) }
		</>
	);
}
