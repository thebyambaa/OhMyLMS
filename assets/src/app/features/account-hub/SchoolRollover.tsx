import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { WidefatTable } from '../../components/WidefatTable';
import { useOperation } from '../../hooks/useOperation';
import { applyRollover, previewRollover } from './operationsApi';
import { gradeMapError, suggestGradeMap } from './operationsModel';
import type {
	GradeMapping,
	RolloverClass,
	RolloverInput,
	RolloverPlan,
	RolloverResult,
	RolloverTotals,
	SchoolDetail,
} from './types';

/**
 * What a rollover does, as sentences.
 *
 * @param totals Plan or result totals.
 * @return Lines.
 */
export function rolloverLines( totals: RolloverTotals ): string[] {
	const skipped = totals.classes_skipped
		? [
				sprintf(
					/* translators: %d: number of classes. */
					_n(
						'%d class without students is skipped: it is archived and no new class is created.',
						'%d classes without students are skipped: they are archived and no new classes are created.',
						totals.classes_skipped,
						'ohmylms'
					),
					totals.classes_skipped
				),
			]
		: [];
	return [
		sprintf(
			/* translators: 1: classes, 2: new classes. */
			__(
				'%1$d classes are archived; %2$d new classes are created in the new year.',
				'ohmylms'
			),
			totals.classes,
			totals.classes_created
		),
		...skipped,
		sprintf(
			/* translators: %d: number of students. */
			_n(
				'%d student moves to the next class.',
				'%d students move to the next classes.',
				totals.students_promoted,
				'ohmylms'
			),
			totals.students_promoted
		),
		sprintf(
			/* translators: %d: number of students. */
			_n(
				'%d student leaves with a graduating class (account and school membership stay).',
				'%d students leave with graduating classes (accounts and school memberships stay).',
				totals.students_leaving,
				'ohmylms'
			),
			totals.students_leaving
		),
		sprintf(
			/* translators: 1: enrolments moved, 2: enrolments cancelled. */
			__(
				'Roster enrolments: %1$d move to the new classes, %2$d of leaving students are cancelled.',
				'ohmylms'
			),
			totals.enrolments_moved,
			totals.enrolments_revoked
		),
		sprintf(
			/* translators: %d: number of teachers. */
			_n(
				'%d teacher joins a new class.',
				'%d teachers join the new classes.',
				totals.teachers_copied,
				'ohmylms'
			),
			totals.teachers_copied
		),
	];
}

/**
 * What a class becomes in the new year, for the preview table.
 *
 * @param row Planned class.
 * @return Text.
 */
function becomes( row: RolloverClass ): string {
	if ( row.target ) {
		return (
			sprintf(
				/* translators: 1: class name, 2: grade. */
				__( '%1$s (grade %2$s)', 'ohmylms' ),
				row.target.name,
				row.target.grade || '—'
			) +
			( row.target.exists
				? ' ' + __( '— already created', 'ohmylms' )
				: '' )
		);
	}
	if ( 'skip' === row.action ) {
		return __( 'Skipped: no students (archived, no new class)', 'ohmylms' );
	}
	return __( 'Leaves (archived, no new class)', 'ohmylms' );
}

/**
 * Year rollover: choose the years and what each grade becomes, preview, then apply once. The preview
 * writes nothing; applying the same preview twice changes nothing more.
 *
 * @param props          Component props.
 * @param props.school   The school.
 * @param props.onChange Called after the rollover was applied (years and classes changed).
 */
