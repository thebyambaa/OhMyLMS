import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { normalizeApiError } from '../../lib/api';
import { getAssignment, updateAssignment } from './api';
import {
	LIMITS,
	TIME_UNITS,
	formFromAssignment,
	isDirty,
	serverFieldError,
	statusLabel,
	toInput,
	validateForm,
} from './model';
import type {
	AssignmentDetail,
	FormErrors,
	FormState,
	TimeUnit,
} from './types';

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

function FieldError( { id, message }: { id: string; message?: string } ) {
	return message ? (
		<p id={ id } role="alert">
			{ message }
		</p>
	) : null;
}

function unitOptions(): Array< { value: TimeUnit; label: string } > {
	return [
		{ value: 'day', label: __( 'Days', 'ohmylms' ) },
		{ value: 'week', label: __( 'Weeks', 'ohmylms' ) },
		{ value: 'month', label: __( 'Months', 'ohmylms' ) },
	];
}

/**
 * Assignment editor (`#/assignments/<id>`): title, instructions, scoring, deadline and upload rules,
 * with read-only course placement. Saving a copy someone else changed meanwhile is refused (409), and
 * leaving with unsaved changes asks first.
 */
export default function AssignmentEditorPage() {
	const params = useParams();
	const id = routeId( params.id );
	const load = useCallback(
		( signal: AbortSignal ) =>
			null === id
				? Promise.reject( new Error( 'invalid id' ) )
				: getAssignment( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const [ current, setCurrent ] = useState< AssignmentDetail | null >( null );
	const [ form, setForm ] = useState< FormState | null >( null );
	const [ saved, setSaved ] = useState< FormState | null >( null );
	const [ errors, setErrors ] = useState< FormErrors >( {} );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const [ conflict, setConflict ] = useState( false );
	const summary = useRef< HTMLDivElement >( null );
	const dirty = Boolean( form && saved && isDirty( form, saved ) );
	const guard = useUnsavedChangesGuard( dirty );

	const adopt = useCallback( ( assignment: AssignmentDetail ) => {
		const next = formFromAssignment( assignment );
		setCurrent( assignment );
		setForm( next );
		setSaved( next );
		setErrors( {} );
		setConflict( false );
		setFailure( null );
	}, [] );

	useEffect( () => {
		if ( request.data ) {
			adopt( request.data );
		}
	}, [ request.data, adopt ] );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Assignment', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This assignment link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/assignments">
					{ __( 'Back to assignments', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	if ( request.loading && ! form ) {
		return (
			<>
				<h2>{ __( 'Assignment', 'ohmylms' ) }</h2>
				<p role="status">{ __( 'Loading assignment…', 'ohmylms' ) }</p>
			</>
		);
	}
	if ( ! form || ! saved || ! current ) {
		return (
			<>
				<h2>{ __( 'Assignment', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ request.error ||
						__( 'The assignment could not be loaded.', 'ohmylms' ) }
				</Notice>
				<p>
					<Button variant="secondary" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>{ ' ' }
					<Link to="/assignments">
						{ __( 'Back to assignments', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	const update = < K extends keyof FormState >(
		key: K,
		value: FormState[ K ]
	) => {
		setForm( ( previous ) =>
			previous ? { ...previous, [ key ]: value } : previous
		);
		setMessage( null );
	};
	const invalid = ( key: keyof FormState ) =>
		errors[ key ] ? true : undefined;
	const described = ( key: keyof FormState ) =>
		errors[ key ] ? `oml-asg-${ key }-error` : undefined;
	const numberField = (
		key: keyof FormState,
		label: string,
		help?: string
	) => (
		<>
			<TextControl
				label={ label }
				value={ String( form[ key ] ) }
				inputMode="numeric"
				help={ help }
				aria-invalid={ invalid( key ) }
				aria-describedby={ described( key ) }
				onChange={ ( next ) => update( key, next ) }
			/>
			<FieldError
				id={ `oml-asg-${ key }-error` }
				message={ errors[ key ] }
			/>
		</>
	);

	function save() {
		if ( ! form || ! current || operation.busy ) {
			return;
		}
		const problems = validateForm( form );
		setErrors( problems );
		setMessage( null );
		setConflict( false );
		if ( Object.keys( problems ).length ) {
			setFailure(
				__( 'Fix the highlighted fields before saving.', 'ohmylms' )
			);
			summary.current?.focus();
			return;
		}
		setFailure( null );
		const input = toInput( form, current.updated_at );
		void operation
			.run(
				async ( signal ) => {
					try {
						return await updateAssignment(
							current.id,
							input,
							signal
						);
					} catch ( problem ) {
						const error = normalizeApiError( problem );
						const mapped = serverFieldError( error );
						if ( ! signal.aborted ) {
							if ( mapped ) {
								setErrors( {
									[ mapped.field ]: mapped.message,
								} );
							}
							setConflict( 409 === error.status );
							setFailure( error.message );
						}
						throw error;
					}
				},
				( result ) => {
					adopt( result );
					setMessage( __( 'Assignment saved.', 'ohmylms' ) );
				}
			)
			.then( ( succeeded ) => {
				if ( ! succeeded ) {
					summary.current?.focus();
				}
			} );
	}

	return (
		<>
			<p>
				<Link to="/assignments">
					{ __( 'Back to assignments', 'ohmylms' ) }
				</Link>{ ' ' }
				<Link to={ `/assignments/${ current.id }/submissions` }>
					{ sprintf(
						/* translators: %d: number of submissions. */
						__( 'Submissions (%d)', 'ohmylms' ),
						current.submission_count
					) }
				</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: assignment title. */
					__( 'Edit assignment: %s', 'ohmylms' ),
					saved.title
				) }
			</h2>
			<p>{ statusLabel( current.status ) }</p>
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
						{ conflict && (
							<>
								{ ' ' }
								<Button
									variant="link"
									onClick={ request.retry }
								>
									{ __(
										'Reload the latest version',
										'ohmylms'
									) }
								</Button>
							</>
						) }
					</Notice>
				) }
			</div>
			<form
				aria-label={ __( 'Assignment', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<fieldset disabled={ operation.busy }>
					<legend>{ __( 'Details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Title', 'ohmylms' ) }
						value={ form.title }
						maxLength={ LIMITS.title }
						required
						aria-invalid={ invalid( 'title' ) }
						aria-describedby={ described( 'title' ) }
						onChange={ ( next ) => update( 'title', next ) }
					/>
					<FieldError
						id="oml-asg-title-error"
						message={ errors.title }
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ form.status }
						options={ [
							{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
							{
								value: 'publish',
								label: __( 'Published', 'ohmylms' ),
							},
						] }
						help={ __(
							'Learners only see published assignments in courses they are enrolled in.',
							'ohmylms'
						) }
						onChange={ ( next ) =>
							update(
								'status',
								'publish' === next ? 'publish' : 'draft'
							)
						}
					/>
					<TextareaControl
						label={ __( 'Instructions', 'ohmylms' ) }
						help={ __(
							'Shown to learners above the submission form. Basic HTML is allowed.',
							'ohmylms'
						) }
						value={ form.instructions }
						rows={ 8 }
						onChange={ ( next ) => update( 'instructions', next ) }
					/>
				</fieldset>

				<fieldset disabled={ operation.busy }>
					<legend>{ __( 'Scoring', 'ohmylms' ) }</legend>
					{ numberField(
						'max_score',
						__( 'Maximum score', 'ohmylms' )
					) }
					{ numberField(
						'pass_score',
						__( 'Passing score', 'ohmylms' ),
						__(
							'Graded submissions at or above this score pass and complete the assignment.',
							'ohmylms'
						)
					) }
				</fieldset>

				<fieldset disabled={ operation.busy }>
					<legend>{ __( 'Deadline', 'ohmylms' ) }</legend>
					<CheckboxControl
						label={ __( 'Set a deadline', 'ohmylms' ) }
						help={ __(
							'The countdown starts when a learner first opens the assignment.',
							'ohmylms'
						) }
						checked={ form.time_limit_enabled }
						onChange={ ( next ) =>
							update( 'time_limit_enabled', next )
						}
					/>
					{ numberField(
						'time_limit',
						__( 'Time allowed', 'ohmylms' )
					) }
					<SelectControl
						label={ __( 'Unit', 'ohmylms' ) }
						value={ form.time_limit_unit }
						options={ unitOptions() }
						onChange={ ( next ) => {
							const found = TIME_UNITS.find(
								( unit ) => unit === next
							);
							if ( found ) {
								update( 'time_limit_unit', found );
							}
						} }
					/>
				</fieldset>

				<fieldset disabled={ operation.busy }>
					<legend>{ __( 'File uploads', 'ohmylms' ) }</legend>
					<CheckboxControl
						label={ __( 'Learners submit files', 'ohmylms' ) }
						checked={ form.allow_file_uploads }
						onChange={ ( next ) =>
							update( 'allow_file_uploads', next )
						}
					/>
					{ numberField(
						'max_files',
						__( 'Files allowed per learner', 'ohmylms' )
					) }
					<CheckboxControl
						label={ __( 'Limit the file size', 'ohmylms' ) }
						checked={ form.file_size_limit_enabled }
						onChange={ ( next ) =>
							update( 'file_size_limit_enabled', next )
						}
					/>
					{ numberField(
						'max_file_size_mb',
						__( 'Largest file (MB)', 'ohmylms' ),
						__(
							'The server upload limit still applies.',
							'ohmylms'
						)
					) }
				</fieldset>

				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy || ! dirty }
					isBusy={ operation.busy }
				>
					{ __( 'Save assignment', 'ohmylms' ) }
				</Button>
			</form>

			<section aria-labelledby="oml-asg-placement">
				<h3 id="oml-asg-placement">
					{ __( 'Course placement', 'ohmylms' ) }
				</h3>
				{ current.placements.length ? (
					<ul>
						{ current.placements.map( ( placement ) => (
							<li
								key={ `${ placement.course_id }-${
									placement.chapter_id ?? 0
								}` }
							>
								<Link
									to={ `/courses/${ placement.course_id }` }
								>
									{ placement.course_title }
								</Link>
								{ placement.chapter_title
									? ` — ${ placement.chapter_title }`
									: ` — ${ __(
											'learning program',
											'ohmylms'
										) }` }
							</li>
						) ) }
					</ul>
				) : (
					<p>
						{ __(
							'Not in a course yet. Add it to a chapter in the course editor; learners cannot submit until then.',
							'ohmylms'
						) }
					</p>
				) }
				{ current.permalink && (
					<p>
						<a
							href={ current.permalink }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'View as a learner', 'ohmylms' ) }
						</a>
					</p>
				) }
			</section>

			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Your changes to this assignment have not been saved.',
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
