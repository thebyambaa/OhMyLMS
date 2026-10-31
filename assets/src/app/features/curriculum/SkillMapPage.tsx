import { Button, Notice, SelectControl } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useListQuery } from '../../components/listQuery';
import { WidefatTable } from '../../components/WidefatTable';
import { useRequest } from '../../hooks/useRequest';
import { normalizeApiError } from '../../lib/api';
import {
	cells,
	readMap,
	readSubjects,
	type MapIssue,
	type MapSkill,
	type SkillMap,
} from './skill-map';

function issueText( issue: MapIssue, names: Map< number, string > ) {
	const skill = names.get( issue.skill ) ?? `#${ issue.skill }`;
	const other = names.get( issue.other ) ?? `#${ issue.other }`;
	if ( issue.type === 'later_prerequisite' ) {
		return sprintf(
			/* translators: 1: skill name, 2: prerequisite skill name. */
			__( '%1$s needs %2$s, which is taught later.', 'ohmylms' ),
			skill,
			other
		);
	}
	if ( issue.type === 'long_gap' ) {
		return sprintf(
			/* translators: 1: skill name, 2: prerequisite skill name. */
			__(
				'%1$s builds on %2$s, which ends four or more levels earlier.',
				'ohmylms'
			),
			skill,
			other
		);
	}
	return sprintf(
		/* translators: %s: skill name. */
		__(
			'%s has no prerequisites although it is not in the first level of its domain.',
			'ohmylms'
		),
		skill
	);
}

const MAP_QUERY = { subject: '', syllabus: '0' };

/**
 * Read-only coherence map of a subject's shared skills, optionally with a syllabus's coverage. The
 * shown subject and syllabus are in the address (`&subject=…&syllabus=…`).
 */
