import { createElement, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	normalizeSkillMap,
	setPrimarySkill,
} from '../question-bank/public.mjs';
import { SkillField } from './SkillField';

/**
 * Primary and supporting skills of each part of a question, chosen from the searchable curriculum.
 * The primary skill is the one the question's answers count towards; supporting skills are recommendations.
 *
 * @param {Object}                                                                                                                       props
 * @param {string[]}                                                                                                                     props.parts      Question part IDs.
 * @param {Record<string, {primary: number, supporting: number[]}>|null}                                                                 props.value      Skill map.
 * @param {Record<number, {name?: string, code?: string}>}                                                                               props.names      Skills known by ID.
 * @param {boolean}                                                                                                                      [props.disabled] Read-only.
 * @param {(map: Record<string, {primary: number, supporting: number[]}>, picked: {id: number, name?: string, code?: string}[]) => void} props.onChange   Receives the new map.
 * @return {React.ReactElement} Fields.
 */
export function QuestionSkillFields( {
	parts,
	value,
	names,
	disabled = false,
	onChange,
} ) {
	return (
		<>
			{ parts.map( ( part ) => {
				const roles = value?.[ part ] || {
					primary: 0,
					supporting: [],
				};
				return (
					<div key={ part } className="ohmylms-question-skills">
						{ parts.length > 1 && (
							<h4>
								{ sprintf(
									/* translators: %s: question part identifier. */
									__( 'Part %s', 'ohmylms' ),
									part
								) }
							</h4>
						) }
						<SkillField
							single
							label={ __( 'Primary skill assessed', 'ohmylms' ) }
							value={ roles.primary ? [ roles.primary ] : [] }
							names={ names }
							disabled={ disabled }
							empty={ __(
								'No primary skill. Answers to this question do not count towards a skill yet.',
								'ohmylms'
							) }
							onChange={ ( ids, picked ) =>
								onChange(
									setPrimarySkill(
										value,
										part,
										ids[ 0 ] || 0
									),
									picked
								)
							}
						/>
						<SkillField
							label={ __( 'Supporting skills', 'ohmylms' ) }
							value={ roles.supporting }
							names={ names }
							disabled={ disabled }
							empty={ __(
								'None. Supporting skills are recommendations only.',
								'ohmylms'
							) }
							onChange={ ( ids, picked ) =>
								onChange(
									normalizeSkillMap( {
										...value,
										[ part ]: {
											...roles,
											supporting: ids,
										},
									} ),
									picked
								)
							}
						/>
					</div>
				);
			} ) }
		</>
	);
}
