import { createElement } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import {
	filterItems,
	orderItems,
	orderBundles,
	sameIds,
	toggleId,
} from './organization.mjs';
import { restUrl } from '../restUrl.mjs';

/**
 * Where a course sits: the curriculum items it is placed under and the Course Bundles it belongs to.
 * These replace course categories and tags. Changes are saved as soon as they are made, because the
 * course editor's Save button only covers the course itself.
 * @param readRuntime
 */
export function createCourseOrganization( readRuntime ) {
	return function CourseOrganization() {
		const {
			I: Controls,
			b: I18n,
			f: Router,
			g: Hooks,
			l: apiFetch,
		} = readRuntime();
		const { id } = Router.g();
		const courseId = Number( id ) || 0;
		const [ load, setLoad ] = Hooks.useState( {
			status: 'loading',
			error: '',
		} );
		const [ items, setItems ] = Hooks.useState( [] );
		const [ bundles, setBundles ] = Hooks.useState( [] );
		const [ curriculumIds, setCurriculumIds ] = Hooks.useState( [] );
		const [ bundleIds, setBundleIds ] = Hooks.useState( [] );
		const [ canManageBundles, setCanManageBundles ] =
			Hooks.useState( false );
		const [ search, setSearch ] = Hooks.useState( '' );
		const [ busy, setBusy ] = Hooks.useState( false );
		const [ error, setError ] = Hooks.useState( '' );
		const [ attempt, setAttempt ] = Hooks.useState( 0 );

		// Resolve against WordPress's localized REST root, not the admin page URL.
		const request = ( { path, ...settings } ) => {
			const root = window.ohmylms_params?.api_url;
			return apiFetch()( {
				...settings,
				...( root ? { url: restUrl( root, path ) } : { path } ),
			} );
		};
		const messageOf = ( cause, fallback ) =>
			( cause && cause.message ) || fallback;
		const idsOf = ( list ) =>
			( list || [] ).map( ( entry ) => Number( entry.id ) );

		Hooks.useEffect( () => {
			let active = true;
			if ( ! courseId ) {
				return undefined;
			}
			setLoad( { status: 'loading', error: '' } );
			Promise.all( [
				request( { path: '/ohmylms/v1/curriculum/outline' } ),
				request( { path: '/ohmylms/v1/bundles/outline' } ),
				request( {
					path: `/ohmylms/v1/courses/${ courseId }/organization`,
				} ),
			] )
				.then( ( [ outline, bundleOutline, organization ] ) => {
					if ( ! active ) {
						return;
					}
					setItems( orderItems( outline.items ) );
					setBundles( orderBundles( bundleOutline.bundles ) );
					setCurriculumIds( idsOf( organization.curriculum ) );
					setBundleIds( idsOf( organization.bundles ) );
					setCanManageBundles( !! organization.can_manage_bundles );
					setLoad( { status: 'ready', error: '' } );
				} )
				.catch( ( cause ) => {
					if ( active ) {
						setLoad( {
							status: 'error',
							error: messageOf(
								cause,
								I18n.__(
									'Could not load the curriculum and course bundles.',
									'ohmylms'
								)
							),
						} );
					}
				} );
			return () => {
				active = false;
			};
		}, [ courseId, attempt ] );

		/**
		 * Save one list; on failure show the server's reason and put the checkbox back.
		 * @param field
		 * @param next
		 * @param previous
		 * @param apply
		 */
		async function save( field, next, previous, apply ) {
			setError( '' );
			setBusy( true );
			apply( next );
			try {
				const saved = await request( {
					path: `/ohmylms/v1/courses/${ courseId }/organization`,
					method: 'PUT',
					data: { [ field ]: next },
				} );
				setCurriculumIds( idsOf( saved.curriculum ) );
				setBundleIds( idsOf( saved.bundles ) );
				setCanManageBundles( !! saved.can_manage_bundles );
			} catch ( cause ) {
				apply( previous );
				setError(
					messageOf(
						cause,
						I18n.__( 'The change could not be saved.', 'ohmylms' )
					)
				);
			} finally {
				setBusy( false );
			}
		}

		// Curriculum and Course Bundles are tabs of the Content Hub.
		const adminHref = ( page ) =>
			window.location.href.split( '#' )[ 0 ] + '#/content-hub/' + page;
		const subjects = items.filter(
			( item ) => item.item_type === 'subject'
		);
		const visibleItems = filterItems( items, search ).filter(
			( item ) => item.item_type !== 'subject'
		);

		if ( ! courseId ) {
			return (
				<Controls.CardWP isBorderless>
					<Controls.SpacerWP padding={ 6 }>
						<p>
							{ I18n.__(
								'Save the course first to place it in the curriculum.',
								'ohmylms'
							) }
						</p>
					</Controls.SpacerWP>
				</Controls.CardWP>
			);
		}
		if ( load.status === 'loading' ) {
			return (
				<Controls.CardWP isBorderless>
					<Controls.SpacerWP padding={ 6 }>
						<Spinner />
					</Controls.SpacerWP>
				</Controls.CardWP>
			);
		}
		if ( load.status === 'error' ) {
			return (
				<Controls.CardWP isBorderless>
					<Controls.SpacerWP padding={ 6 }>
						<Notice status="error" isDismissible={ false }>
							{ load.error }
						</Notice>
						<Button
							variant="secondary"
							onClick={ () => setAttempt( attempt + 1 ) }
						>
							{ I18n.__( 'Try again', 'ohmylms' ) }
						</Button>
					</Controls.SpacerWP>
				</Controls.CardWP>
			);
		}

		return (
			<Controls.CardWP isBorderless>
				<Controls.SpacerWP
					padding={ 6 }
					marginTop={ 6 }
					marginBottom={ 6 }
				>
					{ error && (
						<Notice
							status="error"
							onRemove={ () => setError( '' ) }
						>
							{ error }
						</Notice>
					) }
					<Controls.HeadingWP level={ 4 }>
						{ I18n.__( 'Subject', 'ohmylms' ) }
					</Controls.HeadingWP>
					<p>
						{ I18n.__(
							'Choose the subjects for this course. Published courses appear under their subjects in the Library and Explore.',
							'ohmylms'
						) }
					</p>
					<div
						role="group"
						aria-label={ I18n.__( 'Subject', 'ohmylms' ) }
					>
						{ subjects.map( ( subject ) => (
							<CheckboxControl
								key={ subject.id }
								label={ subject.name }
								checked={ curriculumIds.includes( subject.id ) }
								disabled={ busy }
								onChange={ ( checked ) =>
									save(
										'curriculum_ids',
										toggleId(
											curriculumIds,
											subject.id,
											checked
										),
										curriculumIds,
										setCurriculumIds
									)
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
					<p>
						<a
							href={ adminHref( 'curriculum/subjects' ) }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ I18n.__(
								'Create or edit subject profiles',
								'ohmylms'
							) }
						</a>
					</p>
					<Controls.FlexWP gap={ 6 } align={ 'stretch' }>
						<Controls.FlexItemWP flex={ 1 }>
							<Controls.CardWP
								isBorderless
								variant={ 'secondary' }
								fullHeight
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 5 }
								>
									<Controls.HeadingWP level={ 4 }>
										{ I18n.__( 'Curriculum', 'ohmylms' ) }
									</Controls.HeadingWP>
									<p>
										{ I18n.__(
											'Place this course where it belongs in your curriculum. A course filter for an item also includes everything below it.',
											'ohmylms'
										) }
									</p>
									{ items.length === 0 ? (
										<p>
											{ I18n.__(
												'There are no curriculum items yet.',
												'ohmylms'
											) }{ ' ' }
											<a
												href={ adminHref(
													'curriculum'
												) }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ I18n.__(
													'Create curriculum items',
													'ohmylms'
												) }
											</a>
										</p>
									) : (
										<div>
											<TextControl
												label={ I18n.__(
													'Find a curriculum item',
													'ohmylms'
												) }
												value={ search }
												onChange={ setSearch }
												__nextHasNoMarginBottom
											/>
											<div
												role="group"
												aria-label={ I18n.__(
													'Curriculum items',
													'ohmylms'
												) }
												style={ {
													maxHeight: 320,
													overflowY: 'auto',
												} }
											>
												{ visibleItems.map(
													( item ) => (
														<div
															key={ item.id }
															style={ {
																paddingLeft:
																	item.depth *
																	20,
																margin: '6px 0',
															} }
														>
															<CheckboxControl
																__nextHasNoMarginBottom
																label={
																	item.code
																		? `${ item.name } (${ item.code })`
																		: item.name
																}
																checked={ curriculumIds.includes(
																	item.id
																) }
																disabled={
																	busy
																}
																onChange={ (
																	checked
																) =>
																	save(
																		'curriculum_ids',
																		toggleId(
																			curriculumIds,
																			item.id,
																			checked
																		),
																		curriculumIds,
																		setCurriculumIds
																	)
																}
															/>
														</div>
													)
												) }
												{ search &&
													visibleItems.length ===
														0 && (
														<p>
															{ I18n.__(
																'No curriculum items match your search.',
																'ohmylms'
															) }
														</p>
													) }
											</div>
											<p>
												<a
													href={ adminHref(
														'curriculum'
													) }
													target="_blank"
													rel="noopener noreferrer"
												>
													{ I18n.__(
														'Manage the curriculum',
														'ohmylms'
													) }
												</a>
											</p>
										</div>
									) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP flex={ 1 }>
							<Controls.CardWP
								isBorderless
								variant={ 'secondary' }
								fullHeight
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 5 }
								>
									<Controls.HeadingWP level={ 4 }>
										{ I18n.__(
											'Course bundles',
											'ohmylms'
										) }
									</Controls.HeadingWP>
									<p>
										{ I18n.__(
											'Course bundles are curated paths that learners can follow. Only published bundles are shown to learners.',
											'ohmylms'
										) }
									</p>
									{ bundles.length === 0 ? (
										<p>
											{ I18n.__(
												'There are no course bundles yet.',
												'ohmylms'
											) }{ ' ' }
											{ canManageBundles && (
												<a
													href={ adminHref(
														'bundles'
													) }
													target="_blank"
													rel="noopener noreferrer"
												>
													{ I18n.__(
														'Create a course bundle',
														'ohmylms'
													) }
												</a>
											) }
										</p>
									) : (
										<div
											role="group"
											aria-label={ I18n.__(
												'Course bundles',
												'ohmylms'
											) }
										>
											{ bundles.map( ( bundle ) => (
												<div
													key={ bundle.id }
													style={ {
														margin: '6px 0',
													} }
												>
													<CheckboxControl
														__nextHasNoMarginBottom
														label={
															bundle.status ===
															'published'
																? bundle.title
																: `${ bundle.title } (${ I18n.__( 'draft', 'ohmylms' ) })`
														}
														checked={ bundleIds.includes(
															bundle.id
														) }
														disabled={
															busy ||
															! canManageBundles
														}
														onChange={ (
															checked
														) => {
															const next =
																toggleId(
																	bundleIds,
																	bundle.id,
																	checked
																);
															if (
																! sameIds(
																	next,
																	bundleIds
																)
															) {
																save(
																	'bundle_ids',
																	next,
																	bundleIds,
																	setBundleIds
																);
															}
														} }
													/>
												</div>
											) ) }
										</div>
									) }
									{ ! canManageBundles &&
										bundles.length > 0 && (
											<p>
												{ I18n.__(
													'Only administrators can change which course bundles a course is in.',
													'ohmylms'
												) }
											</p>
										) }
									{ canManageBundles &&
										bundles.length > 0 && (
											<p>
												<a
													href={ adminHref(
														'bundles'
													) }
													target="_blank"
													rel="noopener noreferrer"
												>
													{ I18n.__(
														'Manage course bundles',
														'ohmylms'
													) }
												</a>
											</p>
										) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
