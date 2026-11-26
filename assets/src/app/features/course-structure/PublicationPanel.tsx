import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { useOperation } from '../../hooks/useOperation';
import {
	previewPublication,
	publishCourse,
	type Publication,
} from './publication-api';

/**
 * Counts of what the saved course holds, as one sentence ("2 units, 5 lessons, 1 quiz").
 *
 * @param preview The saved course.
 */
function contents( preview: Publication ) {
	const items = preview.units.flatMap( ( unit ) => unit.items );
	const count = ( type: string ) =>
		items.filter( ( item ) => type === item.type ).length;
	const lessons = count( 'lesson' );
	const quizzes = count( 'quiz' );
	const assignments = count( 'assignment' );
	const parts: string[] = [
		sprintf(
			/* translators: %d: number of units. */
			_n( '%d unit', '%d units', preview.units.length, 'ohmylms' ),
			preview.units.length
		),
		sprintf(
			/* translators: %d: number of lessons. */
			_n( '%d lesson', '%d lessons', lessons, 'ohmylms' ),
			lessons
		),
		sprintf(
			/* translators: %d: number of quizzes. */
			_n( '%d quiz', '%d quizzes', quizzes, 'ohmylms' ),
			quizzes
		),
	];
	if ( assignments > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: number of assignments. */
				_n( '%d assignment', '%d assignments', assignments, 'ohmylms' ),
				assignments
			)
		);
	}
	return parts.join( ', ' );
}

/**
 * Publishes a course in one step: the saved course is read as soon as the panel opens, its contents and
 * anything that blocks publishing are listed, and one button publishes the revision that is shown.
 *
 * @param props             Component props.
 * @param props.course      Course id.
 * @param props.disabled    True while the course has unsaved changes or is being saved; the panel waits.
 * @param props.onPublished Called after the course is published.
 * @param props.onClose     Closes the surrounding dialog; when given, the panel ends on a "done" screen.
 * @param props.viewLink    Link to the published course, shown on the "done" screen.
 * @param props.onEdit      Returns to the outline to resolve publication issues.
 */
export function PublicationPanel( {
	course,
	disabled,
	onPublished,
	onClose,
	viewLink,
	onEdit,
}: {
	course: number;
	disabled: boolean;
	onPublished: () => void;
	onClose?: () => void;
	viewLink?: ReactNode;
	onEdit?: () => void;
} ) {
	const { busy, error, run } = useOperation();
	const [ preview, setPreview ] = useState< Publication | null >( null );
	const [ done, setDone ] = useState( false );
	const load = useCallback(
		() =>
			void run(
				( signal ) => previewPublication( course, signal ),
				setPreview
			),
		[ course, run ]
	);
	useEffect( () => {
		if ( ! disabled ) {
			load();
		}
	}, [ disabled, load ] );

	const published = (
		<Notice status="success" isDismissible={ false }>
			{ __( 'Course published. Students can open it now.', 'ohmylms' ) }
		</Notice>
	);
	if ( done && onClose ) {
		return (
			<section aria-label={ __( 'Course publication', 'ohmylms' ) }>
				{ published }
				<div className="oml-publication-actions">
					{ viewLink }
					<Button variant="primary" onClick={ onClose }>
						{ __( 'Done', 'ohmylms' ) }
					</Button>
				</div>
			</section>
		);
	}
	const blocked = Boolean( preview && preview.readiness.length > 0 );
	const empty = Boolean(
		preview &&
		preview.outcomes === 0 &&
		preview.units.every( ( unit ) => unit.items.length === 0 )
	);
	return (
		<section aria-label={ __( 'Course publication', 'ohmylms' ) }>
			{ done && published }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! preview && ! error && ! disabled && (
				<p role="status">{ __( 'Checking the course…', 'ohmylms' ) }</p>
			) }
			{ ! preview && error && (
				<Button variant="secondary" disabled={ busy } onClick={ load }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			) }
			{ preview && ! disabled && (
				<>
					<h3 className="oml-publication-title">{ preview.title }</h3>
					<p>{ contents( preview ) }</p>
					{ preview.units.length > 0 && (
						<details className="oml-publication-contents">
							<summary>
								{ __( 'Included content', 'ohmylms' ) }
							</summary>
							{ preview.units.map( ( unit ) => (
								<div key={ unit.id }>
									<h4>{ unit.title }</h4>
									<ul>
										{ unit.items.map( ( item ) => (
											<li key={ item.id }>
												{ item.title } ({ item.type })
											</li>
										) ) }
									</ul>
								</div>
							) ) }
						</details>
					) }
					{ 0 === preview.published_program_id && (
						<p role="status">
							{ __(
								'Publish this course and its draft lessons and quizzes for students with access. Keep as draft to edit later.',
								'ohmylms'
							) }
						</p>
					) }
					{ preview.published_program_id > 0 &&
						preview.unpublished_changes && (
							<p role="status">
								{ __(
									'Students still see the last published version. Publish changes for new students; enrolled students keep their version.',
									'ohmylms'
								) }
							</p>
						) }
					{ blocked && (
						<Notice status="warning" isDismissible={ false }>
							{ empty && (
								<p>
									{ __(
										'Add a lesson or quiz, then save and publish.',
										'ohmylms'
									) }
								</p>
							) }
							{ ! empty && (
								<ul>
									{ preview.readiness.map(
										( issue, index ) => (
											<li key={ index }>{ issue }</li>
										)
									) }
								</ul>
							) }
							{ onEdit && (
								<Button
									variant="secondary"
									onClick={ onEdit }
									disabled={ busy }
								>
									{ empty
										? __(
												'Add lessons or quizzes',
												'ohmylms'
											)
										: __( 'Edit outline', 'ohmylms' ) }
								</Button>
							) }
						</Notice>
					) }
					<div className="oml-publication-actions">
						{ onClose && (
							<Button
								variant="tertiary"
								disabled={ busy }
								onClick={ onClose }
							>
								{ __( 'Keep as draft', 'ohmylms' ) }
							</Button>
						) }
						<Button
							variant="primary"
							disabled={ busy || blocked }
							isBusy={ busy }
							onClick={ () =>
								void run(
									( signal ) =>
										publishCourse( preview, signal ),
									( result ) => {
										setPreview( result );
										setDone( true );
										onPublished();
									}
								)
							}
						>
							{ __( 'Publish course', 'ohmylms' ) }
						</Button>
					</div>
				</>
			) }
		</section>
	);
}