export default function SkillMapPage() {
	const loadSubjects = useCallback(
		( signal: AbortSignal ) => readSubjects( signal ),
		[]
	);
	const subjects = useRequest( loadSubjects );
	const query = useListQuery( MAP_QUERY );
	const chosen = query.values.subject;
	const chosenSyllabus = Number( query.values.syllabus ) || 0;
	const [ subject, setSubject ] = useState( chosen );
	const [ syllabus, setSyllabus ] = useState( chosenSyllabus );
	useEffect( () => {
		setSubject( chosen );
		setSyllabus( chosenSyllabus );
	}, [ chosen, chosenSyllabus ] );
	const [ version, setVersion ] = useState( 0 );
	const shown = useMemo(
		() =>
			chosen
				? { subject: chosen, syllabus: chosenSyllabus, version }
				: null,
		[ chosen, chosenSyllabus, version ]
	);
	const [ map, setMap ] = useState< SkillMap | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ selected, setSelected ] = useState< MapSkill | null >( null );
	useEffect( () => {
		if ( ! shown ) {
			return;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( '' );
		readMap( shown.subject, shown.syllabus, controller.signal ).then(
			( result ) => {
				if ( ! controller.signal.aborted ) {
					setMap( result );
					setSelected( null );
					setLoading( false );
				}
			},
			( failure ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ shown ] );
	const options = subjects.data ?? [];
	const current = options.find( ( row ) => row.subject === subject );
	const names = new Map( map?.skills.map( ( row ) => [ row.id, row.name ] ) );
	const covered = new Set( map?.syllabus?.covered ?? [] );
	const grid = map ? cells( map ) : new Map< string, MapSkill[] >();
	const label = ( skill: MapSkill ) =>
		map?.syllabus && covered.has( skill.id )
			? sprintf(
					/* translators: %s: skill name. */
					__( '%s (taught by the syllabus)', 'ohmylms' ),
					skill.name
				)
			: skill.name;
	return (
		<section>
			<p>
				{ __(
					'See how a subject’s shared skills fit together across domains and levels, and which of them a syllabus teaches. The map is read-only; place skills and edit links in the curriculum workspace.',
					'ohmylms'
				) }
			</p>
			{ subjects.loading && (
				<p role="status">{ __( 'Loading subjects…', 'ohmylms' ) }</p>
			) }
			{ subjects.error && (
				<Notice status="error" isDismissible={ false }>
					{ subjects.error }{ ' ' }
					<Button variant="secondary" onClick={ subjects.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ subjects.data && ! options.length && (
				<p>
					{ __(
						'No subject has shared skills or syllabuses yet.',
						'ohmylms'
					) }
				</p>
			) }
			{ options.length > 0 && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! subject ) {
							return;
						}
						if (
							subject === chosen &&
							syllabus === chosenSyllabus
						) {
							setVersion( ( shownVersion ) => shownVersion + 1 );
						} else {
							query.set( {
								subject,
								syllabus: String( syllabus ),
							} );
						}
					} }
				>
					<SelectControl
						label={ __( 'Subject', 'ohmylms' ) }
						value={ subject }
						options={ [
							{
								value: '',
								label: __( 'Choose a subject', 'ohmylms' ),
							},
							...options.map( ( row ) => ( {
								value: row.subject,
								label: sprintf(
									/* translators: 1: subject, 2: number of skills. */
									__( '%1$s (%2$d skills)', 'ohmylms' ),
									row.subject,
									row.skills
								),
							} ) ),
						] }
						onChange={ ( value ) => {
							setSubject( value );
							setSyllabus( 0 );
						} }
					/>
					<SelectControl
						label={ __( 'Syllabus coverage', 'ohmylms' ) }
						value={ String( syllabus ) }
						disabled={ ! current?.syllabuses.length }
						options={ [
							{ value: '0', label: __( 'None', 'ohmylms' ) },
							...( current?.syllabuses ?? [] ).map( ( row ) => ( {
								value: String( row.id ),
								label: row.code
									? `${ row.name } (${ row.code })`
									: row.name,
							} ) ),
						] }
						onChange={ ( value ) => setSyllabus( Number( value ) ) }
					/>
					<Button
						type="submit"
						variant="primary"
						disabled={ ! subject || loading }
						isBusy={ loading }
					>
						{ __( 'Show map', 'ohmylms' ) }
					</Button>
				</form>
			) }
			<div aria-live="polite">
				{ loading && __( 'Loading the skill map…', 'ohmylms' ) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ map && ! loading && (
				<>
					<h2>{ map.subject }</h2>
					{ map.syllabus && (
						<p>
							{ sprintf(
								/* translators: 1: syllabus name, 2: objectives, 3: unmapped objectives. */
								__(
									'%1$s: %2$d objectives, %3$d not mapped to a shared skill.',
									'ohmylms'
								),
								map.syllabus.name,
								map.syllabus.objectives,
								map.syllabus.unmapped
							) }
						</p>
					) }
					{ map.domains.length && map.grades.length ? (
						<WidefatTable
							caption={ __(
								'Shared skills by domain and starting level',
								'ohmylms'
							) }
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Domain', 'ohmylms' ) }
									</th>
									{ map.grades.map( ( grade ) => (
										<th scope="col" key={ grade.value }>
											{ grade.label }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ map.domains.map( ( domain ) => (
									<tr key={ domain.id }>
										<th scope="row">{ domain.label }</th>
										{ map.grades.map( ( grade ) => (
											<td key={ grade.value }>
												{ (
													grid.get(
														`${ domain.id }:${ grade.value }`
													) ?? []
												).map( ( skill ) => (
													<Button
														key={ skill.id }
														variant="link"
														aria-pressed={
															selected?.id ===
															skill.id
														}
														onClick={ () =>
															setSelected( skill )
														}
													>
														{ label( skill ) }
													</Button>
												) ) }
											</td>
										) ) }
									</tr>
								) ) }
							</tbody>
						</WidefatTable>
					) : (
						<p>
							{ __(
								'No skill of this subject has a domain and level yet.',
								'ohmylms'
							) }
						</p>
					) }
					{ selected && (
						<section
							aria-label={ __( 'Selected skill', 'ohmylms' ) }
						>
							<h3>{ selected.name }</h3>
							<p>
								{ sprintf(
									/* translators: 1: code, 2: first level, 3: last level. */
									__(
										'Code: %1$s. Levels %2$d to %3$d.',
										'ohmylms'
									),
									selected.code || '—',
									selected.grade_from,
									selected.grade_to
								) }
							</p>
							<p>
								{ __( 'Builds on:', 'ohmylms' ) }{ ' ' }
								{ selected.prerequisites
									.map(
										( id ) =>
											names.get( id ) ??
											__(
												'a skill of another subject',
												'ohmylms'
											)
									)
									.join( ', ' ) ||
									__( 'nothing', 'ohmylms' ) }
							</p>
							<p>
								{ __( 'Needed by:', 'ohmylms' ) }{ ' ' }
								{ map.skills
									.filter( ( skill ) =>
										skill.prerequisites.includes(
											selected.id
										)
									)
									.map( ( skill ) => skill.name )
									.join( ', ' ) ||
									__( 'nothing', 'ohmylms' ) }
							</p>
						</section>
					) }
					{ map.unplaced.length > 0 && (
						<>
							<h3>{ __( 'Not placed yet', 'ohmylms' ) }</h3>
							<ul>
								{ map.unplaced.map( ( id ) => (
									<li key={ id }>
										{ names.get( id ) ?? `#${ id }` }
									</li>
								) ) }
							</ul>
						</>
					) }
					<h3>{ __( 'Link checks', 'ohmylms' ) }</h3>
					{ map.issues.length ? (
						<ul>
							{ map.issues.map( ( issue ) => (
								<li
									key={ `${ issue.type }:${ issue.skill }:${ issue.other }` }
								>
									{ issueText( issue, names ) }
								</li>
							) ) }
						</ul>
					) : (
						<p>
							{ __(
								'No problems found in the links.',
								'ohmylms'
							) }
						</p>
					) }
				</>
			) }
		</section>
	);
}
