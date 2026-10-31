import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTableSelect } from '../../../ui/ListTable';
import { Pagination } from '../../components/Pagination';
import { getCourseGrid } from './api';
import { CellDialog } from './CellDialog';
import { courseCsv } from './exports';
import { GradeGrid, type GridCell, type GridColumn } from './GradeGrid';
import { ItemDialog } from './ItemDialog';
import {
	cellDescription,
	cellText,
	cellTone,
	downloadCsv,
	formatMarks,
	formatPercent,
	kindLabel,
	paramsFromQuery,
	sourceLabel,
	weightsText,
} from './model';
import {
	CourseSelect,
	Legend,
	LoadState,
	RosterChips,
	ScopeSelect,
	SourceBadge,
	useDefaultCourse,
	useGradebookContext,
	useGradebookQuery,
	ViewActions,
} from './shared';
import type { CourseGrid, GradeItem, LearnerRow } from './types';
import { useLoad } from './useLoad';
import { WeightsDialog } from './WeightsDialog';

/** Columns before the items: source, rosters, total. */
const FIXED = 3;

function statusOptions() {
	return [
		{ value: '', label: __( 'All statuses', 'ohmylms' ) },
		{ value: 'missing', label: __( 'Has missing work', 'ohmylms' ) },
		{ value: 'pending', label: __( 'Has work to grade', 'ohmylms' ) },
		{ value: 'overridden', label: __( 'Has manual grades', 'ohmylms' ) },
		{ value: 'excused', label: __( 'Has excused work', 'ohmylms' ) },
		{ value: 'active', label: __( 'Enrolment active', 'ohmylms' ) },
		{ value: 'completed', label: __( 'Enrolment completed', 'ohmylms' ) },
		{ value: 'suspended', label: __( 'Enrolment suspended', 'ohmylms' ) },
	];
}

function itemHeader( item: GradeItem, onSettings: ( () => void ) | null ) {
	const meta = [ item.category, item.module ].filter( Boolean ).join( ' · ' );
	return (
		<>
			<span className="ohmylms-gradebook-grid__title">
				{ item.title }
			</span>
			<span className="ohmylms-gradebook-grid__meta">
				{ kindLabel( item.kind ) } · { meta }
			</span>
			<span className="ohmylms-gradebook-grid__meta">
				{ sprintf(
					/* translators: %s: maximum score. */
					__( 'out of %s', 'ohmylms' ),
					formatMarks( item.max )
				) }
				{ 'all' !== item.audience && (
					<>
						{ ' · ' }
						{ 'individuals' === item.audience
							? __( 'individuals', 'ohmylms' )
							: __( 'some classes', 'ohmylms' ) }
					</>
				) }
				{ item.due_at && (
					<>
						{ ' · ' }
						{ sprintf(
							/* translators: %s: due date. */
							__( 'due %s', 'ohmylms' ),
							new Date( item.due_at ).toLocaleDateString()
						) }
					</>
				) }
			</span>
			{ onSettings && (
				<Button
					variant="link"
					className="ohmylms-gradebook-grid__settings"
					tabIndex={ -1 }
					onClick={ onSettings }
					aria-label={ sprintf(
						/* translators: %s: item title. */
						__( 'Gradebook settings of %s', 'ohmylms' ),
						item.title
					) }
				>
					{ __( 'Settings', 'ohmylms' ) }
				</Button>
			) }
		</>
	);
}

function totalCell( row: LearnerRow ): GridCell {
	const differs = row.total.percent !== row.total.computed_percent;
	return {
		className: 'ohmylms-gradebook-grid__total',
		label: differs
			? sprintf(
					/* translators: 1: percentage, 2: letter, 3: computed percentage. */
					__(
						'Total %1$s %2$s; computed without manual grades %3$s',
						'ohmylms'
					),
					formatPercent( row.total.percent ),
					row.total.letter ?? '',
					formatPercent( row.total.computed_percent )
				)
			: sprintf(
					/* translators: 1: percentage, 2: letter. */
					__( 'Total %1$s %2$s', 'ohmylms' ),
					formatPercent( row.total.percent ),
					row.total.letter ?? ''
				),
		content: (
			<>
				<strong>{ formatPercent( row.total.percent ) }</strong>{ ' ' }
				<span>{ row.total.letter ?? '' }</span>
				{ 0 !== row.total.weights_class && (
					<span
						className="ohmylms-gradebook-grid__weights"
						title={ __( 'Class weight override', 'ohmylms' ) }
					>
						{ ' ' }
						{ __( '(class weights)', 'ohmylms' ) }
					</span>
				) }
				{ differs && (
					<span className="ohmylms-gradebook-grid__meta">
						{ sprintf(
							/* translators: %s: computed percentage. */
							__( 'computed %s', 'ohmylms' ),
							formatPercent( row.total.computed_percent )
						) }
					</span>
				) }
			</>
		),
	};
}

