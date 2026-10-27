import { createElement, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { AdminCard } from '../../extensions/AdminPage';
import { loadHelpNow } from './api.mjs';

/**
 * Who needs help now: stuck learners of a course, grouped by the prerequisite to reteach, so one
 * mini-lesson helps several. Learners with an exam coming up are listed first.
 *
 * @param {Object}                    root0          Props.
 * @param {number}                    root0.courseId Course the viewer reports on.
 * @param {(student: Object) => void} root0.onSelect Called with a learner to open their skills.
 */
export function HelpNow( { courseId, onSelect } ) {
	const [ data, setData ] = useState( undefined );
	useEffect( () => {
		let active = true;
		setData( undefined );
		loadHelpNow( courseId )
			.then( ( result ) => active && setData( result ) )
			.catch( () => active && setData( null ) );
		return () => {
			active = false;
		};
	}, [ courseId ] );
	if ( data === null ) {
		return null;
	}
	return (
		<AdminCard className="ohmylms-help-now">
			<h2>{ __( 'Who needs help now', 'ohmylms' ) }</h2>
			{ data === undefined && <Spinner /> }
			{ data && data.groups.length === 0 && (
				<p className="ohmylms-ext-muted">
					{ __( 'Nobody is stuck right now.', 'ohmylms' ) }
				</p>
			) }
			{ data && data.groups.length > 0 && (
				<>
					<p className="ohmylms-ext-muted">
						{ sprintf(
							/* translators: 1: stuck learners, 2: learners in the course. */
							__( '%1$d of %2$d learners are stuck.', 'ohmylms' ),
							data.stuck,
							data.students
						) }
					</p>
					<ul className="ohmylms-help-groups">
						{ data.groups.map( ( group ) => (
							<li
								key={ `${ group.kind }-${ group.reteach.id }` }
								className="ohmylms-help-group"
							>
								<h3>
									{ group.kind === 'prerequisite'
										? sprintf(
												/* translators: %s: skill to reteach. */
												__( 'Reteach: %s', 'ohmylms' ),
												group.reteach.name
											)
										: sprintf(
												/* translators: %s: skill to reteach. */
												__(
													'Reteach this skill: %s',
													'ohmylms'
												),
												group.reteach.name
											) }
								</h3>
								<p className="ohmylms-ext-muted">
									{ group.kind === 'prerequisite'
										? sprintf(
												/* translators: 1: number of learners, 2: skills they are stuck on. */
												_n(
													'%1$d learner is stuck on %2$s.',
													'%1$d learners are stuck on %2$s.',
													group.students.length,
													'ohmylms'
												),
												group.students.length,
												group.skills
													.map( ( s ) => s.name )
													.join( ', ' )
											)
										: sprintf(
												/* translators: %d: number of learners. */
												_n(
													'%d learner is stuck on it.',
													'%d learners are stuck on it.',
													group.students.length,
													'ohmylms'
												),
												group.students.length
											) }
								</p>
								<ul>
									{ group.students.map( ( learner ) => (
										<li key={ learner.id }>
											<Button
												variant="link"
												onClick={ () =>
													onSelect( learner )
												}
											>
												{ learner.name }
											</Button>
											{ learner.exam && (
												<span className="ohmylms-help-exam">
													{ ' ' }
													{ sprintf(
														/* translators: 1: exam, 2: days left. */
														_n(
															'%1$s in %2$d day',
															'%1$s in %2$d days',
															learner.exam
																.days_left,
															'ohmylms'
														),
														learner.exam.name,
														learner.exam.days_left
													) }
												</span>
											) }
										</li>
									) ) }
								</ul>
							</li>
						) ) }
					</ul>
				</>
			) }
		</AdminCard>
	);
}
