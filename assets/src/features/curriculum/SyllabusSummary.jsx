import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { workspacePath } from './workspace.mjs';

/**
 * What an item panel shows for a syllabus: its size, and the way into the full-page syllabus editor, where
 * its topics, chapters, skills and course are edited.
 * @param root0
 * @param root0.item
 */
export function SyllabusSummary( { item } ) {
	const { groups = 0, skills = 0 } = item.syllabus || {};
	const size = [
		sprintf(
			/* translators: %d: number of curriculum folders. */ _n(
				'%d folder',
				'%d folders',
				groups,
				'ohmylms'
			),
			groups
		),
		sprintf(
			/* translators: %d: number of skills. */ _n(
				'%d skill',
				'%d skills',
				skills,
				'ohmylms'
			),
			skills
		),
		item.course_id ? __( 'also a course', 'ohmylms' ) : '',
	]
		.filter( Boolean )
		.join( ' · ' );
	return (
		<section
			className="ohmylms-cur-syllabus-summary"
			aria-label={ __( 'Syllabus', 'ohmylms' ) }
		>
			<div>
				<h3>{ __( 'Syllabus', 'ohmylms' ) }</h3>
				<p className="ohmylms-ext-muted">{ size }</p>
				<p className="ohmylms-ext-muted">
					{ __(
						'Its folders, folders and skills are edited in the syllabus editor, where the lessons, quizzes and questions that belong to them are too.',
						'ohmylms'
					) }
				</p>
			</div>
			<Button variant="primary" href={ `#${ workspacePath( item.id ) }` }>
				{ __( 'Open the syllabus editor', 'ohmylms' ) }
			</Button>
		</section>
	);
}
