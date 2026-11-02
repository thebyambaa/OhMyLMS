import { Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTableSelect } from '../../../ui/ListTable';
import { getRoster } from './api';
import { CellDialog } from './CellDialog';
import { rosterCsv } from './exports';
import { GradeGrid, type GridColumn, type GridRow } from './GradeGrid';
import {
	cellDescription,
	cellText,
	cellTone,
	downloadCsv,
	formatPercent,
} from './model';
import {
	Legend,
	LoadState,
	ScopeSelect,
	useGradebookContext,
	useGradebookQuery,
	ViewActions,
} from './shared';
import type { RosterView } from './types';
import { useLoad } from './useLoad';

/**
 * Roster × Assignments: one class's learners against the items of every course linked to the class,
 * with a total per course. "·" marks a course the learner is not enrolled in; "—" an item not assigned.
 */
export default function RosterPage() {
	const { query, update, linkTo, search } = useGradebookQuery();
	const context = useGradebookContext( query.scope );
	const classes = context.data?.classes ?? [];
	useEffect( () => {
		const first = classes[ 0 ];
		if ( context.data && ! query.classId && first ) {
			update( { classId: first.id }, true );
		}
		// Only when the context or the class changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ context.data, query.classId ] );
	const roster = useLoad< RosterView >(
		query.classId && context.data
			? `roster|${ query.scope }|${ query.classId }`
			: null,
		( signal ) => getRoster( query.scope, query.classId, signal )
	);
	const [ open, setOpen ] = useState< {
		course: number;
		item: number;
		learner: number;
	} | null >( null );
	const data = roster.data;

	const columns: ( GridColumn & { course: number; item: number } )[] = [];
	for ( const course of data?.courses ?? [] ) {
		columns.push( {
			key: `total-${ course.id }`,
			course: course.id,
			item: 0,
			label: sprintf(
				/* translators: %s: course title. */
				__( '%s total', 'ohmylms' ),
				course.title
			),
			header: (
				<>
					<Link
						to={ linkTo( 'course', {
							course: course.id,
							audience: `class:${ query.classId }`,
						} ) }
						tabIndex={ -1 }
					>
						{ course.title }
					</Link>
					<span className="ohmylms-gradebook-grid__meta">
						{ sprintf(
							/* translators: %s: class average. */
							__( 'total · average %s', 'ohmylms' ),
							formatPercent( course.average )
						) }
					</span>
				</>
			),
			className: 'ohmylms-gradebook-grid__total',
		} );
		for ( const item of course.items ) {
			columns.push( {
				key: `${ course.id }:${ item.id }`,
				course: course.id,
				item: item.id,
				label: `${ course.title }: ${ item.title }`,
				header: (
					<>
						<span className="ohmylms-gradebook-grid__title">
							{ item.title }
						</span>
						<span className="ohmylms-gradebook-grid__meta">
							{ item.category }
						</span>
					</>
				),
				className: 'ohmylms-gradebook-grid__item',
			} );
		}
	}
	const rows: GridRow[] = ( data?.learners ?? [] ).map( ( learner ) => ( {
		key: String( learner.id ),
		header: (
			<Link
				className="row-title"
				to={ linkTo( 'student', { learner: learner.id } ) }
				tabIndex={ -1 }
			>
				{ learner.name }
			</Link>
		),
		cells: columns.map( ( column ) => {
			const total = learner.totals[ String( column.course ) ];
			if ( ! column.item ) {
				return {
					className: 'ohmylms-gradebook-grid__total',
					label: total
						? sprintf(
								/* translators: 1: percentage, 2: letter. */
								__( 'Total %1$s %2$s', 'ohmylms' ),
								formatPercent( total.percent ),
								total.letter ?? ''
							)
						: __( 'Not enrolled in this course', 'ohmylms' ),
					content: total ? (
						<>
							<strong>{ formatPercent( total.percent ) }</strong>{ ' ' }
							{ total.letter ?? '' }
						</>
					) : (
						<span aria-hidden="true">·</span>
					),
				};
			}
			const cell = total ? learner.cells[ column.key ] : undefined;
			return {
				activatable: Boolean( cell ) && 'not_assigned' !== cell?.state,
				className: `ohmylms-gradebook-cell is-${ cellTone( cell ) }${ cell?.overridden ? ' is-overridden' : '' }`,
				label: `${ column.label }: ${ cellDescription( cell ) }`,
				content: <span aria-hidden="true">{ cellText( cell ) }</span>,
			};
		} ),
	} ) );

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
									update( { scope, classId: 0 } )
								}
							/>
							<ListTableSelect
								label={ __( 'Roster', 'ohmylms' ) }
								value={ String( query.classId ) }
								options={ [
									...( query.classId
										? []
										: [
												{
													value: '0',
													label: __(
														'Choose a class',
														'ohmylms'
													),
												},
											] ),
									...classes.map( ( item ) => ( {
										value: String( item.id ),
										label: item.school_name
											? `${ item.name } (${ item.school_name })`
											: item.name,
									} ) ),
								] }
								onChange={ ( value ) =>
									update( { classId: Number( value ) } )
								}
							/>
						</div>
						<br className="clear" />
					</div>
					<ViewActions
						section="roster"
						search={ search }
						onExport={
							data
								? () =>
										downloadCsv(
											`roster-${ data.class.name }`,
											rosterCsv( data )
										)
								: null
						}
					/>
				</>
			) }
			{ context.data && ! classes.length && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'There are no classes in this school or class.',
						'ohmylms'
					) }
				</Notice>
			) }
			<LoadState
				loading={ roster.loading && ! data }
				error={ roster.error }
				retry={ roster.reload }
				label={ __( 'Loading the roster…', 'ohmylms' ) }
			/>
			{ data && (
				<>
					<p>
						{ sprintf(
							/* translators: 1: class, 2: school, 3: number of learners, 4: number of courses. */
							__(
								'%1$s · %2$s · %3$d learners · %4$d courses linked to the class',
								'ohmylms'
							),
							data.class.name,
							data.class.school_name ||
								__( 'independent class', 'ohmylms' ),
							data.learners.length,
							data.courses.length
						) }
					</p>
					{ data.truncated && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This roster is large; only the first courses and learners are shown. Export or open one course for the rest.',
								'ohmylms'
							) }
						</Notice>
					) }
					<Legend sources={ false } />
					{ columns.length && rows.length ? (
						<div className="ohmylms-gradebook-scroll">
							<GradeGrid
								label={ sprintf(
									/* translators: %s: class name. */
									__(
										'Grades of %s across its courses',
										'ohmylms'
									),
									data.class.name
								) }
								corner={ __( 'Member', 'ohmylms' ) }
								columns={ columns }
								rows={ rows }
								onActivate={ ( row, column ) => {
									const target = columns[ column ];
									const learner = data.learners[ row ];
									if ( target?.item && learner ) {
										setOpen( {
											course: target.course,
											item: target.item,
											learner: learner.id,
										} );
									}
								} }
							/>
						</div>
					) : (
						<p>
							{ __(
								'No courses are linked to this class yet, or it has no learners.',
								'ohmylms'
							) }
						</p>
					) }
					<p className="ohmylms-gradebook-muted">
						{ __(
							'· not enrolled in that course — enrolled but not assigned',
							'ohmylms'
						) }
					</p>
				</>
			) }
			{ open && (
				<CellDialog
					scope={ query.scope }
					course={ open.course }
					item={ open.item }
					learner={ open.learner }
					onClose={ () => setOpen( null ) }
					onSaved={ roster.reload }
				/>
			) }
		</div>
	);
}
