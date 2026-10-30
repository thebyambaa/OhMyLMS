import { __ } from '@wordpress/i18n';
import {
	asFlag,
	asText,
	SelectField,
	TextAreaField,
	ToggleField,
} from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';

/**
 * Account and privacy tab.
 *
 * @param props       Component props.
 * @param props.state State of the `account-and-privacy` group.
 */
export function AccountForm( {
	state,
}: {
	state: SettingsGroupState< 'account-and-privacy' >;
} ) {
	const draft = state.draft;
	return (
		<FormFrame
			title={ __( 'Account and privacy', 'ohmylms' ) }
			description={ __(
				'How students buy and register, and the privacy note shown at checkout.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<ToggleField
						label={ __( 'Allow guest checkout', 'ohmylms' ) }
						help={ __(
							'Visitors can buy a course without creating an account first.',
							'ohmylms'
						) }
						checked={ asFlag( draft.allow_purchase_without_login ) }
						error={ state.errors.allow_purchase_without_login }
						onChange={ ( value ) =>
							state.setField(
								'allow_purchase_without_login',
								value
							)
						}
					/>
					<ToggleField
						label={ __( 'Require email verification', 'ohmylms' ) }
						help={ __(
							'Students must verify their email address before they can open course content.',
							'ohmylms'
						) }
						checked={ asFlag( draft.require_email_verification ) }
						error={ state.errors.require_email_verification }
						onChange={ ( value ) =>
							state.setField(
								'require_email_verification',
								value
							)
						}
					/>
					<SelectField
						label={ __( 'Checkout phone field', 'ohmylms' ) }
						help={ __(
							'Some payment gateways can still make the phone number required on their own.',
							'ohmylms'
						) }
						value={ asText( draft.checkout_phone_field ) }
						options={ [
							{
								value: 'optional',
								label: __( 'Optional', 'ohmylms' ),
							},
							{
								value: 'required',
								label: __( 'Required', 'ohmylms' ),
							},
							{
								value: 'hidden',
								label: __( 'Hidden', 'ohmylms' ),
							},
						] }
						error={ state.errors.checkout_phone_field }
						onChange={ ( value ) =>
							state.setField( 'checkout_phone_field', value )
						}
					/>
					<TextAreaField
						label={ __( 'Privacy policy message', 'ohmylms' ) }
						help={ __(
							'Shown at checkout. Basic HTML is allowed; [privacy_policy] becomes a link to your policy page.',
							'ohmylms'
						) }
						value={ asText( draft.privacy_policy_message ) }
						error={ state.errors.privacy_policy_message }
						onChange={ ( value ) =>
							state.setField( 'privacy_policy_message', value )
						}
					/>
				</>
			) }
		</FormFrame>
	);
}
