/** @jsx createElement */
import { createElement, useEffect, useRef } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { MathfieldElement } from 'ohmylms/mathlive';
import metadata from './block.json';
import { COLORS, MAX_FUNCTIONS, MAX_PARAMS } from './config.mjs';
import { GRAPH, defaultConfig, normalizeActivity } from './activities.mjs';
import { MOUNTERS } from './registry.mjs';
import { ActivitySettings } from './settings.jsx';

/**
 * @return {Object} Interface text for the activity, translated for the editor.
 */
function labels() {
	return {
		graph: __( 'Graph', 'ohmylms' ),
		equations: __( 'Equations', 'ohmylms' ),
		equation: __( 'Equation', 'ohmylms' ),
		addFunction: __( 'Add function', 'ohmylms' ),
		remove: __( 'Remove function', 'ohmylms' ),
		parameters: __( 'Parameters', 'ohmylms' ),
		invalid: __( 'Cannot graph this expression yet.', 'ohmylms' ),
		numberLine: __( 'Number line', 'ohmylms' ),
		point: __( 'Point', 'ohmylms' ),
		value: __( 'Value', 'ohmylms' ),
		fractions: __( 'Fraction', 'ohmylms' ),
		parts: __( 'Equal parts', 'ohmylms' ),
		shaded: __( 'Shaded', 'ohmylms' ),
		part: __( 'Part', 'ohmylms' ),
		simplified: __( 'Simplest form', 'ohmylms' ),
		decimal: __( 'Decimal', 'ohmylms' ),
		percent: __( 'Percent', 'ohmylms' ),
		clock: __( 'Clock', 'ohmylms' ),
		hours: __( 'Hours', 'ohmylms' ),
		minutes: __( 'Minutes', 'ohmylms' ),
		converter: __( 'Unit converter', 'ohmylms' ),
		quantity: __( 'Quantity', 'ohmylms' ),
		amount: __( 'Amount', 'ohmylms' ),
		from: __( 'From', 'ohmylms' ),
		to: __( 'To', 'ohmylms' ),
		length: __( 'Length', 'ohmylms' ),
		mass: __( 'Mass', 'ohmylms' ),
		time: __( 'Time', 'ohmylms' ),
		volume: __( 'Volume', 'ohmylms' ),
		chart: __( 'Chart', 'ohmylms' ),
		label: __( 'Label', 'ohmylms' ),
		total: __( 'Total', 'ohmylms' ),
		mean: __( 'Mean', 'ohmylms' ),
		median: __( 'Median', 'ohmylms' ),
		mode: __( 'Mode', 'ohmylms' ),
		noMode: __( 'none', 'ohmylms' ),
		range: __( 'Range', 'ohmylms' ),
		geometry: __( 'Geometry', 'ohmylms' ),
		side: __( 'Side', 'ohmylms' ),
		perimeter: __( 'Perimeter', 'ohmylms' ),
		area: __( 'Area', 'ohmylms' ),
		angle: __( 'Angle', 'ohmylms' ),
		radius: __( 'Radius', 'ohmylms' ),
		circumference: __( 'Circumference', 'ohmylms' ),
		noArea: __( 'The sides cross, so there is no single area.', 'ohmylms' ),
	};
}

/** @return {Object[]} Picker choices, one per activity type. */
function activityChoices() {
	return [
		{ value: GRAPH, label: __( 'Function graph', 'ohmylms' ) },
		{ value: 'number-line', label: __( 'Number line', 'ohmylms' ) },
		{ value: 'fractions', label: __( 'Fractions', 'ohmylms' ) },
		{ value: 'clock', label: __( 'Clock', 'ohmylms' ) },
		{ value: 'converter', label: __( 'Unit converter', 'ohmylms' ) },
		{ value: 'chart', label: __( 'Chart', 'ohmylms' ) },
		{ value: 'geometry', label: __( 'Geometry', 'ohmylms' ) },
	];
}

/**
 * Numeric field that keeps the raw text while typing and saves finite numbers only.
 *
 * @param {Object}                props          Field properties.
 * @param {string}                props.label    Field label.
 * @param {number}                props.value    Saved number.
 * @param {function(number):void} props.onChange Save a number.
 * @return {Element} Control.
 */
function NumberControl( { label, value, onChange } ) {
	return (
		<TextControl
			label={ label }
			type="number"
			value={ String( value ) }
			onChange={ ( text ) => {
				if ( text !== '' && Number.isFinite( Number( text ) ) ) {
					onChange( Number( text ) );
				}
			} }
		/>
	);
}

