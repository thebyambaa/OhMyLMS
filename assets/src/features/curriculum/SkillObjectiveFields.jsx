import { createElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useWorkspace } from './context';
import { SkillTranslationControls } from './SkillTranslationControls';
import { SkillObjectiveTranslation } from './SkillObjectiveTranslation';
import { SaveField, MESSAGES } from './WorkspaceParts';
import { skillText, skillPatch } from './skillTranslations.mjs';

/**
 * Edit both language versions without changing the canonical definition.
 * @param {Object} props       Component properties.
 * @param {Object} props.skill Shared skill record.
 * @return {Object} Objective fields.
 */
export function SkillObjectiveFields( { skill } ) {
	const w = useWorkspace();
	const [ edits, setEdits ] = useState( {} );
	const [ height, setHeight ] = useState( 240 );
	const validate = ( text ) => {
		if ( ! text.trim() ) {
			return MESSAGES.required();
		}
		return text.length > 2000 ? MESSAGES[ 'too-long' ]() : '';
	};
	const field = ( language, label ) =>
		createElement( SaveField, {
			key: `${ skill.term_id }:${ language }:objective`,
			id: `ohmylms-ws-skill-objective-${ skill.term_id }-${ language }`,
			label,
			multiline: true,
			value: skillText( skill, language ),
			inputClassName: 'ohmylms-ws-objective',
			onDraftChange: ( text ) =>
				setEdits( ( current ) => ( {
					...current,
					[ language ]: text,
				} ) ),
			validate,
			onSave: async ( text ) => {
				const saved = await w.actions.saveSkill(
					skill,
					skillPatch( language, 'objective', text.trim() )
				);
				if ( saved ) {
					setEdits( ( current ) => {
						if ( current[ language ] !== text ) {
							return current;
						}
						const next = { ...current };
						delete next[ language ];
						return next;
					} );
				}
				return saved;
			},
		} );
	return (
		<section
			className="ohmylms-ws-objective-editor"
			aria-label={ __( 'Learning objective translations', 'ohmylms' ) }
			style={ { '--ws-objective-height': `${ height }px` } }
		>
			<SkillTranslationControls />
			<div className="ohmylms-ws-objective-columns">
				{ [ 'en', 'mn' ].map( ( language ) => {
					const source = language === 'en' ? 'mn' : 'en';
					return (
						<SkillObjectiveTranslation
							key={ language }
							skill={ skill }
							language={ language }
							sourceObjective={
								edits[ source ] ?? skillText( skill, source )
							}
							onTranslated={ () =>
								setEdits( ( current ) => {
									const next = { ...current };
									delete next[ language ];
									return next;
								} )
							}
						>
							{ field(
								language,
								language === 'en'
									? __(
											'Learning objective — English',
											'ohmylms'
										)
									: __(
											'Learning objective — Монгол',
											'ohmylms'
										)
							) }
						</SkillObjectiveTranslation>
					);
				} ) }
			</div>
			<div className="ohmylms-ws-objective-resize">
				<label
					htmlFor={ `ohmylms-ws-objective-height-${ skill.term_id }` }
				>
					{ __( 'Resize both textareas', 'ohmylms' ) }
				</label>
				<input
					id={ `ohmylms-ws-objective-height-${ skill.term_id }` }
					type="range"
					min="160"
					max="800"
					step="20"
					value={ height }
					onChange={ ( event ) =>
						setHeight( Number( event.target.value ) )
					}
				/>
			</div>
			<p className="ohmylms-ext-muted">
				{ __(
					'Each language saves separately when you leave its field. Missing translations start with the original definition. Automatic translation sends the opposite field to your configured AI provider; review its draft before saving.',
					'ohmylms'
				) }
			</p>
		</section>
	);
}
