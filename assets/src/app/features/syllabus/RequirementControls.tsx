import { Button, CheckboxControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { Group, Requirement, Target } from './model';

export type RequirementChange = {
	term_id: number;
	required?: boolean;
	target?: Target;
};
export type Requirements = ReadonlyMap< number, Requirement >;

/**
 * Whether learners must reach one skill to finish the syllabus course, and at what target.
 *
 * @param props             Control properties.
 * @param props.requirement The skill's outcome in the course draft; nothing is shown without one.
 * @param props.change      Send a requirement change.
 */
export function SkillRequirement( {
	requirement,
	change,
}: {
	requirement: Requirement | undefined;
	change: ( changes: RequirementChange[] ) => void;
} ) {
	if ( ! requirement ) {
		return null;
	}
	return (
		<>
			<CheckboxControl
				label={ __( 'Required for the course', 'ohmylms' ) }
				checked={ requirement.required }
				onChange={ ( required ) =>
					change( [ { term_id: requirement.term_id, required } ] )
				}
			/>
			<SelectControl
				label={ __( 'Target', 'ohmylms' ) }
				value={ requirement.target }
				options={ [
					{
						value: 'proficient',
						label: __( 'Proficient', 'ohmylms' ),
					},
					{ value: 'mastered', label: __( 'Mastered', 'ohmylms' ) },
				] }
				onChange={ ( target ) =>
					change( [
						{
							term_id: requirement.term_id,
							target: target as Target,
						},
					] )
				}
			/>
		</>
	);
}

/**
 * A group's required count with bulk require/optional actions over its skills in the course draft.
 *
 * @param props              Control properties.
 * @param props.group        Skill group.
 * @param props.requirements Course draft outcomes by skill.
 * @param props.change       Send requirement changes.
 */
export function GroupRequirements( {
	group,
	requirements,
	change,
}: {
	group: Group;
	requirements: Requirements;
	change: ( changes: RequirementChange[] ) => void;
} ) {
	const inCourse = group.skills
		.map( ( skill ) => requirements.get( skill.term_id ) )
		.filter(
			( requirement ): requirement is Requirement => !! requirement
		);
	if ( ! inCourse.length ) {
		return null;
	}
	const required = inCourse.filter( ( item ) => item.required ).length;
	const all = ( value: boolean ) =>
		change(
			inCourse
				.filter( ( item ) => item.required !== value )
				.map( ( item ) => ( {
					term_id: item.term_id,
					required: value,
				} ) )
		);
	return (
		<p>
			{ sprintf(
				/* translators: 1: required skills, 2: skills in the course. */
				__( '%1$d of %2$d required for the course.', 'ohmylms' ),
				required,
				inCourse.length
			) }{ ' ' }
			<Button
				variant="secondary"
				disabled={ required === inCourse.length }
				onClick={ () => all( true ) }
			>
				{ __( 'Require every skill in this group', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="secondary"
				disabled={ ! required }
				onClick={ () => all( false ) }
			>
				{ __( 'Make every skill in this group optional', 'ohmylms' ) }
			</Button>
		</p>
	);
}
