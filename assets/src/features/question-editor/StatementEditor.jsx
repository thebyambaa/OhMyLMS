/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createStatementEditor( readRuntime ) {
	return function StatementEditor() {
		const { React, T: StoreModule, y: WordPressData } = readRuntime();
		const questionId = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).selectSelectedQuestionId();
		}, [] );
		return (
			( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( StoreModule.default ).getQuestionContents();
				},
				[ questionId ]
			),
			( 0, WordPressData.useDispatch )( StoreModule.default )
				.addContentToQuestion,
			( 0, WordPressData.useDispatch )( StoreModule.default ),
			( <React.Fragment /> )
		 );
	};
}
