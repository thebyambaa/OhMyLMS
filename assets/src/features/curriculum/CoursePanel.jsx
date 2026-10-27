import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { PublishDialog } from '../content-hub/PublishDialog';
import { Tag } from './WorkspaceParts';
const MODES = () => ( {
	'skill-based': __( 'Skill-based', 'ohmylms' ),
	blended: __( 'Blended', 'ohmylms' ),
	traditional: __( 'Traditional', 'ohmylms' ),
} );

/**
 * A syllabus is also a course: its chapters are the course's chapters and its skills the course's skills.
 * This card says where the course stands and leads to what only the course has (its settings, enrollment
 * and pricing, publishing). Learners see nothing until the course is published.
 * @param root0
 * @param root0.course
 * @param root0.courseCatalog
 * @param root0.pending
 * @param root0.onUpdate
 */
export function CoursePanel( { course, courseCatalog, pending, onUpdate } ) {
	const [ publishing, setPublishing ] = useState( false );
	const catalog = courseCatalog.catalog;
	const published = Boolean( catalog && catalog.published_version > 0 );
	const changed = Boolean( catalog && catalog.unpublished_changes );
	const requiredSkills = catalog
		? catalog.skills.filter( ( skill ) => skill.required ).length
		: 0;
	const live = course.status === 'publish';
	const stateTag = live
		? {
				text: __( 'Course published', 'ohmylms' ),
				tone: 'ok',
			}
		: {
				text: __( 'Course draft', 'ohmylms' ),
				tone: 'warn',
			};
	return (
		<section
			className="ohmylms-ws-course"
			aria-label={ __( 'The course this syllabus is', 'ohmylms' ) }
		>
			<div className="ohmylms-ws-course-head">
				<h3>{ __( 'Course', 'ohmylms' ) }</h3>
				<Tag tone={ stateTag.tone }>{ stateTag.text }</Tag>
				{ published && (
					<Tag tone={ changed ? 'warn' : 'ok' }>
						{ changed
							? sprintf(
									/* translators: %d: published version number. */ __(
										'Version %d, with unpublished changes',
										'ohmylms'
									),
									catalog.published_version
								)
							: sprintf(
									/* translators: %d: published version number. */ __(
										'Version %d published',
										'ohmylms'
									),
									catalog.published_version
								) }
					</Tag>
				) }
				{ ! published && (
					<Tag>{ __( 'Not published yet', 'ohmylms' ) }</Tag>
				) }
			</div>
			<p className="ohmylms-ext-muted">
				{ __(
					'This syllabus is also a course. Its chapters are the course’s chapters and its skills are the course’s skills, and both follow every change you make here. Learners see the course once it is published.',
					'ohmylms'
				) }
			</p>
			<dl className="ohmylms-ws-facts">
				<div>
					<dt>{ __( 'Chapters', 'ohmylms' ) }</dt>
					<dd>{ course.chapters }</dd>
				</div>
				<div>
					<dt>{ __( 'Skills', 'ohmylms' ) }</dt>
					<dd>{ course.skills }</dd>
				</div>
				<div>
					<dt>{ __( 'Required skills', 'ohmylms' ) }</dt>
					<dd>{ catalog ? requiredSkills : '…' }</dd>
				</div>
				<div>
					<dt>
						{ __( 'Lessons, quizzes and assignments', 'ohmylms' ) }
					</dt>
					<dd>{ course.attachments }</dd>
				</div>
				<div>
					<dt>{ __( 'Learning mode', 'ohmylms' ) }</dt>
					<dd>{ MODES()[ course.mode ] || course.mode }</dd>
				</div>
			</dl>
			{ catalog && requiredSkills === 0 && (
				<p className="ohmylms-ext-muted">
					{ __(
						'No skill is required yet, so the course cannot be published. Open a chapter or a skill and choose which skills the course requires; each one needs approved questions.',
						'ohmylms'
					) }
				</p>
			) }
			{ courseCatalog.error && (
				<Notice status="error" isDismissible={ false }>
					{ courseCatalog.error }
				</Notice>
			) }
			<div className="ohmylms-ws-course-actions">
				<Button variant="secondary" href={ course.edit }>
					{ __( 'Course settings', 'ohmylms' ) }
				</Button>
				{ catalog?.course?.url && (
					<Button
						variant="secondary"
						href={ catalog.course.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Learner view', 'ohmylms' ) }
					</Button>
				) }
				<Button
					variant="secondary"
					disabled={ pending }
					onClick={ onUpdate }
				>
					{ __( 'Update course from syllabus', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					disabled={
						! catalog ||
						courseCatalog.busy ||
						( published && ! changed )
					}
					onClick={ () => setPublishing( true ) }
				>
					{ published
						? __( 'Publish changes', 'ohmylms' )
						: __( 'Publish course', 'ohmylms' ) }
				</Button>
			</div>
			{ publishing && catalog && (
				<PublishDialog
					catalog={ catalog }
					errors={ courseCatalog.publishErrors }
					busy={ courseCatalog.busy }
					onClose={ () => setPublishing( false ) }
					onPublish={ ( applyExisting ) =>
						courseCatalog
							.publish( applyExisting )
							.then( ( ok ) => ok && setPublishing( false ) )
					}
				/>
			) }
		</section>
	);
}
