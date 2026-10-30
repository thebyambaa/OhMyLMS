import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { request } from '../rest.mjs';

const api = ( path, data ) =>
	request( {
		path: `/ohmylms/v1/${ path }`,
		...( data ? { method: 'POST', data } : {} ),
	} );

/**
 * Correct, assign and approve original crops or declarative reconstructions.
 *
 * @param {Object}                                                         props              Component properties.
 * @param {number}                                                         props.jobId        Source job.
 * @param {Object}                                                         props.question     Current review draft.
 * @param {(field: string, value: boolean|string|number|Object[]) => void} props.onChange     Draft field update.
 * @param {Object[]}                                                       props.candidates   New detections that have not replaced teacher edits.
 * @param {string[]}                                                       props.destinations Question numbers in the import.
 * @param {(index: number, question: string) => void}                      props.onMove       Reassign a placement.
 * @return {Element} Figure review controls.
 */
export function ImportFigures( {
	jobId,
	question,
	onChange,
	candidates = [],
	destinations = [],
	onMove = null,
} ) {
	const [ assets, setAssets ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ page, setPage ] = useState( question.page );
	const [ bounds, setBounds ] = useState( [ 0, 0, 1, 1 ] );
	const [ pageImage, setPageImage ] = useState( '' );
	const figures = question.figures || [];
	useEffect( () => {
		let active = true;
		const refresh = () =>
			api( `exam-imports/${ jobId }/figures` )
				.then( ( value ) => {
					if ( active ) {
						setAssets( value );
					}
				} )
				.catch( ( failure ) => {
					if ( active ) {
						setError( failure.message );
					}
				} );
		refresh();
		const timer = setInterval( refresh, 3000 );
		return () => {
			active = false;
			clearInterval( timer );
		};
	}, [ jobId ] );
	const update = ( index, patch ) =>
		onChange(
			'figures',
			figures.map( ( figure, at ) =>
				at === index ? { ...figure, approved: false, ...patch } : figure
			)
		);
	const run = async ( action ) => {
		setBusy( true );
		setError( '' );
		try {
			await action();
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusy( false );
		}
	};
	const crop = async ( index = -1, full = false ) => {
		const asset = await api( `exam-imports/${ jobId }/figures`, {
			page: Number( page ),
			bounds: full ? [ 0, 0, 1, 1 ] : bounds,
		} );
		setAssets( ( previous ) => [
			...previous.filter( ( item ) => item.id !== asset.id ),
			asset,
		] );
		if ( full ) {
			setPageImage( asset.id );
		} else if ( index >= 0 ) {
			update( index, {
				asset_id: asset.id,
				page: Number( page ),
				bounds,
				selected: 'original',
				spec: undefined,
			} );
		} else {
			onChange( 'figures', [
				...figures,
				{
					id: `f_${ Date.now() }`,
					page: Number( page ),
					bounds,
					asset_id: asset.id,
					part_ids: [],
					alt: '',
					approved: false,
					selected: 'original',
					not_to_scale: false,
				},
			] );
		}
	};
	return (
		<section className="ohmylms-import-figures">
			<fieldset disabled={ busy }>
				<h3>{ __( 'Figures', 'ohmylms' ) }</h3>
				<p>
					{ __(
						'For measurement tasks, use the original crop and print at actual size.',
						'ohmylms'
					) }
				</p>
				{ candidates
					.filter(
						( candidate ) =>
							! figures.some(
								( figure ) =>
									figure.asset_id &&
									figure.asset_id === candidate.asset_id
							)
					)
					.map( ( candidate ) => (
						<Button
							key={ candidate.id }
							variant="secondary"
							disabled={ figures.length >= 20 }
							onClick={ () =>
								onChange( 'figures', [
									...figures,
									{
										...candidate,
										id: figures.some(
											( figure ) =>
												figure.id === candidate.id
										)
											? `f_${ Date.now() }`
											: candidate.id,
										approved: false,
										part_ids: (
											candidate.part_ids || []
										).filter( ( id ) =>
											question.parts.some(
												( part ) => part.id === id
											)
										),
									},
								] )
							}
						>
							{ __( 'Use detected figure', 'ohmylms' ) }{ ' ' }
							{ candidate.id }
						</Button>
					) ) }

				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<TextControl
					label={ __( 'Crop page', 'ohmylms' ) }
					type="number"
					min="1"
					max="500"
					value={ page }
					onChange={ ( value ) => setPage( Number( value ) ) }
				/>
				<p>
					{ __(
						'Bounds are percentages from the top-left of the visible page. Include all labels, axes and notes.',
						'ohmylms'
					) }
				</p>
				{ [
					__( 'Left (%)', 'ohmylms' ),
					__( 'Top (%)', 'ohmylms' ),
					__( 'Right (%)', 'ohmylms' ),
					__( 'Bottom (%)', 'ohmylms' ),
				].map( ( label, index ) => (
					<TextControl
						key={ label }
						label={ label }
						type="number"
						min="0"
						max="100"
						step="0.1"
						value={ bounds[ index ] * 100 }
						onChange={ ( value ) =>
							setBounds(
								bounds.map( ( current, at ) =>
									at === index
										? Number( value ) / 100
										: current
								)
							)
						}
					/>
				) ) }
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => run( () => crop( -1, true ) ) }
				>
					{ __( 'Render full page for crop reference', 'ohmylms' ) }
				</Button>
				{ assets.find( ( asset ) => asset.id === pageImage )?.url && (
					<CropReference
						url={
							assets.find( ( asset ) => asset.id === pageImage )
								.url
						}
						bounds={ bounds }
						onBounds={ setBounds }
					/>
				) }
				<Button
					variant="secondary"
					disabled={ busy || figures.length >= 20 }
					onClick={ () => run( () => crop() ) }
				>
					{ __( 'Add crop', 'ohmylms' ) }
				</Button>
				{ figures.map( ( figure, index ) => {
					const asset = assets.find(
						( item ) => item.id === figure.asset_id
					);
					return (
						<fieldset key={ figure.id }>
							<legend>{ figure.id }</legend>
							{ onMove && (
								<SelectControl
									label={ __(
										'Move figure to question',
										'ohmylms'
									) }
									value={ question.number }
									options={ destinations.map(
										( number ) => ( {
											label: number,
											value: number,
										} )
									) }
									onChange={ ( number ) => {
										if ( number !== question.number ) {
											onMove( index, number );
										}
									} }
								/>
							) }
							{ asset?.url ? (
								<div className="ohmylms-figure-comparison">
									<img src={ asset.url } alt={ figure.alt } />
									{ figure.spec && (
										<ReconstructionPreview
											spec={ figure.spec }
											url={ asset.url }
											alt={ figure.alt }
											notToScale={ figure.not_to_scale }
										/>
									) }
								</div>
							) : (
								<p role="status">
									{ asset?.error ||
										asset?.state ||
										__(
											'Crop unavailable. Recreate it using the bounds above.',
											'ohmylms'
										) }
								</p>
							) }
							<TextControl
								label={ __(
									'Accessible figure description (no answers)',
									'ohmylms'
								) }
								value={ figure.alt }
								onChange={ ( alt ) => update( index, { alt } ) }
							/>
							<CheckboxControl
								label={ __( 'Not to scale', 'ohmylms' ) }
								checked={ figure.not_to_scale }
								onChange={ ( notToScale ) =>
									update( index, {
										not_to_scale: notToScale,
									} )
								}
							/>
							<SelectControl
								multiple
								label={ __(
									'Associated parts (select none for the shared stem)',
									'ohmylms'
								) }
								value={ figure.part_ids }
								options={ question.parts.map( ( part ) => ( {
									label: part.label || part.id,
									value: part.id,
								} ) ) }
								onChange={ ( partIds ) =>
									update( index, { part_ids: partIds } )
								}
							/>
							<Button
								onClick={ () => {
									setPage( figure.page );
									setBounds( figure.bounds );
								} }
							>
								{ __( 'Edit crop bounds', 'ohmylms' ) }
							</Button>
							<Button
								disabled={ busy }
								onClick={ () => run( () => crop( index ) ) }
							>
								{ __(
									'Replace with crop bounds above',
									'ohmylms'
								) }
							</Button>
							<Button
								disabled={ busy || asset?.state !== 'ready' }
								onClick={ () =>
									run( async () => {
										const spec = await api(
											`exam-imports/${ jobId }/reconstruct`,
											{
												asset_id: figure.asset_id,
												question: [
													question.question,
													...question.parts.map(
														( part ) =>
															part.prompt || ''
													),
												].join( '\n' ),
											}
										);
										update( index, {
											spec,
											selected: 'jsxgraph',
										} );
									} )
								}
							>
								{ __(
									'Generate / regenerate JSXGraph',
									'ohmylms'
								) }
							</Button>
							{ figure.spec && (
								<>
									<SelectControl
										label={ __(
											'Student figure',
											'ohmylms'
										) }
										value={ figure.selected }
										options={ [
											{
												label: __(
													'Original crop',
													'ohmylms'
												),
												value: 'original',
											},
											{
												label: __(
													'JSXGraph reconstruction',
													'ohmylms'
												),
												value: 'jsxgraph',
											},
										] }
										onChange={ ( selected ) =>
											update( index, { selected } )
										}
									/>
									{ figure.spec.objects.map(
										( object, objectIndex ) => (
											<TextControl
												key={ object.id }
												label={ object.id }
												value={ object.label || '' }
												onChange={ ( label ) =>
													update( index, {
														spec: {
															...figure.spec,
															objects:
																figure.spec.objects.map(
																	(
																		item,
																		at
																	) =>
																		at ===
																		objectIndex
																			? {
																					...item,
																					label,
																				}
																			: item
																),
														},
													} )
												}
											/>
										)
									) }
								</>
							) }
							<CheckboxControl
								label={ __(
									'I checked and approve this selected figure',
									'ohmylms'
								) }
								checked={ figure.approved }
								disabled={
									asset?.state !== 'ready' ||
									! figure.alt.trim()
								}
								onChange={ ( approved ) =>
									update( index, { approved } )
								}
							/>
							<Button
								disabled={ index === 0 }
								onClick={ () => {
									const next = [ ...figures ];
									[ next[ index - 1 ], next[ index ] ] = [
										next[ index ],
										next[ index - 1 ],
									];
									onChange( 'figures', next );
								} }
							>
								{ __( 'Move earlier', 'ohmylms' ) }
							</Button>
							<Button
								isDestructive
								onClick={ () =>
									onChange(
										'figures',
										figures.filter(
											( item ) => item.id !== figure.id
										)
									)
								}
							>
								{ __( 'Remove placement', 'ohmylms' ) }
							</Button>
						</fieldset>
					);
				} ) }
				<CheckboxControl
					label={ __(
						'This question requires a figure or table',
						'ohmylms'
					) }
					checked={ !! question.requires_source }
					onChange={ ( required ) =>
						onChange( 'requires_source', required )
					}
				/>
				<CheckboxControl
					label={ __(
						'I confirm all required figures are complete, or this question needs no figures',
						'ohmylms'
					) }
					checked={ !! question.figures_complete }
					onChange={ ( complete ) =>
						onChange( 'figures_complete', complete )
					}
				/>
			</fieldset>
		</section>
	);
}

