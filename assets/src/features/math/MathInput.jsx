/** @jsx createElement */
import {
	createElement,
	createContext,
	useContext,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import {
	Button,
	Modal,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { loadMath, mathEnabled } from './loader.mjs';
import { equationMarker, equationPattern } from './content.mjs';

export const MathVariables = createContext( [] );

/**
 * Controlled author input with a working text fallback.
 * @param {Object}                   props          Field properties.
 * @param {string}                   props.label    Accessible label.
 * @param {string}                   props.value    Original source.
 * @param {(source: string) => void} props.onChange Save source.
 * @param {string}                   props.help     Help text.
 * @param {boolean}                  props.disabled Readonly state.
 * @return {Object} Field.
 */
export function MathInput( {
	label,
	value = '',
	onChange,
	help,
	disabled = false,
} ) {
	const input = useRef( null );
	const variables = useContext( MathVariables );
	useEffect( () => {
		let disposed = false;
		let cleanup;
		// MathLive belongs to its loading window; keep the text input in Gutenberg
		// canvas documents rather than moving a custom element across documents.
		if ( mathEnabled() && input.current.ownerDocument === document ) {
			loadMath()
				.then( ( runtime ) => {
					if ( ! disposed ) {
						cleanup = runtime.mountInput( input.current );
					}
				} )
				.catch( () => {} );
		}
		return () => {
			disposed = true;
			cleanup?.();
		};
	}, [] );
	useEffect( () => {
		input.current.value = value;
		input.current.dispatchEvent( new Event( 'change' ) );
	}, [ value ] );
	return (
		<div className="ohmylms-author-math">
			<span>{ label }</span>
			<input
				ref={ input }
				type="text"
				aria-label={ label }
				defaultValue={ value }
				readOnly={ disabled }
				onInput={ ( event ) => onChange( event.target.value ) }
			/>
			{ help && <p>{ help }</p> }
			{ mathEnabled() && ! disabled && variables.length > 0 && (
				<div
					role="group"
					aria-label={ __( 'Randomized variables', 'ohmylms' ) }
				>
					{ variables.map( ( variable ) => (
						<Button
							key={ variable.name }
							variant="tertiary"
							onClick={ () =>
								onChange( `${ value }{{${ variable.name }}}` )
							}
						>{ `{{${ variable.name }}}` }</Button>
					) ) }
				</div>
			) }
		</div>
	);
}

/**
 * Insert or replace a marked equation in an existing content string.
 * @param {Object}                   props            Content properties.
 * @param {string}                   props.value      Content.
 * @param {(source: string) => void} props.onChange   Save content.
 * @param {boolean}                  props.html       Whether content is HTML.
 * @param {boolean}                  props.showInsert Whether to show the modal insertion action.
 * @return {Object|null} Toolbar.
 */
export function EquationAction( {
	value = '',
	onChange,
	html = false,
	showInsert = true,
} ) {
	const variables = useContext( MathVariables );
	const [ editing, setEditing ] = useState( null );
	const [ latex, setLatex ] = useState( '' );
	const [ mode, setMode ] = useState( 'inline' );
	if ( ! mathEnabled() ) {
		return null;
	}
	const equations = [ ...value.matchAll( equationPattern() ) ];
	const open = ( match ) => {
		setEditing( match || { index: value.length, 0: '' } );
		const source = match?.[ 2 ] || '';
		setLatex( html ? decodeEntities( source ) : source );
		setMode( match?.[ 1 ] || 'inline' );
	};
	return (
		<div className="ohmylms-equation-actions">
			{ showInsert && (
				<Button variant="secondary" onClick={ () => open() }>
					{ __( 'Insert equation', 'ohmylms' ) }
				</Button>
			) }
			{ equations.map( ( match, index ) => (
				<Button
					key={ match.index }
					variant="tertiary"
					onClick={ () => open( match ) }
				>{ `${ __( 'Edit equation', 'ohmylms' ) } ${ index + 1 }` }</Button>
			) ) }
			{ editing && (
				<Modal
					title={ __( 'Equation', 'ohmylms' ) }
					onRequestClose={ () => setEditing( null ) }
				>
					<MathInput
						label={ __( 'LaTeX source', 'ohmylms' ) }
						value={ latex }
						onChange={ setLatex }
					/>
					<details>
						<summary>
							{ __( 'Edit original source', 'ohmylms' ) }
						</summary>
						<TextareaControl
							label={ __(
								'Original source / random variable tokens',
								'ohmylms'
							) }
							value={ latex }
							onChange={ setLatex }
							help={ __(
								'Use {{a}} for a drawn variable, or {{a*b}} for a computed value. Source is preserved when saved.',
								'ohmylms'
							) }
						/>
					</details>
					{ variables.length > 0 && (
						<div
							role="group"
							aria-label={ __(
								'Insert randomized variable',
								'ohmylms'
							) }
						>
							{ variables.map( ( variable ) => (
								<Button
									key={ variable.name }
									variant="secondary"
									onClick={ () =>
										setLatex(
											`${ latex }{{${ variable.name }}}`
										)
									}
								>{ `{{${ variable.name }}}` }</Button>
							) ) }
						</div>
					) }
					<SelectControl
						label={ __( 'Layout', 'ohmylms' ) }
						value={ mode }
						options={ [
							{
								value: 'inline',
								label: __( 'Inline', 'ohmylms' ),
							},
							{
								value: 'display',
								label: __( 'Display', 'ohmylms' ),
							},
						] }
						onChange={ setMode }
					/>
					<Button
						variant="primary"
						disabled={
							! latex ||
							latex.length > 2000 ||
							latex.includes( '[[/' )
						}
						onClick={ () => {
							let marker = equationMarker( latex, mode );
							if ( html ) {
								const node = document.createElement( 'span' );
								node.textContent = marker;
								marker = node.innerHTML;
							}
							onChange(
								value.slice( 0, editing.index ) +
									marker +
									value.slice(
										editing.index + editing[ 0 ].length
									)
							);
							setEditing( null );
						} }
					>
						{ __( 'Save equation', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</div>
	);
}

/**
 * Text content control with the same equation action as the prompt editor.
 * @param {Object} props Existing textarea properties.
 * @return {Object} Control.
 */
export function EquationTextControl( props ) {
	return (
		<div>
			<TextareaControl { ...props } />
			<EquationAction value={ props.value } onChange={ props.onChange } />
		</div>
	);
}
