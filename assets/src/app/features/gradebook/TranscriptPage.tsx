import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { WidefatTable } from '../../components/WidefatTable';
import { findLearners, getTranscript } from './api';
import { CellDialog } from './CellDialog';
import { transcriptCsv } from './exports';
import {
	cellDescription,
	cellText,
	cellTone,
	downloadCsv,
	formatPercent,
	kindLabel,
	weightsText,
} from './model';
import {
	LoadState,
	RosterChips,
	ScopeSelect,
	SourceBadge,
	useGradebookContext,
	useGradebookQuery,
	ViewActions,
} from './shared';
import type { Transcript } from './types';
import { useLoad } from './useLoad';

/**
 * Student transcript: every enrolment of a learner in the scope's courses (source, rosters, weights,
 * total and letter, each item) and their skill mastery, which follows the learner across courses.
 */
export default function TranscriptPage() {
	const { query, update, linkTo, search } = useGradebookQuery();
	const context = useGradebookContext( query.scope );
	const [ text, setText ] = useState( '' );
	const [ term, setTerm ] = useState( '' );
	const learners = useLoad(
		context.data ? `learners|${ query.scope }|${ term }` : null,
		( signal ) => findLearners( query.scope, term, signal )
	);
	const transcript = useLoad< Transcript >(
		query.learner && context.data
			? `transcript|${ query.scope }|${ query.learner }`
			: null,
		( signal ) => getTranscript( query.scope, query.learner, signal )
	);
	const [ open, setOpen ] = useState< {
		course: number;
		item: number;
	} | null >( null );
	const data = transcript.data;

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
									update( { scope, learner: 0 } )
								}
							/>
							<form
								className="ohmylms-gradebook-inline"
								role="search"
								onSubmit={ ( event ) => {
									event.preventDefault();
									setTerm( text.trim() );
								} }
							>
								<label
									htmlFor="ohmylms-gradebook-learner-search"
									className="screen-reader-text"
								>
									{ __( 'Find a learner', 'ohmylms' ) }
								</label>
								<input
									id="ohmylms-gradebook-learner-search"
									type="search"
									value={ text }
									maxLength={ 100 }
									placeholder={ __(
										'Find a learner',
										'ohmylms'
									) }
									onChange={ ( event ) =>
										setText( event.target.value )
									}
								/>
								<Button
									variant="secondary"
									size="compact"
									type="submit"
								>
									{ __( 'Find', 'ohmylms' ) }
								</Button>
							</form>
							<label
								htmlFor="ohmylms-gradebook-learner"
								className="screen-reader-text"
							>
								{ __( 'Learner', 'ohmylms' ) }
							</label>
							<select
								id="ohmylms-gradebook-learner"
								value={ query.learner }
								onChange={ ( event ) =>
									update( {
										learner: Number( event.target.value ),
									} )
								}
							>
								<option value={ 0 }>
									{ __( 'Choose a learner', 'ohmylms' ) }
								</option>
								{ data &&
									! ( learners.data ?? [] ).some(
										( item ) => item.id === data.learner.id
									) && (
										<option value={ data.learner.id }>
											{ data.learner.name }
										</option>
									) }
								{ ( learners.data ?? [] ).map( ( item ) => (
									<option key={ item.id } value={ item.id }>
										{ item.name }
									</option>
								) ) }
							</select>
						</div>
						<br className="clear" />
					</div>
					<ViewActions
						section="student"
						search={ search }
						onExport={
							data
								? () =>
										downloadCsv(
											`transcript-${ data.learner.name }`,
											transcriptCsv( data )
										)
								: null
						}
					/>
				</>
			) }
			<LoadState
				loading={ transcript.loading && ! data }
				error={ transcript.error }
				retry={ transcript.reload }
				label={ __( 'Loading the transcript…', 'ohmylms' ) }
			/>
			{ ! query.learner && context.data && (
				<p>
					{ __(
						'Choose a learner to see their transcript.',
						'ohmylms'
					) }
				</p>
			) }
			{ data && (
				<div className="postbox ohmylms-gradebook-transcript">
					<div className="postbox-header">
						<h2 className="hndle">
							{ sprintf(
								/* translators: %s: learner name. */
								__( '%s — transcript', 'ohmylms' ),
								data.learner.name
							) }
						</h2>
					</div>
					<div className="inside">
						{ ! data.enrolments.length && (
							<p>
								{ __(
									'No enrolments in this school or class.',
									'ohmylms'
								) }
							</p>
						) }
						{ data.enrolments.map( ( enrolment ) => (
							<section
								key={ enrolment.course.id }
								aria-labelledby={ `ohmylms-transcript-${ enrolment.course.id }` }
							>
								<h3
									id={ `ohmylms-transcript-${ enrolment.course.id }` }
								>
									<Link
										to={ linkTo( 'course', {
											course: enrolment.course.id,
										} ) }
									>
										{ enrolment.course.title }
									</Link>{ ' ' }
									{ enrolment.row.sources.map( ( source ) => (
										<SourceBadge
											key={ source }
											source={ source }
										/>
									) ) }{ ' ' }
									<RosterChips
										rosters={ enrolment.row.rosters }
										linkTo={ linkTo }
									/>
									{ ' — ' }
									<strong>
										{ formatPercent(
											enrolment.row.total.percent
										) }{ ' ' }
										{ enrolment.row.total.letter ?? '' }
									</strong>
								</h3>
								<p className="ohmylms-gradebook-muted">
									{ 0 !== enrolment.row.total.weights_class
										? sprintf(
												/* translators: %s: weights such as "homework 30% · quiz 70%". */
												__(
													'Weights (class override): %s',
													'ohmylms'
												),
												weightsText( enrolment.weights )
											)
										: sprintf(
												/* translators: %s: weights such as "homework 30% · quiz 70%". */
												__(
													'Weights (course): %s',
													'ohmylms'
												),
												weightsText( enrolment.weights )
											) }
									{ enrolment.row.total.percent !==
										enrolment.row.total.computed_percent &&
										` · ${ sprintf(
											/* translators: %s: total without manual grades. */
											__(
												'computed without manual grades: %s',
												'ohmylms'
											),
											formatPercent(
												enrolment.row.total
													.computed_percent
											)
										) }` }
								</p>
								<WidefatTable
									caption={ sprintf(
										/* translators: %s: course title. */ __(
											'Items of %s',
											'ohmylms'
										),
										enrolment.course.title
									) }
								>
									<thead>
										<tr>
											<th scope="col">
												{ __( 'Item', 'ohmylms' ) }
											</th>
											<th scope="col">
												{ __( 'Type', 'ohmylms' ) }
											</th>
											<th scope="col">
												{ __( 'Module', 'ohmylms' ) }
											</th>
											<th scope="col">
												{ __( 'Result', 'ohmylms' ) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ enrolment.items.map( ( item ) => {
											const cell =
												enrolment.row.cells[
													String( item.id )
												];
											const gradable =
												cell &&
												'not_assigned' !== cell.state;
											return (
												<tr key={ item.id }>
													<th scope="row">
														{ item.title }
													</th>
													<td>
														{ kindLabel(
															item.kind
														) }{ ' ' }
														· { item.category }
													</td>
													<td>{ item.module }</td>
													<td>
														<span
															className={ `ohmylms-gradebook-cell is-${ cellTone( cell ) }${ cell?.overridden ? ' is-overridden' : '' }` }
														>
															{ gradable ? (
																<Button
																	variant="link"
																	onClick={ () =>
																		setOpen(
																			{
																				course: enrolment
																					.course
																					.id,
																				item: item.id,
																			}
																		)
																	}
																	aria-label={ `${ item.title }: ${ cellDescription( cell ) }` }
																>
																	{ cellText(
																		cell
																	) }
																</Button>
															) : (
																<span
																	aria-label={ cellDescription(
																		cell
																	) }
																>
																	{ cellText(
																		cell
																	) }
																</span>
															) }
														</span>
													</td>
												</tr>
											);
										} ) }
									</tbody>
								</WidefatTable>
							</section>
						) ) }
						<h3>
							{ __(
								'Skill mastery (follows the learner across courses)',
								'ohmylms'
							) }
						</h3>
						{ data.mastery.skills.length ? (
							<ul className="ohmylms-gradebook-skills">
								{ data.mastery.skills.map( ( skill ) => (
									<li key={ skill.id }>
										<span className="ohmylms-gradebook-skills__name">
											{ skill.name }
										</span>
										<span
											className="ohmylms-gradebook-bar"
											role="img"
											aria-label={ `${ formatPercent( skill.percent ) }` }
										>
											<span
												className={ `ohmylms-gradebook-bar__fill is-${ skill.level }` }
												style={ {
													width: `${ Math.max( 0, Math.min( 100, skill.percent ) ) }%`,
												} }
											/>
										</span>
										<span>
											{ data.mastery.levels.find(
												( level ) =>
													level.id === skill.level
											)?.label ?? skill.level }{ ' ' }
											· { formatPercent( skill.percent ) }
										</span>
									</li>
								) ) }
							</ul>
						) : (
							<p className="ohmylms-gradebook-muted">
								{ __( 'No skill evidence yet.', 'ohmylms' ) }
							</p>
						) }
					</div>
				</div>
			) }
			{ open && data && (
				<CellDialog
					scope={ query.scope }
					course={ open.course }
					item={ open.item }
					learner={ data.learner.id }
					onClose={ () => setOpen( null ) }
					onSaved={ transcript.reload }
				/>
			) }
		</div>
	);
}
