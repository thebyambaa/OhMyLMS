import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { getMastery } from './api';
import { masteryCsv } from './exports';
import { GradeGrid, type GridColumn, type GridRow } from './GradeGrid';
import { downloadCsv, formatPercent } from './model';
import {
	CourseSelect,
	LoadState,
	RosterChips,
	ScopeSelect,
	SourceBadge,
	useGradebookContext,
	useGradebookQuery,
	ViewActions,
} from './shared';
import type { MasteryView } from './types';
import { useLoad } from './useLoad';

/**
 * Skill mastery: learners × skills from the skill evidence (the 20 skills most of the learners have
 * evidence on). Learners are a course's (with the audience filter) or, in a school or class, its learners.
 */
export default function MasteryPage() {
	const { query, update, linkTo, search } = useGradebookQuery();
	const context = useGradebookContext( query.scope );
	const needsCourse = 'all' === context.data?.scope.type;
	const view = useLoad< MasteryView >(
		context.data && ( query.course || ! needsCourse )
			? `mastery|${ search }`
			: null,
		( signal ) => getMastery( query, signal )
	);
	const [ text, setText ] = useState( query.search );
	const data = view.data;

	const columns: GridColumn[] = [
		{
			key: 'source',
			label: __( 'Source', 'ohmylms' ),
			header: __( 'Source', 'ohmylms' ),
		},
		...( data?.skills ?? [] ).map( ( skill ) => ( {
			key: `skill-${ skill.id }`,
			label: skill.name,
			header: (
				<>
					<span className="ohmylms-gradebook-grid__title">
						{ skill.name }
					</span>
					{ skill.code && (
						<span className="ohmylms-gradebook-grid__meta">
							{ skill.code }
						</span>
					) }
				</>
			),
			className: 'ohmylms-gradebook-grid__item',
		} ) ),
	];
	const label = ( id: string ) =>
		data?.levels.find( ( level ) => level.id === id )?.label ?? id;
	const rows: GridRow[] = ( data?.learners ?? [] ).map( ( learner ) => ( {
		key: String( learner.id ),
		header: (
			<>
				<Link
					className="row-title"
					to={ linkTo( 'student', { learner: learner.id } ) }
					tabIndex={ -1 }
				>
					{ learner.name }
				</Link>{ ' ' }
				{ learner.rosters.length > 0 && (
					<RosterChips
						rosters={ learner.rosters }
						linkTo={ linkTo }
					/>
				) }
			</>
		),
		cells: [
			{
				content: learner.source ? (
					<SourceBadge source={ learner.source } />
				) : (
					''
				),
			},
			...( data?.skills ?? [] ).map( ( skill ) => {
				const cell = learner.cells[ String( skill.id ) ];
				const level = cell?.level ?? 'not-started';
				return {
					className: `ohmylms-gradebook-mastery is-${ level }`,
					label: `${ skill.name }: ${ label( level ) }${ cell?.percent !== null && cell?.percent !== undefined ? `, ${ formatPercent( cell.percent ) }` : '' }`,
					content: (
						<span aria-hidden="true">
							{ label( level ) }
							{ null !== ( cell?.percent ?? null ) && (
								<span className="ohmylms-gradebook-grid__meta">
									{ formatPercent( cell?.percent ?? null ) }
								</span>
							) }
						</span>
					),
				};
			} ),
		],
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
									update( {
										scope,
										course: 0,
										audience: 'all',
									} )
								}
							/>
							<CourseSelect
								context={ context.data }
								value={ query.course }
								optional={ ! needsCourse }
								onChange={ ( course ) =>
									update( { course, audience: 'all' } )
								}
							/>
							{ query.course > 0 && (
								<>
									<label
										htmlFor="ohmylms-gradebook-mastery-audience"
										className="screen-reader-text"
									>
										{ __( 'Audience', 'ohmylms' ) }
									</label>
									<select
										id="ohmylms-gradebook-mastery-audience"
										value={ query.audience }
										onChange={ ( event ) =>
											update( {
												audience: event.target.value,
											} )
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
										<optgroup
											label={ __(
												'One roster',
												'ohmylms'
											) }
										>
											{ context.data.classes.map(
												( item ) => (
													<option
														key={ item.id }
														value={ `class:${ item.id }` }
													>
														{ item.name }
													</option>
												)
											) }
										</optgroup>
									</select>
								</>
							) }
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
								htmlFor="ohmylms-gradebook-mastery-search"
							>
								{ __( 'Search learners', 'ohmylms' ) }
							</label>
							<input
								id="ohmylms-gradebook-mastery-search"
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
						section="mastery"
						search={ search }
						onExport={
							data
								? () =>
										downloadCsv(
											'skill-mastery',
											masteryCsv( data )
										)
								: null
						}
					/>
				</>
			) }
			{ needsCourse && ! query.course && (
				<p>
					{ __(
						'Choose a course, or a school or class, to compare skills.',
						'ohmylms'
					) }
				</p>
			) }
			<LoadState
				loading={ view.loading && ! data }
				error={ view.error }
				retry={ view.reload }
				label={ __( 'Loading skill mastery…', 'ohmylms' ) }
			/>
			{ data && (
				<>
					<p className="ohmylms-gradebook-legend">
						{ data.levels.map( ( level ) => (
							<span
								key={ level.id }
								className={ `ohmylms-gradebook-swatch ohmylms-gradebook-mastery is-${ level.id }` }
							>
								{ level.label }
								{ null !== level.min && ` ≥ ${ level.min }%` }
							</span>
						) ) }
					</p>
					{ data.skills.length && rows.length ? (
						<div className="ohmylms-gradebook-scroll">
							<GradeGrid
								label={ __(
									'Skill mastery of learners',
									'ohmylms'
								) }
								corner={ sprintf(
									/* translators: %d: number of learners. */
									__( 'Learners (%d)', 'ohmylms' ),
									data.total
								) }
								columns={ columns }
								rows={ rows }
								rowCount={ data.total }
								rowOffset={ ( data.page - 1 ) * 50 }
							/>
						</div>
					) : (
						<p>
							{ __(
								'No skill evidence for these learners yet.',
								'ohmylms'
							) }
						</p>
					) }
					{ data.total_pages > 1 && (
						<Pagination
							page={ data.page }
							pages={ data.total_pages }
							busy={ view.loading }
							onChange={ ( page ) => update( { page } ) }
						/>
					) }
				</>
			) }
		</div>
	);
}
