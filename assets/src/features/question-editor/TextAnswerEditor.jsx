/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createTextAnswerEditor( readRuntime ) {
	return function TextAnswerEditor( props ) {
		const { React, b: I18n, ld } = readRuntime();
		var message = props.message,
			n =
				void 0 === message
					? ( 0, I18n.__ )(
							'No options are necessary for this question type',
							'ohmylms'
						)
					: message;
		return (
			<React.Fragment>
				<div className={ 'ohmylms-quiz-warning' }>
					<ld.A />
					{ n }
				</div>
			</React.Fragment>
		);
	};
}
