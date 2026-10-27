import { createElement, createRoot } from '@wordpress/element';
import { RegistrationForm } from './RegistrationForm';
export function mountRegistration() {
	document
		.querySelectorAll( '[data-ohmylms-registration-fields]' )
		.forEach( ( host ) => {
			const form = host.closest( 'form' );
			const submitHost = form?.querySelector(
				'[data-ohmylms-registration-submit]'
			);
			if ( ! form || ! submitHost || host.dataset.mounted ) {
				return;
			}
			const countries = Array.from(
				host.querySelector( 'select' )?.options || [],
				( option ) => ( {
					value: option.value,
					label: option.text,
				} )
			);
			const icons = {
				on: host.querySelector( '.eye-on' )?.innerHTML || '',
				off: host.querySelector( '.eye-off' )?.innerHTML || '',
			};
			const initialValues = Object.fromEntries(
				[
					'first_name',
					'last_name',
					'email',
					'password',
					'phone',
					'country',
				].map( ( name ) => [
					name,
					form.elements[ name ]?.value || '',
				] )
			);
			const noticeHost = document.createElement( 'div' );
			form.after( noticeHost );
			host.dataset.mounted = 'true';
			submitHost.replaceChildren();
			createRoot( host ).render(
				createElement( RegistrationForm, {
					form,
					countries,
					submitHost,
					noticeHost,
					icons,
					initialValues,
				} )
			);
		} );
}
