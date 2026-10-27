/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const labels = () => ( {
	write: __( 'Write here', 'ohmylms' ),
	bank: __( 'Pick from bank', 'ohmylms' ),
	skills: __( 'From skills', 'ohmylms' ),
} );

/**
 * The one choice of where a question comes from, shown by both the Question block and the Question Player
 * block so they read as a single block.
 *
 * @param {Object}                   root0               Component properties.
 * @param {string}                   root0.source        write, bank or skills.
 * @param {string[]}                 root0.allowed       Sources offered here.
 * @param {(source: string) => void} root0.onChange      Called with the chosen source.
 * @param {boolean}                  root0.allowReselect Call onChange for the active source too.
 * @return {Element|null} Switch, or nothing when there is only one source.
 */
export function QuestionSourceSwitch( {
	source,
	allowed,
	onChange,
	allowReselect = false,
} ) {
	if ( allowed.length < 2 ) {
		return null;
	}
	const text = labels();
	return (
		<div
			className="ohmylms-question-source"
			role="group"
			aria-label={ __( 'Question source', 'ohmylms' ) }
		>
			{ allowed.map( ( option ) => (
				<Button
					key={ option }
					variant={ source === option ? 'primary' : 'secondary' }
					isPressed={ source === option }
					onClick={ () =>
						( allowReselect || source !== option ) &&
						onChange( option )
					}
				>
					{ text[ option ] }
				</Button>
			) ) }
		</div>
	);
}
