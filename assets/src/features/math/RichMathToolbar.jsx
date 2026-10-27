/** @jsx createElement */
import { createElement, useContext } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { MathVariables } from './MathInput';
import { mathEnabled } from './loader.mjs';

/**
 * Equation tools keep the text selection when clicked.
 * @param {Object}                   props          Toolbar properties.
 * @param {(source: string) => void} props.onInsert Insert at the text caret.
 * @return {Object|null} Toolbar buttons.
 */
export function RichMathToolbar( { onInsert } ) {
	const variables = useContext( MathVariables );
	if ( ! mathEnabled() ) {
		return null;
	}
	const tools = [
		[ __( 'Insert equation', 'ohmylms' ), '', '∑' ],
		[ __( 'Insert fraction', 'ohmylms' ), '\\frac{a}{b}', 'a/b' ],
		[ __( 'Insert power', 'ohmylms' ), 'x^{2}', 'x²' ],
		[ __( 'Insert square root', 'ohmylms' ), '\\sqrt{x}', '√' ],
		[ __( 'Insert sine', 'ohmylms' ), '\\sin(x)', 'sin' ],
	];
	return (
		<div
			className="ohmylms-rich-math-tools"
			role="group"
			aria-label={ __( 'Equation tools', 'ohmylms' ) }
		>
			{ tools.map( ( [ label, source, text ] ) => (
				<Button
					key={ label }
					label={ label }
					onMouseDown={ ( event ) => event.preventDefault() }
					onClick={ () => onInsert( source ) }
				>
					{ text }
				</Button>
			) ) }
			{ variables.map( ( variable ) => (
				<Button
					key={ variable.name }
					onMouseDown={ ( event ) => event.preventDefault() }
					onClick={ () => onInsert( `{{${ variable.name }}}` ) }
				>{ `{{${ variable.name }}}` }</Button>
			) ) }
		</div>
	);
}