export function SchoolRollover( {
	school,
	onChange,
}: {
	school: SchoolDetail;
	onChange: () => void;
} ) {
	const years = school.years.filter( ( year ) => 'active' === year.status );
	const [ chosenFrom, setFromId ] = useState(
		years[ years.length - 1 ]?.id ?? 0
	);
	const fromId = years.some( ( year ) => year.id === chosenFrom )
		? chosenFrom
		: ( years[ years.length - 1 ]?.id ?? 0 );
	const firstTo = years.find( ( year ) => year.id !== fromId )?.id ?? 0;
	const [ toId, setToId ] = useState( firstTo );
	const [ map, setMap ] = useState< GradeMapping[] | null >( null );
	const [ teachers, setTeachers ] = useState( false );
	const [ includeEmpty, setIncludeEmpty ] = useState( false );
	const [ plan, setPlan ] = useState< RolloverPlan | null >( null );
	const [ input, setInput ] = useState< RolloverInput | null >( null );
	const [ result, setResult ] = useState< RolloverResult | null >( null );
	const [ confirming, setConfirming ] = useState( false );
	const [ error, setError ] = useState( '' );
	const preview = useOperation();
	const apply = useOperation();
	const archived = 'archived' === school.status;
	const target = toId && toId !== fromId ? toId : firstTo;

	function reset() {
		setPlan( null );
		setResult( null );
	}

	function run() {
		const found = map ? gradeMapError( map ) : '';
		setError( found );
		if ( found || ! fromId || ! target ) {
			return;
		}
		const next: RolloverInput = {
			from_year_id: fromId,
			to_year_id: target,
			grade_map: map ?? [],
			copy_teachers: teachers,
			include_empty: includeEmpty,
		};
		void preview.run(
			async ( signal ) => {
				const first = await previewRollover( school.id, next, signal );
				if ( map ) {
					return { plan: first, input: next };
				}
				const suggested = suggestGradeMap(
					first.classes.map( ( row ) => row.grade )
				);
				const again = { ...next, grade_map: suggested };
				return {
					plan: await previewRollover( school.id, again, signal ),
					input: again,
				};
			},
			( previewed ) => {
				setMap( previewed.input.grade_map );
				setInput( previewed.input );
				setPlan( previewed.plan );
				setResult( null );
			}
		);
	}

	const done = result && (
		<Notice status="success" onRemove={ () => setResult( null ) }>
			<p>
				{ result.already_applied
					? __(
							'This rollover had already been applied; nothing changed.',
							'ohmylms'
						)
					: sprintf(
							/* translators: 1: old year, 2: new year. */
							__( 'Rolled over from %1$s to %2$s.', 'ohmylms' ),
							result.from.label,
							result.to.label
						) }
			</p>
			<ul className="ul-disc">
				{ rolloverLines( result.totals ).map( ( line ) => (
					<li key={ line }>{ line }</li>
				) ) }
			</ul>
		</Notice>
	);

	if ( archived ) {
		return (
			<p>
				{ __(
					'This school is archived. Restore it to roll over a year.',
					'ohmylms'
				) }
			</p>
		);
	}
	if ( years.length < 2 ) {
		return (
			<section aria-label={ __( 'Year rollover', 'ohmylms' ) }>
				<h3>{ __( 'Year rollover', 'ohmylms' ) }</h3>
				{ done }
				<p>
					{ __(
						'Rolling over needs two active academic years: the current one and the next. Add the next year first.',
						'ohmylms'
					) }{ ' ' }
					<Link to={ `/account-hub/schools/${ school.id }` }>
						{ __( 'Add an academic year', 'ohmylms' ) }
					</Link>
				</p>
			</section>
		);
	}

	return (
		<section aria-label={ __( 'Year rollover', 'ohmylms' ) }>
			<h3>{ __( 'Year rollover', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Moves the active classes of one year to the next: each class is archived and, unless its grade leaves, a next-year class is created and its students join it. Course enrolments that a class roster granted follow the students. Preview first; nothing changes until you apply.',
					'ohmylms'
				) }
			</p>
			{ done }
			{ ( error || preview.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || preview.error }
				</Notice>
			) }
			<form
				noValidate
				aria-label={ __( 'Rollover settings', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					run();
				} }
			>
				<SelectControl
					label={ __( 'Move classes of', 'ohmylms' ) }
					value={ String( fromId ) }
					options={ years.map( ( year ) => ( {
						value: String( year.id ),
						label: year.label,
					} ) ) }
					onChange={ ( value ) => {
						setFromId( Number( value ) );
						setMap( null );
						reset();
					} }
				/>
				<SelectControl
					label={ __( 'To the year', 'ohmylms' ) }
					value={ String( target ) }
					options={ years
						.filter( ( year ) => year.id !== fromId )
						.map( ( year ) => ( {
							value: String( year.id ),
							label: year.label,
						} ) ) }
					onChange={ ( value ) => {
						setToId( Number( value ) );
						reset();
					} }
				/>
				{ map && (
					<fieldset>
						<legend>{ __( 'Grades', 'ohmylms' ) }</legend>
						<p>
							{ __(
								'What each grade becomes. Leave the new grade empty for a class that leaves (graduates).',
								'ohmylms'
							) }
						</p>
						{ map.map( ( row, index ) => (
							<TextControl
								key={ row.from }
								label={ sprintf(
									/* translators: %s: grade. */
									__( 'Grade %s becomes', 'ohmylms' ),
									row.from
								) }
								value={ row.to }
								maxLength={ 50 }
								onChange={ ( value ) => {
									setMap(
										map.map( ( item, at ) =>
											at === index
												? { ...item, to: value }
												: item
										)
									);
									reset();
								} }
							/>
						) ) }
					</fieldset>
				) }
				<CheckboxControl
					label={ __(
						'Also add each class’s teachers to its new class',
						'ohmylms'
					) }
					checked={ teachers }
					onChange={ ( value ) => {
						setTeachers( value );
						reset();
					} }
				/>
				<CheckboxControl
					label={ __(
						'Also create next-year classes for classes without students',
						'ohmylms'
					) }
					help={ __(
						'When off, a class with no students is archived and not copied.',
						'ohmylms'
					) }
					checked={ includeEmpty }
					onChange={ ( value ) => {
						setIncludeEmpty( value );
						reset();
					} }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ preview.busy }
					isBusy={ preview.busy }
				>
					{ plan
						? __( 'Update preview', 'ohmylms' )
						: __( 'Preview rollover', 'ohmylms' ) }
				</Button>
			</form>
			{ plan && input && (
				<>
					<h4>{ __( 'Preview', 'ohmylms' ) }</h4>
					<ul className="ul-disc">
						{ rolloverLines( plan.totals ).map( ( line ) => (
							<li key={ line }>{ line }</li>
						) ) }
					</ul>
					{ plan.classes.length ? (
						<WidefatTable
							caption={ __( 'Rollover preview', 'ohmylms' ) }
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Class', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Grade', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Becomes', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Students', 'ohmylms' ) }
									</th>
									<th scope="col" className="num">
										{ __( 'Roster enrolments', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ plan.classes.map( ( row ) => (
									<tr key={ row.class_id }>
										<th scope="row">{ row.name }</th>
										<td>{ row.grade || '—' }</td>
										<td>{ becomes( row ) }</td>
										<td className="num">
											{ row.students }
										</td>
										<td className="num">
											{ row.enrolments }
										</td>
									</tr>
								) ) }
							</tbody>
						</WidefatTable>
					) : (
						<p>
							{ __(
								'This year has no active classes. Applying archives the year only.',
								'ohmylms'
							) }
						</p>
					) }
					<p>
						<Button
							variant="primary"
							onClick={ () => {
								apply.clearError();
								setConfirming( true );
							} }
						>
							{ __( 'Apply rollover…', 'ohmylms' ) }
						</Button>
					</p>
				</>
			) }
			{ confirming && plan && input && (
				<Modal
					title={ sprintf(
						/* translators: 1: old year, 2: new year. */
						__( 'Roll over %1$s to %2$s?', 'ohmylms' ),
						plan.from.label,
						plan.to.label
					) }
					onRequestClose={ () =>
						! apply.busy && setConfirming( false )
					}
				>
					<ul className="ul-disc">
						{ rolloverLines( plan.totals ).map( ( line ) => (
							<li key={ line }>{ line }</li>
						) ) }
					</ul>
					<p>
						{ sprintf(
							/* translators: %s: year label. */
							__(
								'%s is archived. Archived classes keep their rosters and history.',
								'ohmylms'
							),
							plan.from.label
						) }
					</p>
					{ apply.error && (
						<Notice status="error" isDismissible={ false }>
							{ apply.error }
						</Notice>
					) }
					<Button
						variant="primary"
						disabled={ apply.busy }
						isBusy={ apply.busy }
						onClick={ () =>
							void apply.run(
								( signal ) =>
									applyRollover(
										school.id,
										input,
										plan.review,
										signal
									),
								( applied ) => {
									setConfirming( false );
									setPlan( null );
									setMap( null );
									setResult( applied );
									onChange();
								}
							)
						}
					>
						{ __( 'Apply rollover', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ apply.busy }
						onClick={ () => setConfirming( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
