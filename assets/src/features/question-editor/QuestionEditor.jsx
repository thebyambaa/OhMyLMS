import { createElement } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
export function QuestionEditor( { entry, store } ) {
	const question = useSelect(
		( select ) => select( store ).selectQuestion(),
		[ store ]
	);
	const { updateQuestionData } = useDispatch( store );
	return (
		<ExtensionBoundary id={ entry.id }>
			{ createElement( entry.render, {
				question,
				value: question?.settings || {},
				onChange: ( settings ) =>
					updateQuestionData( question?.id, {
						settings: {
							...question?.settings,
							...settings,
							type: entry.id,
						},
					} ),
			} ) }
		</ExtensionBoundary>
	);
}
