import { createElement } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { NativeQuizRoute } from '../learning/NativeQuizRoute';

/**
 * Preserve the public quiz factory and redirect former routes into Gutenberg.
 * @param {() => Object} readRuntime Read the application's runtime bindings.
 */
export function createQuizEditor( readRuntime ) {
	return function QuizEditor( props ) {
		const runtime = readRuntime();
		const id = useSelect(
			( getStore ) => getStore( runtime.T.default ).getSelectedQuizId(),
			[ runtime.T.default ]
		);
		return <NativeQuizRoute id={ id } chapterId={ props.chapterId } />;
	};
}
