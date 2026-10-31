import {
	Button,
	Modal,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { normalizeApiError } from '../../lib/api';
import { changeCell, getCell, type CellChange } from './api';
import { formatMarks, formatPercent, kindLabel, stateLabel } from './model';
import type { CellDetail, CellState, HistoryEntry } from './types';
import { useLoad } from './useLoad';

function historyValue(
	value: { kind: string; score: number | null },
	max: number | null
) {
	if ( 'score' === value.kind ) {
		return sprintf(
			/* translators: 1: score, 2: maximum. */
			__( '%1$s / %2$s', 'ohmylms' ),
			formatMarks( value.score ),
			formatMarks( max )
		);
	}
	if ( 'excused' === value.kind ) {
		return __( 'Excused', 'ohmylms' );
	}
	return __( 'Computed', 'ohmylms' );
}

function actionLabel( action: HistoryEntry[ 'action' ] ) {
	switch ( action ) {
		case 'override':
			return __( 'Overrode', 'ohmylms' );
		case 'excuse':
			return __( 'Excused', 'ohmylms' );
		case 'revert':
			return __( 'Reverted', 'ohmylms' );
		case 'import':
			return __( 'Imported', 'ohmylms' );
	}
}

function valueText(
	state: CellState,
	score: number | null,
	max: number | null,
	percent: number | null
) {
	return 'score' === state
		? sprintf(
				/* translators: 1: score, 2: maximum, 3: percentage. */
				__( '%1$s / %2$s (%3$s)', 'ohmylms' ),
				formatMarks( score ),
				formatMarks( max ),
				formatPercent( percent )
			)
		: stateLabel( state );
}

/**
 * One cell: the computed value, the manual value with its reason, the learner's total, the history, and
 * the override / excuse / revert form. A change sends the version it read; when someone else changed the
 * cell meanwhile the server answers 409 and the dialog shows the current state instead.
 *
 * @param props         Props.
 * @param props.scope   Scope id.
 * @param props.course  Course.
 * @param props.item    Item.
 * @param props.learner Learner.
 * @param props.onClose Closes the dialog.
 * @param props.onSaved Called after a change.
 */
export function CellDialog( {
	scope,
	course,
	item,
	learner,
	onClose,
	onSaved,
}: {
	scope: string;
	course: number;
	item: number;
	learner: number;
	onClose: () => void;
	onSaved: () => void;
} ) {
	const loaded = useLoad< CellDetail >(
		`${ scope }|${ course }|${ item }|${ learner }`,
		( signal ) => getCell( scope, course, item, learner, signal )
	);
	const [ detail, setDetail ] = useState< CellDetail | null >( null );
	const [ score, setScore ] = useState( '' );
	const [ reason, setReason ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ conflict, setConflict ] = useState( false );
	const [ saved, setSaved ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		if ( loaded.data ) {
			setDetail( loaded.data );
			setScore(
				null === loaded.data.cell.score
					? ''
					: String( loaded.data.cell.score )
			);
		}
	}, [ loaded.data ] );

	async function apply( action: CellChange[ 'action' ] ) {
		if ( ! detail ) {
			return;
		}
		setError( '' );
		setSaved( '' );
		if ( 'revert' !== action && ! reason.trim() ) {
			setError( __( 'Give a reason for the change.', 'ohmylms' ) );
			return;
		}
		const number = Number( score );
		const max = detail.cell.computed.max ?? detail.item.max;
		if (
			'override' === action &&
			( '' === score.trim() ||
				! Number.isFinite( number ) ||
				number < 0 ||
				number > max )
		) {
			setError(
				sprintf(
					/* translators: %s: maximum score. */
					__( 'Enter a score from 0 to %s.', 'ohmylms' ),
					formatMarks( max )
				)
			);
			return;
		}
		setBusy( true );
		try {
			const next = await changeCell( scope, course, item, learner, {
				action,
				...( 'override' === action ? { score: number } : {} ),
				reason: reason.trim(),
				expected_version: detail.cell.version,
			} );
			setDetail( next );
			setConflict( false );
			setReason( '' );
			setSaved(
				'revert' === action
					? __( 'Reverted to the computed grade.', 'ohmylms' )
					: __( 'Saved. The total is recalculated.', 'ohmylms' )
			);
			onSaved();
		} catch ( caught ) {
			const apiError = normalizeApiError( caught );
			const current = (
				apiError.details as { current?: CellDetail } | undefined
			 )?.current;
			if ( 409 === apiError.status && current ) {
				setDetail( current );
				setConflict( true );
				onSaved();
			}
			setError( apiError.message );
		} finally {
			setBusy( false );
		}
	}

	const title = detail
		? sprintf(
				/* translators: 1: item title, 2: learner name. */
				__( '%1$s — %2$s', 'ohmylms' ),
				detail.item.title,
				detail.learner.name
			)
		: __( 'Grade', 'ohmylms' );
	const cell = detail?.cell;
	const notAssigned = 'not_assigned' === cell?.computed.state;

	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			className="ohmylms-gradebook-dialog"
			size="medium"
		>
			{ loaded.loading && ! detail && (
				<p role="status">{ __( 'Loading the grade…', 'ohmylms' ) }</p>
			) }
			{ loaded.error && ! detail && (
				<Notice status="error" isDismissible={ false }>
					{ loaded.error }
				</Notice>
			) }
			{ detail && cell && (
				<>
					<p className="ohmylms-gradebook-muted">
						{ [
							kindLabel( detail.item.kind ),
							detail.item.category,
							detail.item.module,
							sprintf(
								/* translators: %s: maximum score. */
								__( 'out of %s', 'ohmylms' ),
								formatMarks( detail.item.max )
							),
						]
							.filter( Boolean )
							.join( ' · ' ) }
					</p>
					{ conflict && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Someone changed this grade while you had it open. The current grade is shown below; check it before changing it again.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ error && ! conflict && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					{ saved && (
						<Notice status="success" isDismissible={ false }>
							{ saved }
						</Notice>
					) }
					<WidefatTable
						caption={ __( 'Grade summary', 'ohmylms' ) }
						striped={ false }
					>
						<tbody>
							<tr>
								<th scope="row">
									{ __( 'Computed', 'ohmylms' ) }
								</th>
								<td>
									{ valueText(
										cell.computed.state,
										cell.computed.score,
										cell.computed.max,
										cell.computed.percent
									) }
								</td>
							</tr>
							<tr>
								<th scope="row">
									{ __( 'In the gradebook', 'ohmylms' ) }
								</th>
								<td>
									{ valueText(
										cell.state,
										cell.score,
										cell.max,
										cell.percent
									) }
									{ cell.overridden && (
										<>
											{ ' ' }
											<strong>
												{ __(
													'(set manually)',
													'ohmylms'
												) }
											</strong>
											<br />
											{ sprintf(
												/* translators: 1: reason, 2: name, 3: date. */
												__(
													'Reason: %1$s — %2$s, %3$s',
													'ohmylms'
												),
												cell.reason,
												cell.updated_by?.name ?? '—',
												cell.updated_at
													? new Date(
															cell.updated_at
														).toLocaleString()
													: '—'
											) }
										</>
									) }
								</td>
							</tr>
							<tr>
								<th scope="row">
									{ __( 'Course total', 'ohmylms' ) }
								</th>
								<td>
									{ formatPercent( detail.total.percent ) }{ ' ' }
									{ detail.total.letter ?? '' }
									{ detail.total.percent !==
										detail.total.computed_percent && (
										<span className="ohmylms-gradebook-muted">
											{ ' ' }
											{ sprintf(
												/* translators: %s: total without manual grades. */
												__(
													'(computed without manual grades: %s)',
													'ohmylms'
												),
												formatPercent(
													detail.total
														.computed_percent
												)
											) }
										</span>
									) }
								</td>
							</tr>
						</tbody>
					</WidefatTable>
					{ notAssigned ? (
						<p>
							{ __(
								'This item is not assigned to this learner, so there is nothing to grade.',
								'ohmylms'
							) }
						</p>
					) : (
						<form
							className="ohmylms-gradebook-dialog__form"
							onSubmit={ ( event ) => {
								event.preventDefault();
								apply( 'override' );
							} }
						>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								type="number"
								label={ sprintf(
									/* translators: %s: maximum score. */
									__( 'Score (0 to %s)', 'ohmylms' ),
									formatMarks(
										cell.computed.max ?? detail.item.max
									)
								) }
								min={ 0 }
								max={ cell.computed.max ?? detail.item.max }
								step="any"
								value={ score }
								onChange={ setScore }
							/>
							<TextareaControl
								__nextHasNoMarginBottom
								label={ __(
									'Reason (required, kept in the history)',
									'ohmylms'
								) }
								value={ reason }
								maxLength={ 500 }
								rows={ 2 }
								onChange={ setReason }
							/>
							<div className="ohmylms-gradebook-dialog__buttons">
								<Button
									variant="primary"
									type="submit"
									isBusy={ busy }
									disabled={ busy }
								>
									{ __( 'Save grade', 'ohmylms' ) }
								</Button>
								<Button
									variant="secondary"
									disabled={ busy }
									onClick={ () => apply( 'excuse' ) }
								>
									{ __( 'Excuse', 'ohmylms' ) }
								</Button>
								{ cell.overridden && (
									<Button
										variant="secondary"
										disabled={ busy }
										onClick={ () => apply( 'revert' ) }
									>
										{ __(
											'Revert to computed',
											'ohmylms'
										) }
									</Button>
								) }
							</div>
						</form>
					) }
					<h3>{ __( 'History', 'ohmylms' ) }</h3>
					{ detail.history.length ? (
						<WidefatTable
							caption={ __(
								'Changes to this grade, newest first',
								'ohmylms'
							) }
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'When', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Who', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Change', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Reason', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ detail.history.map( ( entry ) => (
									<tr key={ entry.id }>
										<td>
											{ entry.at
												? new Date(
														entry.at
													).toLocaleString()
												: '—' }
										</td>
										<td>{ entry.by?.name ?? '—' }</td>
										<td>
											{ actionLabel( entry.action ) }:{ ' ' }
											{ historyValue(
												entry.old,
												entry.max
											) }{ ' ' }
											→{ ' ' }
											{ historyValue(
												entry.new,
												entry.max
											) }
										</td>
										<td>{ entry.reason }</td>
									</tr>
								) ) }
							</tbody>
						</WidefatTable>
					) : (
						<p className="ohmylms-gradebook-muted">
							{ __( 'No manual changes yet.', 'ohmylms' ) }
						</p>
					) }
				</>
			) }
		</Modal>
	);
}
