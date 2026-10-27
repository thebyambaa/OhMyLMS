/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createQuestionSettings( readRuntime ) {
	return function QuestionSettings( props ) {
		const {
			Ku,
			React,
			T: StoreModule,
			y: WordPressData,
			ys,
		} = readRuntime();
		var t,
			setHovered = props.setHovered,
			question = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectQuestion();
			}, [] );
		return (
			<React.Fragment>
				{ question !== null &&
				question !== void 0 &&
				null !== ( t = question.settings ) &&
				void 0 !== t &&
				t.type ? (
					<React.Fragment>
						{ React.createElement( ys, null ) }
					</React.Fragment>
				) : (
					<React.Fragment>
						<Ku setHovered={ setHovered } />
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