/**
 * Select normalized crop bounds with a pointer; the numeric controls also support keyboards.
 *
 * @param {Object}                     props          Component properties.
 * @param {string}                     props.url      Protected full-page image.
 * @param {number[]}                   props.bounds   Normalized bounds.
 * @param {(bounds: number[]) => void} props.onBounds Selection callback.
 * @return {Element} Page with selection overlay.
 */
function CropReference( { url, bounds, onBounds } ) {
	const [ anchor, setAnchor ] = useState( null );
	const position = ( event ) => {
		const rect = event.currentTarget.getBoundingClientRect();
		return [
			Math.max(
				0,
				Math.min( 1, ( event.clientX - rect.left ) / rect.width )
			),
			Math.max(
				0,
				Math.min( 1, ( event.clientY - rect.top ) / rect.height )
			),
		];
	};
	const select = ( event ) => {
		if ( anchor ) {
			const end = position( event );
			onBounds( [
				Math.min( anchor[ 0 ], end[ 0 ] ),
				Math.min( anchor[ 1 ], end[ 1 ] ),
				Math.max( anchor[ 0 ], end[ 0 ] ),
				Math.max( anchor[ 1 ], end[ 1 ] ),
			] );
		}
	};
	return (
		<button
			type="button"
			className="ohmylms-crop-reference"
			aria-label={ __(
				'Drag to select a crop, or use the percentage fields',
				'ohmylms'
			) }
			onPointerDown={ ( event ) => {
				event.currentTarget.setPointerCapture( event.pointerId );
				setAnchor( position( event ) );
			} }
			onPointerMove={ select }
			onPointerUp={ ( event ) => {
				select( event );
				setAnchor( null );
			} }
			onPointerCancel={ () => setAnchor( null ) }
		>
			<img src={ url } alt="" draggable={ false } />
			<span
				aria-hidden="true"
				style={ {
					left: `${ bounds[ 0 ] * 100 }%`,
					top: `${ bounds[ 1 ] * 100 }%`,
					width: `${ ( bounds[ 2 ] - bounds[ 0 ] ) * 100 }%`,
					height: `${ ( bounds[ 3 ] - bounds[ 1 ] ) * 100 }%`,
				} }
			/>
		</button>
	);
}

/**
 * Mount a disposable comparison using the same fixed renderer as the student.
 *
 * @param {Object}  props            Preview properties.
 * @param {Object}  props.spec       Validated proposal.
 * @param {string}  props.url        Protected original.
 * @param {string}  props.alt        Accessible description.
 * @param {boolean} props.notToScale Whether a scale warning is printed.
 * @return {Element} Preview.
 */
function ReconstructionPreview( { spec, url, alt, notToScale = false } ) {
	useEffect( () => {
		window.OhMyLMSFigures?.scan();
	}, [ spec, url, alt ] );
	return (
		<figure
			key={ JSON.stringify( spec ) }
			className="ohmylms-import-figure"
			data-figure-spec={ JSON.stringify( spec ) }
		>
			<img src={ url } alt={ alt } />
			{ notToScale && (
				<figcaption>{ __( 'Not to scale', 'ohmylms' ) }</figcaption>
			) }
		</figure>
	);
}
