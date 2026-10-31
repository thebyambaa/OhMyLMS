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
import { Link, useParams } from 'react-router-dom';
import { normalizeApiError } from '../../lib/api';
import { updateMembership } from './api';
import { CourseSelector } from './CourseSelector';
import { Members } from './Members';
import { MembershipExtensionSettings } from './MembershipExtensionSettings';
import {
	formFromMembership,
	isDirty,
	isValid,
	serverFieldError,
	toInput,
	validateForm,
} from './model';
import { useMembershipEditor } from './useMembershipEditor';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import type { FormErrors, FormState } from './types';

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

/**
 * Membership plan editor (`#/memberships/<id>`): details, pricing and sale schedule, courses and
 * access rules, and the read-only member list. Edits survive failed saves, and leaving with unsaved
 * changes asks first.
 */
export default function MembershipEditorPage() {
	const params = useParams();
	const id = routeId( params.id );
	const editor = useMembershipEditor( id );
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
	const plan = editor.plan;
	const options = editor.options;

	useEffect( () => () => operation.current?.abort(), [] );

	useEffect( () => {
		if ( plan ) {
			const next = formFromMembership( plan );
			setForm( next );
			setSaved( next );
			setErrors( {} );
		}
	}, [ plan ] );

	if ( null === id ) {
		return (
			<>
				<h2>{ __( 'Membership plan', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This membership plan link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/memberships">
					{ __( 'Back to membership plans', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	if ( editor.loading && ! form ) {
		return (
			<>
				<h2>{ __( 'Membership plan', 'ohmylms' ) }</h2>
				<p role="status">
					{ __( 'Loading membership plan…', 'ohmylms' ) }
				</p>
			</>
		);
	}
	if ( editor.error || ! form || ! saved || ! options || ! plan ) {
		return (
			<>
				<h2>{ __( 'Membership plan', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ editor.error?.message ??
						__(
							'The membership plan could not be loaded.',
							'ohmylms'
						) }
				</Notice>
				<p>
					{ ( ! editor.error ||
						( 403 !== editor.error.status &&
							404 !== editor.error.status ) ) && (
						<>
							<Button
								variant="secondary"
								onClick={ editor.reload }
							>
								{ __( 'Try again', 'ohmylms' ) }
							</Button>{ ' ' }
						</>
					) }
					<Link to="/memberships">
						{ __( 'Back to membership plans', 'ohmylms' ) }
					</Link>
				</p>
			</>
		);
	}

	const code = options.currency.code;
	const update = < K extends keyof FormState >(
		key: K,
		value: FormState[ K ]
	) => {
		setForm( ( current ) =>
			current ? { ...current, [ key ]: value } : current
		);
		setMessage( null );
	};
	const updateExtension = (
		extensionId: string,
		next: Record< string, unknown >
	) => {
		setForm( ( current ) =>
			current
				? {
						...current,
						extension_settings: {
							...current.extension_settings,
							[ extensionId ]: next,
						},
					}
				: current
		);
		setMessage( null );
	};
	const invalid = ( key: string ) => ( errors[ key ] ? true : undefined );
	const described = ( key: string ) =>
		errors[ key ] ? `oml-mem-${ key }-error` : undefined;

	async function save() {
		if ( ! form || ! saved || ! options || ! plan || operation.current ) {
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
			const result = await updateMembership(
				plan.id,
				toInput( form, options.currency.decimals, saved ),
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return;
			}
			editor.setPlan( result );
			setMessage( __( 'Plan saved.', 'ohmylms' ) );
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
			<p>
				<Link to="/memberships">
					{ __( 'Back to membership plans', 'ohmylms' ) }
				</Link>
			</p>
			<h2>
				{ sprintf(
					/* translators: %s: membership plan name. */
					__( 'Edit membership plan: %s', 'ohmylms' ),
					saved.name
				) }
			</h2>
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
					</Notice>
				) }
			</div>
			<form
				aria-label={ __( 'Membership plan', 'ohmylms' ) }
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					void save();
				} }
			>
				<fieldset disabled={ busy }>
					<legend>{ __( 'Details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ form.name }
						maxLength={ options.limits.max_name }
						required
						aria-invalid={ invalid( 'name' ) }
						aria-describedby={ described( 'name' ) }
						onChange={ ( next ) => update( 'name', next ) }
					/>
					<FieldError
						id="oml-mem-name-error"
						message={ errors.name }
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ form.status }
						options={ options.statuses }
						help={ __(
							'Draft plans are not sold; published plans appear on the plans page and in checkout.',
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
						label={ __( 'Description', 'ohmylms' ) }
						value={ form.description }
						rows={ 4 }
						onChange={ ( next ) => update( 'description', next ) }
					/>
				</fieldset>

				<fieldset disabled={ busy }>
					<legend>{ __( 'Pricing', 'ohmylms' ) }</legend>
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
							__( 'Regular price (%s)', 'ohmylms' ),
							code
						) }
						value={ form.regular_price }
						inputMode="decimal"
						required
						aria-invalid={ invalid( 'regular_price' ) }
						aria-describedby={ described( 'regular_price' ) }
						onChange={ ( next ) => update( 'regular_price', next ) }
					/>
					<FieldError
						id="oml-mem-regular_price-error"
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
							'Leave empty for no sale. Enter 0 for free access until the end date.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'sale_price' ) }
						aria-describedby={ described( 'sale_price' ) }
						onChange={ ( next ) => update( 'sale_price', next ) }
					/>
					<FieldError
						id="oml-mem-sale_price-error"
						message={ errors.sale_price }
					/>
					<TextControl
						label={ __( 'Sale starts', 'ohmylms' ) }
						type="date"
						value={ form.sale_start_date }
						aria-invalid={ invalid( 'sale_start_date' ) }
						aria-describedby={ described( 'sale_start_date' ) }
						onChange={ ( next ) =>
							update( 'sale_start_date', next )
						}
					/>
					<FieldError
						id="oml-mem-sale_start_date-error"
						message={ errors.sale_start_date }
					/>
					<TextControl
						label={
							'0' === form.sale_price.trim()
								? __( 'Free until', 'ohmylms' )
								: __( 'Sale ends', 'ohmylms' )
						}
						type="date"
						value={ form.sale_end_date }
						help={ __(
							'Dates use the site timezone and include the whole day.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'sale_end_date' ) }
						aria-describedby={ described( 'sale_end_date' ) }
						onChange={ ( next ) => update( 'sale_end_date', next ) }
					/>
					<FieldError
						id="oml-mem-sale_end_date-error"
						message={ errors.sale_end_date }
					/>
					<TextControl
						label={ sprintf(
							/* translators: %s: currency code. */
							__( 'Sign-up fee (%s)', 'ohmylms' ),
							code
						) }
						value={ form.sign_up_fee }
						inputMode="decimal"
						help={ __(
							'Optional one-off fee. Leave empty for none.',
							'ohmylms'
						) }
						aria-invalid={ invalid( 'sign_up_fee' ) }
						aria-describedby={ described( 'sign_up_fee' ) }
						onChange={ ( next ) => update( 'sign_up_fee', next ) }
					/>
					<FieldError
						id="oml-mem-sign_up_fee-error"
						message={ errors.sign_up_fee }
					/>
					<SelectControl
						label={ __( 'Billing period', 'ohmylms' ) }
						value={ form.billing_period }
						options={ options.billing_periods }
						onChange={ ( next ) => {
							const found = options.billing_periods.find(
								( option ) => option.value === next
							);
							if ( found ) {
								update(
									'billing_period',
									found.value as FormState[ 'billing_period' ]
								);
							}
						} }
					/>
					<TextControl
						label={ __( 'Charge every (periods)', 'ohmylms' ) }
						value={ form.billing_interval }
						inputMode="numeric"
						aria-invalid={ invalid( 'billing_interval' ) }
						aria-describedby={ described( 'billing_interval' ) }
						onChange={ ( next ) =>
							update( 'billing_interval', next )
						}
					/>
					<FieldError
						id="oml-mem-billing_interval-error"
						message={ errors.billing_interval }
					/>
				</fieldset>

				<fieldset disabled={ busy }>
					<legend>{ __( 'Access', 'ohmylms' ) }</legend>
					<CheckboxControl
						label={ __( 'All learning content', 'ohmylms' ) }
						help={ __(
							'Include every published course, now and in the future.',
							'ohmylms'
						) }
						checked={ form.all_learning_content }
						onChange={ ( next ) =>
							update( 'all_learning_content', next )
						}
					/>
					{ form.curriculum_item_ids.length > 0 && (
						<p>
							{ sprintf(
								/* translators: %d: number of curriculum items. */
								__(
									'%d curriculum items are also included. They are kept as they are.',
									'ohmylms'
								),
								form.curriculum_item_ids.length
							) }
						</p>
					) }
					<CourseSelector
						legend={ __( 'Included courses', 'ohmylms' ) }
						help={ __(
							'Members get these courses. Courses that are not published are saved but not granted until they are published.',
							'ohmylms'
						) }
						selected={ form.courses }
						disabled={ busy }
						max={ options.limits.max_courses }
						error={ errors.courses }
						errorId="oml-mem-courses-error"
						onChange={ ( next ) => update( 'courses', next ) }
					/>
					<CourseSelector
						legend={ __( 'Excluded courses', 'ohmylms' ) }
						help={ __(
							'Never granted by this plan, even when another rule includes them.',
							'ohmylms'
						) }
						selected={ form.excluded_courses }
						disabled={ busy }
						max={ options.limits.max_courses }
						error={ errors.excluded_courses }
						errorId="oml-mem-excluded-error"
						onChange={ ( next ) =>
							update( 'excluded_courses', next )
						}
					/>
				</fieldset>

				<MembershipExtensionSettings
					plan={ {
						id: plan.id,
						name: saved.name,
						status: saved.status,
					} }
					namespaces={ options.extension_settings }
					values={ form.extension_settings }
					errors={ errors }
					disabled={ busy }
					onChange={ updateExtension }
				/>

				<Button
					type="submit"
					variant="primary"
					disabled={ busy || ! dirty }
					isBusy={ busy }
				>
					{ __( 'Save plan', 'ohmylms' ) }
				</Button>
			</form>

			<Members id={ plan.id } />

			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
					shouldCloseOnClickOutside={ false }
				>
					<p>
						{ __(
							'Your changes to this membership plan have not been saved.',
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
