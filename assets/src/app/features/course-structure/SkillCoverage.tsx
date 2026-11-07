import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Badge } from '../../../ui/Badge';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	addLessonForSkill,
	getSkillCoverage,
	type AddedLesson,
	type CoverageContent,
	type CoverageSkill,
	type CoverageStatus,
	type CoverageUnit,
} from './coverage-api';
import './SkillCoverage.css';

type Filter = 'all' | 'attention' | 'required' | 'objectives';

const EDIT_ROUTES: Record< string, string > = {
	lesson: '/content-hub/lessons/',
	quiz: '/quizzes/',
	assignment: '/assignments/',
};

const STATUS: Record<
	CoverageStatus,
	{ label: () => string; tone: string; help: () => string }
> = {
	covered: {
		label: () => __( 'Covered', 'ohmylms' ),
		tone: 'success',
		help: () =>
			__(
				'A lesson teaches it and the question pool is big enough.',
				'ohmylms'
			),
	},
	'needs-teaching': {
		label: () => __( 'Needs a lesson', 'ohmylms' ),
		tone: 'warning',
		help: () =>
			__(
				'It can be assessed, but no lesson in this course teaches it.',
				'ohmylms'
			),
	},
	'needs-questions': {
		label: () => __( 'Needs questions', 'ohmylms' ),
		tone: 'warning',
		help: () =>
			__(
				'A lesson teaches it, but there are too few approved questions.',
				'ohmylms'
			),
	},
	'needs-both': {
		label: () => __( 'Needs both', 'ohmylms' ),
		tone: 'danger',
		help: () =>
			__(
				'No lesson teaches it and there are too few approved questions.',
				'ohmylms'
			),
	},
};

const TARGETS: Record< string, () => string > = {
	familiar: () => __( 'Familiar', 'ohmylms' ),
	proficient: () => __( 'Proficient', 'ohmylms' ),
	mastered: () => __( 'Mastered', 'ohmylms' ),
};

function matches( skill: CoverageSkill, filter: Filter ) {
	switch ( filter ) {
		case 'attention':
			return 'covered' !== skill.status;
		case 'required':
			return skill.required;
		case 'objectives':
			return skill.objective;
		default:
			return true;
	}
}

function Where( { items }: { items: CoverageContent[] } ) {
	return (
		<ul className="oml-coverage-where">
			{ items.map( ( item ) => {
				const route = EDIT_ROUTES[ item.type ];
				const title = item.title || String( item.id );
				return (
					<li key={ `${ item.type }:${ item.id }` }>
						{ route ? (
							<Link to={ `${ route }${ item.id }` }>
								{ title }
							</Link>
						) : (
							title
						) }
						{ item.unit && (
							<span className="oml-coverage-unit">
								{ ' ' }
								{ sprintf(
									/* translators: %s: unit name. */
									__( 'in %s', 'ohmylms' ),
									item.unit
								) }
							</span>
						) }
					</li>
				);
			} ) }
		</ul>
	);
}

function Role( { skill }: { skill: CoverageSkill } ) {
	const target = skill.target
		? ( TARGETS[ skill.target ]?.() ?? skill.target )
		: '';
	return (
		<>
			{ skill.outcome ? (
				<Badge tone={ skill.required ? 'primary' : 'info' }>
					{ skill.required
						? sprintf(
								/* translators: %s: target level. */
								__( 'Required · %s', 'ohmylms' ),
								target
							)
						: sprintf(
								/* translators: %s: target level. */
								__( 'Optional · %s', 'ohmylms' ),
								target
							) }
				</Badge>
			) : (
				<Badge>{ __( 'Linked only', 'ohmylms' ) }</Badge>
			) }
			{ skill.objective && (
				<Badge tone="info">
					{ __( 'Syllabus objective', 'ohmylms' ) }
				</Badge>
			) }
			{ skill.practice && (
				<Badge>{ __( 'Has practice', 'ohmylms' ) }</Badge>
			) }
		</>
	);
}

/**
 * Dialog that fixes a missing-lesson gap: it creates an empty draft lesson for the skill and places it in a unit.
 *
 * @param props          Component props.
 * @param props.skill    The skill that has no lesson.
 * @param props.units    Units of the course.
 * @param props.course   Course ID.
 * @param props.revision Draft revision the report was built from.
 * @param props.onClose  Closes the dialog without changing anything.
 * @param props.onAdded  Called with the result once the lesson is created and placed.
 */