/**
 * MathLive field for the sidebar. The editor canvas is an iframe that MathLive cannot style,
 * so equations are edited here in the main window and shown as text in the canvas.
 *
 * @param {Object}                props          Field properties.
 * @param {string}                props.label    Accessible label.
 * @param {string}                props.value    Saved LaTeX.
 * @param {function(string):void} props.onChange Save LaTeX.
 * @return {Element} Field host.
 */
function EquationField( { label, value, onChange } ) {
	const host = useRef( null );
	const field = useRef( null );
	const latest = useRef( onChange );
	latest.current = onChange;
	useEffect( () => {
		MathfieldElement.fontsDirectory = window.ohmylmsActivity?.fonts;
		MathfieldElement.soundsDirectory = null;
		MathfieldElement.computeEngine = null;
		const math = new MathfieldElement();
		math.className = 'ohmylms-activity-field';
		math.setAttribute( 'aria-label', label );
		math.mathVirtualKeyboardPolicy = 'auto';
		math.smartMode = false;
		math.setValue( value, { format: 'latex', silenceNotifications: true } );
		math.addEventListener( 'input', () =>
			latest.current( math.getValue( 'latex' ) )
		);
		host.current.append( math );
		math.menuItems = [];
		field.current = math;
		return () => math.remove();
		// Created once; later saved values are pushed in below.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );
	useEffect( () => {
		if ( field.current && field.current.getValue( 'latex' ) !== value ) {
			field.current.setValue( value, {
				format: 'latex',
				silenceNotifications: true,
			} );
		}
	}, [ value ] );
	return <div ref={ host } />;
}

/**
 * Live preview of the learner view; the graph's equations are edited in the sidebar.
 *
 * @param {Object} props          Preview properties.
 * @param {string} props.activity Activity type.
 * @param {Object} props.config   Normalized configuration.
 * @return {Element} Preview host.
 */
function ActivityPreview( { activity, config } ) {
	const host = useRef( null );
	const handle = useRef( null );
	useEffect( () => {
		handle.current = MOUNTERS[ activity ]( host.current, config, {
			preview: true,
			labels: labels(),
		} );
		return () => handle.current.destroy();
		// Mounted again for another activity; later edits go through update().
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ activity ] );
	useEffect( () => {
		handle.current?.update( config );
	}, [ config ] );
	return <div ref={ host } className="ohmylms-activity-host" />;
}

/**
 * Editor for the interactive activity block.
 *
 * @param {Object}                props               Block editor properties.
 * @param {Object}                props.attributes    Saved activity and configuration.
 * @param {function(Object):void} props.setAttributes Update saved attributes.
 * @return {Element} Editor.
 */
export function InteractiveActivityEdit( { attributes, setAttributes } ) {
	const activity = MOUNTERS[ attributes.activity ]
		? attributes.activity
		: GRAPH;
	const isGraph = activity === GRAPH;
	const config = attributes.config?.version
		? normalizeActivity( activity, attributes.config )
		: defaultConfig( activity );
	const blockProps = useBlockProps( { className: 'ohmylms-activity' } );
	const save = ( next ) => setAttributes( { config: next } );
	const setView = ( key, value ) =>
		save( { ...config, view: { ...config.view, [ key ]: value } } );
	const setParam = ( index, key, value ) =>
		save( {
			...config,
			params: config.params.map( ( param, position ) =>
				position === index ? { ...param, [ key ]: value } : param
			),
		} );
	const setFunction = ( index, latex ) =>
		save( {
			...config,
			functions: config.functions.map( ( item, position ) =>
				position === index ? { ...item, latex } : item
			),
		} );
	const addFunction = () => {
		let number = config.functions.length + 1;
		while (
			config.functions.some( ( item ) => item.id === `f${ number }` )
		) {
			number++;
		}
		const color =
			COLORS.find(
				( candidate ) =>
					! config.functions.some(
						( item ) => item.color === candidate
					)
			) || COLORS[ config.functions.length % COLORS.length ];
		save( {
			...config,
			functions: [
				...config.functions,
				{ id: `f${ number }`, latex: '', color },
			],
		} );
	};
	const usedNames = config.params.map( ( param ) => param.name );
	const freeName = 'abcdfghijklmnopqrstuvwyz'
		.split( '' )
		.find( ( name ) => ! usedNames.includes( name ) );
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Activity', 'ohmylms' ) }>
					<SelectControl
						label={ __( 'Type', 'ohmylms' ) }
						value={ activity }
						options={ activityChoices() }
						onChange={ ( next ) =>
							setAttributes( {
								activity: next,
								config: defaultConfig( next ),
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ ! isGraph && (
				<ActivitySettings
					activity={ activity }
					config={ config }
					save={ save }
				/>
			) }
			{ isGraph && (
				<InspectorControls>
					<PanelBody title={ __( 'Equations', 'ohmylms' ) }>
						{ config.functions.map( ( item, index ) => (
							<div
								key={ item.id }
								className="ohmylms-activity-equation-editor"
								style={ {
									'--ohmylms-activity-color': item.color,
								} }
							>
								<EquationField
									label={ __( 'Equation', 'ohmylms' ) }
									value={ item.latex }
									onChange={ ( latex ) =>
										setFunction( index, latex )
									}
								/>
								{ config.functions.length > 1 && (
									<Button
										variant="link"
										isDestructive
										onClick={ () =>
											save( {
												...config,
												functions:
													config.functions.filter(
														( other, position ) =>
															position !== index
													),
											} )
										}
									>
										{ __( 'Remove function', 'ohmylms' ) }
									</Button>
								) }
							</div>
						) ) }
						{ config.functions.length < MAX_FUNCTIONS && (
							<Button variant="secondary" onClick={ addFunction }>
								{ __( 'Add function', 'ohmylms' ) }
							</Button>
						) }
					</PanelBody>
					<PanelBody title={ __( 'Graph window', 'ohmylms' ) }>
						{ [
							[ 'xMin', __( 'x minimum', 'ohmylms' ) ],
							[ 'xMax', __( 'x maximum', 'ohmylms' ) ],
							[ 'yMin', __( 'y minimum', 'ohmylms' ) ],
							[ 'yMax', __( 'y maximum', 'ohmylms' ) ],
						].map( ( [ key, label ] ) => (
							<NumberControl
								key={ key }
								label={ label }
								value={ config.view[ key ] }
								onChange={ ( value ) => setView( key, value ) }
							/>
						) ) }
					</PanelBody>
					<PanelBody
						title={ __( 'Variables (sliders)', 'ohmylms' ) }
						initialOpen
					>
						<p>
							{ __(
								'Use a variable name as a letter in an equation, for example a x^2 + b.',
								'ohmylms'
							) }
						</p>
						{ config.params.map( ( param, index ) => (
							<div
								key={ param.name }
								className="ohmylms-activity-param-editor"
							>
								<strong>{ param.name }</strong>
								{ [
									[ 'min', __( 'Minimum', 'ohmylms' ) ],
									[ 'max', __( 'Maximum', 'ohmylms' ) ],
									[ 'step', __( 'Step', 'ohmylms' ) ],
									[ 'value', __( 'Start value', 'ohmylms' ) ],
								].map( ( [ key, label ] ) => (
									<NumberControl
										key={ key }
										label={ label }
										value={ param[ key ] }
										onChange={ ( value ) =>
											setParam( index, key, value )
										}
									/>
								) ) }
								<Button
									variant="link"
									isDestructive
									onClick={ () =>
										save( {
											...config,
											params: config.params.filter(
												( item, position ) =>
													position !== index
											),
										} )
									}
								>
									{ __( 'Remove variable', 'ohmylms' ) }
								</Button>
							</div>
						) ) }
						{ config.params.length < MAX_PARAMS && freeName && (
							<Button
								variant="secondary"
								onClick={ () =>
									save( {
										...config,
										params: [
											...config.params,
											{
												name: freeName,
												min: -5,
												max: 5,
												step: 0.1,
												value: 1,
											},
										],
									} )
								}
							>
								{ __( 'Add variable', 'ohmylms' ) }
							</Button>
						) }
					</PanelBody>
					<PanelBody
						title={ __( 'Learner options', 'ohmylms' ) }
						initialOpen={ false }
					>
						<ToggleControl
							label={ __(
								'Learners can edit equations',
								'ohmylms'
							) }
							checked={ config.allowEdit }
							onChange={ ( value ) =>
								save( { ...config, allowEdit: value } )
							}
						/>
						<ToggleControl
							label={ __(
								'Learners can add functions',
								'ohmylms'
							) }
							checked={ config.allowAdd }
							onChange={ ( value ) =>
								save( { ...config, allowAdd: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
			) }
			<ActivityPreview
				key={ activity }
				activity={ activity }
				config={ config }
			/>
		</div>
	);
}

registerBlockType( metadata, {
	edit: InteractiveActivityEdit,
	save: () => null,
} );
