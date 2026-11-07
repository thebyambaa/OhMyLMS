import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { MediaPicker } from '../../components/MediaPicker';
import { normalizeApiError } from '../../lib/api';
import { IMAGE_MIME_TYPES } from '../../lib/media';
import { Link } from 'react-router-dom';
import { updateCourse } from './api';
import { CourseViewLink } from './CourseViewLink';
import {
	formFromCourse,
	invalidResponse,
	isDirty,
	isValid,
	LEVELS,
	serverFieldError,
	statusLabel,
	toInput,
	validateForm,
} from './model';
import { useCourseEditor } from './useCourseEditor';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { useBuilderSave } from '../../hooks/useBuilderSave';
import type {
	FormErrors,
	FormState,
	Level,
	Option,
	WritableStatus,
} from './types';

/** Form section shown by a course editor tab. */
export type CourseFormSection = 'details' | 'settings';

/** Fields edited on the Details tab; every other field belongs to Settings. */
const DETAILS_FIELDS = [
	'title',
	'slug',
	'status',
	'scheduled_at',
	'level',
	'excerpt',
	'description',
	'learning_outcomes',
	'target_audience',
	'requirements',
	'thumbnail_id',
];

function inSection( field: string, section: CourseFormSection ) {
	return DETAILS_FIELDS.includes( field ) === ( 'details' === section );
}

function FieldError( { id, message }: { id: string; message?: string } ) {
	return message ? (
		<p id={ id } role="alert">
			{ message }
		</p>
	) : null;
}

/**
 * Add the current status to the choices when the user may not pick it (so the select still shows it).
 *
 * @param choices Statuses the user may set.
 * @param current Status of the form.
 * @return The choices to render.
 */
function withCurrentStatus(
	choices: Option[],
	current: WritableStatus
): Option[] {
	if ( choices.some( ( option ) => option.value === current ) ) {
		return choices;
	}
	return [ ...choices, { value: current, label: statusLabel( current ) } ];
}

function pick< T extends string >(
	value: string,
	choices: readonly T[],
	fallback: T
): T {
	return choices.find( ( choice ) => choice === value ) ?? fallback;
}

const WRITABLE: readonly WritableStatus[] = [
	'draft',
	'pending',
	'publish',
	'future',
	'private',
];

/**
 * Course form for the Details tab (basics and thumbnail) or the Settings tab (membership guidance,
 * reviews, capacity and certificate). Both save the whole course through the courses API.
 * Edits survive failed saves, and leaving with unsaved changes asks first.
 *
 * @param props         Component props.
 * @param props.id      Course ID.
 * @param props.section Fields to show.
 */
