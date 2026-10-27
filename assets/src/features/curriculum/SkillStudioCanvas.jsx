/** @jsx createElement */
import {
	createElement,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { relationshipLabels } from './relationships.mjs';
import { fitStudioCanvas, zoomStudioCanvas } from './studio-canvas.mjs';

/**
 * Focused spatial view. Moving cards never changes learning order or mappings.
 * @param {Object}                props              Canvas props.
 * @param {Object}                props.skill        Shared skill.
 * @param {Array}                 props.rows         Visible objectives.
 * @param {Object}                props.proposal     Unsaved connection.
 * @param {number}                props.selectedId   Selected objective, or zero for the shared skill.
 * @param {boolean}               props.disabled     Disable editing during writes.
 * @param {(row: Object) => void} props.onSelect     Select an objective.
 * @param {(row: Object) => void} props.onConnection Inspect a saved connection.
 */
export function SkillStudioCanvas( {
	skill,
	rows,
	proposal,
	selectedId,
	disabled,
	onSelect,
	onConnection,
} ) {
	const stage = useRef( null );
	const drag = useRef( null );
	const [ size, setSize ] = useState( { width: 660, height: 380 } );
	const [ positions, setPositions ] = useState( {} );
	const [ view, setView ] = useState( { zoom: 1, pan: { x: 0, y: 0 } } );
	const { zoom, pan } = view;
	const width = Math.max( 660, size.width );
	const labels = relationshipLabels();
	const signature = rows.map( ( row ) => row.term_id ).join( ',' );
	const cardWidth = 190;
	const defaults = useMemo( () => {
		const points = { shared: { x: width / 2 - cardWidth / 2, y: 170 } };
		signature
			.split( ',' )
			.filter( Boolean )
			.forEach( ( id, index ) => {
				points[ id ] = {
					x: index % 2 ? width - cardWidth - 20 : 20,
					y: Math.floor( index / 2 ) * 145 + 24,
				};
			} );
		return points;
	}, [ signature, width ] );
	const position = ( id ) => positions[ id ] || defaults[ id ];
	useEffect( () => {
		const observer = new ResizeObserver( ( entries ) => {
			const { width: measuredWidth, height } = entries[ 0 ].contentRect;
			setSize( { width: measuredWidth, height } );
		} );
		observer.observe( stage.current );
		return () => observer.disconnect();
	}, [] );
	useEffect( () => {
		setPositions( {} );
		drag.current = null;
		setView( fitStudioCanvas( defaults, size.width, size.height ) );
	}, [ skill?.id, defaults, size ] );
	useEffect( () => {
		const element = stage.current;
		const wheel = ( event ) => {
			event.preventDefault();
			const bounds = element.getBoundingClientRect();
			const unit = event.deltaMode === 1 ? 16 : 1;
			setView( ( current ) =>
				event.ctrlKey || event.metaKey
					? zoomStudioCanvas(
							current,
							current.zoom *
								Math.exp( -event.deltaY * unit * 0.002 ),
							{
								x: event.clientX - bounds.left,
								y: event.clientY - bounds.top,
							}
						)
					: {
							...current,
							pan: {
								x: current.pan.x - event.deltaX * unit,
								y: current.pan.y - event.deltaY * unit,
							},
						}
			);
		};
		element.addEventListener( 'wheel', wheel, { passive: false } );
		return () => element.removeEventListener( 'wheel', wheel );
	}, [] );
	function move( id, x, y ) {
		setPositions( ( current ) => ( {
			...current,
			[ id ]: { x: position( id ).x + x, y: position( id ).y + y },
		} ) );
	}
	function pointerDown( event, id ) {
		if (
			( id && disabled ) ||
			( event.button !== 0 && event.button !== 1 )
		) {
			return;
		}
		drag.current = {
			id,
			x: event.clientX,
			y: event.clientY,
			origin: id ? position( id ) : pan,
		};
		event.currentTarget.setPointerCapture( event.pointerId );
		event.preventDefault();
	}
	function pointerMove( event ) {
		const current = drag.current;
		if ( ! current ) {
			return;
		}
		const x = event.clientX - current.x;
		const y = event.clientY - current.y;
		if ( current.id ) {
			setPositions( ( previous ) => ( {
				...previous,
				[ current.id ]: {
					x: current.origin.x + x / zoom,
					y: current.origin.y + y / zoom,
				},
			} ) );
		} else {
			setView( ( previous ) => ( {
				...previous,
				pan: { x: current.origin.x + x, y: current.origin.y + y },
			} ) );
		}
	}
	const endDrag = () => {
		drag.current = null;
	};
	function card( id, name, context, selected, onClick ) {
		const point = position( id );
		return (
			<article
				key={ id }
				className={ `ohmylms-studio-node${ id === 'shared' ? ' is-shared' : '' }${ selected ? ' is-selected' : '' }` }
				style={ { left: point.x, top: point.y } }
			>
				<button
					type="button"
					className="ohmylms-studio-node-handle"
					disabled={ disabled }
					aria-label={ sprintf(
						/* translators: %s: skill name. */
						__( 'Move %s with the arrow keys or drag', 'ohmylms' ),
						name
					) }
					onPointerDown={ ( event ) => pointerDown( event, id ) }
					onPointerMove={ pointerMove }
					onPointerUp={ endDrag }
					onPointerCancel={ endDrag }
					onKeyDown={ ( event ) => {
						const vectors = {
							ArrowLeft: [ -20, 0 ],
							ArrowRight: [ 20, 0 ],
							ArrowUp: [ 0, -20 ],
							ArrowDown: [ 0, 20 ],
						};
						if ( vectors[ event.key ] ) {
							event.preventDefault();
							move( id, ...vectors[ event.key ] );
						}
					} }
				>
					{ context }
					<span aria-hidden="true">⠿</span>
				</button>
				<Button
					className="ohmylms-studio-node-title"
					variant="tertiary"
					aria-pressed={ selected }
					disabled={ disabled }
					onClick={ onClick }
				>
					<span>{ name }</span>
				</Button>
				{ id !== 'shared' && (
					<small className="ohmylms-studio-node-relation">
						{ labels[
							rows
								.find( ( row ) => row.term_id === id )
								?.maps_to.find(
									( mapping ) =>
										mapping.shared_id === skill?.id
								)?.relation
						] || __( 'Not connected', 'ohmylms' ) }
					</small>
				) }
			</article>
		);
	}
	return (
		<div className="ohmylms-studio-canvas">
			<div className="ohmylms-studio-canvas-toolbar">
				<Button
					variant="secondary"
					onClick={ () => {
						setPositions( {} );
						setView(
							fitStudioCanvas( defaults, size.width, size.height )
						);
					} }
				>
					{ __( 'Arrange and fit', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					aria-label={ __( 'Zoom out', 'ohmylms' ) }
					onClick={ () =>
						setView(
							zoomStudioCanvas( view, zoom / 1.2, {
								x: size.width / 2,
								y: size.height / 2,
							} )
						)
					}
				>
					−
				</Button>
				<span>{ Math.round( zoom * 100 ) }%</span>
				<Button
					variant="secondary"
					aria-label={ __( 'Zoom in', 'ohmylms' ) }
					onClick={ () =>
						setView(
							zoomStudioCanvas( view, zoom * 1.2, {
								x: size.width / 2,
								y: size.height / 2,
							} )
						)
					}
				>
					+
				</Button>
			</div>
			<div
				ref={ stage }
				className="ohmylms-studio-stage"
				style={ {
					backgroundSize: `${ 20 * zoom }px ${ 20 * zoom }px`,
					backgroundPosition: `${ pan.x }px ${ pan.y }px`,
				} }
				onPointerDown={ ( event ) => {
					if (
						event.button === 1 ||
						! event.target.closest( 'button, article, g' )
					) {
						pointerDown( event, null );
					}
				} }
				onPointerMove={ pointerMove }
				onPointerUp={ endDrag }
				onPointerCancel={ endDrag }
			>
				<div
					className="ohmylms-studio-world"
					style={ {
						width,
						transform: `translate(${ pan.x }px, ${ pan.y }px) scale(${ zoom })`,
					} }
				>
					<svg
						className="ohmylms-studio-lines"
						aria-label={ __(
							'Source objectives connected to the shared skill',
							'ohmylms'
						) }
					>
						{ skill &&
							rows.map( ( row ) => {
								const mapping = row.maps_to.find(
									( link ) => link.shared_id === skill.id
								);
								const pending =
									proposal &&
									( proposal.replacement || proposal.row )
										.term_id === row.term_id;
								if ( ! mapping && ! pending ) {
									return null;
								}
								const from = position( row.term_id );
								const to = position( 'shared' );
								const right = from.x > to.x;
								const x1 = from.x + ( right ? 0 : cardWidth );
								const x2 = to.x + ( right ? cardWidth : 0 );
								const y1 = from.y + 66;
								const y2 = to.y + 66;
								const mid = ( x1 + x2 ) / 2;
								const label =
									labels[
										pending
											? proposal.relation
											: mapping.relation
									] ||
									mapping?.relation ||
									proposal?.relation;
								const path = `M ${ x1 } ${ y1 } C ${ mid } ${ y1 }, ${ mid } ${ y2 }, ${ x2 } ${ y2 }`;
								const labelWidth = Math.max(
									90,
									label.length * 6.5 + 20
								);
								return (
									<g
										key={ row.term_id }
										className={ `ohmylms-studio-edge${ pending ? ' is-pending' : '' }${ mapping?.group_uuid ? ' is-combined' : '' }${ selectedId === row.term_id ? ' is-selected' : '' }` }
										role="button"
										tabIndex={ disabled ? -1 : 0 }
										aria-disabled={ disabled }
										aria-label={ sprintf(
											/* translators: 1: objective name, 2: relationship type. */
											__(
												'Edit connection for %1$s: %2$s',
												'ohmylms'
											),
											row.name,
											label
										) }
										onKeyDown={ ( event ) => {
											if (
												event.key === 'Enter' ||
												event.key === ' '
											) {
												event.preventDefault();
												if ( ! disabled ) {
													onConnection( row );
												}
											}
										} }
										onClick={ () =>
											! disabled && onConnection( row )
										}
									>
										<title>{ `${ row.name }: ${ label }` }</title>
										<path
											d={ path }
											className="ohmylms-studio-edge-hit"
										/>
										<path
											d={ path }
											className="ohmylms-studio-edge-line"
										/>
										<g
											className="ohmylms-studio-edge-label"
											transform={ `translate(${ mid }, ${ ( y1 + y2 ) / 2 })` }
										>
											<rect
												x={ -labelWidth / 2 }
												y={ -12 }
												width={ labelWidth }
												height={ 24 }
												rx={ 12 }
											/>
											<text
												textAnchor="middle"
												dominantBaseline="central"
											>
												{ label }
											</text>
										</g>
									</g>
								);
							} ) }
					</svg>
					{ skill &&
						card(
							'shared',
							skill.name,
							__( 'Shared skill', 'ohmylms' ),
							! selectedId,
							() => onSelect( null )
						) }
					{ rows.map( ( row ) =>
						card(
							row.term_id,
							row.name,
							`${ row.code } · ${ row.category || row.source_names?.join( ' · ' ) || '' }`,
							selectedId === row.term_id,
							() => onSelect( row )
						)
					) }
				</div>
			</div>
			<div className="ohmylms-studio-canvas-footer">
				<span>
					{ __(
						'Drag cards or empty canvas. Scroll to pan; Ctrl + scroll to zoom.',
						'ohmylms'
					) }
				</span>
				<div className="ohmylms-studio-actions">
					{ [
						[ '←', -40, 0 ],
						[ '↑', 0, -40 ],
						[ '↓', 0, 40 ],
						[ '→', 40, 0 ],
					].map( ( [ label, x, y ] ) => (
						<Button
							key={ label }
							variant="secondary"
							aria-label={ sprintf(
								/* translators: %s: direction arrow. */ __(
									'Move selected card %s',
									'ohmylms'
								),
								label
							) }
							disabled={ disabled || ! skill }
							onClick={ () =>
								move( selectedId || 'shared', x, y )
							}
						>
							{ label }
						</Button>
					) ) }
				</div>
			</div>
		</div>
	);
}
