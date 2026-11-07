import { lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/** Course editor tabs owned by this feature; the courses feature loads this module lazily. */
export { CourseOutline } from './CourseEditorPage';
export { PublicationPanel } from './PublicationPanel';

const LazySkillCoverage = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-skill-coverage" */ './SkillCoverage'
	).then( ( module ) => ( { default: module.SkillCoverage } ) )
);

/**
 * The skill coverage step. It has its own chunk, so the outline and publication screens do not carry it;
 * the chunk loads when the step is opened. This is a plain component, so a caller can load this module
 * lazily without nesting one lazy component inside another.
 *
 * @param props           Component props.
 * @param props.course    Course ID.
 * @param props.unsaved   Whether any step has unsaved changes.
 * @param props.onChanged Called after a fix changed the course's draft.
 */
export function SkillCoverage( {
	course,
	unsaved,
	onChanged,
}: {
	course: number;
	unsaved: boolean;
	onChanged?: () => void;
} ) {
	return (
		<Suspense
			fallback={
				<p role="status">
					{ __( 'Loading skill coverage…', 'ohmylms' ) }
				</p>
			}
		>
			<LazySkillCoverage
				course={ course }
				unsaved={ unsaved }
				onChanged={ onChanged }
			/>
		</Suspense>
	);
}