function AddLessonDialog( {
	skill,
	units,
	course,
	revision,
	onClose,
	onAdded,
}: {
	skill: CoverageSkill;
	units: CoverageUnit[];
	course: number;
	revision: string;
	onClose: () => void;
	onAdded: ( result: AddedLesson ) => void;
} ) {
	const operation = useOperation();
	// A skill tagged on a quiz probably belongs in that quiz's unit; otherwise start with the first unit.
	const tagged = skill.assessing[ 0 ]?.unitId;
	const [ unitId, setUnitId ] = useState(
		units.find( ( unit ) => unit.id === tagged )?.id ?? units[ 0 ]?.id ?? 0
	);
	const [ title, setTitle ] = useState( skill.name );
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: skill name. */
				__( 'Add a lesson for “%s”', 'ohmylms' ),
				skill.name
			) }
			onRequestClose={ operation.busy ? () => undefined : onClose }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void operation.run(
						( signal ) =>
							addLessonForSkill(
								course,
								{
									skillId: skill.id,
									unitId,
									title: title.trim(),
									revision,
								},
								signal
							),
						onAdded
					);
				} }
			>
				<p>
					{ __(
						'This creates an empty draft lesson, links it to the skill and places it at the end of the unit you choose. Nothing is published, and you write the lesson next.',
						'ohmylms'
					) }
				</p>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				<SelectControl
					label={ __( 'Unit', 'ohmylms' ) }
					value={ String( unitId ) }
					options={ units.map( ( unit ) => ( {
						value: String( unit.id ),
						label: unit.title || __( 'New unit', 'ohmylms' ),
					} ) ) }
					disabled={ operation.busy }
					onChange={ ( value ) => setUnitId( Number( value ) ) }
				/>
				<TextControl
					label={ __( 'Lesson title', 'ohmylms' ) }
					value={ title }
					maxLength={ 200 }
					disabled={ operation.busy }
					onChange={ setTitle }
				/>
				<div className="oml-coverage-dialog-actions">
					<Button
						type="submit"
						variant="primary"
						isBusy={ operation.busy }
						disabled={ operation.busy || ! unitId }
					>
						{ __( 'Add lesson', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ onClose }
						disabled={ operation.busy }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}

/**
 * What an author can do about a skill's gap, in its row.
 *
 * @param props             Component props.
 * @param props.skill       The skill.
 * @param props.canAdd      Whether a lesson can be added now.
 * @param props.why         Why it cannot, when it cannot.
 * @param props.onAddLesson Opens the add-lesson dialog.
 */
function Fixes( {
	skill,
	canAdd,
	why,
	onAddLesson,
}: {
	skill: CoverageSkill;
	canAdd: boolean;
	why: string;
	onAddLesson: () => void;
} ) {
	if ( skill.teachingOk && skill.questionsOk ) {
		return <span className="oml-coverage-none">—</span>;
	}
	return (
		<div className="oml-coverage-fixes">
			{ ! skill.teachingOk && (
				<Button
					variant="secondary"
					size="small"
					disabled={ ! canAdd }
					title={ canAdd ? undefined : why }
					aria-label={ sprintf(
						/* translators: %s: skill name. */
						__( 'Add a lesson for %s', 'ohmylms' ),
						skill.name
					) }
					onClick={ onAddLesson }
				>
					{ __( 'Add a lesson', 'ohmylms' ) }
				</Button>
			) }
			{ ! skill.questionsOk && (
				<Link
					className="components-button is-secondary is-small"
					to={ `/content-hub/question-bank?action=new&skill=${ skill.id }` }
					aria-label={ sprintf(
						/* translators: %s: skill name. */
						__( 'Add a question for %s', 'ohmylms' ),
						skill.name
					) }
				>
					{ __( 'Add a question', 'ohmylms' ) }
				</Link>
			) }
		</div>
	);
}

/**
 * Skill coverage step of the course builder: for every skill the course touches, whether a lesson teaches it
 * and whether there are enough approved questions to assess it. It reports on the saved outline.
 *
 * @param props           Component props.
 * @param props.course    Course ID.
 * @param props.unsaved   Whether any step has unsaved changes, which this report does not include.
 * @param props.onChanged Called after a fix changed the course's draft, so other steps can reload it.
 */
