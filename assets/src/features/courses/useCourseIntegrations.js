import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function useCourseIntegrations( { actions, enableSpin } ) {
	const [ automation, setAutomation ] = useState( null );
	const [ integration, setIntegration ] = useState( null );
	const listener = useRef( null );
	const openAutomation = useCallback(
		( forWhat, contentId, contentName ) => {
			if ( ! window.ohmylms_params?.is_mailmint_active ) {
				localStorage.setItem( 'clms_automation_for_what', forWhat );
				localStorage.setItem( 'clms_automation_content_id', contentId );
				localStorage.setItem(
					'clms_automation_content_name',
					contentName
				);
				actions.setIsProModalOpen( true );
				actions.updateProModalTitle(
					__( 'Missing Mail Mint Plugin!', 'ohmylms' )
				);
				actions.updateProModalContent(
					__(
						'Mail Mint is required to enable automation. Please install and activate the plugin.',
						'ohmylms'
					)
				);
				actions.updateProModalButtonText(
					__( 'Install and Activate', 'ohmylms' )
				);
				actions.updateProModalButtonAction( 'activate-mail-mint' );
				if ( listener.current ) {
					window.removeEventListener(
						'openAutomationModal',
						listener.current
					);
				}
				listener.current = ( event ) => setAutomation( event.detail );
				window.addEventListener(
					'openAutomationModal',
					listener.current
				);
				return;
			}
			setAutomation( { forWhat, contentId, contentName } );
		},
		[ actions ]
	);
	function openIntegration( forWhat, contentId, contentName ) {
		setIntegration( { forWhat, contentId, contentName } );
	}
	useEffect( () => {
		if (
			localStorage.getItem( 'ohmylms_automation_modal_open' ) &&
			! enableSpin
		) {
			openAutomation(
				localStorage.getItem( 'clms_automation_for_what' ),
				localStorage.getItem( 'clms_automation_content_id' ),
				localStorage.getItem( 'clms_automation_content_name' )
			);
			[
				'ohmylms_automation_modal_open',
				'clms_automation_for_what',
				'clms_automation_content_id',
				'clms_automation_content_name',
			].forEach( ( key ) => localStorage.removeItem( key ) );
		}
		return () => {
			if ( listener.current ) {
				window.removeEventListener(
					'openAutomationModal',
					listener.current
				);
			}
		};
	}, [ enableSpin, openAutomation ] );
	return {
		automation,
		integration,
		openAutomation,
		openIntegration,
		closeAutomation: () => setAutomation( null ),
		closeIntegration: () => setIntegration( null ),
	};
}
