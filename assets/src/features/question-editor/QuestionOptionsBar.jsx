/** @jsx createElement */
import { isUngradedType } from './extendedModel.mjs';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { RichContentControl } from '../math/RichContentControl';
import { TextControl, ToggleControl } from '@wordpress/components';

export function QuestionOptionsBar( {
	question,
	onChange,
	hideRequired = false,
	hideRandomize = false,
} ) {
	const settings = question.settings || {};
	const set = ( patch ) =>
		onChange( { settings: { ...settings, ...patch } } );
	return (
		<div
			className="ohmylms-question-options-bar"
			aria-label={ __( 'Question options', 'ohmylms' ) }
		>
			<div className="ohmylms-question-options-controls">
				{ ! hideRequired && (
					<ToggleControl
						label={ __( 'Required', 'ohmylms' ) }
						checked={ !! settings.required }
						onChange={ ( required ) => set( { required } ) }
					/>
				) }
				{ ! hideRandomize && (
					<ToggleControl
						label={ __( 'Randomize', 'ohmylms' ) }
						checked={ !! settings.randomize }
						onChange={ ( randomize ) => set( { randomize } ) }
					/>
				) }
				<TextControl
					label={ __( 'Score', 'ohmylms' ) }
					type="number"
					min={ 0 }
					step="0.5"
					value={ String( settings.score?.value ?? 1 ) }
					disabled={
						[ 'structured', 'passage' ].includes( settings.type ) ||
						isUngradedType( settings.type )
					}
					onChange={ ( value ) =>
						set( {
							score: {
								...settings.score,
								enabled: true,
								value: Number( value ),
							},
						} )
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="ohmylms-question-options-feedback">
				<RichContentControl
					compact
					html={ false }
					label={ __( 'Hint', 'ohmylms' ) }
					rows={ 2 }
					value={ settings.hint || '' }
					onChange={ ( hint ) => set( { hint } ) }
					__nextHasNoMarginBottom
				/>
				<RichContentControl
					compact
					html={ false }
					label={ __( 'Explanation', 'ohmylms' ) }
					rows={ 2 }
					value={ settings.explanation || '' }
					onChange={ ( explanation ) => set( { explanation } ) }
					__nextHasNoMarginBottom
				/>
			</div>
		</div>
	);
}
