import { Button, Notice, SelectControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { exportReport, getReport } from './operationsApi';
import type { ReportPeriod, SchoolDetail } from './types';

function percent( value: number | null ): string {
	return null === value
		? '—'
		: sprintf(
				/* translators: %d: percentage. */
				__( '%d%%', 'ohmylms' ),
				value
			);
}

/**
 * Save text as a file.
 *
 * @param filename File name.
 * @param type     MIME type.
 * @param text     Content.
 */
export function download( filename: string, type: string, text: string ) {
	const url = URL.createObjectURL( new Blob( [ text ], { type } ) );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.append( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

/**
 * School report: learners, active learners, course completion, distributed work and a mastery
 * overview, from the same read models as the Dashboard, with a spreadsheet-safe CSV export.
 *
 * @param props        Component props.
 * @param props.school The school.
 */
export function SchoolReportPanel( { school }: { school: SchoolDetail } ) {
	const [ period, setPeriod ] = useState< ReportPeriod >( 30 );
	const report = useRequest(
		useCallback(
			( signal: AbortSignal ) => getReport( school.id, period, signal ),
			[ school.id, period ]
		)
	);
	const exporting = useOperation();
	const data = report.data;

	return (
		<section aria-label={ __( 'School report', 'ohmylms' ) }>
			<h3>{ __( 'School report', 'ohmylms' ) }</h3>
			<SelectControl
				label={ __( 'Activity period', 'ohmylms' ) }
				value={ String( period ) }
				options={ [ 7, 30, 90 ].map( ( days ) => ( {
					value: String( days ),
					label: sprintf(
						/* translators: %d: number of days. */
						__( 'Last %d days', 'ohmylms' ),
						days
					),
				} ) ) }
				onChange={ ( value ) =>
					setPeriod( Number( value ) as ReportPeriod )
				}
			/>
			{ report.loading && (
				<p role="status">{ __( 'Loading report…', 'ohmylms' ) }</p>
			) }
			{ report.error && (
				<Notice status="error" isDismissible={ false }>
					{ report.error }{ ' ' }
					<Button variant="link" onClick={ report.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ exporting.error && (
				<Notice status="error" isDismissible={ false }>
					{ exporting.error }
				</Notice>
			) }
			{ data && (
				<>
					<p>
						<Button
							variant="secondary"
							disabled={ exporting.busy }
							isBusy={ exporting.busy }
							onClick={ () =>
								void exporting.run(
									( signal ) =>
										exportReport(
											school.id,
											period,
											signal
										),
									( file ) =>
										download(
											file.filename,
											file.content_type,
											file.csv
										)
								)
							}
						>
							{ __( 'Download CSV', 'ohmylms' ) }
						</Button>
					</p>
					<WidefatTable
						caption={ __( 'Whole school', 'ohmylms' ) }
						fixed
					>
						<tbody>
							{ [
								[
									__( 'Active classes', 'ohmylms' ),
									sprintf(
										/* translators: 1: active classes, 2: archived classes. */
										__( '%1$d (%2$d archived)', 'ohmylms' ),
										data.summary.classes,
										data.summary.archived_classes
									),
								],
								[
									__( 'Learners', 'ohmylms' ),
									String( data.summary.learners ),
								],
								[
									sprintf(
										/* translators: %d: number of days. */
										__(
											'Active learners (last %d days)',
											'ohmylms'
										),
										data.period.days
									),
									String( data.summary.active_learners ),
								],
								[
									__( 'Course completion', 'ohmylms' ),
									sprintf(
										/* translators: 1: percentage, 2: completed, 3: enrolments. */
										__(
											'%1$s (%2$d of %3$d enrolments)',
											'ohmylms'
										),
										percent( data.summary.completion ),
										data.summary.completed,
										data.summary.enrolled
									),
								],
								[
									__(
										'Distributed work completed',
										'ohmylms'
									),
									sprintf(
										/* translators: 1: completed, 2: assigned. */
										__( '%1$d of %2$d', 'ohmylms' ),
										data.summary.assigned_completed,
										data.summary.assigned
									),
								],
								[
									__( 'Average mastery', 'ohmylms' ),
									percent( data.mastery.average ),
								],
							].map( ( [ label, value ] ) => (
								<tr key={ label }>
									<th scope="row">{ label }</th>
									<td>{ value }</td>
								</tr>
							) ) }
						</tbody>
					</WidefatTable>
					<h4>{ __( 'Classes', 'ohmylms' ) }</h4>
					{ data.classes.length ? (
						<WidefatTable
							caption={ __( 'Report by class', 'ohmylms' ) }
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Class', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Grade', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Learners', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Active', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Completion', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Work completed', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Teachers', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.classes.map( ( row ) => (
									<tr key={ row.id }>
										<th scope="row">{ row.name }</th>
										<td>{ row.grade || '—' }</td>
										<td className="num">
											{ row.learners }
										</td>
										<td className="num">
											{ row.active_learners }
										</td>
										<td className="num">
											{ percent( row.completion ) }
										</td>
										<td className="num">
											{ sprintf(
												/* translators: 1: completed, 2: assigned. */
												__( '%1$d of %2$d', 'ohmylms' ),
												row.assigned_completed,
												row.assigned
											) }
										</td>
										<td className="num">
											{ row.teachers }
										</td>
									</tr>
								) ) }
							</tbody>
						</WidefatTable>
					) : (
						<p>{ __( 'No active classes.', 'ohmylms' ) }</p>
					) }
					{ data.more_classes > 0 && (
						<p>
							{ sprintf(
								/* translators: %d: number of classes not shown. */
								__(
									'%d more classes are counted in the whole-school figures.',
									'ohmylms'
								),
								data.more_classes
							) }
						</p>
					) }
					<h4>{ __( 'Mastery overview', 'ohmylms' ) }</h4>
					{ data.mastery.skills.length ? (
						<WidefatTable
							caption={ __( 'Mastery by skill', 'ohmylms' ) }
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Skill', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Learners assessed', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Average mastery', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Below 50%', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.mastery.skills.map( ( skill ) => (
									<tr key={ skill.id }>
										<th scope="row">
											{ skill.code
												? `${ skill.code } ${ skill.name }`
												: skill.name }
										</th>
										<td className="num">
											{ skill.assessed }
										</td>
										<td className="num">
											{ percent( skill.average ) }
										</td>
										<td className="num">{ skill.below }</td>
									</tr>
								) ) }
							</tbody>
						</WidefatTable>
					) : (
						<p>
							{ __(
								'No skill evidence for this school’s learners yet.',
								'ohmylms'
							) }
						</p>
					) }
				</>
			) }
		</section>
	);
}
