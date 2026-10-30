import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useRequest } from '../../hooks/useRequest';
import { useOperation } from '../../hooks/useOperation';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import {
	readSettings,
	saveSettings,
	type CourseSettings,
} from './settings-api';

export default function CourseSettingsPage() {
	const id = Number( useParams().id );
	return Number.isSafeInteger( id ) && id > 0 ? (
		<LoadedSettings key={ id } id={ id } />
	) : (
		<p role="alert">{ __( 'Invalid course ID.', 'ohmylms' ) }</p>
	);
}

function LoadedSettings( { id }: { id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readSettings( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	return (
		<>
			<h1>{ __( 'Course settings', 'ohmylms' ) }</h1>
			{ request.loading && (
				<p role="status">
					{ __( 'Loading course settings…', 'ohmylms' ) }
				</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<SettingsWorkspace
					key={ `${ id }:${ request.data.revision }` }
					initial={ request.data }
					reload={ request.retry }
				/>
			) }
		</>
	);
}

export function SettingsWorkspace( {
	initial,
	reload,
}: {
	initial: CourseSettings;
	reload: () => void;
} ) {
	const [ draft, setDraft ] = useState( initial );
	const [ saved, setSaved ] = useState( JSON.stringify( initial ) );
	const [ password, setPassword ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ confirmReload, setConfirmReload ] = useState( false );
	const operation = useOperation();
	const dirty = JSON.stringify( draft ) !== saved || password !== '';
	const guard = useUnsavedChangesGuard( dirty );
	function change( patch: Partial< CourseSettings > ) {
		setDraft( { ...draft, ...patch } );
		setMessage( '' );
	}
	const statusOptions: { value: string; label: string }[] = [
		{ value: 'draft', label: __( 'Draft', 'ohmylms' ) },
		{ value: 'pending', label: __( 'Pending review', 'ohmylms' ) },
		{ value: 'private', label: __( 'Private', 'ohmylms' ) },
		...( initial.status === 'publish'
			? [ { value: 'publish', label: __( 'Published', 'ohmylms' ) } ]
			: [] ),
	];
	return (
		<section aria-label={ __( 'Course settings editor', 'ohmylms' ) }>
			<p>
				<Link to={ `/content-hub/courses/${ draft.course_id }` }>
					{ __( 'Course structure and learning', 'ohmylms' ) }
				</Link>{ ' ' }
				·{ ' ' }
				<Link to="/content-hub/courses">
					{ __( 'Back to courses', 'ohmylms' ) }
				</Link>
			</p>
			<p>
				{ __(
					'Settings take effect when saved, including on published courses. Learning structure has its own draft and publication workflow.',
					'ohmylms'
				) }
			</p>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			<p role="status">
				{ dirty
					? __( 'Unsaved changes', 'ohmylms' )
					: __( 'Saved settings', 'ohmylms' ) }
			</p>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void operation.run(
						( signal ) => saveSettings( draft, password, signal ),
						( result ) => {
							setDraft( result );
							setSaved( JSON.stringify( result ) );
							setPassword( '' );
							setMessage(
								__( 'Course settings saved.', 'ohmylms' )
							);
						}
					);
				} }
			>
				<fieldset disabled={ operation.busy }>
					<legend>{ __( 'Course details', 'ohmylms' ) }</legend>
					<TextControl
						label={ __( 'Course title', 'ohmylms' ) }
						value={ draft.title }
						required
						maxLength={ 190 }
						onChange={ ( title ) => change( { title } ) }
					/>
					<TextareaControl
						label={ __(
							'Description (HTML supported)',
							'ohmylms'
						) }
						value={ draft.description }
						onChange={ ( description ) =>
							change( { description } )
						}
					/>
					<TextControl
						label={ __( 'Author user ID', 'ohmylms' ) }
						type="number"
						min="1"
						step="1"
						value={ draft.author_id }
						onChange={ ( value ) =>
							change( { author_id: Number( value ) } )
						}
						help={ __(
							'Choose an existing user who can author courses.',
							'ohmylms'
						) }
					/>
					<TextControl
						label={ __(
							'Featured image attachment ID',
							'ohmylms'
						) }
						type="number"
						min="0"
						step="1"
						value={ draft.image_id }
						onChange={ ( value ) =>
							change( { image_id: Number( value ) } )
						}
						help={ __(
							'Use an image ID from the Media Library, or 0 to remove the image.',
							'ohmylms'
						) }
					/>
					<SelectControl< string >
						label={ __( 'Course status', 'ohmylms' ) }
						value={ draft.status }
						options={ statusOptions }
						onChange={ ( status ) => change( { status } ) }
					/>
				</fieldset>
				<fieldset disabled={ operation.busy }>
					<legend>
						{ __( 'Pricing and enrollment', 'ohmylms' ) }
					</legend>
					<SelectControl< string >
						label={ __( 'Pricing', 'ohmylms' ) }
						value={ draft.price_type }
						options={ [
							{ value: 'free', label: __( 'Free', 'ohmylms' ) },
							{ value: 'paid', label: __( 'Paid', 'ohmylms' ) },
						] }
						onChange={ ( value ) =>
							change( { price_type: value } )
						}
					/>
					<TextControl
						label={ __( 'Regular price', 'ohmylms' ) }
						value={ draft.regular_price }
						type="number"
						min="0"
						step="any"
						required
						onChange={ ( value ) =>
							change( { regular_price: value } )
						}
					/>
					<TextControl
						label={ __( 'Sale price', 'ohmylms' ) }
						value={ draft.sale_price }
						type="number"
						min="0"
						step="any"
						onChange={ ( value ) =>
							change( { sale_price: value } )
						}
						help={ __(
							'Leave empty for no sale price. Existing sale schedules are preserved.',
							'ohmylms'
						) }
					/>
					<SelectControl< string >
						label={ __( 'Course access', 'ohmylms' ) }
						value={ draft.access_type }
						options={ [
							{
								value: 'public',
								label: __( 'Public', 'ohmylms' ),
							},
							{
								value: 'password_protected',
								label: __( 'Password protected', 'ohmylms' ),
							},
						] }
						onChange={ ( value ) =>
							change( { access_type: value } )
						}
					/>
					{ draft.access_type === 'password_protected' && (
						<TextControl
							label={ __( 'New course password', 'ohmylms' ) }
							value={ password }
							type="password"
							autoComplete="new-password"
							onChange={ setPassword }
							help={ __(
								'Leave empty to keep the existing password.',
								'ohmylms'
							) }
						/>
					) }
					<CheckboxControl
						label={ __( 'Limit enrollment capacity', 'ohmylms' ) }
						checked={ draft.has_capacity }
						onChange={ ( value ) =>
							change( { has_capacity: value } )
						}
					/>
					{ draft.has_capacity && (
						<TextControl
							label={ __( 'Enrollment capacity', 'ohmylms' ) }
							value={ draft.capacity }
							type="number"
							min="1"
							step="1"
							required
							onChange={ ( value ) =>
								change( { capacity: Number( value ) } )
							}
						/>
					) }
				</fieldset>
				<Button
					variant="primary"
					type="submit"
					isBusy={ operation.busy }
					disabled={
						operation.busy || ! dirty || ! draft.title.trim()
					}
				>
					{ __( 'Save course settings', 'ohmylms' ) }
				</Button>
				<Button
					disabled={ operation.busy }
					onClick={ () =>
						dirty ? setConfirmReload( true ) : reload()
					}
				>
					{ __( 'Reload saved settings', 'ohmylms' ) }
				</Button>
			</form>
			{ confirmReload && (
				<Modal
					title={ __( 'Discard unsaved settings?', 'ohmylms' ) }
					onRequestClose={ () => setConfirmReload( false ) }
				>
					<Button
						onClick={ () => {
							setConfirmReload( false );
							reload();
						} }
					>
						{ __( 'Discard and reload', 'ohmylms' ) }
					</Button>
					<Button onClick={ () => setConfirmReload( false ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Discard unsaved settings?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.leave }>
						{ __( 'Discard and leave', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
