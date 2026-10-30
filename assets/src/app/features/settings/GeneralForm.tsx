import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { restoreDefaultPages } from './api';
import { asNumber } from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';
import { describeFailure } from './model';
import { PagePicker } from './PagePicker';
import type { FieldName } from './types';

const PAGES: ReadonlyArray< {
	name: FieldName< 'general' >;
	label: string;
	help?: string;
} > = [
	{
		name: 'course_page_id',
		label: __( 'Course archive page', 'ohmylms' ),
		help: __( 'Lists all courses.', 'ohmylms' ),
	},
	{ name: 'checkout_page_id', label: __( 'Checkout page', 'ohmylms' ) },
	{
		name: 'thank_you_page_id',
		label: __( 'Thank you page', 'ohmylms' ),
		help: __( 'Shown after a successful checkout.', 'ohmylms' ),
	},
	{
		name: 'student_dashboard_page_id',
		label: __( 'Student dashboard page', 'ohmylms' ),
	},
	{
		name: 'student_profile_page_id',
		label: __( 'Student profile page', 'ohmylms' ),
	},
	{
		name: 'student_courses_page_id',
		label: __( 'My courses page', 'ohmylms' ),
	},
	{
		name: 'registration_page_id',
		label: __( 'Registration page', 'ohmylms' ),
	},
	{
		name: 'terms_page_id',
		label: __( 'Terms and conditions page', 'ohmylms' ),
	},
	{
		name: 'privacy_policy_page_id',
		label: __( 'Privacy policy page', 'ohmylms' ),
	},
	{
		name: 'profile_page_id',
		label: __( 'Membership profile page', 'ohmylms' ),
		help: __( 'Used by memberships.', 'ohmylms' ),
	},
];

/**
 * General tab: the pages the LMS links to, and re-creating the default pages.
 *
 * @param props       Component props.
 * @param props.state State of the `general` group.
 */
export function GeneralForm( {
	state,
}: {
	state: SettingsGroupState< 'general' >;
} ) {
	const [ restoring, setRestoring ] = useState( false );
	const [ outcome, setOutcome ] = useState< string | null >( null );
	const [ problem, setProblem ] = useState< string | null >( null );
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	async function restore() {
		setRestoring( true );
		setOutcome( null );
		setProblem( null );
		try {
			const result = await restoreDefaultPages();
			if ( ! mounted.current ) {
				return;
			}
			state.adopt( result.values );
			setOutcome(
				result.restored.length
					? sprintf(
							/* translators: %s: comma-separated page titles. */
							__( 'Restored: %s', 'ohmylms' ),
							result.restored
								.map( ( page ) => page.title )
								.join( ', ' )
						)
					: __(
							'All default pages already exist. Nothing changed.',
							'ohmylms'
						)
			);
		} catch ( failure ) {
			if ( mounted.current ) {
				setProblem(
					describeFailure( normalizeApiError( failure ) ).message
				);
			}
		} finally {
			if ( mounted.current ) {
				setRestoring( false );
			}
		}
	}

	const draft = state.draft;
	return (
		<FormFrame
			title={ __( 'General', 'ohmylms' ) }
			description={ __(
				'Choose the pages the LMS links to. Only published pages can be selected.',
				'ohmylms'
			) }
			state={ state }
			extra={
				<>
					{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void restore() }
						isBusy={ restoring }
						disabled={ restoring || state.saving }
						accessibleWhenDisabled
					>
						{ __( 'Restore default pages', 'ohmylms' ) }
					</Button>
					{ outcome && <span role="status"> { outcome }</span> }
					{ problem && <span role="alert"> { problem }</span> }
				</>
			}
		>
			{ draft &&
				PAGES.map( ( page ) => (
					<PagePicker
						key={ page.name }
						label={ page.label }
						help={ page.help }
						value={ asNumber( draft[ page.name ] ) || 0 }
						error={ state.errors[ page.name ] }
						disabled={ state.saving }
						onChange={ ( id ) => state.setField( page.name, id ) }
					/>
				) ) }
		</FormFrame>
	);
}
