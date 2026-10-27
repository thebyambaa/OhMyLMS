/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import {
	objectiveKey,
	objectiveReferences,
	objectiveRequest,
	syllabusLabel,
} from './objectives.mjs';
import { ObjectivePicker } from './ObjectivePicker';

/**
 * Course targets reuse official syllabus records without changing completion requirements.
 * @param root0
 * @param root0.courseId
 * @param root0.outlineRevision Refresh coverage after outline changes.
 */
export function CourseObjectives( { courseId, outlineRevision } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ adding, setAdding ] = useState( false );
	const [ attempt, setAttempt ] = useState( 0 );

	useEffect( () => {
		let active = true;
		setData( null );
		setError( '' );
		objectiveRequest( courseId )
			.then( ( result ) => {
				if ( active ) {
					setData( result );
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setError(
						cause.message ||
							__(
								'Could not load syllabus objectives.',
								'ohmylms'
							)
					);
				}
			} );
		return () => {
			active = false;
		};
	}, [ courseId, attempt, outlineRevision ] );

	async function save( rows ) {
		if ( busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			const result = await objectiveRequest( courseId, {
				method: 'PUT',
				data: {
					mappings: objectiveReferences( rows ),
					revision: data.revision,
				},
			} );
			setData( result );
			setAdding( false );
			setNotice( __( 'Syllabus objectives saved.', 'ohmylms' ) );
		} catch ( cause ) {
			setError(
				cause.message ||
					__( 'Could not save syllabus objectives.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}

	return (
		<section
			className="ohmylms-course-objectives"
			aria-label={ __( 'Syllabus objectives', 'ohmylms' ) }
			aria-busy={ busy }
		>
			<div className="ohmylms-objectives-heading">
				<div>
					<h2>{ __( 'Syllabus Objectives', 'ohmylms' ) }</h2>
					<p>
						{ __(
							'Choose official objectives this course intends to cover. Mappings save separately from course content.',
							'ohmylms'
						) }
					</p>
				</div>
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => {
						setAdding( false );
						setAttempt( attempt + 1 );
					} }
				>
					{ __( 'Reload objectives', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					disabled={
						! data ||
						busy ||
						! data.syllabuses.length ||
						data.mappings.length >= data.limit
					}
					onClick={ () => setAdding( true ) }
				>
					{ __( 'Add objectives', 'ohmylms' ) }
				</Button>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ notice && <div role="status">{ notice }</div> }
			{ ! data && ! error && <Spinner /> }
			{ data && (
				<>
					{ ! data.syllabuses.length && (
						<p>
							{ __(
								'No syllabuses are available. An administrator can add an official syllabus in Content Hub → Curriculum.',
								'ohmylms'
							) }
						</p>
					) }
					{ ! data.mappings.length ? (
						<p>
							{ __(
								'No target objectives selected yet.',
								'ohmylms'
							) }
						</p>
					) : (
						<details
							className="ohmylms-objectives-details"
							open={ data.mappings.length <= 5 }
						>
							<summary>
								{ sprintf(
									/* translators: 1: objectives, 2: objectives with content. */
									__(
										'%1$d objectives, %2$d with content linked',
										'ohmylms'
									),
									data.mappings.length,
									data.mappings.filter(
										( row ) => row.content.length
									).length
								) }
							</summary>
							<div className="ohmylms-objectives-table-wrap">
								<table className="widefat striped">
									<caption className="screen-reader-text">
										{ __(
											'Selected syllabus objectives and existing course content connections',
											'ohmylms'
										) }
									</caption>
									<thead>
										<tr>
											<th scope="col">
												{ __( 'Objective', 'ohmylms' ) }
											</th>
											<th scope="col">
												{ __(
													'Syllabus / edition',
													'ohmylms'
												) }
											</th>
											<th scope="col">
												{ __(
													'Linked content',
													'ohmylms'
												) }
											</th>
											<th scope="col">
												{ __( 'Actions', 'ohmylms' ) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ data.mappings.map( ( row ) => {
											const syllabus =
												data.syllabuses.find(
													( item ) =>
														item.id ===
														row.syllabus_id
												);
											const lessons = row.content.filter(
												( item ) =>
													item.type === 'lesson'
											).length;
											const assessments =
												row.content.length - lessons;
											return (
												<tr key={ objectiveKey( row ) }>
													<td>
														<strong>
															{ row.code ||
																`#${ row.term_id }` }{ ' ' }
															·{ ' ' }
															{ row.name ||
																__(
																	'Unavailable objective',
																	'ohmylms'
																) }
														</strong>
														<p>{ row.objective }</p>
														<small>
															{ row.topic }
														</small>
														{ row.missing && (
															<p role="status">
																{ __(
																	'This objective was removed from its source syllabus. Remove this mapping or choose a replacement.',
																	'ohmylms'
																) }
															</p>
														) }
													</td>
													<td>
														{ syllabus
															? syllabusLabel(
																	syllabus
																)
															: __(
																	'Unavailable syllabus',
																	'ohmylms'
																) }
													</td>
													<td>
														{ row.content.length ? (
															<details>
																<summary>
																	{ sprintf(
																		/* translators: 1: lessons, 2: assessment activities. */ __(
																			'Lessons: %1$d · assessment activities: %2$d',
																			'ohmylms'
																		),
																		lessons,
																		assessments
																	) }
																</summary>
																<ul>
																	{ row.content.map(
																		(
																			item
																		) => (
																			<li
																				key={
																					item.id
																				}
																			>
																				{
																					item.title
																				}
																			</li>
																		)
																	) }
																</ul>
															</details>
														) : (
															__(
																'No content linked',
																'ohmylms'
															)
														) }
													</td>
													<td>
														<Button
															variant="tertiary"
															isDestructive
															disabled={ busy }
															label={ sprintf(
																/* translators: %s: objective name. */ __(
																	'Remove objective: %s',
																	'ohmylms'
																),
																row.name ||
																	row.term_id
															) }
															onClick={ () =>
																save(
																	data.mappings.filter(
																		(
																			item
																		) =>
																			objectiveKey(
																				item
																			) !==
																			objectiveKey(
																				row
																			)
																	)
																)
															}
														>
															{ __(
																'Remove',
																'ohmylms'
															) }
														</Button>
													</td>
												</tr>
											);
										} ) }
									</tbody>
								</table>
							</div>
						</details>
					) }
					<p className="description">
						{ __(
							'Connections show existing skill links on course content and draft attachments. They do not verify content suitability or student achievement. Question-level assessment coverage is not included.',
							'ohmylms'
						) }
					</p>
				</>
			) }
			{ adding && data && (
				<ObjectivePicker
					courseId={ courseId }
					data={ data }
					busy={ busy }
					error={ error }
					onClose={ () => {
						if ( ! busy ) {
							setAdding( false );
						}
					} }
					onSave={ ( selected ) =>
						save( [ ...data.mappings, ...selected ] )
					}
				/>
			) }
		</section>
	);
}
