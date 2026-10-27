import {
	createElement,
	useContext,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { AdminCard, AdminPage } from '../../extensions/AdminPage';
import { HubContext } from '../content-hub/context';
import { BundleEditor } from './BundleEditor';
import * as api from './api.mjs';
import { validateBundle } from './model.mjs';

/**
 * Course Bundles: named groupings of selected courses and curriculum items that can span
 * curricula. Creating and editing happen inline on this screen.
 */
export function BundlesPage() {
	const [ bundles, setBundles ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ openId, setOpenId ] = useState( 0 );
	const [ notice, setNotice ] = useState( null );
	const [ creating, setCreating ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ description, setDescription ] = useState( '' );
	const [ touched, setTouched ] = useState( false );
	const [ pending, setPending ] = useState( false );
	const errors = validateBundle( {
		title,
		description,
	} );
	const hub = useContext( HubContext );
	const reload = () => {
		setLoadError( '' );
		return api
			.listBundles()
			.then( ( response ) => setBundles( response.bundles || [] ) )
			.catch( ( error ) =>
				setLoadError(
					error?.message ||
						__( 'Could not load course bundles.', 'ohmylms' )
				)
			);
	};
	useEffect( () => {
		reload();
	}, [] );
	useEffect( () => {
		if ( ! notice || notice.kind !== 'success' ) {
			return undefined;
		}
		const timer = setTimeout(
			() =>
				setNotice( ( current ) =>
					current === notice ? null : current
				),
			8000
		);
		return () => clearTimeout( timer );
	}, [ notice ] );
	const say = ( kind, text ) =>
		setNotice( {
			id: Date.now() + Math.random(),
			kind,
			text,
		} );
	const replace = ( bundle ) =>
		setBundles( ( previous ) =>
			( previous || [] ).map( ( existing ) =>
				existing.id === bundle.id ? bundle : existing
			)
		);
	async function create( event ) {
		event.preventDefault();
		setTouched( true );
		if ( Object.keys( errors ).length || pending ) {
			return;
		}
		setPending( true );
		try {
			const response = await api.createBundle( {
				title,
				description,
			} );
			setBundles( ( previous ) => [
				...( previous || [] ),
				response.bundle,
			] );
			setOpenId( response.bundle.id );
			setCreating( false );
			setTitle( '' );
			setDescription( '' );
			setTouched( false );
			say(
				'success',
				sprintf(
					/* translators: %s: item label. */ __(
						'Created “%s”. Add courses or syllabuses, then publish it.',
						'ohmylms'
					),
					response.bundle.title
				)
			);
		} catch ( error ) {
			say(
				'error',
				error?.message ||
					__( 'The bundle could not be created.', 'ohmylms' )
			);
		} finally {
			setPending( false );
		}
	}
	return (
		<AdminPage
			className="ohmylms-bundles-admin"
			headingLevel={ hub ? 2 : 1 }
			title={ __( 'Course Bundles', 'ohmylms' ) }
			description={ __(
				'A Course Bundle groups selected courses and syllabuses, from any curricula, into one path learners can add to their dashboard. Following a bundle never enrols anyone or changes access.',
				'ohmylms'
			) }
			actions={
				<Button
					variant="primary"
					onClick={ () => {
						setCreating( true );
						setTimeout(
							() =>
								document
									.getElementById(
										'ohmylms-bundle-new-title'
									)
									?.focus(),
							0
						);
					} }
				>
					{ __( 'New bundle', 'ohmylms' ) }
				</Button>
			}
		>
			<div className="ohmylms-cur-status">
				{ notice && (
					<Notice
						key={ notice.id }
						status={ notice.kind }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.text }
					</Notice>
				) }
			</div>
			{ loadError && (
				<Notice status="error" isDismissible={ false }>
					{ loadError }{ ' ' }
					<Button variant="link" onClick={ reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! bundles && ! loadError && <Spinner /> }
			{ bundles && (
				<AdminCard>
					{ creating && (
						<form
							className="ohmylms-cur-add ohmylms-bundle-new"
							onSubmit={ create }
							noValidate
						>
							<TextControl
								id="ohmylms-bundle-new-title"
								label={ __(
									'Name of the new bundle',
									'ohmylms'
								) }
								value={ title }
								onChange={ setTitle }
								help={ ( () => {
									if ( touched && errors.title ) {
										if ( errors.title === 'required' ) {
											return __(
												'Enter a name for the bundle.',
												'ohmylms'
											);
										}
										return __(
											'That is too long.',
											'ohmylms'
										);
									}
									return undefined;
								} )() }
								__nextHasNoMarginBottom
							/>
							<TextareaControl
								label={ __(
									'Description (optional)',
									'ohmylms'
								) }
								value={ description }
								onChange={ setDescription }
								__nextHasNoMarginBottom
							/>
							<div className="ohmylms-cur-add-actions">
								<Button
									variant="primary"
									type="submit"
									isBusy={ pending }
									disabled={ pending }
								>
									{ __( 'Create bundle', 'ohmylms' ) }
								</Button>
								<Button
									variant="secondary"
									onClick={ () => setCreating( false ) }
								>
									{ __( 'Cancel', 'ohmylms' ) }
								</Button>
							</div>
						</form>
					) }
					{ ! bundles.length && ! creating && (
						<div className="ohmylms-cur-empty">
							<p>
								<strong>
									{ __(
										'No course bundles yet.',
										'ohmylms'
									) }
								</strong>
							</p>
							<p className="ohmylms-ext-muted">
								{ __(
									'Create a bundle such as “English Exam Preparation” and pick the courses and syllabuses that belong in it. Nothing is added automatically.',
									'ohmylms'
								) }
							</p>
							<Button
								variant="primary"
								onClick={ () => setCreating( true ) }
							>
								{ __( 'Create the first bundle', 'ohmylms' ) }
							</Button>
						</div>
					) }
					<ul
						className="ohmylms-cur-tree ohmylms-bundle-list"
						aria-label={ __( 'Course bundles', 'ohmylms' ) }
					>
						{ bundles.map( ( bundle ) => {
							const editing = openId === bundle.id;
							return (
								<li
									key={ bundle.id }
									className="ohmylms-cur-node"
								>
									<div
										className={ `ohmylms-cur-row${ editing ? ' is-editing' : '' }` }
										style={ {
											'--ohmylms-cur-depth': 0,
										} }
									>
										<span
											className="ohmylms-cur-toggle-spacer"
											aria-hidden="true"
										/>
										<span className="ohmylms-cur-type">
											{ bundle.status === 'published'
												? __( 'Published', 'ohmylms' )
												: __( 'Draft', 'ohmylms' ) }
										</span>
										<span className="ohmylms-cur-name">
											{ bundle.title }
										</span>
										<span className="ohmylms-cur-counts">
											{ sprintf(
												/* translators: %d: item count. */ _n(
													'%d member',
													'%d members',
													bundle.member_count,
													'ohmylms'
												),
												bundle.member_count
											) }
											{ bundle.follower_count > 0
												? ` · ${ sprintf( /* translators: %d: item count. */ _n( '%d follower', '%d followers', bundle.follower_count, 'ohmylms' ), bundle.follower_count ) }`
												: '' }
										</span>
										<span className="ohmylms-cur-actions">
											<Button
												variant="secondary"
												size="small"
												aria-expanded={ editing }
												aria-controls={
													editing
														? `ohmylms-bundle-panel-${ bundle.id }`
														: undefined
												}
												aria-label={
													editing
														? sprintf(
																/* translators: %s: item label. */ __(
																	'Close editor for %s',
																	'ohmylms'
																),
																bundle.title
															)
														: sprintf(
																/* translators: %s: item label. */ __(
																	'Edit %s',
																	'ohmylms'
																),
																bundle.title
															)
												}
												onClick={ () =>
													setOpenId(
														editing ? 0 : bundle.id
													)
												}
											>
												{ editing
													? __( 'Close', 'ohmylms' )
													: __( 'Edit', 'ohmylms' ) }
											</Button>
										</span>
									</div>
									{ editing && (
										<BundleEditor
											key={ bundle.id }
											bundle={ bundle }
											say={ say }
											onChanged={ replace }
											onDeleted={ ( id ) => {
												setBundles( ( previous ) =>
													previous.filter(
														( existing ) =>
															existing.id !== id
													)
												);
												setOpenId( 0 );
											} }
										/>
									) }
								</li>
							);
						} ) }
					</ul>
				</AdminCard>
			) }
		</AdminPage>
	);
}
