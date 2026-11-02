import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { WidefatTable } from '../../components/WidefatTable';
import { getRollup } from './api';
import { rollupCsv } from './exports';
import { downloadCsv, formatPercent } from './model';
import {
	LoadState,
	ScopeSelect,
	useGradebookContext,
	useGradebookQuery,
	ViewActions,
} from './shared';
import type { RollupNode, RollupView } from './types';
import { useLoad } from './useLoad';

function typeLabel( type: RollupNode[ 'type' ] ) {
	switch ( type ) {
		case 'school':
			return __( 'School', 'ohmylms' );
		case 'class':
			return __( 'Class', 'ohmylms' );
		case 'independent':
			return __( 'Independent classes', 'ohmylms' );
		case 'individuals':
			return __( 'Individual learners', 'ohmylms' );
	}
}

/**
 * Org roll-up: school → class (and, for all schools, independent classes and individual learners) with
 * learners, enrolments, the average weighted total, learners with missing work and work to grade. A
 * learner in two classes counts once per school.
 */
export default function RollupPage() {
	const { query, update, linkTo, search } = useGradebookQuery();
	const context = useGradebookContext( query.scope );
	const view = useLoad< RollupView >(
		context.data ? `rollup|${ query.scope }` : null,
		( signal ) => getRollup( query.scope, signal )
	);
	const data = view.data;

	const rows: { node: RollupNode; depth: number }[] = [];
	const walk = ( nodes: readonly RollupNode[], depth: number ) => {
		for ( const node of nodes ) {
			rows.push( { node, depth } );
			walk( node.children, depth + 1 );
		}
	};
	walk( data?.nodes ?? [], 0 );

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
								onChange={ ( scope ) => update( { scope } ) }
							/>
						</div>
						<br className="clear" />
					</div>
					<ViewActions
						section="rollup"
						search={ search }
						onExport={
							data
								? () =>
										downloadCsv(
											'gradebook-roll-up',
											rollupCsv( data.nodes )
										)
								: null
						}
					/>
				</>
			) }
			<LoadState
				loading={ view.loading && ! data }
				error={ view.error }
				retry={ view.reload }
				label={ __( 'Loading the roll-up…', 'ohmylms' ) }
			/>
			{ data?.truncated && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Only the first 60 classes are summarised. Pick a school to see the rest.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ data && (
				<>
					<WidefatTable
						caption={ __(
							'Schools, classes and individual learners',
							'ohmylms'
						) }
					>
						<thead>
							<tr>
								<th scope="col">
									{ __(
										'School, class or group',
										'ohmylms'
									) }
								</th>
								<th scope="col" className="num">
									{ __( 'Learners', 'ohmylms' ) }
								</th>
								<th scope="col" className="num">
									{ __( 'Enrolments', 'ohmylms' ) }
								</th>
								<th scope="col" className="num">
									{ __( 'Average total', 'ohmylms' ) }
								</th>
								<th scope="col" className="num">
									{ __( 'With missing work', 'ohmylms' ) }
								</th>
								<th scope="col" className="num">
									{ __( 'To grade', 'ohmylms' ) }
								</th>
								<th scope="col">
									<span className="screen-reader-text">
										{ __( 'Actions', 'ohmylms' ) }
									</span>
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( { node, depth } ) => (
								<tr
									key={ `${ node.type }-${ node.id }-${ node.name }` }
									className={ `ohmylms-gradebook-rollup is-${ node.type }` }
								>
									<th
										scope="row"
										className={ `ohmylms-gradebook-rollup__name is-depth-${ Math.min( depth, 3 ) }` }
									>
										{ 'class' === node.type ? (
											node.name
										) : (
											<strong>{ node.name }</strong>
										) }
										<span className="ohmylms-gradebook-grid__meta">
											{ typeLabel( node.type ) }
										</span>
									</th>
									<td className="num">{ node.learners }</td>
									<td className="num">{ node.enrolments }</td>
									<td className="num">
										{ formatPercent( node.average ) }
									</td>
									<td className="num">
										{ node.missing_learners }
									</td>
									<td className="num">{ node.pending }</td>
									<td>
										{ 'class' === node.type && (
											<Link
												className="button button-small"
												to={ linkTo( 'roster', {
													classId: node.id,
												} ) }
											>
												{ __(
													'Open roster',
													'ohmylms'
												) }
											</Link>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</WidefatTable>
					{ ! rows.length && (
						<p>{ __( 'No schools or classes yet.', 'ohmylms' ) }</p>
					) }
					<p className="ohmylms-gradebook-muted">
						{ __(
							'Averages are over each learner’s weighted course totals in the courses linked to the class. “Individual learners” are enrolled learners in no class.',
							'ohmylms'
						) }
					</p>
				</>
			) }
		</div>
	);
}