/**
 * Course × Students (Content Hub › Gradebook): every learner of a course in the scope against its graded
 * items, with the audience filter (everyone, individuals only, one roster), status, category and module
 * filters, search, weighted totals with letters, a column summary, saved views, share and CSV export.
 * Cells open the grade dialog (override with a reason, excuse, revert, history).
 */
export default function CourseStudentsPage() {
	const { query, update, linkTo, search } = useGradebookQuery();
	const context = useGradebookContext( query.scope );
	useDefaultCourse( context.data, query.course, ( course ) =>
		update( { course }, true )
	);
	const grid = useLoad< CourseGrid >(
		query.course && context.data ? `grid|${ search }` : null,
		( signal ) => getCourseGrid( query, signal )
	);
	const [ text, setText ] = useState( query.search );
	const [ open, setOpen ] = useState< {
		item: number;
		learner: number;
	} | null >( null );
	const [ settings, setSettings ] = useState< GradeItem | null >( null );
	const [ weighting, setWeighting ] = useState( false );

	const data = grid.data;
	const columns: GridColumn[] = data
		? [
				{
					key: 'source',
					label: __( 'Source', 'ohmylms' ),
					header: __( 'Source', 'ohmylms' ),
				},
				{
					key: 'rosters',
					label: __( 'Rosters', 'ohmylms' ),
					header: __( 'Rosters', 'ohmylms' ),
				},
				{
					key: 'total',
					label: __( 'Total', 'ohmylms' ),
					header: (
						<Link
							to={ `?${ paramsFromQuery( {
								...query,
								page: 1,
								orderby: 'total',
								order:
									'total' === query.orderby &&
									'desc' === query.order
										? 'asc'
										: 'desc',
							} ).toString() }` }
							tabIndex={ -1 }
						>
							{ __( 'Total', 'ohmylms' ) }
						</Link>
					),
					className: 'ohmylms-gradebook-grid__total',
				},
				...data.items.map( ( item ) => ( {
					key: `item-${ item.id }`,
					label: item.title,
					header: itemHeader(
						item,
						data.can.manage_course
							? () => setSettings( item )
							: null
					),
					className: 'ohmylms-gradebook-grid__item',
				} ) ),
			]
		: [];
	const rows =
		data?.rows.map( ( row ) => ( {
			key: String( row.id ),
			className: 'suspended' === row.status ? 'is-suspended' : undefined,
			header: (
				<>
					<Link
						className="row-title"
						to={ linkTo( 'student', { learner: row.id } ) }
						tabIndex={ -1 }
					>
						{ row.name }
					</Link>
					{ 'active' !== row.status && (
						<span className="ohmylms-gradebook-grid__meta">
							{ row.status }
						</span>
					) }
				</>
			),
			cells: [
				{
					content: row.sources.map( ( source ) => (
						<SourceBadge key={ source } source={ source } />
					) ),
					label: row.sources.map( sourceLabel ).join( ', ' ),
				},
				{
					content: (
						<RosterChips
							rosters={ row.rosters }
							linkTo={ linkTo }
						/>
					),
				},
				totalCell( row ),
				...data.items.map( ( item ) => {
					const cell = row.cells[ String( item.id ) ];
					return {
						activatable:
							Boolean( cell ) && 'not_assigned' !== cell?.state,
						className: `ohmylms-gradebook-cell is-${ cellTone( cell ) }${ cell?.overridden ? ' is-overridden' : '' }`,
						label: `${ item.title }: ${ cellDescription( cell ) }`,
						content: (
							<span aria-hidden="true">
								{ cellText( cell ) }
								{ cell?.overridden && (
									<span className="ohmylms-gradebook-cell__mark">
										✎
									</span>
								) }
							</span>
						),
					};
				} ),
			],
		} ) ) ?? [];
	const footer =
		data && data.rows.length
			? {
					key: 'summary',
					className: 'ohmylms-gradebook-grid__summary',
					header: (
						<strong>{ __( 'Class average', 'ohmylms' ) }</strong>
					),
					cells: [
						{ content: '' },
						{ content: '' },
						{
							content: (
								<strong>
									{ formatPercent( data.summary.average ) }
								</strong>
							),
						},
						...data.items.map( ( item ) => {
							const summary =
								data.summary.items[ String( item.id ) ];
							const missing = summary?.states.missing ?? 0;
							const pending = summary?.states.pending ?? 0;
							return {
								content: (
									<>
										{ formatPercent(
											summary?.average ?? null
										) }
										{ ( missing > 0 || pending > 0 ) && (
											<span className="ohmylms-gradebook-grid__meta">
												{ [
													missing
														? sprintf(
																/* translators: %d: count. */ __(
																	'%d missing',
																	'ohmylms'
																),
																missing
															)
														: '',
													pending
														? sprintf(
																/* translators: %d: count. */ __(
																	'%d to grade',
																	'ohmylms'
																),
																pending
															)
														: '',
												]
													.filter( Boolean )
													.join( ', ' ) }
											</span>
										) }
									</>
								),
							};
						} ),
					],
				}
			: undefined;

	const classOverride = ( id: number ) =>
		data?.classes.find( ( item ) => item.id === id )?.name ??
		__( 'a class', 'ohmylms' );
	const canWeight = Boolean(
		data && ( data.can.manage_course || data.can.weight_classes.length )
	);

	return (
		<div className="ohmylms-gradebook">
			<LoadState
				loading={ context.loading && ! context.data }
				error={ context.error }
				retry={ context.reload }
				label={ __( 'Loading the gradebook…', 'ohmylms' ) }
			/>
			{ context.data && (
				<>
					<div className="tablenav top ohmylms-gradebook-filters">
						<div className="alignleft actions">
							<ScopeSelect
								context={ context.data }
								onChange={ ( scope ) =>
									update( {
										scope,
										course: 0,
										audience: 'all',
										module: 0,
										type: '',
									} )
								}
							/>
							<CourseSelect
								context={ context.data }
								value={ query.course }
								onChange={ ( course ) =>
									update( {
										course,
										audience: 'all',
										module: 0,
										type: '',
									} )
								}
							/>
							<label
								htmlFor="ohmylms-gradebook-audience"
								className="screen-reader-text"
							>
								{ __( 'Audience', 'ohmylms' ) }
							</label>
							<select
								id="ohmylms-gradebook-audience"
								value={ query.audience }
								onChange={ ( event ) =>
									update( { audience: event.target.value } )
								}
							>
								<option value="all">
									{ __( 'All enrolled', 'ohmylms' ) }
								</option>
								<option value="individuals">
									{ __(
										'Individuals only (no roster)',
										'ohmylms'
									) }
								</option>
								{ data && data.classes.length > 0 && (
									<optgroup
										label={ __( 'One roster', 'ohmylms' ) }
									>
										{ data.classes.map( ( item ) => (
											<option
												key={ item.id }
												value={ `class:${ item.id }` }
											>
												{ item.name }
											</option>
										) ) }
									</optgroup>
								) }
							</select>
							<ListTableSelect
								label={ __( 'Status', 'ohmylms' ) }
								value={ query.status }
								options={ statusOptions() }
								onChange={ ( status ) => update( { status } ) }
							/>
							<ListTableSelect
								label={ __( 'Type', 'ohmylms' ) }
								value={ query.type }
								options={ [
									{
										value: '',
										label: __( 'All types', 'ohmylms' ),
									},
									...( data?.categories ?? [] ).map(
										( category ) => ( {
											value: category,
											label: category,
										} )
									),
								] }
								onChange={ ( type ) => update( { type } ) }
							/>
							<ListTableSelect
								label={ __( 'Module', 'ohmylms' ) }
								value={ String( query.module ) }
								options={ [
									{
										value: '0',
										label: __( 'All modules', 'ohmylms' ),
									},
									...( data?.modules ?? [] ).map(
										( module ) => ( {
											value: String( module.id ),
											label: module.title,
										} )
									),
								] }
								onChange={ ( module ) =>
									update( { module: Number( module ) } )
								}
							/>
						</div>
						<form
							className="search-box"
							role="search"
							onSubmit={ ( event ) => {
								event.preventDefault();
								update( { search: text.trim() } );
							} }
						>
							<label
								className="screen-reader-text"
								htmlFor="ohmylms-gradebook-search"
							>
								{ __( 'Search learners', 'ohmylms' ) }
							</label>
							<input
								id="ohmylms-gradebook-search"
								type="search"
								value={ text }
								maxLength={ 200 }
								onChange={ ( event ) =>
									setText( event.target.value )
								}
							/>
							<input
								type="submit"
								className="button"
								value={ __( 'Search learners', 'ohmylms' ) }
							/>
						</form>
						<br className="clear" />
					</div>
					<ViewActions
						section="course"
						search={ search }
						onExport={
							data
								? async () => {
										const all = await getCourseGrid(
											query,
											undefined,
											true
										);
										downloadCsv(
											`gradebook-${ all.course.title }`,
											courseCsv( all )
										);
									}
								: null
						}
					/>
				</>
			) }
			{ context.data && ! context.data.courses.length && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'There are no courses in this school or class yet.',
						'ohmylms'
					) }
				</Notice>
			) }
			<LoadState
				loading={ grid.loading && ! data }
				error={ grid.error }
				retry={ grid.reload }
				label={ __( 'Loading grades…', 'ohmylms' ) }
			/>
			{ data && (
				<>
					<p className="ohmylms-gradebook-weighting">
						{ sprintf(
							/* translators: 1: course title, 2: weights such as "homework 30% · quiz 70%". */
							__(
								'Weighting for %1$s (per course): %2$s',
								'ohmylms'
							),
							data.course.title,
							weightsText( data.weights.course.weights )
						) }
						{ data.weights.overrides.map( ( override ) => (
							<span
								key={ override.class_id }
								className="ohmylms-gradebook-override"
							>
								{ sprintf(
									/* translators: 1: class name, 2: weights. */
									__( '%1$s override: %2$s', 'ohmylms' ),
									classOverride( override.class_id ),
									weightsText( override.weights )
								) }
							</span>
						) ) }
						{ canWeight && (
							<Button
								variant="link"
								onClick={ () => setWeighting( true ) }
							>
								{ __( 'Edit weights', 'ohmylms' ) }
							</Button>
						) }
						<span className="ohmylms-gradebook-muted">
							{ ' · ' }
							{ data.missing_as_zero
								? __(
										'Missing work counts as zero; work to grade, not yet due, not assigned and excused work does not count.',
										'ohmylms'
									)
								: __(
										'Missing work does not count; only graded work counts.',
										'ohmylms'
									) }
						</span>
					</p>
					<Legend />
					{ data.items.length ? (
						<div
							className="ohmylms-gradebook-scroll"
							aria-busy={ grid.loading }
						>
							<GradeGrid
								label={ sprintf(
									/* translators: %s: course title. */
									__( 'Grades for %s', 'ohmylms' ),
									data.course.title
								) }
								corner={
									<Link
										to={ `?${ paramsFromQuery( {
											...query,
											page: 1,
											orderby: 'name',
											order:
												'name' === query.orderby &&
												'asc' === query.order
													? 'desc'
													: 'asc',
										} ).toString() }` }
										tabIndex={ -1 }
									>
										{ sprintf(
											/* translators: %d: number of learners. */
											_n(
												'Learner (%d)',
												'Learners (%d)',
												data.total,
												'ohmylms'
											),
											data.total
										) }
									</Link>
								}
								columns={ columns }
								rows={ rows }
								footer={ footer }
								rowCount={ data.total }
								rowOffset={ ( data.page - 1 ) * data.per_page }
								onActivate={ ( row, column ) => {
									const item = data.items[ column - FIXED ];
									const learner = data.rows[ row ];
									if ( item && learner ) {
										setOpen( {
											item: item.id,
											learner: learner.id,
										} );
									}
								} }
							/>
						</div>
					) : (
						<p>
							{ __(
								'This course has no published quizzes, exams or assignments to grade.',
								'ohmylms'
							) }
						</p>
					) }
					{ ! data.rows.length && (
						<p>
							{ __(
								'No learners match these filters.',
								'ohmylms'
							) }
						</p>
					) }
					{ data.total_pages > 1 && (
						<Pagination
							page={ data.page }
							pages={ data.total_pages }
							busy={ grid.loading }
							onChange={ ( page ) => update( { page } ) }
						/>
					) }
					<p className="ohmylms-gradebook-muted">
						{ __(
							'Use the arrow keys to move between cells; Enter opens a grade.',
							'ohmylms'
						) }
					</p>
				</>
			) }
			{ open && data && (
				<CellDialog
					scope={ query.scope }
					course={ data.course.id }
					item={ open.item }
					learner={ open.learner }
					onClose={ () => setOpen( null ) }
					onSaved={ grid.reload }
				/>
			) }
			{ settings && data && (
				<ItemDialog
					scope={ query.scope }
					course={ data.course.id }
					item={ settings }
					classes={ data.classes }
					onClose={ () => setSettings( null ) }
					onSaved={ grid.reload }
				/>
			) }
			{ weighting && data && (
				<WeightsDialog
					scope={ query.scope }
					course={ data.course.id }
					weights={ data.weights }
					categories={ data.categories }
					classes={ data.classes.filter( ( item ) =>
						data.can.weight_classes.includes( item.id )
					) }
					canCourse={ data.can.manage_course }
					onClose={ () => setWeighting( false ) }
					onSaved={ grid.reload }
				/>
			) }
		</div>
	);
}
