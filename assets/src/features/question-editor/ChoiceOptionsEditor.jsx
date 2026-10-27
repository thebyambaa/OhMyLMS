import {
	createElement,
	Fragment,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { moveOption } from './model.mjs';
import { EquationAction } from '../math/MathInput';

/**
 * Shared single/multiple-choice editor, retaining the existing option control.
 * @param readRuntime
 */
export function createChoiceOptionsEditor( readRuntime ) {
	return function ChoiceOptionsEditor( { type = 'multiple' } ) {
		const {
			As: Option,
			T: { default: store },
			xs: ordering,
		} = readRuntime();
		const { questionId, options, hasValidationErrors } = useSelect(
			( select ) => {
				const state = select( store );
				return {
					questionId: state.selectSelectedQuestionId(),
					options: state.getQuestionContents() || [],
					hasValidationErrors: state.selectQuizzesError(),
				};
			},
			[ store ]
		);
		const { addContentToQuestion } = useDispatch( store );
		return (
			<ChoiceAnswerFields
				type={ type }
				options={ options }
				onChange={ ( next ) =>
					addContentToQuestion( questionId, next )
				}
				Option={ Option }
				ordering={ ordering }
				hasValidationErrors={ hasValidationErrors }
			/>
		);
	};
}

/**
 * Controlled version of the quiz answer editor, usable outside the quiz store.
 * @param root0
 * @param root0.type
 * @param root0.options
 * @param root0.onChange
 * @param root0.Option
 * @param root0.ordering
 * @param root0.hasValidationErrors
 */
export function ChoiceAnswerFields( {
	type = 'multiple',
	options,
	onChange,
	Option,
	ordering,
	hasValidationErrors = false,
} ) {
	const [ error, setError ] = useState( null );
	const [ focused, setFocused ] = useState( false );
	const draggedIndex = useRef( null );
	const errorTimer = useRef( null );
	useEffect( () => () => clearTimeout( errorTimer.current ), [] );
	const update = onChange;
	function addOption() {
		update( [
			...options,
			{
				id: Date.now(),
				answer: '',
				is_correct: false,
				order_number: options.length + 1,
				temp: true,
			},
		] );
	}
	function removeOption( id ) {
		if ( options.length < 3 ) {
			setError( __( 'You must have at least 2 options', 'ohmylms' ) );
			clearTimeout( errorTimer.current );
			errorTimer.current = setTimeout( () => setError( null ), 3000 );
			return;
		}
		update( options.filter( ( option ) => option.id !== id ) );
	}
	function changeAnswer( id, answer ) {
		update(
			options.map( ( option ) =>
				option.id === id
					? {
							...option,
							answer,
						}
					: option
			)
		);
	}
	function changeCorrect( id ) {
		update(
			options.map( ( option ) =>
				( () => {
					if ( type === 'single' ) {
						return {
							...option,
							is_correct: option.id === id,
						};
					}
					if ( option.id === id ) {
						return {
							...option,
							is_correct: ! option.is_correct,
						};
					}
					return option;
				} )()
			)
		);
	}
	function startDrag( event, index ) {
		draggedIndex.current = index;
		event.currentTarget.classList.add( 'dragging' );
	}
	function drop( event, index ) {
		event.preventDefault();
		if ( draggedIndex.current !== null ) {
			update( moveOption( options, draggedIndex.current, index ) );
		}
		draggedIndex.current = null;
	}
	function endDrag( event ) {
		draggedIndex.current = null;
		event.currentTarget.classList.remove( 'dragging' );
	}
	return (
		<div className={ `ohmylms-options-list ohmylms-${ type }-choice` }>
			{ ordering.I( options ).map( ( option, index ) => (
				<Fragment key={ option.id }>
					<Option
						className={ `ohmylms-quiz-option-item ohmylms-quiz-option-item-${ index }` }
						key={ option.id }
						option={ option }
						index={ index }
						type={ type }
						isInputFocused={ focused }
						showError={ hasValidationErrors }
						onDragStart={ startDrag }
						onDragOver={ ( event ) => event.preventDefault() }
						onDrop={ drop }
						onDragEnd={ endDrag }
						onTextChange={ changeAnswer }
						onCheckboxChange={ changeCorrect }
						onRemoveOption={ removeOption }
						onAddOption={ addOption }
						onInputFocus={ () => setFocused( true ) }
						onInputBlur={ () => setFocused( false ) }
					/>
					<EquationAction
						value={ option.answer || '' }
						onChange={ ( answer ) =>
							changeAnswer( option.id, answer )
						}
					/>
				</Fragment>
			) ) }
			{ error && <p className="ohmylms-option-error-msg">{ error }</p> }
			{ hasValidationErrors &&
				! options.some(
					( option ) => Number( option.is_correct ) === 1
				) && (
					<p className="ohmylms-option-error-msg">
						{ __(
							'Please select at least one correct answer',
							'ohmylms'
						) }
					</p>
				) }
		</div>
	);
}
