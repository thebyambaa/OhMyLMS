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
import { useNavigate } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	createFromSyllabus,
	MAX_COURSE_SKILLS,
	MAX_COURSE_TITLE,
	planCourse,
	readSource,
	readSources,
	sourceSkillIds,
	type SourcePreview,
	type SourceSkill,
} from './generation';

function skillLabel( skill: SourceSkill ) {
	return `${ skill.code } ${ skill.name }`.trim();
}

/**
 * Reviewed selection → review → create workflow for an independent draft course. Nothing is written
 * before "Create draft course"; the server rejects a selection read from a since-changed syllabus.
 *
 * @param props            Dialog properties.
 * @param props.syllabusId Source syllabus; without it the author chooses one.
 * @param props.onClose    Close without creating.
 */
export function CourseFromSyllabusDialog( {
	syllabusId,
	onClose,
}: {
	syllabusId?: number;
	onClose: () => void;
} ) {
	const navigate = useNavigate();
	const [ source, setSource ] = useState( syllabusId ?? 0 );
	const [ selected, setSelected ] = useState< ReadonlySet< number > >(
		new Set()
	);
	const [ title, setTitle ] = useState( '' );
	const [ reviewing, setReviewing ] = useState( false );
	const operation = useOperation( { errorShape: 'object' } );
	const sources = useRequest(
		( signal ) =>
			syllabusId ? Promise.resolve( [] ) : readSources( signal ),
		{ key: String( syllabusId ?? 0 ) }
	);
	const preview = useRequest(
		( signal ): Promise< SourcePreview | null > =>
			source ? readSource( source, signal ) : Promise.resolve( null ),
		{ key: String( source ) }
	);
	const data = preview.data;
	const chosen = data
		? sourceSkillIds( data ).filter( ( id ) => selected.has( id ) )
		: [];
	const units = data ? planCourse( data, new Set( chosen ) ) : [];
	const lessons = units.reduce(
		( total, unit ) => total + unit.lessons.length,
		0
	);
	const tooMany = chosen.length > MAX_COURSE_SKILLS;
	const titleTooLong = Array.from( title.trim() ).length > MAX_COURSE_TITLE;
	function toggle( ids: number[], on: boolean ) {
		const next = new Set( selected );
		for ( const id of ids ) {
			if ( on ) {
				next.add( id );
			} else {
				next.delete( id );
			}
		}
		setSelected( next );
	}
	function check( ids: number[], label: string ) {
		const count = ids.filter( ( id ) => selected.has( id ) ).length;
		return (
			<CheckboxControl
				label={ label }
				checked={ count > 0 && count === ids.length }
				indeterminate={ count > 0 && count < ids.length }
				disabled={ operation.busy }
				onChange={ ( on ) => toggle( ids, on ) }
			/>
		);
	}
	function close() {
		if ( ! operation.busy ) {
			onClose();
		}
	}
	function create() {
		if ( ! data ) {
			return;
		}
		void operation.run(
			( signal ) =>
				createFromSyllabus(
					{
						syllabusId: source,
						title: title.trim(),
						skillIds: chosen,
						revision: data.revision,
					},
					signal
				),
			( created ) => navigate( `/courses/${ created.id }/outline` )
		);
	}
	const unitName = ( name: string, position: number ) =>
		name ||
		sprintf(
			/* translators: %d: unit number. */
			__( 'Unit %d', 'ohmylms' ),
			position + 1
		);
	return (
		<Modal
			title={ __( 'Create a course from a syllabus', 'ohmylms' ) }
			size="large"
			onRequestClose={ close }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ __(
					'Choose syllabus topics or skills to seed an independent draft course. The course gets its own units and lessons; later syllabus edits do not change it.',
					'ohmylms'
				) }
			</p>
			{ ! syllabusId &&
				( sources.loading ? (
					<p role="status">
						{ __( 'Loading syllabuses…', 'ohmylms' ) }
					</p>
				) : (
					<>
						{ sources.error && (
							<Notice status="error" isDismissible={ false }>
								{ sources.error }{ ' ' }
								<Button
									variant="link"
									onClick={ sources.retry }
								>
									{ __( 'Retry', 'ohmylms' ) }
								</Button>
							</Notice>
						) }
						<SelectControl
							label={ __( 'Syllabus', 'ohmylms' ) }
							value={ String( source ) }
							disabled={ operation.busy || reviewing }
							options={ [
								{
									value: '0',
									label: __( 'Choose a syllabus', 'ohmylms' ),
								},
								...( sources.data ?? [] ).map( ( choice ) => ( {
									value: String( choice.id ),
									label: [
										choice.name,
										choice.code,
										choice.version,
										'roadmap' === choice.item_type
											? __( '(roadmap)', 'ohmylms' )
											: '',
									]
										.filter( Boolean )
										.join( ' ' ),
								} ) ),
							] }
							onChange={ ( value ) => {
								setSource( Number( value ) );
								setSelected( new Set() );
								operation.clearError();
							} }
						/>
					</>
				) ) }
			{ source > 0 && preview.loading && (
				<p role="status">
					{ __( 'Loading syllabus objectives…', 'ohmylms' ) }
				</p>
			) }
			{ preview.error && (
				<Notice status="error" isDismissible={ false }>
					{ preview.error }{ ' ' }
					<Button variant="link" onClick={ preview.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ data && ! data.topics.length && (
				<p>
					{ __(
						'This syllabus has no skills yet. Add skills to its groups first.',
						'ohmylms'
					) }
				</p>
			) }
			{ data && data.topics.length > 0 && ! reviewing && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						setReviewing( true );
					} }
				>
					<TextControl
						label={ __( 'Course title', 'ohmylms' ) }
						value={ title }
						placeholder={ data.name }
						maxLength={ MAX_COURSE_TITLE }
						help={ __(
							'Leave empty to use the syllabus name.',
							'ohmylms'
						) }
						onChange={ setTitle }
					/>
					<p role="status">
						{ sprintf(
							/* translators: 1: selected skills, 2: maximum skills. */
							_n(
								'%1$d skill selected (at most %2$d).',
								'%1$d skills selected (at most %2$d).',
								chosen.length,
								'ohmylms'
							),
							chosen.length,
							MAX_COURSE_SKILLS
						) }
					</p>
					{ tooMany && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %d: maximum skills. */
								__(
									'A course can start from at most %d skills. Clear some, or create more than one course.',
									'ohmylms'
								),
								MAX_COURSE_SKILLS
							) }
						</Notice>
					) }
					<Button
						variant="secondary"
						onClick={ () =>
							setSelected( new Set( sourceSkillIds( data ) ) )
						}
					>
						{ __( 'Select every skill', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => setSelected( new Set() ) }
					>
						{ __( 'Clear selection', 'ohmylms' ) }
					</Button>
					{ data.topics.map( ( topic ) => (
						<fieldset key={ topic.id }>
							<legend>{ topic.name }</legend>
							{ check(
								topic.lessons.flatMap( ( lesson ) =>
									lesson.skills.map(
										( skill ) => skill.term_id
									)
								),
								sprintf(
									/* translators: %s: syllabus topic. */
									__( 'Every skill in %s', 'ohmylms' ),
									topic.name
								)
							) }
							{ topic.lessons.map( ( lesson ) => (
								<fieldset key={ lesson.id }>
									<legend>{ lesson.title }</legend>
									{ check(
										lesson.skills.map(
											( skill ) => skill.term_id
										),
										sprintf(
											/* translators: %s: skill group. */
											__(
												'Every skill in %s',
												'ohmylms'
											),
											lesson.title
										)
									) }
									<ul>
										{ lesson.skills.map( ( skill ) => (
											<li key={ skill.term_id }>
												{ check(
													[ skill.term_id ],
													skillLabel( skill )
												) }
											</li>
										) ) }
									</ul>
								</fieldset>
							) ) }
						</fieldset>
					) ) }
					<Button
						variant="primary"
						type="submit"
						disabled={ ! chosen.length || tooMany || titleTooLong }
					>
						{ __( 'Review course outline', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button variant="tertiary" onClick={ close }>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</form>
			) }
			{ data && reviewing && (
				<section
					aria-label={ __( 'Review course outline', 'ohmylms' ) }
				>
					<h3>
						{ sprintf(
							/* translators: %s: course title. */
							__( 'Draft course: %s', 'ohmylms' ),
							title.trim() || data.name
						) }
					</h3>
					<p>
						{ sprintf(
							/* translators: 1: units, 2: lessons, 3: skills. */
							__(
								'%1$d units, %2$d draft lessons and %3$d linked skills.',
								'ohmylms'
							),
							units.length,
							lessons,
							chosen.length
						) }
					</p>
					<ol>
						{ units.map( ( unit, position ) => (
							<li key={ unit.id }>
								<strong>
									{ unitName( unit.name, position ) }
								</strong>
								<ol>
									{ unit.lessons.map( ( lesson ) => (
										<li key={ lesson.id }>
											{ lesson.title }
											<ul
												aria-label={ sprintf(
													/* translators: %s: lesson title. */
													__(
														'Skills linked to %s',
														'ohmylms'
													),
													lesson.title
												) }
											>
												{ lesson.skills.map(
													( skill ) => (
														<li
															key={
																skill.term_id
															}
														>
															{ skillLabel(
																skill
															) }
														</li>
													)
												) }
											</ul>
										</li>
									) ) }
								</ol>
							</li>
						) ) }
					</ol>
					<p>
						{ __(
							'Lessons start with empty bodies for you to write. Linked skills become optional outcomes: choose required skills and targets in the course outline before publishing. No questions or practice activities are created, and the syllabus is not changed.',
							'ohmylms'
						) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error.message }
							{ 409 === operation.error.status && (
								<>
									{ ' ' }
									<Button
										variant="link"
										onClick={ () => {
											operation.clearError();
											setReviewing( false );
											preview.reload();
										} }
									>
										{ __(
											'Reload the syllabus and review again',
											'ohmylms'
										) }
									</Button>
								</>
							) }
						</Notice>
					) }
					<Button
						variant="primary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ create }
					>
						{ __( 'Create draft course', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ operation.busy }
						onClick={ () => {
							operation.clearError();
							setReviewing( false );
						} }
					>
						{ __( 'Back to selection', 'ohmylms' ) }
					</Button>
				</section>
			) }
		</Modal>
	);
}
