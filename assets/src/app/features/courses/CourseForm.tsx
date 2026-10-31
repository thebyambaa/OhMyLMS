import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { Suspense, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { PointPricePanel } from '../commerce/public';
import { updateCourse } from './api';
import { MediaPicker } from './MediaPicker';
import {
	formFromCourse,
	isDirty,
	isValid,
	LEVELS,
	serverFieldError,
	statusLabel,
	toInput,
	validateForm,
} from './model';
import { useCourseEditor } from './useCourseEditor';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import type {
	AccessType,
	FormErrors,
	FormState,
	Level,
	Option,
	PriceType,
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
 * Course form for the Details tab (basics and thumbnail) or the Settings tab (pricing and sale schedule,
 * access, availability, capacity and certificate). Both save the whole course through the courses API.
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
	const [ picking, setPicking ] = useState( false );
	const [ reveal, setReveal ] = useState( false );
	const summary = useRef< HTMLDivElement >( null );
	const operation = useRef< AbortController | null >( null );
	const dirty = Boolean( form && saved && isDirty( form, saved ) );
	const guard = useUnsavedChangesGuard( dirty );
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
	const code = options.currency.code;
	const paid = 'paid' === form.price_type;
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
			<h2>
				{ sprintf(
					/* translators: %s: course title. */
					__( 'Edit course: %s', 'ohmylms' ),
					saved.title
				) }
			</h2>
			<p>
				<a href={ course.permalink }>
					{ __( 'View the course page', 'ohmylms' ) }
				</a>
			</p>
			{ trashed && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This course is in the trash. Restore it from the course list to edit it.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ dirty && (
				<p role="status">
					{ __( 'You have unsaved changes.', 'ohmylms' ) }
				</p>
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
				{ details && (
					<>
						<fieldset disabled={ busy || trashed }>
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
							<TextControl
								label={ __( 'Slug', 'ohmylms' ) }
								value={ form.slug }
								help={ __(
									'The address of the course page. It is made unique automatically.',
									'ohmylms'
								) }
								aria-invalid={ invalid( 'slug' ) }
								aria-describedby={ described( 'slug' ) }
								onChange={ ( next ) => update( 'slug', next ) }
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
										label={ __( 'Publish on', 'ohmylms' ) }
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
							<TextareaControl
								label={ __(
									'What students will learn',
									'ohmylms'
								) }
								value={ form.learning_outcomes }
								rows={ 3 }
								aria-invalid={ invalid( 'learning_outcomes' ) }
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
								aria-invalid={ invalid( 'target_audience' ) }
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
								aria-describedby={ described( 'requirements' ) }
								onChange={ ( next ) =>
									update( 'requirements', next )
								}
							/>
							<FieldError
								id="oml-course-requirements-error"
								message={ errors.requirements }
							/>
						</fieldset>

						<fieldset disabled={ busy || trashed }>
							<legend>{ __( 'Thumbnail', 'ohmylms' ) }</legend>
							{ form.thumbnail ? (
								<p>
									{ form.thumbnail.url && (
										<img
											src={ form.thumbnail.url }
											alt=""
											width={ 96 }
											height={ 96 }
										/>
									) }{ ' ' }
									{ sprintf(
										/* translators: %d: image ID. */
										__( 'Image #%d', 'ohmylms' ),
										form.thumbnail.id
									) }
								</p>
							) : (
								<p>
									{ __(
										'No thumbnail selected.',
										'ohmylms'
									) }
								</p>
							) }
							<FieldError
								id="oml-course-thumbnail_id-error"
								message={ errors.thumbnail_id }
							/>
							{ options.abilities.upload_files ? (
								<Button
									variant="secondary"
									onClick={ () => setPicking( true ) }
								>
									{ form.thumbnail
										? __( 'Change thumbnail', 'ohmylms' )
										: __( 'Choose thumbnail', 'ohmylms' ) }
								</Button>
							) : (
								<p>
									{ __(
										'You do not have permission to pick images from the media library.',
										'ohmylms'
									) }
								</p>
							) }{ ' ' }
							{ form.thumbnail && (
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () =>
										update( 'thumbnail', null )
									}
								>
									{ __( 'Remove thumbnail', 'ohmylms' ) }
								</Button>
							) }
						</fieldset>
					</>
				) }

				{ ! details && (
					<>
						<fieldset disabled={ busy || trashed }>
							<legend>{ __( 'Pricing', 'ohmylms' ) }</legend>
							<SelectControl
								label={ __( 'Price type', 'ohmylms' ) }
								value={ form.price_type }
								options={ options.price_types }
								onChange={ ( next ) =>
									update(
										'price_type',
										pick< PriceType >(
											next,
											[ 'free', 'paid' ],
											'free'
										)
									)
								}
							/>
							{ paid && (
								<>
									<p>
										{ sprintf(
											/* translators: %s: currency code such as USD. */
											__(
												'Prices use the site currency (%s). Change it in the store settings.',
												'ohmylms'
											),
											code
										) }
									</p>
									<TextControl
										label={ sprintf(
											/* translators: %s: currency code. */
											__(
												'Regular price (%s)',
												'ohmylms'
											),
											code
										) }
										value={ form.regular_price }
										inputMode="decimal"
										required
										aria-invalid={ invalid(
											'regular_price'
										) }
										aria-describedby={ described(
											'regular_price'
										) }
										onChange={ ( next ) =>
											update( 'regular_price', next )
										}
									/>
									<FieldError
										id="oml-course-regular_price-error"
										message={ errors.regular_price }
									/>
									<TextControl
										label={ sprintf(
											/* translators: %s: currency code. */
											__( 'Sale price (%s)', 'ohmylms' ),
											code
										) }
										value={ form.sale_price }
										inputMode="decimal"
										help={ __(
											'Leave empty for no sale. A sale needs a start and an end date.',
											'ohmylms'
										) }
										aria-invalid={ invalid( 'sale_price' ) }
										aria-describedby={ described(
											'sale_price'
										) }
										onChange={ ( next ) =>
											update( 'sale_price', next )
										}
									/>
									<FieldError
										id="oml-course-sale_price-error"
										message={ errors.sale_price }
									/>
									<TextControl
										label={ __( 'Sale starts', 'ohmylms' ) }
										type="date"
										value={ form.sale_start_date }
										aria-invalid={ invalid(
											'sale_start_date'
										) }
										aria-describedby={ described(
											'sale_start_date'
										) }
										onChange={ ( next ) =>
											update( 'sale_start_date', next )
										}
									/>
									<FieldError
										id="oml-course-sale_start_date-error"
										message={ errors.sale_start_date }
									/>
									<TextControl
										label={ __( 'Sale ends', 'ohmylms' ) }
										type="date"
										value={ form.sale_end_date }
										help={ __(
											'Dates use the site timezone and include the whole day.',
											'ohmylms'
										) }
										aria-invalid={ invalid(
											'sale_end_date'
										) }
										aria-describedby={ described(
											'sale_end_date'
										) }
										onChange={ ( next ) =>
											update( 'sale_end_date', next )
										}
									/>
									<FieldError
										id="oml-course-sale_end_date-error"
										message={ errors.sale_end_date }
									/>
								</>
							) }
						</fieldset>

						<Suspense fallback={ null }>
							<PointPricePanel
								courseId={ id }
								disabled={ busy || trashed }
							/>
						</Suspense>

						<fieldset disabled={ busy || trashed }>
							<legend>{ __( 'Access', 'ohmylms' ) }</legend>
							<SelectControl
								label={ __(
									'Who can open the course',
									'ohmylms'
								) }
								value={ form.access_type }
								options={ options.access_types }
								aria-invalid={ invalid( 'access_type' ) }
								aria-describedby={ described( 'access_type' ) }
								onChange={ ( next ) =>
									update(
										'access_type',
										pick< AccessType >(
											next,
											[ 'public', 'password_protected' ],
											'public'
										)
									)
								}
							/>
							<FieldError
								id="oml-course-access_type-error"
								message={ errors.access_type }
							/>
							{ 'password_protected' === form.access_type && (
								<>
									<TextControl
										label={ __(
											'Course password',
											'ohmylms'
										) }
										type={ reveal ? 'text' : 'password' }
										autoComplete="new-password"
										value={ form.password }
										maxLength={
											options.limits.max_password
										}
										aria-invalid={ invalid( 'password' ) }
										aria-describedby={ described(
											'password'
										) }
										onChange={ ( next ) =>
											update( 'password', next )
										}
									/>
									<FieldError
										id="oml-course-password-error"
										message={ errors.password }
									/>
									<CheckboxControl
										label={ __(
											'Show password',
											'ohmylms'
										) }
										checked={ reveal }
										onChange={ setReveal }
									/>
								</>
							) }
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
							<legend>{ __( 'Availability', 'ohmylms' ) }</legend>
							<TextControl
								label={ __( 'Available from', 'ohmylms' ) }
								type="date"
								value={ form.available_date }
								help={ __(
									'Optional. Leave empty for no date.',
									'ohmylms'
								) }
								aria-invalid={ invalid( 'available_date' ) }
								aria-describedby={ described(
									'available_date'
								) }
								onChange={ ( next ) =>
									update( 'available_date', next )
								}
							/>
							<FieldError
								id="oml-course-available_date-error"
								message={ errors.available_date }
							/>
							<TextControl
								label={ __( 'Duration (hours)', 'ohmylms' ) }
								value={ form.duration_hours }
								inputMode="numeric"
								aria-invalid={ invalid( 'duration_hours' ) }
								aria-describedby={ described(
									'duration_hours'
								) }
								onChange={ ( next ) =>
									update( 'duration_hours', next )
								}
							/>
							<FieldError
								id="oml-course-duration_hours-error"
								message={ errors.duration_hours }
							/>
							<TextControl
								label={ __( 'Duration (minutes)', 'ohmylms' ) }
								value={ form.duration_minutes }
								inputMode="numeric"
								aria-invalid={ invalid( 'duration_minutes' ) }
								aria-describedby={ described(
									'duration_minutes'
								) }
								onChange={ ( next ) =>
									update( 'duration_minutes', next )
								}
							/>
							<FieldError
								id="oml-course-duration_minutes-error"
								message={ errors.duration_minutes }
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
					</>
				) }

				<Button
					type="submit"
					variant="primary"
					disabled={ busy || ! dirty || trashed }
					isBusy={ busy }
				>
					{ __( 'Save course', 'ohmylms' ) }
				</Button>
			</form>

			{ picking && (
				<MediaPicker
					title={ __( 'Choose a thumbnail', 'ohmylms' ) }
					onClose={ () => setPicking( false ) }
					onSelect={ ( image ) => {
						update( 'thumbnail', {
							id: image.id,
							url: image.thumbnail_url,
						} );
						setPicking( false );
					} }
				/>
			) }

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