export function CourseForm( {
	id,
	section,
}: {
	id: number;
	section: CourseFormSection;
} ) {
	const editor = useCourseEditor( id );
	const [ form, setForm ] = useState< FormState | null >( null );
	const [ saved, setSaved ] = useState< FormState | null >( null );
	const [ errors, setErrors ] = useState< FormErrors >( {} );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const summary = useRef< HTMLDivElement >( null );
	const operation = useRef< AbortController | null >( null );
	const dirty = Boolean( form && saved && isDirty( form, saved ) );
	const guard = useUnsavedChangesGuard( dirty );
	// The builder bar has the Save button; it runs this form's save.
	useBuilderSave( {
		save: () => void save(),
		dirty,
		busy,
		course: editor.course ?? undefined,
	} );
	const course = editor.course;
	const options = editor.options;

	useEffect( () => () => operation.current?.abort(), [] );

	useEffect( () => {
		if ( course ) {
			const next = formFromCourse( course );
			setForm( next );
			setSaved( next );
			setErrors( {} );
		}
	}, [ course ] );

	if ( editor.loading && ! form ) {
		return (
			<>
				<h2>{ __( 'Course', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading course…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( editor.error || ! form || ! saved || ! options || ! course ) {
		return (
			<>
				<h2>{ __( 'Course', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ editor.error?.message ??
						__( 'The course could not be loaded.', 'ohmylms' ) }
				</Notice>
				{ ( ! editor.error ||
					( 403 !== editor.error.status &&
						404 !== editor.error.status ) ) && (
					<p>
						<Button variant="secondary" onClick={ editor.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					</p>
				) }
			</>
		);
	}
	const hiddenErrors = Object.entries( errors ).filter(
		( [ field ] ) => ! inSection( field, section )
	);
	const details = 'details' === section;

	const trashed = 'trash' === course.status;
	const statusOptions = withCurrentStatus( options.statuses, form.status );
	const update = < K extends keyof FormState >(
		key: K,
		value: FormState[ K ]
	) => {
		setForm( ( current ) =>
			current ? { ...current, [ key ]: value } : current
		);
		setMessage( null );
	};
	const invalid = ( key: string ) => ( errors[ key ] ? true : undefined );
	const described = ( key: string ) =>
		errors[ key ] ? `oml-course-${ key }-error` : undefined;

	async function save() {
		if ( ! form || ! options || ! course || operation.current || trashed ) {
			return;
		}
		const problems = validateForm( form, options );
		setErrors( problems );
		setMessage( null );
		setFailure( null );
		if ( ! isValid( problems ) ) {
			setFailure(
				__( 'Fix the highlighted fields before saving.', 'ohmylms' )
			);
			summary.current?.focus();
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		try {
			const result = await updateCourse(
				course.id,
				toInput( form, options.currency.decimals ),
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return;
			}
			editor.setCourse( result );
			setMessage( __( 'Course saved.', 'ohmylms' ) );
		} catch ( problem ) {
			if ( ! controller.signal.aborted ) {
				const error = normalizeApiError( problem );
				const mapped = serverFieldError( error );
				if ( mapped ) {
					setErrors( { [ mapped.path ]: mapped.message } );
				}
				setFailure( error.message );
				summary.current?.focus();
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}

	return (
		<>
			<div className="oml-editor-header">
				<div className="oml-editor-header-title">
					<h2>
						{ sprintf(
							/* translators: %s: course title. */
							__( 'Edit course: %s', 'ohmylms' ),
							saved.title
						) }
					</h2>
					<span
						className={ `oml-editor-status oml-editor-status-${ course.status }` }
					>
						{ statusOptions.find(
							( row ) => row.value === course.status
						)?.label ?? course.status }
					</span>
					{ dirty && (
						<span className="oml-editor-saved" role="status">
							{ __( 'You have unsaved changes.', 'ohmylms' ) }
						</span>
					) }
				</div>
				<div className="oml-editor-header-actions">
					<CourseViewLink course={ course } />
					<Button
						type="submit"
						form="oml-course-form"
						variant="primary"
						className="oml-builder-inner-save"
						disabled={ busy || ! dirty || trashed }
						isBusy={ busy }
					>
						{ __( 'Save course', 'ohmylms' ) }
					</Button>
				</div>
			</div>
			{ trashed && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This course is in the trash. Restore it from the course list to edit it.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			<div ref={ summary } tabIndex={ -1 }>
				{ failure && (
					<Notice status="error" isDismissible={ false }>
						{ failure }
						{ hiddenErrors.length > 0 && (
							<ul>
								{ hiddenErrors.map( ( [ field, text ] ) => (
									<li key={ field }>
										{ details
											? sprintf(
													/* translators: %s: validation message. */
													__(
														'Settings tab: %s',
														'ohmylms'
													),
													text
												)
											: sprintf(
													/* translators: %s: validation message. */
													__(
														'Details tab: %s',
														'ohmylms'
													),
													text
												) }
									</li>
								) ) }
							</ul>
						) }
					</Notice>
				) }
			</div>
			<form
				id="oml-course-form"
				className="oml-editor-form"
				aria-label={
					details
						? __( 'Course details', 'ohmylms' )
						: __( 'Course settings', 'ohmylms' )
				}
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					void save();
				} }
			>
				{ /* Lets Enter in a field submit; the visible Save is in the header. */ }
				<button
					type="submit"
					hidden
					aria-hidden="true"
					tabIndex={ -1 }
				/>
				{ details && (
					<div className="oml-editor-grid">
						<fieldset
							className="oml-editor-main"
							disabled={ busy || trashed }
						>
							<legend>{ __( 'Basics', 'ohmylms' ) }</legend>
							<TextControl
								label={ __( 'Title', 'ohmylms' ) }
								value={ form.title }
								maxLength={ options.limits.max_title }
								required
								aria-invalid={ invalid( 'title' ) }
								aria-describedby={ described( 'title' ) }
								onChange={ ( next ) => update( 'title', next ) }
							/>
							<FieldError
								id="oml-course-title-error"
								message={ errors.title }
							/>
							<TextareaControl
								label={ __( 'Short description', 'ohmylms' ) }
								value={ form.excerpt }
								rows={ 3 }
								aria-invalid={ invalid( 'excerpt' ) }
								aria-describedby={ described( 'excerpt' ) }
								onChange={ ( next ) =>
									update( 'excerpt', next )
								}
							/>
							<FieldError
								id="oml-course-excerpt-error"
								message={ errors.excerpt }
							/>
							<TextareaControl
								label={ __( 'Description', 'ohmylms' ) }
								value={ form.description }
								rows={ 8 }
								onChange={ ( next ) =>
									update( 'description', next )
								}
							/>
							<details
								className="oml-editor-more"
								open={
									!! (
										form.learning_outcomes ||
										form.target_audience ||
										form.requirements ||
										errors.learning_outcomes ||
										errors.target_audience ||
										errors.requirements
									)
								}
							>
								<summary>
									{ __(
										'Outcomes, audience and requirements',
										'ohmylms'
									) }
								</summary>
								<TextareaControl
									label={ __(
										'What students will learn',
										'ohmylms'
									) }
									value={ form.learning_outcomes }
									rows={ 3 }
									aria-invalid={ invalid(
										'learning_outcomes'
									) }
									aria-describedby={ described(
										'learning_outcomes'
									) }
									onChange={ ( next ) =>
										update( 'learning_outcomes', next )
									}
								/>
								<FieldError
									id="oml-course-learning_outcomes-error"
									message={ errors.learning_outcomes }
								/>
								<TextareaControl
									label={ __(
										'Who the course is for',
										'ohmylms'
									) }
									value={ form.target_audience }
									rows={ 3 }
									aria-invalid={ invalid(
										'target_audience'
									) }
									aria-describedby={ described(
										'target_audience'
									) }
									onChange={ ( next ) =>
										update( 'target_audience', next )
									}
								/>
								<FieldError
									id="oml-course-target_audience-error"
									message={ errors.target_audience }
								/>
								<TextareaControl
									label={ __( 'Requirements', 'ohmylms' ) }
									value={ form.requirements }
									rows={ 3 }
									aria-invalid={ invalid( 'requirements' ) }
									aria-describedby={ described(
										'requirements'
									) }
									onChange={ ( next ) =>
										update( 'requirements', next )
									}
								/>
								<FieldError
									id="oml-course-requirements-error"
									message={ errors.requirements }
								/>
							</details>
						</fieldset>
						<div className="oml-editor-aside">
							<fieldset disabled={ busy || trashed }>
								<legend>
									{ __( 'Publishing', 'ohmylms' ) }
								</legend>
								<TextControl
									label={ __( 'Slug', 'ohmylms' ) }
									value={ form.slug }
									help={ __(
										'The address of the course page. It is made unique automatically.',
										'ohmylms'
									) }
									aria-invalid={ invalid( 'slug' ) }
									aria-describedby={ described( 'slug' ) }
									onChange={ ( next ) =>
										update( 'slug', next )
									}
								/>
								<FieldError
									id="oml-course-slug-error"
									message={ errors.slug }
								/>
								<SelectControl
									label={ __( 'Status', 'ohmylms' ) }
									value={ form.status }
									options={ statusOptions }
									help={
										options.abilities.publish
											? undefined
											: __(
													'You can save drafts and submit courses for review. A reviewer publishes them.',
													'ohmylms'
												)
									}
									onChange={ ( next ) =>
										update(
											'status',
											pick< WritableStatus >(
												next,
												WRITABLE,
												'draft'
											)
										)
									}
								/>
								{ 'future' === form.status && (
									<>
										<TextControl
											label={ __(
												'Publish on',
												'ohmylms'
											) }
											type="datetime-local"
											value={ form.scheduled_at }
											help={ __(
												'Site time. The course is published automatically.',
												'ohmylms'
											) }
											aria-invalid={ invalid(
												'scheduled_at'
											) }
											aria-describedby={ described(
												'scheduled_at'
											) }
											onChange={ ( next ) =>
												update( 'scheduled_at', next )
											}
										/>
										<FieldError
											id="oml-course-scheduled_at-error"
											message={ errors.scheduled_at }
										/>
									</>
								) }
								<SelectControl
									label={ __( 'Level', 'ohmylms' ) }
									value={ form.level }
									options={ options.levels }
									onChange={ ( next ) =>
										update(
											'level',
											pick< Level >(
												next,
												LEVELS,
												'beginner'
											)
										)
									}
								/>
							</fieldset>

							<fieldset disabled={ busy || trashed }>
								<legend>
									{ __( 'Thumbnail', 'ohmylms' ) }
								</legend>
								<MediaPicker
									label={ __(
										'Choose a thumbnail',
										'ohmylms'
									) }
									chooseText={ __(
										'Choose thumbnail',
										'ohmylms'
									) }
									changeText={ __(
										'Change thumbnail',
										'ohmylms'
									) }
									removeText={ __(
										'Remove thumbnail',
										'ohmylms'
									) }
									destructiveRemove
									description={
										form.thumbnail
											? sprintf(
													/* translators: %d: image ID. */
													__(
														'Image #%d',
														'ohmylms'
													),
													form.thumbnail.id
												)
											: __(
													'No thumbnail selected.',
													'ohmylms'
												)
									}
									previewSize={ 96 }
									value={ form.thumbnail }
									unavailable={
										options.abilities.upload_files
											? undefined
											: __(
													'You do not have permission to pick images from the media library.',
													'ohmylms'
												)
									}
									accept={ IMAGE_MIME_TYPES }
									fallback="missing"
									invalid={ invalidResponse }
									onChange={ ( image ) =>
										update( 'thumbnail', {
											id: image.id,
											url: image.thumbnail_url,
										} )
									}
									onRemove={ () =>
										update( 'thumbnail', null )
									}
								/>
								<FieldError
									id="oml-course-thumbnail_id-error"
									message={ errors.thumbnail_id }
								/>
							</fieldset>
						</div>
					</div>
				) }

				{ ! details && (
					<div className="oml-editor-cards">
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Manage pricing and course access through membership plans. Add this course to a plan in Memberships.',
								'ohmylms'
							) }{ ' ' }
							<Link to="/memberships">
								{ __( 'Manage memberships', 'ohmylms' ) }
							</Link>
						</Notice>
						<fieldset disabled={ busy || trashed }>
							<legend>{ __( 'Reviews', 'ohmylms' ) }</legend>
							<CheckboxControl
								label={ __(
									'Allow student reviews',
									'ohmylms'
								) }
								checked={ form.enable_reviews }
								onChange={ ( next ) =>
									update( 'enable_reviews', next )
								}
							/>
						</fieldset>

						<fieldset disabled={ busy || trashed }>
							<legend>{ __( 'Capacity', 'ohmylms' ) }</legend>
							<CheckboxControl
								label={ __(
									'Limit the number of students',
									'ohmylms'
								) }
								checked={ form.has_capacity }
								onChange={ ( next ) =>
									update( 'has_capacity', next )
								}
							/>
							{ form.has_capacity && (
								<>
									<TextControl
										label={ __(
											'Maximum students',
											'ohmylms'
										) }
										value={ form.capacity }
										inputMode="numeric"
										help={ sprintf(
											/* translators: %d: number of students currently enrolled. */
											__(
												'%d students are enrolled now.',
												'ohmylms'
											),
											course.enrolled_count
										) }
										aria-invalid={ invalid( 'capacity' ) }
										aria-describedby={ described(
											'capacity'
										) }
										onChange={ ( next ) =>
											update( 'capacity', next )
										}
									/>
									<FieldError
										id="oml-course-capacity-error"
										message={ errors.capacity }
									/>
								</>
							) }
						</fieldset>
					</div>
				) }
			</form>

			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Your changes to this course have not been saved.',
							'ohmylms'
						) }
					</p>
					<Button variant="primary" onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						isDestructive
						onClick={ guard.leave }
					>
						{ __( 'Discard changes', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
