/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	CHART_KINDS,
	CLOCK_SNAPS,
	FRACTION_SHAPES,
	MAX_ITEMS,
	MAX_PARTS,
	MAX_POINTS,
	SHAPES,
	UNITS,
	normalizeActivity,
} from './activities.mjs';

/**
 * Numeric field that keeps the raw text while typing and saves finite numbers only.
 *
 * @param {Object}                props          Field properties.
 * @param {string}                props.label    Field label.
 * @param {number}                props.value    Saved number.
 * @param {function(number):void} props.onChange Save a number.
 * @param {number}                props.min      Smallest value.
 * @param {number}                props.max      Largest value.
 * @return {Element} Control.
 */
function NumberField( { label, value, onChange, min, max } ) {
	return (
		<TextControl
			label={ label }
			type="number"
			min={ min }
			max={ max }
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
 * Text that is saved when the author leaves the field, so a half-typed list is never rewritten.
 *
 * @param {Object}                props           Field properties.
 * @param {string}                props.label     Field label.
 * @param {string}                props.help      Help text.
 * @param {string}                props.value     Saved text.
 * @param {function(string):void} props.onCommit  Save the text.
 * @param {boolean}               props.multiline Use a textarea.
 * @return {Element} Control.
 */
function CommitText( { label, help, value, onCommit, multiline } ) {
	const [ draft, setDraft ] = useState( value );
	useEffect( () => setDraft( value ), [ value ] );
	const Control = multiline ? TextareaControl : TextControl;
	return (
		<Control
			label={ label }
			help={ help }
			value={ draft }
			onChange={ setDraft }
			onBlur={ () => onCommit( draft ) }
		/>
	);
}

const options = ( values, labels = {} ) =>
	values.map( ( value ) => ( {
		value: String( value ),
		label: labels[ value ] || String( value ),
	} ) );

/**
 * The sidebar panels of every activity other than the function graph.
 *
 * @param {Object}                props          Properties.
 * @param {string}                props.activity Activity type.
 * @param {Object}                props.config   Normalized configuration.
 * @param {function(Object):void} props.save     Save a configuration.
 * @return {Element|null} Panel.
 */
export function ActivitySettings( { activity, config, save } ) {
	const set = ( changes ) =>
		save( normalizeActivity( activity, { ...config, ...changes } ) );
	const toggle = ( key, label ) => (
		<ToggleControl
			label={ label }
			checked={ !! config[ key ] }
			onChange={ ( value ) => set( { [ key ]: value } ) }
		/>
	);
	let panel = null;
	switch ( activity ) {
		case 'number-line':
			panel = (
				<PanelBody title={ __( 'Number line', 'ohmylms' ) }>
					<NumberField
						label={ __( 'Smallest number', 'ohmylms' ) }
						value={ config.min }
						onChange={ ( min ) => set( { min } ) }
					/>
					<NumberField
						label={ __( 'Largest number', 'ohmylms' ) }
						value={ config.max }
						onChange={ ( max ) => set( { max } ) }
					/>
					<NumberField
						label={ __( 'Step between ticks', 'ohmylms' ) }
						value={ config.step }
						onChange={ ( step ) => set( { step } ) }
					/>
					<CommitText
						label={ __( 'Start positions', 'ohmylms' ) }
						help={ __(
							'Numbers separated by commas, one per movable point.',
							'ohmylms'
						) }
						value={ config.points.join( ', ' ) }
						onCommit={ ( text ) =>
							set( {
								points: text
									.split( ',' )
									.map( ( part ) => part.trim() )
									.slice( 0, MAX_POINTS ),
							} )
						}
					/>
					{ toggle(
						'snap',
						__( 'Points snap to the ticks', 'ohmylms' )
					) }
				</PanelBody>
			);
			break;
		case 'fractions':
			panel = (
				<PanelBody title={ __( 'Fractions', 'ohmylms' ) }>
					<SelectControl
						label={ __( 'Shape', 'ohmylms' ) }
						value={ config.shape }
						options={ options( FRACTION_SHAPES, {
							bar: __( 'Bar', 'ohmylms' ),
							circle: __( 'Circle', 'ohmylms' ),
						} ) }
						onChange={ ( shape ) => set( { shape } ) }
					/>
					<NumberField
						label={ __( 'Equal parts', 'ohmylms' ) }
						value={ config.parts }
						min={ 1 }
						max={ MAX_PARTS }
						onChange={ ( parts ) => set( { parts } ) }
					/>
					<NumberField
						label={ __( 'Parts shaded at the start', 'ohmylms' ) }
						value={ config.shaded }
						min={ 0 }
						max={ config.parts }
						onChange={ ( shaded ) => set( { shaded } ) }
					/>
					{ toggle(
						'allowParts',
						__(
							'Learners can change the number of parts',
							'ohmylms'
						)
					) }
				</PanelBody>
			);
			break;
		case 'clock':
			panel = (
				<PanelBody title={ __( 'Clock', 'ohmylms' ) }>
					<NumberField
						label={ __( 'Start hour (0 to 23)', 'ohmylms' ) }
						value={ config.hour }
						min={ 0 }
						max={ 23 }
						onChange={ ( hour ) => set( { hour } ) }
					/>
					<NumberField
						label={ __( 'Start minute (0 to 59)', 'ohmylms' ) }
						value={ config.minute }
						min={ 0 }
						max={ 59 }
						onChange={ ( minute ) => set( { minute } ) }
					/>
					<SelectControl
						label={ __(
							'Minute hand moves in steps of',
							'ohmylms'
						) }
						value={ String( config.snap ) }
						options={ options( CLOCK_SNAPS ) }
						onChange={ ( snap ) => set( { snap: Number( snap ) } ) }
					/>
					{ toggle(
						'showDigital',
						__( 'Show the digital time', 'ohmylms' )
					) }
					{ toggle(
						'format24',
						__( 'Use the 24-hour clock', 'ohmylms' )
					) }
				</PanelBody>
			);
			break;
		case 'converter':
			panel = (
				<PanelBody title={ __( 'Unit converter', 'ohmylms' ) }>
					<SelectControl
						label={ __( 'Quantity', 'ohmylms' ) }
						value={ config.quantity }
						options={ options( Object.keys( UNITS ), {
							length: __( 'Length', 'ohmylms' ),
							mass: __( 'Mass', 'ohmylms' ),
							time: __( 'Time', 'ohmylms' ),
							volume: __( 'Volume', 'ohmylms' ),
						} ) }
						onChange={ ( quantity ) =>
							// The default units of the new quantity apply.
							save(
								normalizeActivity( activity, {
									...config,
									quantity,
									from: '',
									to: '',
								} )
							)
						}
					/>
					<SelectControl
						label={ __( 'Convert from', 'ohmylms' ) }
						value={ config.from }
						options={ options(
							Object.keys( UNITS[ config.quantity ] )
						) }
						onChange={ ( from ) => set( { from } ) }
					/>
					<SelectControl
						label={ __( 'Convert to', 'ohmylms' ) }
						value={ config.to }
						options={ options(
							Object.keys( UNITS[ config.quantity ] )
						) }
						onChange={ ( to ) => set( { to } ) }
					/>
					<NumberField
						label={ __( 'Start amount', 'ohmylms' ) }
						value={ config.value }
						onChange={ ( value ) => set( { value } ) }
					/>
					{ toggle(
						'allowQuantity',
						__( 'Learners can change the quantity', 'ohmylms' )
					) }
				</PanelBody>
			);
			break;
		case 'chart':
			panel = (
				<PanelBody title={ __( 'Chart', 'ohmylms' ) }>
					<SelectControl
						label={ __( 'Kind', 'ohmylms' ) }
						value={ config.kind }
						options={ options( CHART_KINDS, {
							bar: __( 'Bar chart', 'ohmylms' ),
							pie: __( 'Pie chart', 'ohmylms' ),
							line: __( 'Line chart', 'ohmylms' ),
						} ) }
						onChange={ ( kind ) => set( { kind } ) }
					/>
					<CommitText
						label={ __( 'Title', 'ohmylms' ) }
						value={ config.title }
						onCommit={ ( title ) => set( { title } ) }
					/>
					<CommitText
						multiline
						label={ __( 'Data', 'ohmylms' ) }
						help={ __(
							'One row per line: a label, a comma, then a number. Two rows at least.',
							'ohmylms'
						) }
						value={ config.items
							.map(
								( item ) => `${ item.label }, ${ item.value }`
							)
							.join( '\n' ) }
						onCommit={ ( data ) =>
							set( {
								items: data
									.split( '\n' )
									.filter( ( line ) => line.trim() )
									.slice( 0, MAX_ITEMS )
									.map( ( line ) => {
										const cut = line.lastIndexOf( ',' );
										return cut < 0
											? { label: line.trim(), value: 0 }
											: {
													label: line
														.slice( 0, cut )
														.trim(),
													value: line
														.slice( cut + 1 )
														.trim(),
												};
									} ),
							} )
						}
					/>
					{ toggle(
						'allowEdit',
						__( 'Learners can change the numbers', 'ohmylms' )
					) }
				</PanelBody>
			);
			break;
		case 'geometry':
			panel = (
				<PanelBody title={ __( 'Geometry', 'ohmylms' ) }>
					<SelectControl
						label={ __( 'Shape', 'ohmylms' ) }
						value={ config.shape }
						options={ options( SHAPES, {
							triangle: __( 'Triangle', 'ohmylms' ),
							quadrilateral: __( 'Four-sided shape', 'ohmylms' ),
							circle: __( 'Circle', 'ohmylms' ),
							angle: __( 'Angle', 'ohmylms' ),
						} ) }
						onChange={ ( shape ) => set( { shape } ) }
					/>
					{ toggle(
						'snap',
						__( 'Corners snap to the grid', 'ohmylms' )
					) }
					{ toggle(
						'showMeasures',
						__( 'Show lengths, angles and area', 'ohmylms' )
					) }
				</PanelBody>
			);
			break;
		default:
			return null;
	}
	return <InspectorControls>{ panel }</InspectorControls>;
}
