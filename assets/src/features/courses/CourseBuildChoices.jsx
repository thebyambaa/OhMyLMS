/** @jsx createElement */
import { createElement, useEffect, useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { importQuiz } from '../content-hub/api.mjs';
import { problemLines } from '../content-hub/quizExchange.mjs';
import { syllabusLabel } from './objectives.mjs';
import { createCourse, loadCourse } from './api.mjs';
import { SyllabusCourseDialog } from './SyllabusCourseDialog';

const request = ( options ) => window.wp.apiFetch( options );

/**
 * The ways to start a course: from nothing, from a syllabus (units and skills come with it), or from a
 * quiz file. A choice with one option told authors nothing, and a syllabus already holds the structure.
 *
 * @param {Object}                       root0           Component properties.
 * @param {(community: boolean) => void} root0.onScratch Start an empty course.
 * @param {(course: Object) => void}     root0.onCreated A course was made another way.
 * @param {boolean}                      root0.loading   An empty course is being made.
 */
export function CourseBuildChoices( { onScratch, onCreated, loading } ) {
	const [ syllabuses, setSyllabuses ] = useState( null );
	const [ syllabusId, setSyllabusId ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ file, setFile ] = useState( null );
	const [ selecting, setSelecting ] = useState( false );
	const [ method, setMethod ] = useState( 'empty' );
	const [ title, setTitle ] = useState( '' );
	const fileId = useId();

	useEffect( () => {
		let active = true;
		request( { path: '/ohmylms/v1/courses/from-syllabus' } )
			.then( ( rows ) => {
				if ( active ) {
					setSyllabuses( rows );
					setSyllabusId( rows[ 0 ] ? String( rows[ 0 ].id ) : '' );
				}
			} )
			.catch( () => active && setSyllabuses( [] ) );
		return () => {
			active = false;
		};
	}, [] );

	async function fromFile() {
		setBusy( true );
		setError( '' );
		try {
			const quiz = await importQuiz( '', await file.text() );
			const course = await createCourse( {
				name: quiz.name,
				status: 'draft',
				course_type: 'self-paced',
				creation_method: '',
				isCommunityEnable: 'no',
			} );
			const full = await loadCourse( course.id );
			const unit = ( full.chapters || [] )[ 0 ];
			if ( unit ) {
				await request( {
					path: `/ohmylms/v1/content-hub/catalog/${ course.id }/chapters/${ unit.id }`,
					method: 'POST',
					data: { name: quiz.name },
				} );
				await request( {
					path: `/ohmylms/v1/chapters/${ unit.id }/content`,
					method: 'POST',
					data: [
						{
							id: Number( quiz.id ),
							name: quiz.name,
							type: 'quiz',
							order_number: 1,
						},
					],
				} );
			}
			onCreated( course );
		} catch ( cause ) {
			const lines = problemLines( cause );
			setError(
				lines.length
					? lines.join( ' ' )
					: cause.message ||
							__( 'The course could not be made.', 'ohmylms' )
			);
			setBusy( false );
		}
	}

	return (
		<div className="ohmylms-build-choices">
			{ selecting && (
				<SyllabusCourseDialog
					syllabusId={ Number( syllabusId ) }
					onClose={ () => setSelecting( false ) }
					onCreated={ onCreated }
				/>
			) }
			<h3>{ __( 'Create a course', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Every course uses the same builder: units contain lessons, and lessons contain teaching activities, practice, and quizzes.',
					'ohmylms'
				) }
			</p>
			<div
				className="ohmylms-course-start-options"
				role="group"
				aria-label={ __( 'Starting content', 'ohmylms' ) }
			>
				{ [
					[ 'empty', __( 'Empty course', 'ohmylms' ) ],
					[ 'syllabus', __( 'From syllabus', 'ohmylms' ) ],
					[ 'file', __( 'From quiz file', 'ohmylms' ) ],
				].map( ( [ value, label ] ) => (
					<Button
						key={ value }
						variant={ method === value ? 'primary' : 'secondary' }
						aria-pressed={ method === value }
						disabled={ busy || loading }
						onClick={ () => setMethod( value ) }
					>
						{ label }
					</Button>
				) ) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<section hidden={ method !== 'empty' }>
				<h4>{ __( 'Start from scratch', 'ohmylms' ) }</h4>
				<p>
					{ __(
						'Name your course, then add units, lessons, skills, and activities in the builder.',
						'ohmylms'
					) }
				</p>
				<TextControl
					label={ __( 'Course title', 'ohmylms' ) }
					value={ title }
					onChange={ setTitle }
					disabled={ busy || loading }
				/>
				<Button
					variant="secondary"
					disabled={ busy || loading || ! title.trim() }
					onClick={ () => onScratch( false, title ) }
				>
					{ __( 'Start an empty course', 'ohmylms' ) }
				</Button>
			</section>
			<section hidden={ method !== 'syllabus' }>
				<h4>{ __( 'From a syllabus', 'ohmylms' ) }</h4>
				<p>
					{ __(
						'Prefill the same builder with syllabus units, lessons, and skills. Your course is an independent draft; later syllabus edits are not applied automatically.',
						'ohmylms'
					) }
				</p>
				{ syllabuses === null && <Spinner /> }
				{ syllabuses && ! syllabuses.length && (
					<p className="description">
						{ __(
							'No syllabus has been added yet. An administrator can add one in Content Hub → Curriculum.',
							'ohmylms'
						) }
					</p>
				) }
				{ syllabuses && syllabuses.length > 0 && (
					<>
						<SelectControl
							label={ __( 'Syllabus', 'ohmylms' ) }
							value={ syllabusId }
							options={ syllabuses.map( ( row ) => ( {
								value: String( row.id ),
								label: syllabusLabel( row ),
							} ) ) }
							onChange={ setSyllabusId }
							disabled={ busy }
							__nextHasNoMarginBottom
						/>
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || loading || ! syllabusId }
							onClick={ () => setSelecting( true ) }
						>
							{ __( 'Create course from syllabus', 'ohmylms' ) }
						</Button>
					</>
				) }
			</section>
			<section hidden={ method !== 'file' }>
				<h4>{ __( 'From a quiz file', 'ohmylms' ) }</h4>
				<p>
					{ __(
						'A course with one unit that holds the quiz in an OhMyLMS quiz file (.json) or the simple quiz CSV.',
						'ohmylms'
					) }
				</p>
				<label htmlFor={ fileId } className="screen-reader-text">
					{ __( 'Quiz file', 'ohmylms' ) }
				</label>
				<input
					id={ fileId }
					type="file"
					accept=".json,.csv,application/json,text/csv"
					onChange={ ( event ) =>
						setFile( event.target.files[ 0 ] || null )
					}
					disabled={ busy }
				/>{ ' ' }
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy || loading || ! file }
					onClick={ fromFile }
				>
					{ __( 'Create course from file', 'ohmylms' ) }
				</Button>
			</section>
		</div>
	);
}
