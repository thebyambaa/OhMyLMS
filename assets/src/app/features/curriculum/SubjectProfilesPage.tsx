import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { normalizeApiError } from '../../lib/api';
import {
	formOf,
	profileCommand,
	readProfiles,
	saveProfile,
	type Profile,
	type ProfileForm,
	type ProfilesPayload,
} from './subject-profiles';

const CONFLICT = 'ohmylms_subject_profile_conflict';

/** Subject profiles: domains, ordered levels and AI context of each subject's skills. */
export default function SubjectProfilesPage() {
	const [ data, setData ] = useState< ProfilesPayload | null >( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ version, setVersion ] = useState( 0 );
	const [ form, setForm ] = useState< ProfileForm | null >( null );
	const [ baseline, setBaseline ] = useState( '' );
	const [ stale, setStale ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ removing, setRemoving ] = useState< Profile | null >( null );
	const operation = useOperation();
	const dirty = form !== null && JSON.stringify( form ) !== baseline;
	const guard = useUnsavedChangesGuard( dirty );
	useEffect( () => {
		const controller = new AbortController();
		setLoadError( '' );
		readProfiles( controller.signal ).then(
			( payload ) => ! controller.signal.aborted && setData( payload ),
			( failure ) =>
				! controller.signal.aborted &&
				setLoadError( normalizeApiError( failure ).message )
		);
		return () => controller.abort();
	}, [ version ] );
	function open( profile: Profile | null ) {
		const next = formOf( profile );
		setForm( next );
		setBaseline( JSON.stringify( next ) );
		setStale( false );
		operation.clearError();
	}
	function change( patch: Partial< ProfileForm > ) {
		setForm( ( current ) =>
			current ? { ...current, ...patch } : current
		);
	}
	async function guarded< T >(
		work: ( signal: AbortSignal ) => Promise< T >,
		signal: AbortSignal
	) {
		try {
			return await work( signal );
		} catch ( failure ) {
			if ( normalizeApiError( failure ).code === CONFLICT ) {
				setStale( true );
			}
			throw failure;
		}
	}
	function save() {
		if ( ! form || ! data ) {
			return;
		}
		setMessage( '' );
		void operation.run(
			( signal ) =>
				guarded(
					( inner ) => saveProfile( form, data.revision, inner ),
					signal
				),
			( payload ) => {
				setData( payload );
				const saved =
					payload.profiles.find( ( row ) => row.key === form.key ) ??
					payload.profiles.find(
						( row ) => row.label === form.label.trim()
					) ??
					null;
				open( saved );
				setMessage( __( 'Profile saved.', 'ohmylms' ) );
			}
		);
	}
	function command( action: 'remove' | 'default', profile: Profile ) {
		if ( ! data ) {
			return;
		}
		setMessage( '' );
		void operation.run(
			( signal ) =>
				guarded(
					( inner ) =>
						profileCommand(
							action,
							profile.key,
							data.revision,
							inner
						),
					signal
				),
			( payload ) => {
				setData( payload );
				setRemoving( null );
				if ( action === 'remove' ) {
					setForm( null );
				}
				setMessage(
					action === 'default'
						? __( 'Default profile saved.', 'ohmylms' )
						: __( 'Profile changes removed.', 'ohmylms' )
				);
			}
		);
	}
	function reload() {
		void operation.run(
			( signal ) => readProfiles( signal ),
			( payload ) => {
				setData( payload );
				setStale( false );
				setMessage(
					__(
						'Profiles reloaded. Your unsaved edits were kept; save again to apply them.',
						'ohmylms'
					)
				);
			}
		);
	}
	const busy = operation.busy;
	return (
		<section>
			{ data && (
				<PageTitleActions>
					<PageTitleAction
						disabled={ busy || dirty }
						onClick={ () => open( null ) }
					>
						{ __( 'Add profile', 'ohmylms' ) }
					</PageTitleAction>
				</PageTitleActions>
			) }
			<p>
				{ __(
					'A profile describes how a subject’s skills are organised: its domains, ordered levels and the context AI analysis is given. Saving a profile also creates or renames its curriculum subject. Generic is the fallback profile.',
					'ohmylms'
				) }
			</p>
			{ loadError && (
				<Notice status="error" isDismissible={ false }>
					{ loadError }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => setVersion( ( value ) => value + 1 ) }
					>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! data && ! loadError && (
				<p role="status">{ __( 'Loading profiles…', 'ohmylms' ) }</p>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
					{ stale && (
						<>
							{ ' ' }
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ reload }
							>
								{ __(
									'Reload profiles and keep my edits',
									'ohmylms'
								) }
							</Button>
						</>
					) }
				</Notice>
			) }
			{ data && (
				<>
					<ListTable
						caption={ __( 'Profiles', 'ohmylms' ) }
						heading={ __( 'Subject profiles list', 'ohmylms' ) }
						rows={ data.profiles }
						rowKey={ ( profile ) => profile.key }
						columns={ [
							{
								id: 'profile',
								label: __( 'Profile', 'ohmylms' ),
								primary: true,
								render: ( profile ) => (
									<strong className="row-title">
										{ profile.label }
									</strong>
								),
							},
							{
								id: 'levels',
								label: __( 'Levels', 'ohmylms' ),
								className: 'num',
								render: ( profile ) => profile.levels.length,
							},
							{
								id: 'status',
								label: __( 'Status', 'ohmylms' ),
								render: ( profile ) =>
									[
										profile.builtin
											? __( 'Built-in', 'ohmylms' )
											: __( 'Custom', 'ohmylms' ),
										profile.builtin && profile.customized
											? __( 'customised', 'ohmylms' )
											: '',
										data.default === profile.key
											? __( 'default', 'ohmylms' )
											: '',
									]
										.filter( Boolean )
										.join( ', ' ),
							},
						] }
						rowActions={ ( profile ) => [
							{
								key: 'edit',
								label: __( 'Edit', 'ohmylms' ),
								disabled: busy || dirty,
								ariaLabel: sprintf(
									/* translators: %s: profile name. */
									__( 'Edit profile: %s', 'ohmylms' ),
									profile.label
								),
								onClick: () => open( profile ),
							},
							data.default !== profile.key && {
								key: 'default',
								label: __( 'Make default', 'ohmylms' ),
								disabled: busy,
								onClick: () => command( 'default', profile ),
							},
							profile.customized && {
								key: 'delete',
								label: profile.builtin
									? __( 'Restore built-in', 'ohmylms' )
									: __( 'Remove', 'ohmylms' ),
								destructive: true,
								disabled: busy,
								onClick: () => setRemoving( profile ),
							},
						] }
					/>
					{ data.subjects.length > 0 && (
						<>
							<h2>{ __( 'Subjects in use', 'ohmylms' ) }</h2>
							<ul>
								{ data.subjects.map( ( row ) => (
									<li key={ row.subject }>
										{ sprintf(
											/* translators: 1: subject, 2: number of skills, 3: profile key. */
											__(
												'%1$s: %2$d skills, profile %3$s',
												'ohmylms'
											),
											row.subject,
											row.skills,
											row.profile
										) }
									</li>
								) ) }
							</ul>
						</>
					) }
				</>
			) }
			{ form && data && (
				<form
					aria-label={ __( 'Profile details', 'ohmylms' ) }
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! busy && ! stale ) {
							save();
						}
					} }
				>
					<h2>
						{ form.key
							? __( 'Edit profile', 'ohmylms' )
							: __( 'New profile', 'ohmylms' ) }
					</h2>
					<fieldset disabled={ busy }>
						<TextControl
							label={ __( 'Subject name', 'ohmylms' ) }
							value={ form.label }
							maxLength={ 100 }
							required
							help={
								form.key
									? __(
											'Renaming keeps the profile key, so skills keep their subject.',
											'ohmylms'
										)
									: undefined
							}
							onChange={ ( label ) => change( { label } ) }
						/>
						<TextControl
							label={ __( 'Language code', 'ohmylms' ) }
							value={ form.language }
							maxLength={ 20 }
							help={ __(
								'For example en or mn. Optional.',
								'ohmylms'
							) }
							onChange={ ( language ) => change( { language } ) }
						/>
						<fieldset>
							<legend>{ __( 'Domains', 'ohmylms' ) }</legend>
							{ form.domains.map( ( domain, index ) => (
								<div key={ index }>
									<TextControl
										label={ sprintf(
											/* translators: %d: domain number. */
											__( 'Domain %d name', 'ohmylms' ),
											index + 1
										) }
										value={ domain.label }
										maxLength={ 100 }
										onChange={ ( label ) =>
											change( {
												domains: form.domains.map(
													( row, at ) =>
														at === index
															? { ...row, label }
															: row
												),
											} )
										}
									/>
									<TextControl
										label={ sprintf(
											/* translators: %d: domain number. */
											__( 'Domain %d id', 'ohmylms' ),
											index + 1
										) }
										value={ domain.id }
										maxLength={ 40 }
										help={ __(
											'Lower-case letters, digits and hyphens. Leave empty to build it from the name.',
											'ohmylms'
										) }
										onChange={ ( id ) =>
											change( {
												domains: form.domains.map(
													( row, at ) =>
														at === index
															? { ...row, id }
															: row
												),
											} )
										}
									/>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											change( {
												domains: form.domains.filter(
													( _row, at ) => at !== index
												),
											} )
										}
									>
										{ sprintf(
											/* translators: %d: domain number. */
											__( 'Remove domain %d', 'ohmylms' ),
											index + 1
										) }
									</Button>
								</div>
							) ) }
							<Button
								variant="secondary"
								disabled={ form.domains.length >= 60 }
								onClick={ () =>
									change( {
										domains: [
											...form.domains,
											{ id: '', label: '' },
										],
									} )
								}
							>
								{ __( 'Add domain', 'ohmylms' ) }
							</Button>
						</fieldset>
						<TextareaControl
							label={ __(
								'Levels, one per line, in order',
								'ohmylms'
							) }
							value={ form.levels }
							onChange={ ( levels ) => change( { levels } ) }
						/>
						<TextControl
							label={ __( 'AI role', 'ohmylms' ) }
							value={ form.role }
							maxLength={ 2000 }
							onChange={ ( role ) => change( { role } ) }
						/>
						<TextareaControl
							label={ __( 'AI context', 'ohmylms' ) }
							value={ form.context }
							maxLength={ 2000 }
							onChange={ ( context ) => change( { context } ) }
						/>
						<TextareaControl
							label={ __( 'AI level hints', 'ohmylms' ) }
							value={ form.level_hints }
							maxLength={ 2000 }
							onChange={ ( levelHints ) =>
								change( { level_hints: levelHints } )
							}
						/>
						<Button
							variant="primary"
							type="submit"
							disabled={ stale || ! form.label.trim() }
							isBusy={ busy }
						>
							{ __( 'Save profile', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="secondary"
							onClick={ () => setForm( null ) }
						>
							{ dirty
								? __( 'Discard edits', 'ohmylms' )
								: __( 'Close', 'ohmylms' ) }
						</Button>
					</fieldset>
				</form>
			) }
			{ removing && (
				<Modal
					title={
						removing.builtin
							? __( 'Restore built-in profile?', 'ohmylms' )
							: __( 'Remove profile?', 'ohmylms' )
					}
					onRequestClose={ () => ! busy && setRemoving( null ) }
					shouldCloseOnClickOutside={ false }
				>
					<p>{ removing.label }</p>
					<p>
						{ __(
							'Skills keep their subject. The curriculum subject is not deleted.',
							'ohmylms'
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ busy }
						disabled={ busy }
						onClick={ () => command( 'remove', removing ) }
					>
						{ __( 'Confirm', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () => setRemoving( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Leave with unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.leave }>
						{ __( 'Discard edits and leave', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
