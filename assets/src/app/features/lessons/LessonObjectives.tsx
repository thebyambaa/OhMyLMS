import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { normalizeApiError } from '../../lib/api';
import {
	listSyllabuses,
	readObjectives,
	saveObjectives,
	searchObjectives,
	searchSkills,
} from './api';
import {
	RELATIONS,
	type LessonObjectives as Objectives,
	type ObjectiveMapping,
	type ObjectiveRef,
	type Relation,
} from './model';

const RELATION_LABELS: Record< Relation, () => string > = {
	teaches: () => __( 'Teaches', 'ohmylms' ),
	practices: () => __( 'Practises', 'ohmylms' ),
	assesses: () => __( 'Assesses', 'ohmylms' ),
};

const key = ( row: { syllabus_id: number; term_id: number } ) =>
	`${ row.syllabus_id }:${ row.term_id }`;

function label( row: ObjectiveRef ): string {
	return row.code ? `${ row.code } · ${ row.name }` : row.name;
}

function SkillSearch( {
	lessonId,
	search,
	exclude,
	onPick,
}: {
	lessonId: number;
	search: string;
	exclude: number[];
	onPick: ( skill: { id: number; name: string; code: string } ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => searchSkills( lessonId, search, signal ),
		[ lessonId, search ]
	);
	const request = useRequest( load );
	if ( request.loading ) {
		return <p role="status">{ __( 'Searching…', 'ohmylms' ) }</p>;
	}
	if ( ! request.data ) {
		return <p role="alert">{ request.error }</p>;
	}
	const items = request.data.items.filter(
		( item ) => ! exclude.includes( item.id )
	);
	return items.length ? (
		<ul aria-label={ __( 'Matching skills', 'ohmylms' ) }>
			{ items.map( ( item ) => (
				<li key={ item.id }>
					<Button variant="link" onClick={ () => onPick( item ) }>
						{ sprintf(
							/* translators: %s: skill name. */
							__( 'Link skill %s', 'ohmylms' ),
							item.code
								? `${ item.code } · ${ item.name }`
								: item.name
						) }
					</Button>
				</li>
			) ) }
		</ul>
	) : (
		<p>{ __( 'No other skill matches.', 'ohmylms' ) }</p>
	);
}

function ObjectiveSearch( {
	lessonId,
	syllabusId,
	search,
	exclude,
	onPick,
}: {
	lessonId: number;
	syllabusId: number;
	search: string;
	exclude: string[];
	onPick: ( objective: ObjectiveRef ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) =>
			searchObjectives( lessonId, syllabusId, search, signal ),
		[ lessonId, syllabusId, search ]
	);
	const request = useRequest( load );
	if ( request.loading ) {
		return <p role="status">{ __( 'Searching…', 'ohmylms' ) }</p>;
	}
	if ( ! request.data ) {
		return <p role="alert">{ request.error }</p>;
	}
	const items = request.data.items.filter(
		( item ) => ! exclude.includes( key( item ) )
	);
	return items.length ? (
		<ul aria-label={ __( 'Matching objectives', 'ohmylms' ) }>
			{ items.map( ( item ) => (
				<li key={ key( item ) }>
					<Button variant="link" onClick={ () => onPick( item ) }>
						{ sprintf(
							/* translators: %s: objective. */
							__( 'Add objective %s', 'ohmylms' ),
							label( item )
						) }
					</Button>
					{ item.objective && <> — { item.objective }</> }
				</li>
			) ) }
		</ul>
	) : (
		<p>{ __( 'No other objective matches.', 'ohmylms' ) }</p>
	);
}

function SyllabusBrowser( {
	lessonId,
	exclude,
	onPick,
}: {
	lessonId: number;
	exclude: string[];
	onPick: ( objective: ObjectiveRef, syllabusName: string ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => listSyllabuses( lessonId, signal ),
		[ lessonId ]
	);
	const request = useRequest( load );
	const [ syllabus, setSyllabus ] = useState( 0 );
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	if ( request.loading ) {
		return <p role="status">{ __( 'Loading syllabuses…', 'ohmylms' ) }</p>;
	}
	if ( ! request.data ) {
		return <p role="alert">{ request.error }</p>;
	}
	if ( ! request.data.items.length ) {
		return <p>{ __( 'There are no syllabuses yet.', 'ohmylms' ) }</p>;
	}
	const chosen = request.data.items.find( ( item ) => item.id === syllabus );
	return (
		<>
			<SelectControl< string >
				label={ __( 'Syllabus', 'ohmylms' ) }
				value={ String( syllabus ) }
				options={ [
					{ value: '0', label: __( 'Choose a syllabus', 'ohmylms' ) },
					...request.data.items.map( ( item ) => ( {
						value: String( item.id ),
						label: [ item.name, item.code, item.version ]
							.filter( Boolean )
							.join( ' · ' ),
					} ) ),
				] }
				onChange={ ( value ) => {
					setSyllabus( Number( value ) );
					setSearch( null );
				} }
			/>
			{ chosen && (
				<>
					<TextControl
						label={ __( 'Find an objective', 'ohmylms' ) }
						value={ query }
						maxLength={ 100 }
						onChange={ setQuery }
					/>
					<Button
						variant="secondary"
						onClick={ () => setSearch( query ) }
					>
						{ __( 'Search objectives', 'ohmylms' ) }
					</Button>
					{ null !== search && (
						<ObjectiveSearch
							lessonId={ lessonId }
							syllabusId={ chosen.id }
							search={ search }
							exclude={ exclude }
							onPick={ ( objective ) =>
								onPick( objective, chosen.name )
							}
						/>
					) }
				</>
			) }
		</>
	);
}

function Editor( {
	initial,
	reload,
}: {
	initial: Objectives;
	reload: () => void;
} ) {
	const [ state, setState ] = useState( initial );
	const [ saved, setSaved ] = useState( JSON.stringify( initial ) );
	const [ message, setMessage ] = useState( '' );
	const [ conflict, setConflict ] = useState( false );
	const [ skillQuery, setSkillQuery ] = useState( '' );
	const [ skillSearch, setSkillSearch ] = useState< string | null >( null );
	const [ browse, setBrowse ] = useState( false );
	const operation = useOperation();
	const dirty = JSON.stringify( state ) !== saved;
	const mapped = state.mappings.map( key );
	const change = ( next: Objectives ) => {
		setState( next );
		setMessage( '' );
	};
	const addMapping = ( objective: ObjectiveRef, syllabusName: string ) => {
		const mapping: ObjectiveMapping = {
			...objective,
			relation: 'teaches',
			syllabus_name: syllabusName,
			missing: false,
		};
		change( {
			...state,
			mappings: [ ...state.mappings, mapping ],
			skills: state.skills.some(
				( skill ) => skill.id === objective.term_id
			)
				? state.skills
				: [
						...state.skills,
						{
							id: objective.term_id,
							name: objective.name,
							code: objective.code,
							managed: true,
						},
					],
		} );
	};
	const removeMapping = ( mapping: ObjectiveMapping ) => {
		const mappings = state.mappings.filter(
			( row ) => key( row ) !== key( mapping )
		);
		const stillMapped = mappings.some(
			( row ) => row.term_id === mapping.term_id
		);
		change( {
			...state,
			mappings,
			skills: state.skills.filter(
				( skill ) =>
					! (
						skill.managed &&
						skill.id === mapping.term_id &&
						! stillMapped
					)
			),
		} );
	};
	const targets = state.course_targets.filter(
		( target ) => ! mapped.includes( key( target ) )
	);
	return (
		<>
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
					{ conflict && (
						<>
							{ ' ' }
							<Button variant="link" onClick={ reload }>
								{ __(
									'Reload the saved objectives',
									'ohmylms'
								) }
							</Button>
						</>
					) }
				</Notice>
			) }
			<h3>{ __( 'Skills taught in this lesson', 'ohmylms' ) }</h3>
			{ state.skills.length ? (
				<ul>
					{ state.skills.map( ( skill ) => (
						<li key={ skill.id }>
							{ skill.code
								? `${ skill.code } · ${ skill.name }`
								: skill.name }
							{ skill.managed ? (
								` ${ __( '(from a syllabus objective)', 'ohmylms' ) }`
							) : (
								<>
									{ ' ' }
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											change( {
												...state,
												skills: state.skills.filter(
													( row ) =>
														row.id !== skill.id
												),
											} )
										}
									>
										{ sprintf(
											/* translators: %s: skill name. */
											__( 'Unlink skill %s', 'ohmylms' ),
											skill.name
										) }
									</Button>
								</>
							) }
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'No skills linked yet.', 'ohmylms' ) }</p>
			) }
			<TextControl
				label={ __( 'Find a skill', 'ohmylms' ) }
				value={ skillQuery }
				maxLength={ 100 }
				onChange={ setSkillQuery }
			/>
			<Button
				variant="secondary"
				onClick={ () => setSkillSearch( skillQuery ) }
			>
				{ __( 'Search skills', 'ohmylms' ) }
			</Button>
			{ null !== skillSearch && (
				<SkillSearch
					lessonId={ state.id }
					search={ skillSearch }
					exclude={ state.skills.map( ( skill ) => skill.id ) }
					onPick={ ( skill ) =>
						change( {
							...state,
							skills: [
								...state.skills,
								{ ...skill, managed: false },
							],
						} )
					}
				/>
			) }

			<h3>{ __( 'Syllabus objectives', 'ohmylms' ) }</h3>
			{ state.mappings.length ? (
				<WidefatTable
					caption={ __(
						'Syllabus objectives of this lesson',
						'ohmylms'
					) }
				>
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Objective', 'ohmylms' ) }
							</th>
							<th scope="col">
								{ __( 'Relationship', 'ohmylms' ) }
							</th>
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ state.mappings.map( ( mapping ) => (
							<tr key={ key( mapping ) }>
								<td>
									<strong>
										{ label( mapping ) ||
											__(
												'Unavailable objective',
												'ohmylms'
											) }
									</strong>
									{ mapping.missing &&
										` ${ __( '(no longer in its syllabus)', 'ohmylms' ) }` }
									{ mapping.objective && (
										<p>{ mapping.objective }</p>
									) }
									<p>
										{ [
											mapping.syllabus_name,
											mapping.topic,
										]
											.filter( Boolean )
											.join( ' — ' ) }
									</p>
								</td>
								<td>
									<SelectControl< string >
										label={ sprintf(
											/* translators: %s: objective. */
											__(
												'Relationship to %s',
												'ohmylms'
											),
											label( mapping )
										) }
										hideLabelFromVision
										value={ mapping.relation }
										options={ RELATIONS.map(
											( relation ) => ( {
												value: relation,
												label: RELATION_LABELS[
													relation
												](),
											} )
										) }
										onChange={ ( value ) => {
											const relation = RELATIONS.find(
												( entry ) => entry === value
											);
											if ( relation ) {
												change( {
													...state,
													mappings:
														state.mappings.map(
															( row ) =>
																key( row ) ===
																key( mapping )
																	? {
																			...row,
																			relation,
																		}
																	: row
														),
												} );
											}
										} }
									/>
								</td>
								<td>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											removeMapping( mapping )
										}
									>
										{ sprintf(
											/* translators: %s: objective. */
											__(
												'Remove objective %s',
												'ohmylms'
											),
											label( mapping )
										) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</WidefatTable>
			) : (
				<p>{ __( 'No syllabus objectives mapped yet.', 'ohmylms' ) }</p>
			) }
			{ targets.length > 0 && (
				<>
					<h4>
						{ __(
							'Objectives of this lesson’s courses',
							'ohmylms'
						) }
					</h4>
					<ul>
						{ targets.map( ( target ) => (
							<li
								key={ `${ target.course_id }-${ key( target ) }` }
							>
								<Button
									variant="link"
									onClick={ () =>
										addMapping(
											target,
											target.syllabus_name
										)
									}
								>
									{ sprintf(
										/* translators: %s: objective. */
										__( 'Add objective %s', 'ohmylms' ),
										label( target )
									) }
								</Button>{ ' ' }
								({ target.course_title })
							</li>
						) ) }
					</ul>
				</>
			) }
			{ browse ? (
				<SyllabusBrowser
					lessonId={ state.id }
					exclude={ mapped }
					onPick={ addMapping }
				/>
			) : (
				<Button variant="secondary" onClick={ () => setBrowse( true ) }>
					{ __( 'Browse syllabus objectives', 'ohmylms' ) }
				</Button>
			) }
			<p role="status">
				{ dirty
					? __( 'Objectives have unsaved changes.', 'ohmylms' )
					: __( 'Objectives saved.', 'ohmylms' ) }
			</p>
			<Button
				variant="primary"
				disabled={ ! dirty || operation.busy }
				isBusy={ operation.busy }
				onClick={ () => {
					setConflict( false );
					void operation.run(
						async ( signal ) => {
							try {
								return await saveObjectives( state, signal );
							} catch ( problem ) {
								const error = normalizeApiError( problem );
								setConflict( 409 === error.status );
								throw error;
							}
						},
						( result ) => {
							setState( result );
							setSaved( JSON.stringify( result ) );
							setMessage(
								__( 'Lesson objectives saved.', 'ohmylms' )
							);
						}
					);
				} }
			>
				{ __( 'Save objectives', 'ohmylms' ) }
			</Button>
		</>
	);
}

/**
 * Lesson objectives: the skills it teaches and its syllabus objective mappings (teaches, practises or
 * assesses), with the objectives of its courses as quick choices. Saved separately from the lesson
 * content, under their own revision.
 *
 * @param props          Component props.
 * @param props.lessonId Lesson ID.
 */
export default function LessonObjectives( { lessonId }: { lessonId: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readObjectives( lessonId, signal ),
		[ lessonId ]
	);
	const request = useRequest( load );
	return (
		<section aria-labelledby="oml-lesson-objectives">
			<h2 id="oml-lesson-objectives">
				{ __( 'Objectives', 'ohmylms' ) }
			</h2>
			<p>
				{ __(
					'Linked skills feed coverage and practice. Mapping a syllabus objective also links its skill. Nothing here awards mastery or changes a course.',
					'ohmylms'
				) }
			</p>
			{ request.loading && ! request.data && (
				<p role="status">{ __( 'Loading objectives…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<Editor
					key={ request.data.revision }
					initial={ request.data }
					reload={ request.retry }
				/>
			) }
		</section>
	);
}
