import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	getQuizColorFields,
	getQuizColors,
	isDirty,
	saveQuizColors,
	validateQuizColors,
	type QuizColors,
} from './designSettings';
import { ColorField } from './fields';
import { FormFrame } from './FormFrame';
import { useResource } from './useResource';

/**
 * Quiz design tab (`/settings/quiz-design`): the colours of the quiz editor stage and the quiz player.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function QuizDesignForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< QuizColors, QuizColors >(
		{
			load: async ( signal ) => ( await getQuizColors( signal ) ).values,
			toDraft: ( stored ) => ( { ...stored } ),
			isDirty,
			validate: validateQuizColors,
			save: async ( stored, draft, signal ) => {
				const result = await saveQuizColors( stored, draft, signal );
				return { snapshot: result.values, changed: result.changed };
			},
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const draft = state.draft;

	return (
		<FormFrame
			title={ __( 'Quiz design', 'ohmylms' ) }
			description={ __(
				'Colours of interactive quiz questions in the editor stage and in the learner quiz player.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft &&
				getQuizColorFields().map( ( { name, label } ) => (
					<ColorField
						key={ name }
						label={ label }
						pickerLabel={ sprintf(
							/* translators: %s: colour name. */
							__( 'Pick %s', 'ohmylms' ),
							label
						) }
						value={ draft[ name ] ?? '' }
						error={ state.errors[ name ] }
						onChange={ ( value ) =>
							state.update(
								( current ) => ( {
									...current,
									[ name ]: value,
								} ),
								name
							)
						}
					/>
				) ) }
		</FormFrame>
	);
}
