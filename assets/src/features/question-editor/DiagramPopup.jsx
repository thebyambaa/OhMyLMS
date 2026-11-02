import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Modal,
	RangeControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** @typedef {import('./editorTypes').Data} Data */

const clamp = ( /** @type {number} */ number ) =>
	Math.max( 0, Math.min( 100, number ) );

/**
 * Full-screen popup for placing hotspot regions or labels on a large image.
 *
 * Click the image to add a marker, drag a marker to move it, and use the arrow
 * keys on a focused marker for fine adjustment.
 * @param {Object}                  root0
 * @param {string}                  root0.type     Either hotspot or labeling.
 * @param {string}                  root0.imageUrl Diagram image.
 * @param {Data[]}                  root0.rows     Zones (hotspot) or targets (labeling).
 * @param {number}                  root0.selected Index of the selected marker.
 * @param {(index: number) => void} root0.onSelect Receives a marker index.
 * @param {(rows: Data[]) => void}  root0.onChange Receives the full replacement list.
 * @param {() => void}              root0.onClose  Closes the popup.
 */
export function DiagramPopup( {
	type,
	imageUrl,
	rows,
	selected,
	onSelect,
	onChange,
	onClose,
} ) {
	const hotspot = type === 'hotspot';
	const frame = useRef( /** @type {HTMLDivElement|null} */ ( null ) );
	const dragging = useRef( -1 );
	const area = useRef( /** @type {HTMLDivElement|null} */ ( null ) );
	const natural = useRef(
		/** @type {{width: number, height: number}|null} */ ( null )
	);
	const [ fit, setFit ] = useState(
		/** @type {{width: number, height: number}|null} */ ( null )
	);
	// Scale the image up or down to fill the popup, keeping its proportions.
	const refit = () => {
		if ( ! natural.current || ! area.current ) {
			return;
		}
		const scale = Math.min(
			area.current.clientWidth / natural.current.width,
			Math.max( 240, window.innerHeight - 220 ) / natural.current.height,
			4
		);
		setFit( {
			width: Math.round( natural.current.width * scale ),
			height: Math.round( natural.current.height * scale ),
		} );
	};
	useEffect( () => {
		window.addEventListener( 'resize', refit );
		return () => window.removeEventListener( 'resize', refit );
	}, [] );
	const current = rows[ selected ];
	const patch = ( /** @type {number} */ index, /** @type {Data} */ change ) =>
		onChange(
			rows.map( ( row, i ) =>
				i === index ? { ...row, ...change } : row
			)
		);
	const position = (
		/** @type {{clientX: number, clientY: number}} */ event
	) => {
		const bounds = /** @type {HTMLDivElement} */ (
			frame.current
		).getBoundingClientRect();
		return {
			x: clamp(
				Math.round(
					( ( event.clientX - bounds.left ) / bounds.width ) * 100
				)
			),
			y: clamp(
				Math.round(
					( ( event.clientY - bounds.top ) / bounds.height ) * 100
				)
			),
		};
	};
	const add = ( /** @type {{x: number, y: number}} */ at ) => {
		onChange( [
			...rows,
			hotspot
				? { ...at, radius: 10 }
				: { id: 'l' + Date.now(), ...at, answer: '' },
		] );
		onSelect( rows.length );
	};
	const remove = ( /** @type {number} */ index ) => {
		onChange( rows.filter( ( _, i ) => i !== index ) );
		onSelect( Math.max( 0, index - 1 ) );
	};
	const nudge = (
		/** @type {import('react').KeyboardEvent} */ event,
		/** @type {number} */ index
	) => {
		const step = event.shiftKey ? 5 : 1;
		const delta = /** @type {Record<string, number[]>} */ ( {
			ArrowLeft: [ -step, 0 ],
			ArrowRight: [ step, 0 ],
			ArrowUp: [ 0, -step ],
			ArrowDown: [ 0, step ],
		} )[ event.key ];
		if ( ! delta ) {
			return;
		}
		event.preventDefault();
		patch( index, {
			x: clamp( rows[ index ].x + delta[ 0 ] ),
			y: clamp( rows[ index ].y + delta[ 1 ] ),
		} );
	};

	return (
		<Modal
			title={
				hotspot
					? __( 'Place hotspots', 'ohmylms' )
					: __( 'Place labels', 'ohmylms' )
			}
			onRequestClose={ onClose }
			isFullScreen
			className="ohmylms-diagram-popup"
		>
			<div
				style={ {
					display: 'flex',
					flexWrap: 'wrap',
					gap: 24,
					alignItems: 'flex-start',
				} }
			>
				<div ref={ area } style={ { flex: '1 1 480px', minWidth: 0 } }>
					<p style={ { marginTop: 0 } }>
						{ hotspot
							? __(
									'Click the image to add a hotspot. Drag a hotspot to move it, or select one and use the arrow keys.',
									'ohmylms'
								)
							: __(
									'Click the image to add a label. Drag a label to move it, or select one and use the arrow keys.',
									'ohmylms'
								) }
					</p>
					<div
						ref={ frame }
						onPointerMove={ ( event ) => {
							if ( dragging.current >= 0 ) {
								patch( dragging.current, position( event ) );
							}
						} }
						onPointerUp={ () => {
							dragging.current = -1;
						} }
						onPointerLeave={ () => {
							dragging.current = -1;
						} }
						style={ {
							position: 'relative',
							width: 'fit-content',
							maxWidth: '100%',
							margin: '0 auto',
							lineHeight: 0,
							cursor: 'crosshair',
						} }
					>
						<button
							type="button"
							aria-label={ __(
								'Add a marker to the image',
								'ohmylms'
							) }
							onClick={ ( event ) =>
								add(
									event.detail === 0
										? { x: 50, y: 50 }
										: position( event )
								)
							}
							style={ {
								display: 'block',
								padding: 0,
								border: 0,
								background: 'none',
								cursor: 'crosshair',
							} }
						>
							<img
								src={ imageUrl }
								alt={ __( 'Diagram', 'ohmylms' ) }
								draggable={ false }
								onLoad={ ( event ) => {
									const image =
										/** @type {HTMLImageElement} */ (
											event.target
										);
									natural.current = {
										width: image.naturalWidth || 1,
										height: image.naturalHeight || 1,
									};
									refit();
								} }
								style={ {
									display: 'block',
									userSelect: 'none',
									...( fit
										? {
												width: fit.width,
												height: fit.height,
											}
										: {
												maxWidth: '100%',
												maxHeight:
													'calc(100vh - 220px)',
											} ),
								} }
							/>
						</button>
						{ rows.map( ( row, index ) => (
							<span key={ row.id || index }>
								{ hotspot && (
									<span
										aria-hidden="true"
										style={ {
											position: 'absolute',
											left: `${ row.x }%`,
											top: `${ row.y }%`,
											width: `${ ( row.radius || 0 ) * 2 }%`,
											height: `${ ( row.radius || 0 ) * 2 }%`,
											transform: 'translate(-50%, -50%)',
											border: '2px dashed #fff',
											borderRadius: '50%',
											background:
												'rgba(67, 36, 116, 0.25)',
											boxShadow:
												selected === index
													? '0 0 0 3px #dba617'
													: 'none',
											pointerEvents: 'none',
										} }
									/>
								) }
								<button
									type="button"
									aria-pressed={ selected === index }
									aria-label={ `${
										hotspot
											? __( 'Hotspot', 'ohmylms' )
											: __( 'Label', 'ohmylms' )
									} ${ index + 1 }` }
									onClick={ ( event ) => {
										event.stopPropagation();
										onSelect( index );
									} }
									onKeyDown={ ( event ) =>
										nudge( event, index )
									}
									onPointerDown={ ( event ) => {
										event.stopPropagation();
										dragging.current = index;
										onSelect( index );
										try {
											event.currentTarget.setPointerCapture(
												event.pointerId
											);
										} catch {
											// Without capture the frame still follows the pointer.
										}
									} }
									style={ {
										position: 'absolute',
										left: `${ row.x }%`,
										top: `${ row.y }%`,
										width: 36,
										height: 36,
										padding: 0,
										border: '2px solid #fff',
										borderRadius: '50%',
										color: '#fff',
										background: '#432474',
										transform: 'translate(-50%, -50%)',
										cursor: 'move',
										touchAction: 'none',
										lineHeight: '32px',
										outline:
											selected === index
												? '3px solid #dba617'
												: 'none',
									} }
								>
									{ index + 1 }
								</button>
							</span>
						) ) }
					</div>
				</div>
				<div style={ { flex: '0 1 300px', minWidth: 240 } }>
					<h3 style={ { marginTop: 0 } }>
						{ hotspot
							? __( 'Hotspots', 'ohmylms' )
							: __( 'Labels', 'ohmylms' ) }{ ' ' }
						({ rows.length })
					</h3>
					{ current ? (
						<div>
							<p>
								<strong>
									{ hotspot
										? __( 'Hotspot', 'ohmylms' )
										: __( 'Label', 'ohmylms' ) }{ ' ' }
									{ selected + 1 }
								</strong>
							</p>
							{ hotspot ? (
								<RangeControl
									label={ __( 'Size (radius %)', 'ohmylms' ) }
									value={ current.radius || 10 }
									min={ 1 }
									max={ 50 }
									onChange={ ( radius ) =>
										patch( selected, { radius } )
									}
								/>
							) : (
								<TextControl
									label={ __(
										'Correct label text',
										'ohmylms'
									) }
									value={ current.answer || '' }
									onChange={ ( answer ) =>
										patch( selected, { answer } )
									}
								/>
							) }
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => remove( selected ) }
							>
								{ hotspot
									? __( 'Remove hotspot', 'ohmylms' )
									: __( 'Remove label', 'ohmylms' ) }
							</Button>
						</div>
					) : (
						<p>
							{ __(
								'No markers yet. Click the image to add one.',
								'ohmylms'
							) }
						</p>
					) }
					<p style={ { marginTop: 24 } }>
						<Button variant="primary" onClick={ onClose }>
							{ __( 'Done', 'ohmylms' ) }
						</Button>
					</p>
				</div>
			</div>
		</Modal>
	);
}