export function SkillCoverage( {
	course,
	unsaved,
	onChanged,
}: {
	course: number;
	unsaved: boolean;
	onChanged?: () => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => getSkillCoverage( course, signal ),
		[ course ]
	);
	const request = useRequest( load );
	const [ filter, setFilter ] = useState< Filter >( 'all' );
	const [ fixing, setFixing ] = useState< CoverageSkill | null >( null );
	const [ added, setAdded ] = useState< AddedLesson[ 'lesson' ] | null >(
		null
	);
	const report = request.data;
	const noUnits = ! report || 0 === report.units.length;
	let canAdd = true;
	let why = '';
	if ( unsaved ) {
		canAdd = false;
		why = __(
			'Save your changes first: a fix changes the saved outline.',
			'ohmylms'
		);
	} else if ( noUnits ) {
		canAdd = false;
		why = __( 'Add a unit in the Curriculum step first.', 'ohmylms' );
	}
	const skills = report?.skills ?? [];
	const attention = skills.filter( ( skill ) => 'covered' !== skill.status );
	const shown = skills.filter( ( skill ) => matches( skill, filter ) );
	const filters: Array< [ Filter, string, number ] > = [
		[ 'all', __( 'All skills', 'ohmylms' ), skills.length ],
		[ 'attention', __( 'Needs attention', 'ohmylms' ), attention.length ],
		[
			'required',
			__( 'Required', 'ohmylms' ),
			skills.filter( ( skill ) => skill.required ).length,
		],
		[
			'objectives',
			__( 'Syllabus objectives', 'ohmylms' ),
			skills.filter( ( skill ) => skill.objective ).length,
		],
	];

	return (
		<section className="oml-coverage" aria-labelledby="oml-coverage-title">
			<div className="oml-coverage-head">
				<div>
					<h2 id="oml-coverage-title">
						{ __( 'Skill coverage', 'ohmylms' ) }
					</h2>
					<p className="oml-coverage-intro">
						{ __(
							'For each skill this course touches: is it taught by a lesson in the course, and are there enough approved questions to assess it? A skill counts as taught only through a lesson, and as assessable only through approved questions.',
							'ohmylms'
						) }
					</p>
				</div>
				<Button
					variant="secondary"
					onClick={ request.reload }
					isBusy={ request.loading }
					disabled={ request.loading }
				>
					{ __( 'Refresh', 'ohmylms' ) }
				</Button>
			</div>

			{ added && (
				<Notice status="success" onRemove={ () => setAdded( null ) }>
					{ sprintf(
						/* translators: 1: lesson title, 2: unit title. */
						__(
							'Added the draft lesson “%1$s” to %2$s and linked it to the skill. It is empty: write it next.',
							'ohmylms'
						),
						added.title,
						report?.units.find(
							( unit ) => unit.id === added.unitId
						)?.title ?? ''
					) }{ ' ' }
					<Link to={ `/content-hub/lessons/${ added.id }` }>
						{ __( 'Open the lesson', 'ohmylms' ) }
					</Link>
				</Notice>
			) }
			{ unsaved && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'You have unsaved changes. This report is based on the saved outline: save, then refresh to see them counted. The fixes below also need your changes saved first.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.loading && ! report && (
				<p role="status">
					{ __( 'Loading skill coverage…', 'ohmylms' ) }
				</p>
			) }

			{ report && 0 === skills.length && (
				<div className="oml-coverage-empty">
					<p>
						{ __(
							'No skills are connected to this course yet.',
							'ohmylms'
						) }
					</p>
					<p>
						{ __(
							'Connect skills to lessons and quizzes in the Curriculum step, add skill outcomes there, or create the course from a syllabus.',
							'ohmylms'
						) }{ ' ' }
						<Link to={ `/courses/${ course }/outline` }>
							{ __( 'Open the Curriculum step', 'ohmylms' ) }
						</Link>
					</p>
				</div>
			) }

			{ report && skills.length > 0 && (
				<>
					<ul
						className="oml-coverage-summary"
						aria-label={ __( 'Coverage summary', 'ohmylms' ) }
					>
						{ (
							[
								[ 'covered', report.summary.covered ],
								[
									'needs-teaching',
									report.summary.needsTeaching,
								],
								[
									'needs-questions',
									report.summary.needsQuestions,
								],
								[ 'needs-both', report.summary.needsBoth ],
							] as Array< [ CoverageStatus, number ] >
						 ).map( ( [ status, count ] ) => (
							<li
								key={ status }
								className={ `oml-coverage-stat is-${ status }` }
							>
								<strong>{ count }</strong>
								<span>{ STATUS[ status ].label() }</span>
							</li>
						) ) }
					</ul>
					{ report.summary.required > 0 &&
						( report.summary.requiredNotReady > 0 ? (
							<Notice status="warning" isDismissible={ false }>
								{ sprintf(
									/* translators: %d: number of required skills. */
									_n(
										'%d required skill does not have enough approved questions yet, which blocks publishing.',
										'%d required skills do not have enough approved questions yet, which blocks publishing.',
										report.summary.requiredNotReady,
										'ohmylms'
									),
									report.summary.requiredNotReady
								) }
							</Notice>
						) : (
							<Notice status="success" isDismissible={ false }>
								{ __(
									'Every required skill has enough approved questions.',
									'ohmylms'
								) }
							</Notice>
						) ) }
					{ report.summary.objectives > 0 && (
						<p className="oml-coverage-objectives">
							{ sprintf(
								/* translators: 1: taught objectives, 2: all objectives. */
								__(
									'Syllabus objectives taught by a lesson: %1$d of %2$d.',
									'ohmylms'
								),
								report.summary.objectivesTaught,
								report.summary.objectives
							) }
						</p>
					) }

					<div
						className="oml-coverage-filters"
						role="group"
						aria-label={ __( 'Show skills', 'ohmylms' ) }
					>
						{ filters.map( ( [ id, label, count ] ) => (
							<Button
								key={ id }
								variant={
									filter === id ? 'primary' : 'secondary'
								}
								size="small"
								aria-pressed={ filter === id }
								onClick={ () => setFilter( id ) }
							>
								{ `${ label } (${ count })` }
							</Button>
						) ) }
					</div>

					{ 0 === shown.length ? (
						<p className="oml-coverage-none">
							{ __( 'No skills match this filter.', 'ohmylms' ) }
						</p>
					) : (
						<div className="oml-coverage-scroll">
							<table className="widefat striped oml-coverage-table">
								<caption className="screen-reader-text">
									{ __(
										'Skills of this course and their coverage',
										'ohmylms'
									) }
								</caption>
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Skill', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Role', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Taught in', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Tagged on', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __(
												'Approved questions',
												'ohmylms'
											) }
										</th>
										<th scope="col">
											{ __( 'Status', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Fix', 'ohmylms' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ shown.map( ( skill ) => (
										<tr key={ skill.id }>
											<th scope="row">
												<span className="oml-coverage-name">
													{ skill.name ||
														__(
															'Unavailable skill',
															'ohmylms'
														) }
												</span>
												{ skill.code && (
													<span className="oml-coverage-code">
														{ skill.code }
													</span>
												) }
											</th>
											<td>
												<Role skill={ skill } />
											</td>
											<td>
												{ skill.teaching.length ? (
													<Where
														items={ skill.teaching }
													/>
												) : (
													<span className="oml-coverage-missing">
														{ __(
															'No lesson',
															'ohmylms'
														) }
													</span>
												) }
											</td>
											<td>
												{ skill.assessing.length ? (
													<Where
														items={
															skill.assessing
														}
													/>
												) : (
													<span className="oml-coverage-none">
														—
													</span>
												) }
											</td>
											<td>
												<span
													className={
														skill.questionsOk
															? 'oml-coverage-count is-ok'
															: 'oml-coverage-count is-short'
													}
												>
													{ sprintf(
														/* translators: 1: questions, 2: questions needed, 3: families, 4: families needed. */
														__(
															'%1$d of %2$d questions, %3$d of %4$d families',
															'ohmylms'
														),
														skill.questions,
														skill.questionsNeed,
														skill.families,
														skill.familiesNeed
													) }
												</span>
												{ 'default' ===
													skill.needBasis && (
													<span className="oml-coverage-basis">
														{ __(
															'Measured against Proficient',
															'ohmylms'
														) }
													</span>
												) }
											</td>
											<td>
												<Badge
													tone={
														STATUS[ skill.status ]
															.tone
													}
													title={ STATUS[
														skill.status
													].help() }
												>
													{ STATUS[
														skill.status
													].label() }
												</Badge>
											</td>
											<td>
												<Fixes
													skill={ skill }
													canAdd={ canAdd }
													why={ why }
													onAddLesson={ () =>
														setFixing( skill )
													}
												/>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</>
			) }
			{ fixing && report && (
				<AddLessonDialog
					skill={ fixing }
					units={ report.units }
					course={ course }
					revision={ report.revision }
					onClose={ () => setFixing( null ) }
					onAdded={ ( result ) => {
						request.replace( result.report );
						setFixing( null );
						setAdded( result.lesson );
						onChanged?.();
					} }
				/>
			) }
		</section>
	);
}
