import {
	createElement,
	Fragment,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { AdminCard, AdminPage } from '../../extensions/AdminPage';
import {
	askInsight,
	loadCourses,
	loadSkillMatrix,
	loadStudentSkills,
	rebuildStudent,
} from './api.mjs';
import { SkillMatrix } from './SkillMatrix';
import { LearnerGuidance } from './LearnerGuidance';
import { HelpNow } from './HelpNow';

/**
 * Skill performance by course and unit: learners x skills, separate from grades.
 * Levels use the Khan mastery names; review-due is shown beside the level.
 */
export function PerformancePage() {
	const [ courses, setCourses ] = useState( null );
	const [ courseId, setCourseId ] = useState( '' );
	const [ unitId, setUnitId ] = useState( '' );
	const [ matrix, setMatrix ] = useState( null );
	const [ units, setUnits ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ student, setStudent ] = useState( null );
	useEffect( () => {
		loadCourses()
			.then( ( data ) =>
				setCourses( Array.isArray( data ) ? data : data?.data || [] )
			)
			.catch( ( cause ) => setError( cause.message ) );
	}, [] );
	useEffect( () => {
		if ( ! courseId ) {
			setUnits( [] );
			return setMatrix( null );
		}
		let active = true;
		setMatrix( undefined );
		loadSkillMatrix( courseId, undefined, unitId )
			.then( ( data ) => {
				if ( active ) {
					setMatrix( data );
					setUnits( data.units || [] );
				}
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ courseId, unitId ] );
	return (
		<AdminPage
			className="ohmylms-performance"
			title={ __( 'Skill performance', 'ohmylms' ) }
			description={ __(
				'Skill levels come from question-level evidence (quizzes, practice and lesson checks). Grades and gradebook overrides are not skill evidence.',
				'ohmylms'
			) }
		>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<AdminCard>
				<div className="ohmylms-mx-filter">
					{ ! courses ? (
						<Spinner />
					) : (
						<SelectControl
							label={ __( 'Course', 'ohmylms' ) }
							value={ courseId }
							options={ [
								{
									value: '',
									label: __( 'Choose a course', 'ohmylms' ),
								},
								...courses.map( ( course ) => ( {
									value: String( course.id ),
									label:
										course.name ||
										course.title ||
										`#${ course.id }`,
								} ) ),
							] }
							onChange={ ( value ) => {
								setCourseId( value );
								setUnitId( '' );
							} }
						/>
					) }
					{ units.length > 0 && (
						<SelectControl
							label={ __( 'Unit', 'ohmylms' ) }
							value={ unitId }
							options={ [
								{
									value: '',
									label: __( 'All units', 'ohmylms' ),
								},
								...units.map( ( unit ) => ( {
									value: String( unit.id ),
									label: sprintf(
										/* translators: 1: unit name, 2: number of skills. */
										__( '%1$s (%2$d)', 'ohmylms' ),
										unit.name,
										unit.skills
									),
								} ) ),
							] }
							onChange={ setUnitId }
						/>
					) }
				</div>
				{ matrix === undefined && <Spinner /> }
				{ matrix &&
					( matrix.skills.length === 0 ? (
						<p className="ohmylms-ext-muted">
							{ unitId
								? __( 'No skills in this unit yet.', 'ohmylms' )
								: __(
										'No skill evidence for this course yet.',
										'ohmylms'
									) }
						</p>
					) : (
						<SkillMatrix
							matrix={ matrix }
							onSelect={ setStudent }
						/>
					) ) }
			</AdminCard>
			{ courseId && (
				<HelpNow courseId={ courseId } onSelect={ setStudent } />
			) }
			{ student && (
				<StudentSkills
					student={ student }
					courseId={ courseId }
					unitId={ unitId }
					onClose={ () => setStudent( null ) }
				/>
			) }
		</AdminPage>
	);
}

function StudentSkills( { student, courseId, unitId, onClose } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ note, setNote ] = useState( null );
	const [ writing, setWriting ] = useState( false );
	const writeNote = () => {
		setWriting( true );
		setError( '' );
		askInsight( student.id, 'teacher' )
			.then( setNote )
			.catch( ( cause ) => setError( cause.message ) )
			.finally( () => setWriting( false ) );
	};
	const load = useCallback(
		() =>
			loadStudentSkills( student.id, courseId, undefined, unitId )
				.then( setData )
				.catch( ( cause ) => setError( cause.message ) ),
		[ student.id, courseId, unitId ]
	);
	useEffect( () => {
		load();
	}, [ load ] );
	return (
		<Modal title={ student.name } onRequestClose={ onClose } size="large">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! data ? (
				<Spinner />
			) : (
				<Fragment>
					<h3>{ __( 'Skills', 'ohmylms' ) }</h3>
					<ul>
						{ data.skills.map( ( skill ) => (
							<li key={ skill.id }>
								<strong>{ skill.name }</strong>:{ ' ' }
								{ skill.display_label || skill.level_label }
								{ skill.review_due &&
									` · ${ __( 'review due', 'ohmylms' ) }` }{ ' ' }
								·{ ' ' }
								{ sprintf(
									/* translators: 1: evidence count, 2: independent correct count, 3: question families. */
									__(
										'%1$d evidence · %2$d correct independently · %3$d question families',
										'ohmylms'
									),
									skill.evidence_count,
									skill.independent_correct,
									skill.families
								) }
							</li>
						) ) }
					</ul>
					{ data.insights && (
						<Fragment>
							<h3>{ __( 'AI note', 'ohmylms' ) }</h3>
							{ note ? (
								<p>{ note.text }</p>
							) : (
								<p>
									{ __(
										'A short AI-written summary of where this learner stands. It explains the levels; it never changes them.',
										'ohmylms'
									) }
								</p>
							) }
							<Button
								variant="secondary"
								onClick={ writeNote }
								isBusy={ writing }
								disabled={ writing }
							>
								{ note
									? __( 'Write again', 'ohmylms' )
									: __( 'Write a note', 'ohmylms' ) }
							</Button>
						</Fragment>
					) }
					<LearnerGuidance
						studentId={ student.id }
						courseId={ courseId }
					/>
					<h3>{ __( 'Recent evidence', 'ohmylms' ) }</h3>
					<table className="widefat striped">
						<thead>
							<tr>
								<th>{ __( 'When (UTC)', 'ohmylms' ) }</th>
								<th>{ __( 'Question', 'ohmylms' ) }</th>
								<th>{ __( 'Source', 'ohmylms' ) }</th>
								<th>{ __( 'Marks', 'ohmylms' ) }</th>
								<th>{ __( 'Flags', 'ohmylms' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ data.evidence
								.filter( ( row ) => row.role === 'primary' )
								.map( ( row, index ) => (
									<tr
										key={ index }
										style={
											Number( row.superseded )
												? { opacity: 0.5 }
												: null
										}
									>
										<td>{ row.evidence_at }</td>
										<td>{ row.question }</td>
										<td>{ row.source_type }</td>
										<td>
											{ Number( row.awarded ) } /{ ' ' }
											{ Number( row.available ) }
										</td>
										<td>
											{ [
												Number( row.independent )
													? ''
													: __(
															'assisted',
															'ohmylms'
														),
												Number( row.first_try )
													? ''
													: __( 'repeat', 'ohmylms' ),
												Number( row.superseded )
													? __(
															'regraded',
															'ohmylms'
														)
													: '',
											]
												.filter( Boolean )
												.join( ', ' ) }
										</td>
									</tr>
								) ) }
						</tbody>
					</table>
					{ window.ohmylmsAssessment?.isAdmin && (
						<Button
							variant="secondary"
							onClick={ () =>
								rebuildStudent( student.id ).then(
									load,
									( cause ) => setError( cause.message )
								)
							}
						>
							{ __( 'Rebuild from grade history', 'ohmylms' ) }
						</Button>
					) }
				</Fragment>
			) }
		</Modal>
	);
}
