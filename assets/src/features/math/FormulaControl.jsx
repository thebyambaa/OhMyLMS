/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { MathInput } from './MathInput';
import { mathEnabled } from './loader.mjs';

/**
 * Optional teacher syntax check; the server preview remains authoritative.
 * @param {Object} props Original formula field properties.
 * @return {Object} Field and advisory status.
 */
export function FormulaControl( props ) {
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const inspect = async () => {
		setBusy( true );
		try {
			const { inspectFormula } = await import( './teacherCompute.mjs' );
			setResult(
				inspectFormula( props.value || '' ).valid
					? __(
							'Syntax recognized. Show examples to check server support and sampled values.',
							'ohmylms'
						)
					: __( 'Check the formula syntax.', 'ohmylms' )
			);
		} catch {
			setResult(
				__(
					'Syntax preview unavailable. Use Show examples for server validation.',
					'ohmylms'
				)
			);
		}
		setBusy( false );
	};
	return (
		<div>
			<MathInput { ...props } />
			{ mathEnabled() && (
				<Button
					variant="secondary"
					disabled={ busy || props.disabled }
					isBusy={ busy }
					onClick={ inspect }
				>
					{ __( 'Check syntax with Compute Engine', 'ohmylms' ) }
				</Button>
			) }
			{ result && <p role="status">{ result }</p> }
		</div>
	);
}
