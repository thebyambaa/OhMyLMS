import { createElement, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { MemberPicker } from './MemberPicker';
import * as api from './api.mjs';
import {
	addMember,
	detailsDirty,
	draftFrom,
	memberKey,
	memberPayload,
	moveMember,
	publishBlocker,
	removeMember,
	sameMembers,
	validateBundle,
} from './model.mjs';
const TITLE_MESSAGES = {
	required: () => __( 'Enter a name for the bundle.', 'ohmylms' ),
	'too-long': () => __( 'That is too long.', 'ohmylms' ),
};
const BLOCKERS = {
	unsaved: () => __( 'Save your changes before publishing.', 'ohmylms' ),
	empty: () =>
		__(
			'Add at least one course or curriculum item before publishing.',
			'ohmylms'
		),
	title: () => __( 'Give the bundle a name before publishing.', 'ohmylms' ),
};

/**
 * Inline editor for one bundle: name, description, ordered members, publishing and deletion.
 * @param root0
 * @param root0.bundle
 * @param root0.say
 * @param root0.onChanged
 * @param root0.onDeleted
 */
export function BundleEditor( { bundle, say, onChanged, onDeleted } ) {
	const [ detail, setDetail ] = useState( null );
	const [ draft, setDraft ] = useState( () => draftFrom( bundle ) );
	const [ members, setMembers ] = useState( [] );
	const [ touched, setTouched ] = useState( false );
	const [ pending, setPending ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ loadError, setLoadError ] = useState( '' );
	const current = detail?.bundle || bundle;
	const errors = validateBundle( draft );
	const dirtyDetails = detailsDirty( draft, current );
	const dirtyMembers = detail
		? ! sameMembers( members, detail.members )
		: false;
	const dirty = dirtyDetails || dirtyMembers;
	const published = current.status === 'published';
	const blocker = publishBlocker(
		{
			title: draft.title,
		},
		members,
		dirty
	);
	useEffect( () => {
		let live = true;
		api.loadBundle( bundle.id )
			.then( ( response ) => {
				if ( ! live ) {
					return;
				}
				setDetail( response );
				setDraft( draftFrom( response.bundle ) );
				setMembers( response.members );
			} )
			.catch(
				( error ) =>
					live &&
					setLoadError(
						error?.message ||
							__( 'Could not load the bundle.', 'ohmylms' )
					)
			);
		document
			.getElementById( `ohmylms-bundle-title-${ bundle.id }` )
			?.focus();
		return () => {
			live = false;
		};
	}, [ bundle.id ] );
	function adopt( response ) {
		setDetail( response );
		setMembers( response.members );
		onChanged( response.bundle );
	}
	async function save() {
		setTouched( true );
		if ( Object.keys( errors ).length || pending ) {
			return;
		}
		setPending( true );
		let latest = detail.bundle;
		try {
			if ( dirtyDetails ) {
				const response = await api.updateBundle( bundle.id, {
					title: draft.title,
					description: draft.description,
					expected_updated_at: latest.updated_at,
				} );
				latest = response.bundle;
				setDetail( ( previous ) => ( {
					...previous,
					bundle: response.bundle,
				} ) );
				onChanged( response.bundle );
			}
			if ( dirtyMembers ) {
				adopt(
					await api.setMembers(
						bundle.id,
						memberPayload( members ),
						latest.updated_at
					)
				);
			}
			setSaved( true );
			say(
				'success',
				sprintf(
					/* translators: %s: course bundle or member title. */ __(
						'Saved “%s”.',
						'ohmylms'
					),
					draft.title.trim()
				)
			);
		} catch ( error ) {
			say(
				'error',
				error?.message ||
					__( 'The bundle could not be saved.', 'ohmylms' )
			);
		} finally {
			setPending( false );
		}
	}
	async function togglePublish() {
		setPending( true );
		try {
			const response = await api.publishBundle( bundle.id, ! published );
			setDetail( ( previous ) => ( {
				...previous,
				bundle: response.bundle,
			} ) );
			onChanged( response.bundle );
			say(
				'success',
				response.bundle.status === 'published'
					? sprintf(
							/* translators: %s: course bundle or member title. */ __(
								'“%s” is published. Learners can now find and add it.',
								'ohmylms'
							),
							response.bundle.title
						)
					: sprintf(
							/* translators: %s: course bundle or member title. */ __(
								'“%s” is unpublished and hidden from learners.',
								'ohmylms'
							),
							response.bundle.title
						)
			);
		} catch ( error ) {
			say(
				'error',
				error?.message ||
					__( 'The bundle could not be changed.', 'ohmylms' )
			);
		} finally {
			setPending( false );
		}
	}
	async function remove() {
		setPending( true );
		try {
			await api.deleteBundle( bundle.id, current.follower_count > 0 );
			say(
				'success',
				sprintf(
					/* translators: %s: course bundle or member title. */ __(
						'Deleted “%s”.',
						'ohmylms'
					),
					current.title
				)
			);
			onDeleted( bundle.id );
		} catch ( error ) {
			say(
				'error',
				error?.message ||
					__( 'The bundle could not be deleted.', 'ohmylms' )
			);
			setPending( false );
		}
	}
	if ( loadError ) {
		return (
			<p role="alert" className="ohmylms-cur-panel">
				{ loadError }
			</p>
		);
	}
	if ( ! detail ) {
		return (
			<div className="ohmylms-cur-panel">
				<Spinner />
			</div>
		);
	}
	return (
		<section
			id={ `ohmylms-bundle-panel-${ bundle.id }` }
			className="ohmylms-cur-panel ohmylms-bundle-panel"
			aria-label={ sprintf(
				/* translators: %s: course bundle or member title. */ __(
					'Editing %s',
					'ohmylms'
				),
				current.title
			) }
		>
			<TextControl
				id={ `ohmylms-bundle-title-${ bundle.id }` }
				label={ __( 'Name', 'ohmylms' ) }
				value={ draft.title }
				onChange={ ( value ) => {
					setSaved( false );
					setDraft( {
						...draft,
						title: value,
					} );
				} }
				help={
					touched && errors.title
						? TITLE_MESSAGES[ errors.title ]()
						: undefined
				}
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( 'Description', 'ohmylms' ) }
				help={ __(
					'Shown to learners when they discover this bundle.',
					'ohmylms'
				) }
				value={ draft.description }
				onChange={ ( value ) => {
					setSaved( false );
					setDraft( {
						...draft,
						description: value,
					} );
				} }
				__nextHasNoMarginBottom
			/>

			<h3>
				{ __( 'Courses and syllabuses in this bundle', 'ohmylms' ) }
			</h3>
			<p className="ohmylms-ext-muted">
				{ __(
					'Only what you add appears here. The same course or syllabus can also be in other bundles, and adding it does not copy it or change who can open it.',
					'ohmylms'
				) }
			</p>
			{ members.length ? (
				<ol className="ohmylms-bundle-members">
					{ members.map( ( member, index ) => (
						<li key={ memberKey( member ) }>
							<span className="ohmylms-bundle-member-name">
								<strong>
									{ member.title ||
										sprintf(
											/* translators: %d: item number. */ __(
												'Item %d',
												'ohmylms'
											),
											member.id
										) }
								</strong>
								<span className="ohmylms-cur-badge">
									{ member.type === 'course'
										? __( 'Course', 'ohmylms' )
										: __( 'Curriculum item', 'ohmylms' ) }
								</span>
								{ member.type === 'course' &&
									member.status !== 'publish' &&
									member.status !== 'missing' && (
										<span className="ohmylms-cur-badge">
											{ __(
												'Not published: learners will not see it yet',
												'ohmylms'
											) }
										</span>
									) }
								{ member.available === false && (
									<span className="ohmylms-cur-badge">
										{ __(
											'No longer available: remove it',
											'ohmylms'
										) }
									</span>
								) }
								{ member.type === 'curriculum' &&
									member.path?.length > 0 && (
										<small className="ohmylms-ext-muted">
											{ member.path.join( ' › ' ) }
										</small>
									) }
							</span>
							<span className="ohmylms-cur-actions">
								<Button
									variant="secondary"
									size="small"
									disabled={ index === 0 || pending }
									aria-label={ sprintf(
										/* translators: %s: course bundle or member title. */ __(
											'Move %s up',
											'ohmylms'
										),
										member.title
									) }
									onClick={ () =>
										setMembers(
											moveMember( members, index, -1 )
										)
									}
								>
									<span aria-hidden="true">↑</span>
								</Button>
								<Button
									variant="secondary"
									size="small"
									disabled={
										index === members.length - 1 || pending
									}
									aria-label={ sprintf(
										/* translators: %s: course bundle or member title. */ __(
											'Move %s down',
											'ohmylms'
										),
										member.title
									) }
									onClick={ () =>
										setMembers(
											moveMember( members, index, 1 )
										)
									}
								>
									<span aria-hidden="true">↓</span>
								</Button>
								<Button
									variant="secondary"
									size="small"
									isDestructive
									disabled={ pending }
									aria-label={ sprintf(
										/* translators: %s: course bundle or member title. */ __(
											'Remove %s from the bundle',
											'ohmylms'
										),
										member.title
									) }
									onClick={ () =>
										setMembers(
											removeMember(
												members,
												memberKey( member )
											)
										)
									}
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</span>
						</li>
					) ) }
				</ol>
			) : (
				<p className="ohmylms-ext-muted">
					{ __( 'No members yet.', 'ohmylms' ) }
				</p>
			) }
			<MemberPicker
				members={ members }
				disabled={ pending }
				onAdd={ ( member ) => {
					setSaved( false );
					setMembers( ( previous ) => addMember( previous, member ) );
				} }
			/>

			<div className="ohmylms-cur-save">
				<Button
					variant="primary"
					isBusy={ pending }
					disabled={ pending || ! dirty }
					onClick={ save }
				>
					{ __( 'Save bundle', 'ohmylms' ) }
				</Button>
				{ dirty && (
					<span className="ohmylms-cur-dirty">
						{ __( 'Unsaved changes', 'ohmylms' ) }
					</span>
				) }
				{ saved && ! dirty && (
					<span className="ohmylms-cur-saved" role="status">
						{ __( 'Saved', 'ohmylms' ) }
					</span>
				) }
			</div>

			<div className="ohmylms-bundle-publish">
				<p>
					<strong>
						{ published
							? __( 'Published', 'ohmylms' )
							: __( 'Draft', 'ohmylms' ) }
					</strong>
					{ ' — ' }
					{ published
						? __(
								'Learners can find this bundle, add it to their dashboard and see their progress in it.',
								'ohmylms'
							)
						: __(
								'Only administrators can see this bundle. Publish it to suggest it to learners.',
								'ohmylms'
							) }
				</p>
				<Button
					variant="secondary"
					disabled={
						pending || ( ! published && Boolean( blocker ) )
					}
					onClick={ togglePublish }
				>
					{ published
						? __( 'Unpublish', 'ohmylms' )
						: __( 'Publish', 'ohmylms' ) }
				</Button>
				{ ! published && blocker && (
					<p className="ohmylms-ext-muted" role="note">
						{ BLOCKERS[ blocker ]() }
					</p>
				) }
				{ published && current.follower_count > 0 && (
					<p className="ohmylms-ext-muted">
						{ sprintf(
							/* translators: %d: number of learners following the bundle. */ _n(
								'%d learner follows this bundle. Unpublishing hides it from them; they keep it if you publish again.',
								'%d learners follow this bundle. Unpublishing hides it from them; they keep it if you publish again.',
								current.follower_count,
								'ohmylms'
							),
							current.follower_count
						) }
					</p>
				) }
			</div>

			<div className="ohmylms-cur-delete">
				{ ! confirming ? (
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => setConfirming( true ) }
						aria-label={ sprintf(
							/* translators: %s: course bundle or member title. */ __(
								'Delete %s…',
								'ohmylms'
							),
							current.title
						) }
					>
						{ __( 'Delete…', 'ohmylms' ) }
					</Button>
				) : (
					<div
						className="is-confirming"
						role="group"
						aria-label={ sprintf(
							/* translators: %s: course bundle or member title. */ __(
								'Confirm deleting %s',
								'ohmylms'
							),
							current.title
						) }
					>
						<p>
							<strong>
								{ sprintf(
									/* translators: %s: course bundle or member title. */ __(
										'Delete “%s”?',
										'ohmylms'
									),
									current.title
								) }
							</strong>
						</p>
						<p>
							{ current.follower_count > 0
								? sprintf(
										/* translators: %d: number of learners following the bundle. */ _n(
											'%d learner follows it and it will disappear from their dashboard.',
											'%d learners follow it and it will disappear from their dashboards.',
											current.follower_count,
											'ohmylms'
										),
										current.follower_count
									)
								: __(
										'No learners follow it.',
										'ohmylms'
									) }{ ' ' }
							{ __(
								'The courses and curriculum items in it are not deleted, and nobody is unenrolled.',
								'ohmylms'
							) }
						</p>
						<div className="ohmylms-cur-delete-actions">
							<Button
								variant="primary"
								isDestructive
								isBusy={ pending }
								disabled={ pending }
								onClick={ remove }
							>
								{ __( 'Delete bundle', 'ohmylms' ) }
							</Button>
							<Button
								variant="secondary"
								onClick={ () => setConfirming( false ) }
							>
								{ __( 'Cancel', 'ohmylms' ) }
							</Button>
						</div>
					</div>
				) }
			</div>
		</section>
	);
}
