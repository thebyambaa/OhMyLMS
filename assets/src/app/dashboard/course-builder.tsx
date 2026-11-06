import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import { Field } from './field';

interface CourseItem {
	id: number;
	title: string;
	type: string;
	kind: string;
	edit: string;
	skills: number[];
}

interface Unit {
	id: number;
	title: string;
	items: CourseItem[];
}

interface CourseTree {
	id: number;
	title: string;
	canEdit: boolean;
	units: Unit[];
	skills: Array< { id: number; name: string } >;
}

/**
 * Units, lessons and exercises. Reorder with the mouse or the keyboard.
 */
export function CourseBuilder() {
	const initial = Number(
		new URLSearchParams( window.location.search ).get( 'course' ) || 0
	);
	const [ courses, setCourses ] = useState<
		Array< { id: number; title: string } >
	>( [] );
	const [ courseId, setCourseId ] = useState( initial );
	const [ tree, setTree ] = useState< CourseTree | null >( null );
	const [ title, setTitle ] = useState( '' );
	const [ itemTitle, setItemTitle ] = useState( '' );
	const [ kind, setKind ] = useState( 'lesson' );
	const [ message, setMessage ] = useState( '' );
	const [ selected, setSelected ] = useState< CourseItem | null >( null );
	const [ addingUnit, setAddingUnit ] = useState( false );
	const [ addingOn, setAddingOn ] = useState( 0 );

	useEffect( () => {
		void api< { courses: Array< { id: number; title: string } > } >(
			'/studio/courses'
		).then( ( body ) => setCourses( body.courses ) );
	}, [] );

	useEffect( () => {
		if ( ! courseId ) {
			setTree( null );
			return;
		}
		void api< CourseTree >( `/studio/courses/${ courseId }` ).then(
			( body ) => {
				setTree( body );
			}
		);
	}, [ courseId, message ] );

	function saveOrder( next: Unit[] ) {
		if ( ! tree ) {
			return;
		}
		void api( `/studio/courses/${ tree.id }/order`, {
			method: 'POST',
			body: JSON.stringify( {
				units: next.map( ( unit ) => ( {
					id: unit.id,
					items: unit.items.map( ( item ) => ( { id: item.id } ) ),
				} ) ),
			} ),
		} )
			.then( () => setMessage( __( 'Order saved.', 'ohmylms' ) ) )
			.catch( ( failure: Error ) => setMessage( failure.message ) );
	}

	function moveItem(
		unitIndex: number,
		itemIndex: number,
		direction: number
	) {
		if ( ! tree ) {
			return;
		}
		const next = tree.units.map( ( unit ) => ( {
			...unit,
			items: unit.items.slice(),
		} ) );
		const items = next[ unitIndex ]?.items;
		const target = itemIndex + direction;
		if ( ! items || target < 0 || target >= items.length ) {
			return;
		}
		const current = items[ itemIndex ];
		const swap = items[ target ];
		if ( ! current || ! swap ) {
			return;
		}
		items[ itemIndex ] = swap;
		items[ target ] = current;
		setTree( { ...tree, units: next } );
		saveOrder( next );
	}

	return (
		<section className="oml-builder">
			<header className="oml-page-bar">
				<h1 className="oml-dash-title">
					{ __( 'Courses', 'ohmylms' ) }
				</h1>
				<Field label={ __( 'Course', 'ohmylms' ) }>
					<select
						value={ courseId }
						onChange={ ( event ) =>
							setCourseId( Number( event.target.value ) )
						}
					>
						<option value={ 0 }>
							{ __( 'Choose a course', 'ohmylms' ) }
						</option>
						{ courses.map( ( course ) => (
							<option key={ course.id } value={ course.id }>
								{ course.title }
							</option>
						) ) }
					</select>
				</Field>
			</header>
			{ tree && (
				<div className="oml-builder-grid">
					<div>
						{ tree.canEdit &&
							( addingUnit ? (
								<form
									onSubmit={ ( event ) => {
										event.preventDefault();
										void api(
											`/studio/courses/${ tree.id }/units`,
											{
												method: 'POST',
												body: JSON.stringify( {
													title,
												} ),
											}
										)
											.then( () => {
												setTitle( '' );
												setAddingUnit( false );
												setMessage(
													__(
														'Unit added.',
														'ohmylms'
													)
												);
											} )
											.catch( ( failure: Error ) =>
												setMessage( failure.message )
											);
									} }
								>
									<Field
										label={ __( 'New unit', 'ohmylms' ) }
									>
										<input
											value={ title }
											onChange={ ( event ) =>
												setTitle( event.target.value )
											}
										/>
									</Field>
									<button
										type="submit"
										className="oml-dash-btn is-primary"
									>
										{ __( 'Add unit', 'ohmylms' ) }
									</button>
								</form>
							) : (
								<button
									type="button"
									className="oml-dash-btn is-ghost"
									onClick={ () => setAddingUnit( true ) }
								>
									{ __( '+ Add unit', 'ohmylms' ) }
								</button>
							) ) }
						{ tree.units.map( ( unit, unitIndex ) => (
							<section key={ unit.id } className="oml-dash-card">
								<div className="oml-dash-actions">
									<h2>{ unit.title }</h2>
									{ tree.canEdit && (
										<button
											type="button"
											className="oml-dash-btn is-ghost"
											onClick={ () =>
												setAddingOn(
													addingOn === unit.id
														? 0
														: unit.id
												)
											}
										>
											{ __( '+ Add', 'ohmylms' ) }
										</button>
									) }
								</div>
								<ul className="oml-unit-list">
									{ unit.items.map( ( item, itemIndex ) => (
										<li
											key={ item.id }
											className={
												selected?.id === item.id
													? 'oml-row is-selected'
													: 'oml-row'
											}
											draggable={ tree.canEdit }
											onDragStart={ ( event ) =>
												event.dataTransfer.setData(
													'text/plain',
													`${ unitIndex }:${ itemIndex }`
												)
											}
											onDragOver={ ( event ) =>
												event.preventDefault()
											}
											onDrop={ ( event ) => {
												const raw = event.dataTransfer
													.getData( 'text/plain' )
													.split( ':' );
												moveItem(
													Number( raw[ 0 ] ),
													Number( raw[ 1 ] ),
													itemIndex -
														Number( raw[ 1 ] )
												);
											} }
										>
											<span
												className="oml-handle"
												aria-hidden="true"
											>
												⋮⋮
											</span>
											<button
												type="button"
												className="link"
												onClick={ () =>
													setSelected( item )
												}
											>
												{ item.title }
											</button>
											<span
												className={
													item.kind === 'quiz'
														? 'oml-badge is-quiz'
														: 'oml-badge'
												}
											>
												{ item.kind === 'quiz'
													? __( 'Quiz', 'ohmylms' )
													: __(
															'Lesson',
															'ohmylms'
														) }
											</span>
											{ tree.canEdit && (
												<>
													<button
														type="button"
														className="oml-dash-btn oml-icon-btn"
														aria-label={ __(
															'Move up',
															'ohmylms'
														) }
														onClick={ () =>
															moveItem(
																unitIndex,
																itemIndex,
																-1
															)
														}
													>
														↑
													</button>
													<button
														type="button"
														className="oml-dash-btn oml-icon-btn"
														aria-label={ __(
															'Move down',
															'ohmylms'
														) }
														onClick={ () =>
															moveItem(
																unitIndex,
																itemIndex,
																1
															)
														}
													>
														↓
													</button>
													{ item.kind === 'lesson' &&
														item.edit && (
															<a
																className="oml-dash-btn is-primary"
																href={
																	item.edit
																}
															>
																{ __(
																	'Open',
																	'ohmylms'
																) }
															</a>
														) }
													{ item.kind === 'quiz' && (
														<button
															type="button"
															className="oml-dash-btn is-primary"
															onClick={ () => {
																const url =
																	new URL(
																		window
																			.location
																			.href
																	);
																url.searchParams.set(
																	'section',
																	'quizzes'
																);
																url.searchParams.set(
																	'quiz',
																	String(
																		item.id
																	)
																);
																window.history.pushState(
																	{},
																	'',
																	url
																);
																window.location.reload();
															} }
														>
															{ __(
																'Open',
																'ohmylms'
															) }
														</button>
													) }
												</>
											) }
										</li>
									) ) }
								</ul>
								{ tree.canEdit && addingOn === unit.id && (
									<form
										onSubmit={ ( event ) => {
											event.preventDefault();
											void api(
												`/studio/units/${ unit.id }/items`,
												{
													method: 'POST',
													body: JSON.stringify( {
														title: itemTitle,
														kind,
													} ),
												}
											)
												.then( () => {
													setItemTitle( '' );
													setAddingOn( 0 );
													setMessage(
														__(
															'Item added.',
															'ohmylms'
														)
													);
												} )
												.catch( ( failure: Error ) =>
													setMessage(
														failure.message
													)
												);
										} }
									>
										<Field
											label={ __( 'Name', 'ohmylms' ) }
										>
											<input
												value={ itemTitle }
												onChange={ ( event ) =>
													setItemTitle(
														event.target.value
													)
												}
											/>
										</Field>
										<Field
											label={ __( 'Kind', 'ohmylms' ) }
										>
											<select
												value={ kind }
												onChange={ ( event ) =>
													setKind(
														event.target.value
													)
												}
											>
												<option value="lesson">
													{ __(
														'Lesson',
														'ohmylms'
													) }
												</option>
												<option value="video">
													{ __( 'Video', 'ohmylms' ) }
												</option>
												<option value="article">
													{ __(
														'Article',
														'ohmylms'
													) }
												</option>
												<option value="exercise">
													{ __(
														'Exercise',
														'ohmylms'
													) }
												</option>
												<option value="unit_test">
													{ __(
														'Unit test',
														'ohmylms'
													) }
												</option>
											</select>
										</Field>
										<button
											type="submit"
											className="oml-dash-btn is-primary"
										>
											{ __( 'Add', 'ohmylms' ) }
										</button>
									</form>
								) }
							</section>
						) ) }
					</div>
					<aside className="oml-dash-card">
						<h2>{ __( 'Item', 'ohmylms' ) }</h2>
						{ selected ? (
							<>
								<p>{ selected.title }</p>
								<p>
									<span
										className={
											selected.kind === 'quiz'
												? 'oml-badge is-quiz'
												: 'oml-badge'
										}
									>
										{ selected.kind === 'quiz'
											? __( 'Quiz', 'ohmylms' )
											: __( 'Lesson', 'ohmylms' ) }
									</span>
								</p>
								<p className="oml-dash-note">
									{ selected.skills.length
										? __(
												'Skill chips are saved on the lesson or quiz.',
												'ohmylms'
											)
										: __(
												'No skills tagged yet.',
												'ohmylms'
											) }
								</p>
								{ selected.kind === 'lesson' &&
									selected.edit && (
										<p>
											<a href={ selected.edit }>
												{ __(
													'Edit in Gutenberg',
													'ohmylms'
												) }
											</a>
										</p>
									) }
								{ selected.kind === 'quiz' && (
									<p>
										<button
											type="button"
											className="oml-dash-btn"
											onClick={ () => {
												const url = new URL(
													window.location.href
												);
												url.searchParams.set(
													'section',
													'quizzes'
												);
												url.searchParams.set(
													'quiz',
													String( selected.id )
												);
												window.history.pushState(
													{},
													'',
													url
												);
												window.location.reload();
											} }
										>
											{ __(
												'Open in quiz builder',
												'ohmylms'
											) }
										</button>
									</p>
								) }
							</>
						) : (
							<div className="oml-empty">
								<span
									className="oml-empty-mark"
									aria-hidden="true"
								>
									+
								</span>
								<p>
									{ __(
										'Select a lesson or quiz to see its kind and where to edit it.',
										'ohmylms'
									) }
								</p>
							</div>
						) }
					</aside>
				</div>
			) }
			{ message && <p role="status">{ message }</p> }
		</section>
	);
}
