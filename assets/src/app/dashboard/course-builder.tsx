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
	const [ unitId, setUnitId ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	const [ selected, setSelected ] = useState< CourseItem | null >( null );

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
				setUnitId( body.units[ 0 ]?.id || 0 );
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
			{ tree && (
				<div className="oml-builder-grid">
					<div>
						{ tree.canEdit && (
							<form
								onSubmit={ ( event ) => {
									event.preventDefault();
									void api(
										`/studio/courses/${ tree.id }/units`,
										{
											method: 'POST',
											body: JSON.stringify( { title } ),
										}
									)
										.then( () => {
											setTitle( '' );
											setMessage(
												__( 'Unit added.', 'ohmylms' )
											);
										} )
										.catch( ( failure: Error ) =>
											setMessage( failure.message )
										);
								} }
							>
								<Field label={ __( 'New unit', 'ohmylms' ) }>
									<input
										value={ title }
										onChange={ ( event ) =>
											setTitle( event.target.value )
										}
									/>
								</Field>
								<button type="submit" className="oml-dash-btn">
									{ __( 'Add unit', 'ohmylms' ) }
								</button>
							</form>
						) }
						{ tree.units.map( ( unit, unitIndex ) => (
							<section key={ unit.id } className="oml-dash-card">
								<h2>{ unit.title }</h2>
								<ul>
									{ unit.items.map( ( item, itemIndex ) => (
										<li
											key={ item.id }
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
											<button
												type="button"
												className="link"
												onClick={ () =>
													setSelected( item )
												}
											>
												{ item.title }
											</button>
											<span className="oml-dash-note">
												{ ' ' }
												{ item.type }
											</span>
											{ tree.canEdit && (
												<>
													<button
														type="button"
														className="oml-dash-btn"
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
														{ __(
															'Up',
															'ohmylms'
														) }
													</button>
													<button
														type="button"
														className="oml-dash-btn"
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
														{ __(
															'Down',
															'ohmylms'
														) }
													</button>
												</>
											) }
										</li>
									) ) }
								</ul>
							</section>
						) ) }
						{ tree.canEdit && unitId > 0 && (
							<form
								onSubmit={ ( event ) => {
									event.preventDefault();
									void api(
										`/studio/units/${ unitId }/items`,
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
											setMessage(
												__( 'Item added.', 'ohmylms' )
											);
										} )
										.catch( ( failure: Error ) =>
											setMessage( failure.message )
										);
								} }
							>
								<h2>{ __( 'Add item', 'ohmylms' ) }</h2>
								<Field label={ __( 'Unit', 'ohmylms' ) }>
									<select
										value={ unitId }
										onChange={ ( event ) =>
											setUnitId(
												Number( event.target.value )
											)
										}
									>
										{ tree.units.map( ( unit ) => (
											<option
												key={ unit.id }
												value={ unit.id }
											>
												{ unit.title }
											</option>
										) ) }
									</select>
								</Field>
								<Field label={ __( 'Name', 'ohmylms' ) }>
									<input
										value={ itemTitle }
										onChange={ ( event ) =>
											setItemTitle( event.target.value )
										}
									/>
								</Field>
								<Field label={ __( 'Kind', 'ohmylms' ) }>
									<select
										value={ kind }
										onChange={ ( event ) =>
											setKind( event.target.value )
										}
									>
										<option value="lesson">
											{ __( 'Lesson', 'ohmylms' ) }
										</option>
										<option value="video">
											{ __( 'Video', 'ohmylms' ) }
										</option>
										<option value="article">
											{ __( 'Article', 'ohmylms' ) }
										</option>
										<option value="exercise">
											{ __( 'Exercise', 'ohmylms' ) }
										</option>
										<option value="unit_test">
											{ __( 'Unit test', 'ohmylms' ) }
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
					</div>
					<aside className="oml-dash-card">
						<h2>{ __( 'Item', 'ohmylms' ) }</h2>
						{ selected ? (
							<>
								<p>{ selected.title }</p>
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
							<p className="oml-dash-note">
								{ __( 'Select an item.', 'ohmylms' ) }
							</p>
						) }
					</aside>
				</div>
			) }
			{ message && <p role="status">{ message }</p> }
		</section>
	);
}
