/** @jsx createElement */
import {
	createElement,
	createRoot,
	render,
	useState,
	useRef,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function Launcher() {
	const [ Panel, setPanel ] = useState( null );
	const [ open, setOpen ] = useState( false );
	const [ error, setError ] = useState( '' );
	const button = useRef( null );
	return (
		<>
			{ Panel && (
				<div hidden={ ! open }>
					<Panel
						active={ open }
						onClose={ () => {
							setOpen( false );
							button.current?.focus();
						} }
					/>
				</div>
			) }
			{ error && <p role="alert">{ error }</p> }
			<button
				ref={ button }
				className="oml-assistant-launcher"
				type="button"
				aria-expanded={ open }
				aria-controls="oml-assistant-panel"
				onClick={ async () => {
					try {
						if ( ! Panel ) {
							const loaded = await import(
								/* webpackChunkName: "admin-assistant-panel" */ './AssistantPanel'
							);
							setPanel( () => loaded.default );
						}
						setError( '' );
						setOpen( ! open );
					} catch {
						setError(
							__(
								'Assistant could not load. Try again.',
								'ohmylms'
							)
						);
					}
				} }
			>
				{ __( 'Ask OhMyLMS', 'ohmylms' ) }
			</button>
		</>
	);
}

/**
 * Mount only into the server-authorized administrator root.
 * @param {HTMLElement} element Mount point.
 */
export function mountAssistant( element ) {
	if ( createRoot ) {
		createRoot( element ).render( <Launcher /> );
	} else {
		render( <Launcher />, element );
	}
}
