import { createElement, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { CheckboxControl, SelectControl } from '@wordpress/components';
import {
	flattenTree,
	skillTree,
	setPrimarySkill,
	toggleSupportingSkill,
} from './model.mjs';

/**
 * Format a skill choice.
 * @param {import('./model.mjs').FlatSkill} skill Skill with its tree depth.
 * @return {string} Indented skill label.
 */
const skillLabel = ( skill ) =>
	`${ '— '.repeat( skill.depth ) }${ skill.code ? `${ skill.code } · ` : '' }${ skill.name }`;
/**
 * Primary and supporting skills of each question part.
 * @param {Object}                                          root0            Component properties.
 * @param {import('./model.mjs').Skill[]}                   root0.skills     Skills to choose from.
 * @param {import('./model.mjs').SkillMap|null}             [root0.value]    Skill map.
 * @param {string[]}                                        [root0.parts]    Question parts.
 * @param {boolean}                                         [root0.disabled] Read-only state.
 * @param {(value: import('./model.mjs').SkillMap) => void} root0.onChange   Save the skill map.
 * @return {React.ReactElement} Editor.
 */
export function SkillMapEditor( {
	skills,
	value,
	parts = [ 'p1' ],
	disabled = false,
	onChange,
} ) {
	const options = flattenTree( skillTree( skills ) );
	if ( ! options.length ) {
		return (
			<p>
				{ __( 'Create skills on the Skills page first.', 'ohmylms' ) }
			</p>
		);
	}
	return (
		<fieldset disabled={ disabled } className="ohmylms-skill-map">
			{ parts.map( ( part ) => {
				const roles = value?.[ part ] || {
					primary: 0,
					supporting: [],
				};
				return (
					<Fragment key={ part }>
						{ parts.length > 1 && (
							<h4>
								{ sprintf(
									/* translators: %s: question part identifier. */ __(
										'Part %s',
										'ohmylms'
									),
									part
								) }
							</h4>
						) }
						<SelectControl
							label={ __( 'Primary skill assessed', 'ohmylms' ) }
							value={ String( roles.primary || '' ) }
							options={ [
								{
									value: '',
									label: __( '— none —', 'ohmylms' ),
								},
								...options.map( ( skill ) => ( {
									value: String( skill.id ),
									label: skillLabel( skill ),
								} ) ),
							] }
							onChange={ ( skillId ) =>
								onChange(
									setPrimarySkill( value, part, skillId )
								)
							}
						/>
						<details>
							<summary>
								{ __(
									'Supporting skills (recommendations only)',
									'ohmylms'
								) }
							</summary>
							{ options
								.filter(
									( skill ) => skill.id !== roles.primary
								)
								.map( ( skill ) => (
									<CheckboxControl
										key={ skill.id }
										label={ skillLabel( skill ) }
										checked={ roles.supporting.includes(
											skill.id
										) }
										onChange={ () =>
											onChange(
												toggleSupportingSkill(
													value,
													part,
													skill.id
												)
											)
										}
									/>
								) ) }
						</details>
					</Fragment>
				);
			} ) }
		</fieldset>
	);
}
