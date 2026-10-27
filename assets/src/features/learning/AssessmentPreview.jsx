/** @jsx createElement */
import {
	createContext,
	createElement,
	useContext,
	useRef,
	useState,
} from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { saveQuizForPreview } from './quizPreview.mjs';

const PreviewContext = createContext( null );

/**
 * Host one student player for all preview buttons in the assessment editor.
 *
 * @param {Object}  props          Component properties.
 * @param {Element} props.children Editor controls.
 * @return {Element} Controls and the shared preview dialog.
 */
export function AssessmentPreviewProvider( { children } ) {
	const [ opened, setOpened ] = useState( false );
	const [ source, setSource ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ session, setSession ] = useState( 0 );
	const request = useRef( 0 );
	const { savePost } = useDispatch( 'core/editor' );
	const close = () => {
		request.current++;
		setOpened( false );
		setSource( '' );
	};
	const open = async () => {
		const current = ++request.current;
		setOpened( true );
		setSource( '' );
		setError( '' );
		try {
			if (
				! ( await saveQuizForPreview( {
					editor: select( 'core/editor' ),
					savePost,
				} ) )
			) {
				throw new Error( 'assessment-preview-not-ready' );
			}
			if ( current === request.current ) {
				setSession( ( value ) => value + 1 );
				setSource( window.ohmylmsNativeLesson.previewUrl );
			}
		} catch {
			if ( current === request.current ) {
				setError(
					__(
						'The assessment could not be saved for preview. Check the questions and settings, then try again.',
						'ohmylms'
					)
				);
			}
		}
	};
	return (
		<PreviewContext.Provider value={ { open } }>
			{ children }
			{ opened && (
				<Modal
					title={ __( 'Student preview', 'ohmylms' ) }
					className="ohmylms-assessment-preview"
					onRequestClose={ close }
				>
					<div className="ohmylms-assessment-preview-toolbar">
						<p>
							{ __(
								'Try the student player. Preview responses do not count as learner attempts.',
								'ohmylms'
							) }
						</p>
						<Button
							variant="secondary"
							onClick={ open }
							disabled={ ! source && ! error }
						>
							{ __( 'Restart preview', 'ohmylms' ) }
						</Button>
					</div>
					{ error ? (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) : null }
					{ ! error && ! source && (
						<p role="status">
							<Spinner />
							{ __(
								'Saving assessment and preparing preview…',
								'ohmylms'
							) }
						</p>
					) }
					{ ! error && source && (
						<iframe
							key={ session }
							src={ source }
							title={ __(
								'Student assessment player',
								'ohmylms'
							) }
							className="ohmylms-assessment-preview-frame"
							onLoad={ ( event ) => {
								try {
									const location =
										event.currentTarget.contentWindow
											.location;
									if (
										location.pathname.endsWith(
											'/wp-admin/post.php'
										)
									) {
										close();
									}
								} catch {
									// A redirected sign-in page can use a different origin.
								}
							} }
						/>
					) }
				</Modal>
			) }
		</PreviewContext.Provider>
	);
}

/**
 * Open the shared student preview after pending question edits are complete.
 *
 * @return {Element} Preview button.
 */
export function AssessmentPreviewButton() {
	const preview = useContext( PreviewContext );
	const { locked, saving } = useSelect(
		( getStore ) => ( {
			locked: getStore( 'core/editor' ).isPostSavingLocked(),
			saving: getStore( 'core/editor' ).isSavingPost(),
		} ),
		[]
	);
	return (
		<Button
			variant="secondary"
			disabled={ locked || saving }
			onClick={ preview?.open }
			title={
				locked
					? __(
							'Complete the question and its answer key before previewing.',
							'ohmylms'
						)
					: __(
							'Try the full student experience inside the editor.',
							'ohmylms'
						)
			}
		>
			{ locked
				? __( 'Complete question to preview', 'ohmylms' )
				: __( 'Student preview', 'ohmylms' ) }
		</Button>
	);
}
