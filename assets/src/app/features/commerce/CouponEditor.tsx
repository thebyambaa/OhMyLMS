import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useCourseOptions } from './hooks';
import {
	CODE_MAX_LENGTH,
	COURSE_OPTION_LIMIT,
	EMPTY_COUPON_FORM,
	couponToForm,
	toCouponPayload,
	validateCouponForm,
} from './model';
import type {
	Coupon,
	CouponField,
	CouponForm,
	CouponPayload,
	CouponStatus,
	CourseScope,
	DiscountType,
} from './types';

interface Props {
	coupon: Coupon | null;
	busy: boolean;
	error: string | null;
	onClose: () => void;
	onSave: ( payload: CouponPayload ) => void;
}

/**
 * Modal form to create or edit a coupon. Input survives a failed save.
 *
 * @param props         Component props.
 * @param props.coupon  Coupon being edited, or null to create one.
 * @param props.busy    True while a save is running.
 * @param props.error   Server error to show, if any.
 * @param props.onClose Closes the editor.
 * @param props.onSave  Receives the validated payload.
 */
export function CouponEditor( {
	coupon,
	busy,
	error,
	onClose,
	onSave,
}: Props ) {
	const [ form, setForm ] = useState< CouponForm >(
		coupon ? couponToForm( coupon ) : EMPTY_COUPON_FORM
	);
	const [ errors, setErrors ] = useState<
		Partial< Record< CouponField, string > >
	>( {} );
	const courses = useCourseOptions( '' );
	const update = < K extends CouponField >(
		key: K,
		value: CouponForm[ K ]
	) => {
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
		setErrors( ( current ) => ( { ...current, [ key ]: undefined } ) );
	};
	const toggleCourse = ( id: number, checked: boolean ) =>
		update(
			'course_ids',
			checked
				? [ ...form.course_ids, id ]
				: form.course_ids.filter( ( value ) => value !== id )
		);
	const invalid = Object.values( errors ).some( Boolean );

	return (
		<Modal
			title={
				coupon
					? __( 'Edit coupon', 'ohmylms' )
					: __( 'Add coupon', 'ohmylms' )
			}
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( busy ) {
						return;
					}
					const found = validateCouponForm( form );
					setErrors( found );
					if ( 0 === Object.keys( found ).length ) {
						onSave( toCouponPayload( form ) );
					}
				} }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ invalid && (
					<Notice status="error" isDismissible={ false }>
						{ __( 'Fix the highlighted fields.', 'ohmylms' ) }
					</Notice>
				) }
				<fieldset disabled={ busy }>
					<legend>{ __( 'Coupon', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Code', 'ohmylms' ) }
						value={ form.code }
						maxLength={ CODE_MAX_LENGTH }
						required
						help={
							errors.code ??
							__( 'Customers type this at checkout.', 'ohmylms' )
						}
						onChange={ ( value ) => update( 'code', value ) }
					/>
					<TextControl
						label={ __( 'Label', 'ohmylms' ) }
						value={ form.title }
						help={ __( 'Only visible to staff.', 'ohmylms' ) }
						onChange={ ( value ) => update( 'title', value ) }
					/>
					<TextareaControl
						label={ __( 'Description', 'ohmylms' ) }
						value={ form.description }
						onChange={ ( value ) => update( 'description', value ) }
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ form.status }
						options={ [
							{
								value: 'publish',
								label: __( 'Active', 'ohmylms' ),
							},
							{
								value: 'draft',
								label: __( 'Disabled', 'ohmylms' ),
							},
						] }
						onChange={ ( value ) =>
							update(
								'status',
								( 'draft' === value
									? 'draft'
									: 'publish' ) as CouponStatus
							)
						}
					/>
					<SelectControl
						label={ __( 'Discount type', 'ohmylms' ) }
						value={ form.discount_type }
						options={ [
							{
								value: 'percent',
								label: __( 'Percentage', 'ohmylms' ),
							},
							{
								value: 'flat-rate',
								label: __( 'Fixed amount', 'ohmylms' ),
							},
						] }
						onChange={ ( value ) =>
							update(
								'discount_type',
								( 'flat-rate' === value
									? 'flat-rate'
									: 'percent' ) as DiscountType
							)
						}
					/>
					<TextControl
						label={
							'percent' === form.discount_type
								? __( 'Discount (%)', 'ohmylms' )
								: __( 'Discount amount', 'ohmylms' )
						}
						value={ form.amount }
						inputMode="decimal"
						required
						help={ errors.amount }
						onChange={ ( value ) => update( 'amount', value ) }
					/>
					<TextControl
						label={ __( 'Starts', 'ohmylms' ) }
						type="datetime-local"
						value={ form.date_start }
						help={ __( 'Optional. Site time.', 'ohmylms' ) }
						onChange={ ( value ) => update( 'date_start', value ) }
					/>
					<TextControl
						label={ __( 'Expires', 'ohmylms' ) }
						type="datetime-local"
						value={ form.date_expires }
						help={
							errors.date_expires ??
							__( 'Optional. Site time.', 'ohmylms' )
						}
						onChange={ ( value ) =>
							update( 'date_expires', value )
						}
					/>
					<TextControl
						label={ __( 'Total usage limit', 'ohmylms' ) }
						value={ form.usage_limit }
						inputMode="numeric"
						help={
							errors.usage_limit ??
							__( 'Leave blank for unlimited.', 'ohmylms' )
						}
						onChange={ ( value ) => update( 'usage_limit', value ) }
					/>
					<TextControl
						label={ __( 'Limit per customer', 'ohmylms' ) }
						value={ form.usage_limit_per_user }
						inputMode="numeric"
						help={
							errors.usage_limit_per_user ??
							__( 'Leave blank for unlimited.', 'ohmylms' )
						}
						onChange={ ( value ) =>
							update( 'usage_limit_per_user', value )
						}
					/>
					<CheckboxControl
						label={ __(
							'Cannot be combined with other coupons',
							'ohmylms'
						) }
						checked={ form.individual_use }
						onChange={ ( value ) =>
							update( 'individual_use', value )
						}
					/>
					<SelectControl
						label={ __( 'Applies to', 'ohmylms' ) }
						value={ form.course_id_type }
						options={ [
							{
								value: 'all',
								label: __( 'All courses', 'ohmylms' ),
							},
							{
								value: 'selected_course',
								label: __( 'Selected courses', 'ohmylms' ),
							},
						] }
						onChange={ ( value ) =>
							update(
								'course_id_type',
								( 'selected_course' === value
									? 'selected_course'
									: 'all' ) as CourseScope
							)
						}
					/>
					{ 'selected_course' === form.course_id_type && (
						<fieldset>
							<legend>{ __( 'Courses', 'ohmylms' ) }</legend>
							{ courses.error && (
								<Notice
									status="warning"
									isDismissible={ false }
								>
									{ __(
										'Courses could not be loaded.',
										'ohmylms'
									) }
								</Notice>
							) }
							{ errors.course_ids && (
								<p role="alert">{ errors.course_ids }</p>
							) }
							{ ( courses.data?.items ?? [] ).map( ( course ) => (
								<CheckboxControl
									key={ course.id }
									label={ course.title }
									checked={ form.course_ids.includes(
										course.id
									) }
									onChange={ ( checked ) =>
										toggleCourse( course.id, checked )
									}
								/>
							) ) }
							{ ( courses.data?.total ?? 0 ) >
								COURSE_OPTION_LIMIT && (
								<p>
									{ __(
										'Only the first 100 courses are listed.',
										'ohmylms'
									) }
								</p>
							) }
						</fieldset>
					) }
				</fieldset>
				<Button
					type="submit"
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
				>
					{ coupon
						? __( 'Save coupon', 'ohmylms' )
						: __( 'Create coupon', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}
