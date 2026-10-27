/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { outlineLessonReadiness } from './outline.mjs';
import { outlineSkillName } from './CourseOutlineTree';

/**
 * Current-draft checklist; publication checks remain separate.
 * @param {Object}               root0          Properties.
 * @param {Object}               root0.program  Draft program.
 * @param {Object}               root0.names    Skill labels.
 * @param {(id: string) => void} root0.onSelect Select a lesson to repair.
 */
export function LessonReadiness( { program, names, onSelect } ) {
	const rows = outlineLessonReadiness( program );
	if ( ! rows.length ) {
		return null;
	}
	const labels = ( ids ) =>
		ids.map( ( id ) => outlineSkillName( names, id ) ).join( ', ' );
	return (
		<details className="ohmylms-outline-readiness">
			<summary>
				{ sprintf(
					/* translators: 1: complete lesson count, 2: total lesson count. */ __(
						'Lesson checklist: %1$d of %2$d complete',
						'ohmylms'
					),
					rows.filter( ( row ) => row.complete ).length,
					rows.length
				) }
			</summary>
			<p>
				{ __(
					'Skill links describe intended teaching and assessment coverage. Preview checks resource publication and assessment requirements separately.',
					'ohmylms'
				) }
			</p>
			<ul>
				{ rows.map( ( row ) => (
					<li key={ row.id }>
						<Button
							variant="link"
							onClick={ () => onSelect( row.id ) }
						>
							{ row.title }
						</Button>
						{ row.complete && (
							<p>
								{ __(
									'Activities and skill links complete.',
									'ohmylms'
								) }
							</p>
						) }
						{ ! row.items && (
							<p>{ __( 'Needs activities.', 'ohmylms' ) }</p>
						) }
						{ ! row.skills.length && (
							<p>
								{ __(
									'No skills linked to this lesson.',
									'ohmylms'
								) }
							</p>
						) }
						{ !! row.missingTeaching.length && (
							<p>
								{ sprintf(
									/* translators: %s: skill names. */ __(
										'Skills to teach: %s',
										'ohmylms'
									),
									labels( row.missingTeaching )
								) }
							</p>
						) }
						{ !! row.missingAssessment.length && (
							<p>
								{ sprintf(
									/* translators: %s: skill names. */ __(
										'Skills to assess: %s',
										'ohmylms'
									),
									labels( row.missingAssessment )
								) }
							</p>
						) }
						{ !! row.drafts && (
							<p>
								{ __(
									'Publish the draft resources before publishing the outline.',
									'ohmylms'
								) }
							</p>
						) }
					</li>
				) ) }
			</ul>
		</details>
	);
}
