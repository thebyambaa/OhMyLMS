import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { isDirty } from './designSettings';
import { NumberField, ToggleField } from './fields';
import { FormFrame } from './FormFrame';
import {
	BOUNDARIES,
	getGradeScale,
	saveGradeScale,
	scaleRanges,
	validateGradeScale,
	type GradeScaleValues,
} from './gradeScale';
import { useResource } from './useResource';

/**
 * Letter grades tab (`/settings/grade-scale`): the one site-wide A–F scale the gradebook uses, and whether
 * missing work counts as zero. Percentages are always shown next to letters.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function GradeScaleForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< GradeScaleValues, GradeScaleValues >(
		{
			load: async ( signal ) => ( await getGradeScale( signal ) ).values,
			toDraft: ( stored ) => ( { ...stored } ),
			isDirty,
			validate: validateGradeScale,
			save: async ( stored, draft, signal ) => {
				const result = await saveGradeScale( stored, draft, signal );
				return { snapshot: result.values, changed: result.changed };
			},
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const draft = state.draft;
	const set = < K extends keyof GradeScaleValues >(
		key: K,
		value: GradeScaleValues[ K ]
	) => state.update( ( current ) => ( { ...current, [ key ]: value } ), key );

	return (
		<FormFrame
			title={ __( 'Letter grades', 'ohmylms' ) }
			description={ __(
				'One scale for the whole site. The gradebook shows the letter next to every course total; percentages are always shown too.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<fieldset className="ohmylms-settings-fieldset">
						<legend>
							<strong>
								{ __(
									'Lowest percentage for each letter',
									'ohmylms'
								) }
							</strong>
						</legend>
						{ BOUNDARIES.map( ( [ name, letter ] ) => (
							<NumberField
								key={ name }
								label={ sprintf(
									/* translators: %s: letter grade. */
									__( '%s from (%%)', 'ohmylms' ),
									letter
								) }
								value={ draft[ name ] }
								min={ 1 }
								max={ 100 }
								step={ 1 }
								error={ state.errors[ name ] }
								onChange={ ( value ) => set( name, value ) }
							/>
						) ) }
						<p className="description">
							{ __(
								'F is everything below D. Each letter must start below the one above it.',
								'ohmylms'
							) }
						</p>
						<WidefatTable
							caption={ __( 'Preview of the scale', 'ohmylms' ) }
							striped={ false }
						>
							<tbody>
								{ scaleRanges( draft ).map( ( row ) => (
									<tr key={ row.letter }>
										<th scope="row">{ row.letter }</th>
										<td>{ row.range }</td>
									</tr>
								) ) }
							</tbody>
						</WidefatTable>
					</fieldset>
					<fieldset className="ohmylms-settings-fieldset">
						<legend>
							<strong>{ __( 'Missing work', 'ohmylms' ) }</strong>
						</legend>
						<ToggleField
							label={ __(
								'Count missing work as zero',
								'ohmylms'
							) }
							help={ __(
								'Missing means assigned, past its due date and never submitted. Work waiting for grading, not yet due, not assigned or excused never counts.',
								'ohmylms'
							) }
							checked={ draft.missing_as_zero }
							onChange={ ( value ) =>
								set( 'missing_as_zero', value )
							}
						/>
					</fieldset>
				</>
			) }
		</FormFrame>
	);
}
