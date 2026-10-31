import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../../lib/api';
import { SearchPicker } from '../SearchPicker';
import { useOperation } from '../useOperation';
import { createCohort, searchTargets, updateCohort } from './api';
import {
	COHORT_CAPACITY_MAX,
	COHORT_TITLE_MAX,
	cohortBody,
	cohortDraft,
	emptyCohortDraft,
	validateCohort,
	type Cohort,
	type CohortDraft,
} from './model';

function messagesOf( error: unknown ): Record< string, string > {
	const details = normalizeApiError( error ).details;
	const params =
		details && 'object' === typeof details
			? ( details as { params?: unknown } ).params
			: null;
	return params && 'object' === typeof params
		? ( params as Record< string, string > )
		: {};
}

/**
 * Create or edit a cohort in a dialog. The course or plan cannot change after creation.
 *
 * @param props         Component props.
 * @param props.cohort  Cohort to edit, or null to create one.
 * @param props.onSaved Receives the stored cohort.
 * @param props.onClose Closes the dialog.
 */
export function CohortForm( {
	cohort,
	onSaved,
	onClose,
}: {
	cohort: Cohort | null;
	onSaved: ( cohort: Cohort ) => void;
	onClose: () => void;
} ) {
	const [ draft, setDraft ] = useState< CohortDraft >(
		cohort ? cohortDraft( cohort ) : emptyCohortDraft()
	);
	const [ errors, setErrors ] = useState< Record< string, string > >( {} );
	const operation = useOperation();
	const set = ( change: Partial< CohortDraft >, field: string ) => {
		setDraft( ( current ) => ( { ...current, ...change } ) );
		setErrors( ( current ) => {
			const next = { ...current };
			delete next[ field ];
			return next;
		} );
	};
	const submit = async () => {
		const invalid = validateCohort( draft );
		setErrors( invalid );
		if ( Object.keys( invalid ).length ) {
			return;
		}
		let saved: Cohort | null = null;
		const done = await operation.run( async ( signal ) => {
			try {
				saved = cohort
					? await updateCohort(
							cohort.id,
							cohortBody( draft, cohort ),
							signal
						)
					: await createCohort( cohortBody( draft ), signal );
			} catch ( failure ) {
				setErrors( messagesOf( failure ) );
				throw failure;
			}
		} );
		if ( done && saved ) {
			onSaved( saved );
		}
	};
	const dateField = (
		field: 'start' | 'end' | 'enrollmentStart' | 'enrollmentEnd',
		key: string,
		label: string,
		help: string
	) => (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			type="datetime-local"
			label={ label }
			help={ errors[ key ] ?? help }
			value={ draft[ field ] }
			aria-invalid={ errors[ key ] ? true : undefined }
			onChange={ ( value ) => set( { [ field ]: value }, key ) }
		/>
	);

	return (
		<Modal
			title={
				cohort
					? __( 'Edit cohort', 'ohmylms' )
					: __( 'Add cohort', 'ohmylms' )
			}
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					void submit();
				} }
			>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Title', 'ohmylms' ) }
					value={ draft.title }
					maxLength={ COHORT_TITLE_MAX }
					help={ errors.title }
					aria-invalid={ errors.title ? true : undefined }
					onChange={ ( title ) => set( { title }, 'title' ) }
				/>
				{ cohort ? (
					<p>
						{ 'course' === draft.scope
							? __( 'Course:', 'ohmylms' )
							: __( 'Membership plan:', 'ohmylms' ) }{ ' ' }
						<strong>{ draft.targetTitle }</strong>
					</p>
				) : (
					<>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Group learners of', 'ohmylms' ) }
							value={ draft.scope }
							options={ [
								{
									value: 'course',
									label: __( 'A course', 'ohmylms' ),
								},
								{
									value: 'membership',
									label: __( 'A membership plan', 'ohmylms' ),
								},
							] }
							onChange={ ( scope ) =>
								set(
									{
										scope:
											'membership' === scope
												? 'membership'
												: 'course',
										targetId: 0,
										targetTitle: '',
									},
									'scope'
								)
							}
						/>
						<SearchPicker
							key={ draft.scope }
							label={
								'course' === draft.scope
									? __( 'Course', 'ohmylms' )
									: __( 'Membership plan', 'ohmylms' )
							}
							value={ draft.targetId }
							valueLabel={ draft.targetTitle }
							search={ ( term, signal ) =>
								searchTargets( draft.scope, term, signal )
							}
							error={ errors.course_id ?? errors.membership_id }
							help={
								'course' === draft.scope
									? __(
											'The course becomes cohort-based: it can be bought only while a cohort takes learners.',
											'ohmylms'
										)
									: undefined
							}
							onChange={ ( targetId, targetTitle ) =>
								set(
									{ targetId, targetTitle },
									'course' === draft.scope
										? 'course_id'
										: 'membership_id'
								)
							}
						/>
					</>
				) }
				{ dateField(
					'start',
					'start_date',
					__( 'Starts', 'ohmylms' ),
					__(
						'Members cannot open the course before this date.',
						'ohmylms'
					)
				) }
				{ dateField(
					'end',
					'end_date',
					__( 'Ends (optional)', 'ohmylms' ),
					__( 'Enrolment closes at the latest here.', 'ohmylms' )
				) }
				{ dateField(
					'enrollmentStart',
					'enrollment_start',
					__( 'Enrolment opens (optional)', 'ohmylms' ),
					__(
						'Learners who enrol or buy from this date join this cohort.',
						'ohmylms'
					)
				) }
				{ dateField(
					'enrollmentEnd',
					'enrollment_end',
					__( 'Enrolment deadline (optional)', 'ohmylms' ),
					__( 'After this date the cohort takes no one.', 'ohmylms' )
				) }
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="number"
					min={ 1 }
					max={ COHORT_CAPACITY_MAX }
					label={ __( 'Seats (optional)', 'ohmylms' ) }
					help={
						errors.capacity ??
						__(
							'Leave empty for no limit. A full cohort sends new learners to the next open one.',
							'ohmylms'
						)
					}
					aria-invalid={ errors.capacity ? true : undefined }
					value={ draft.capacity }
					onChange={ ( capacity ) => set( { capacity }, 'capacity' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Close access when the cohort ends',
						'ohmylms'
					) }
					help={ __(
						'Members can no longer open the course content after the end date.',
						'ohmylms'
					) }
					checked={ draft.closeAccessAtEnd }
					onChange={ ( closeAccessAtEnd ) =>
						set( { closeAccessAtEnd }, 'close_access_at_end' )
					}
				/>
				{ cohort && (
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Archived', 'ohmylms' ) }
						help={ __(
							'An archived cohort takes no new members and no longer affects access.',
							'ohmylms'
						) }
						checked={ 'archived' === draft.status }
						onChange={ ( archived ) =>
							set(
								{ status: archived ? 'archived' : 'active' },
								'status'
							)
						}
					/>
				) }
				<Button
					type="submit"
					variant="primary"
					isBusy={ operation.busy }
					disabled={ operation.busy }
				>
					{ cohort
						? __( 'Save cohort', 'ohmylms' )
						: __( 'Create cohort', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}
