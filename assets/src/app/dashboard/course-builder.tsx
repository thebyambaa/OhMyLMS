import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Alert } from '../../ui/Alert';
import { Badge } from '../../ui/Badge';
import { Button } from '../../ui/Button';
import { IconButton } from '../../ui/IconButton';
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';
import { api } from './api';
import { quizUrl } from './studio-links';

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
	const [ failed, setFailed ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
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

	function done( text: string ) {
		setFailed( false );
		setMessage( text );
		setBusy( '' );
	}

	function fail( failure: Error ) {
		setFailed( true );
		setMessage( failure.message );
		setBusy( '' );
	}

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
			.then( () => done( __( 'Order saved.', 'ohmylms' ) ) )
			.catch( ( failure: Error ) => fail( failure ) );
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

	function kindBadge( item: CourseItem ) {
		return item.kind === 'quiz' ? (
			<Badge tone="info">{ __( 'Quiz', 'ohmylms' ) }</Badge>
		) : (
			<Badge>{ __( 'Lesson', 'ohmylms' ) }</Badge>
		);
	}

	return (
		<section className="oml-builder">
			<header className="oml-page-bar">
				<h1 className="oml-dash-title">
					{ __( 'Courses', 'ohmylms' ) }
				</h1>
				<Select
					label={ __( 'Course', 'ohmylms' ) }
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
				</Select>
			</header>
			{ tree && (
				<div className="oml-builder-grid">
					<div>
						{ tree.canEdit &&
							( addingUnit ? (
								<form
									onSubmit={ ( event ) => {
										event.preventDefault();
										setBusy( 'unit' );
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
												done(
													__(
														'Unit added.',
														'ohmylms'
													)
												);
											} )
											.catch( ( failure: Error ) =>
												fail( failure )
											);
									} }
								>
									<Input
										label={ __( 'New unit', 'ohmylms' ) }
										required
										value={ title }
										onChange={ ( event ) =>
											setTitle( event.target.value )
										}
									/>
									<Button
										type="submit"
										variant="primary"
										loading={ busy === 'unit' }
									>
										{ __( 'Add unit', 'ohmylms' ) }
									</Button>
								</form>
							) : (
								<Button
									variant="tertiary"
									onClick={ () => setAddingUnit( true ) }
								>
									{ __( 'Add unit', 'ohmylms' ) }
								</Button>
							) ) }
						{ tree.units.map( ( unit, unitIndex ) => (
							<section key={ unit.id } className="oml-dash-card">
								<div className="oml-dash-actions">
									<h2>{ unit.title }</h2>
									{ tree.canEdit && (
										<Button
											variant="tertiary"
											size="sm"
											aria-expanded={
												addingOn === unit.id
											}
											onClick={ () =>
												setAddingOn(
													addingOn === unit.id
														? 0
														: unit.id
												)
											}
										>
											{ __( 'Add item', 'ohmylms' ) }
										</Button>
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
											<Button
												variant="tertiary"
												size="sm"
												aria-pressed={
													selected?.id === item.id
												}
												onClick={ () =>
													setSelected( item )
												}
											>
												{ item.title }
											</Button>
											{ kindBadge( item ) }
											{ tree.canEdit && (
												<>
													<IconButton
														label={ __(
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
													</IconButton>
													<IconButton
														label={ __(
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
													</IconButton>
													{ item.kind === 'lesson' &&
														item.edit && (
															<Button
																variant="primary"
																size="sm"
																href={
																	item.edit
																}
															>
																{ __(
																	'Open lesson',
																	'ohmylms'
																) }
															</Button>
														) }
													{ item.kind === 'quiz' && (
														<Button
															variant="primary"
															size="sm"
															href={ quizUrl(
																item.id
															) }
														>
															{ __(
																'Open quiz',
																'ohmylms'
															) }
														</Button>
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
											setBusy( 'item' );
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
													done(
														__(
															'Item added.',
															'ohmylms'
														)
													);
												} )
												.catch( ( failure: Error ) =>
													fail( failure )
												);
										} }
									>
										<Input
											label={ __( 'Name', 'ohmylms' ) }
											required
											value={ itemTitle }
											onChange={ ( event ) =>
												setItemTitle(
													event.target.value
												)
											}
										/>
										<Select
											label={ __( 'Kind', 'ohmylms' ) }
											value={ kind }
											onChange={ ( event ) =>
												setKind( event.target.value )
											}
											options={ [
												{
													value: 'lesson',
													label: __(
														'Lesson',
														'ohmylms'
													),
												},
												{
													value: 'video',
													label: __(
														'Video',
														'ohmylms'
													),
												},
												{
													value: 'article',
													label: __(
														'Article',
														'ohmylms'
													),
												},
												{
													value: 'exercise',
													label: __(
														'Exercise',
														'ohmylms'
													),
												},
												{
													value: 'unit_test',
													label: __(
														'Unit test',
														'ohmylms'
													),
												},
											] }
										/>
										<Button
											type="submit"
											variant="primary"
											loading={ busy === 'item' }
										>
											{ __( 'Add item', 'ohmylms' ) }
										</Button>
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
								<p>{ kindBadge( selected ) }</p>
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
											<Button href={ selected.edit }>
												{ __(
													'Edit in Gutenberg',
													'ohmylms'
												) }
											</Button>
										</p>
									) }
								{ selected.kind === 'quiz' && (
									<p>
										<Button href={ quizUrl( selected.id ) }>
											{ __(
												'Open in quiz builder',
												'ohmylms'
											) }
										</Button>
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
			{ message && (
				<Alert tone={ failed ? 'danger' : 'success' }>
					{ message }
				</Alert>
			) }
		</section>
	);
}
