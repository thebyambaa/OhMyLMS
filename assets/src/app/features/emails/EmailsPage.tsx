import { useParams, useSearchParams } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { EmailEditor } from './EmailEditor';
import { EmailList } from './EmailList';
import { SettingsForm } from './SettingsForm';

/**
 * Emails route. The editor is shown for `#/emails/<id>` when the shell provides an `:id` route
 * parameter, and for `#/emails?email=<id>`, which works with the shell's flat routes.
 */
export default function EmailsPage() {
	const { id } = useParams();
	const [ query ] = useSearchParams();
	const emailId = id ?? query.get( 'email' );

	if ( emailId ) {
		return <EmailEditor key={ emailId } id={ emailId } />;
	}
	return (
		<>
			<h2>{ __( 'Emails', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Choose which system emails are sent and edit what they say.',
					'ohmylms'
				) }
			</p>
			<EmailList />
			<SettingsForm />
		</>
	);
}
