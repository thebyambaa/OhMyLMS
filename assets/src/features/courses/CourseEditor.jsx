/** @jsx createElement */
import {
	createElement,
	Fragment,
	useCallback,
	useEffect,
	useState,
	useRef,
} from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCourseEditor } from './useCourseEditor';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
import { useCourseIntegrations } from './useCourseIntegrations';
import { courseEditPath } from './model.mjs';
import { CourseObjectives } from './CourseObjectives';
import { createCourseOrganization } from './CourseOrganization';
import { CourseSaveStatus } from './CourseSaveStatus';
import { CourseOutlineEditor } from './CourseOutlineEditor';

/**
 * Course orchestration with the existing controls, routes and data store.
 * @param readRuntime
 */
export function createCourseEditor( readRuntime ) {
	const CourseOrganization = createCourseOrganization( readRuntime );
	return function CourseEditor( { courseId = null, enableSpin = false } ) {
		const r = readRuntime();
		const {
			Zp: InfoHeader,
			oz: Toolbar,

			MH: Settings,
			JH: Preview,
			GH: Funnel,
			Bf: Community,
			ZD: AutomationDialog,
			TW: IntegrationDialog,
			qt: Visibility,
		} = r;
		const { step = 'content' } = r.f.g();
		const navigate = r.f.Zp();
		const [ outlineDirty, setOutlineDirty ] = useState( false );
		const [ outlineRevision, setOutlineRevision ] = useState( '' );
		const [ skillSourceRevision, setSkillSourceRevision ] = useState( 0 );
		const [ saveError, setSaveError ] = useState( '' );
		const [ courseSaving, setCourseSaving ] = useState( false );
		const [ pendingNavigation, setPendingNavigation ] = useState( null );
		const requestNavigation = useCallback(
			( target ) => {
				if ( outlineDirty ) {
					setPendingNavigation( target );
				} else {
					navigate( target );
				}
			},
			[ navigate, outlineDirty ]
		);
		useEffect( () => {
			const guard = ( event ) => {
				if ( ! outlineDirty ) {
					return;
				}
				const anchor = event.target.closest?.( 'a[href]' );
				if ( ! anchor || anchor.target === '_blank' ) {
					return;
				}
				const target = new URL( anchor.href, window.location.href );
				if (
					target.origin === window.location.origin &&
					target.pathname === window.location.pathname &&
					target.search === window.location.search &&
					target.hash.startsWith( '#/' ) &&
					target.hash !== window.location.hash
				) {
					event.preventDefault();
					event.stopPropagation();
					requestNavigation( target.hash.slice( 1 ) );
				}
			};
			document.addEventListener( 'click', guard, true );
			return () => document.removeEventListener( 'click', guard, true );
		}, [ outlineDirty, requestNavigation ] );
		const { totalSteps } = r.JW();
		const editor = useCourseEditor( {
			store: r.T.default,
			courseId,
			enableSpin,
			manageStructure: true,
		} );
		const dialogs = useCourseIntegrations( {
			actions: editor.actions,
			enableSpin,
		} );
		const { contextHolder, openNotificationWithIcon } = useNotifications();
		const course = editor.course;
		async function saveCourse() {
			if ( courseSaving ) {
				return false;
			}
			setCourseSaving( true );
			setSaveError( '' );
			try {
				if (
					editor.dirty &&
					! ( await editor.save( undefined, undefined, undefined, {
						silent: true,
					} ) )
				) {
					return false;
				}
				const path = `/ohmylms/v1/courses/${ courseId }/structure`;
				const structure = await window.wp.apiFetch( { path } );
				const saved = await window.wp.apiFetch( {
					path,
					method: 'PUT',
					data: {
						revision: structure.revision,
						units: structure.units.filter(
							( unit ) => unit.id > 0
						),
						program: structure.program,
						activate: true,
					},
				} );
				setOutlineRevision( saved.revision );
				await editor.refreshStructure( { syncStatus: true } );
				return true;
			} catch ( cause ) {
				setSaveError(
					[ cause.message, ...( cause.data?.errors || [] ) ]
						.filter( Boolean )
						.join( ' ' )
				);
				return false;
			} finally {
				setCourseSaving( false );
			}
		}
		useEffect( () => {
			if ( step === 'preview' ) {
				navigate( `/course-edit/${ courseId }/content`, {
					replace: true,
				} );
			}
		}, [ step, courseId, navigate ] );
		const shownNotice = useRef( null );
		const syllabusContent = ! editor.loading && course?.syllabus_id > 0;
		const syllabusPath = syllabusContent ? courseEditPath( course ) : '';
		useEffect( () => {
			if ( syllabusContent ) {
				navigate(
					`${ syllabusPath }${ step === 'content' ? '' : '?view=settings' }`,
					{
						replace: true,
					}
				);
			}
		}, [ syllabusContent, syllabusPath, step, navigate ] );
		useEffect( () => {
			const previous = document.body.style.background;
			document.body.style.background = '#FFFFFF';
			return () => {
				document.body.style.background = previous;
			};
		}, [] );
		useEffect( () => {
			if (
				! editor.loading &&
				editor.notice &&
				shownNotice.current !== editor.notice
			) {
				shownNotice.current = editor.notice;
				openNotificationWithIcon( editor.noticeStatus, editor.notice );
			}
		}, [
			editor.loading,
			editor.notice,
			editor.noticeStatus,
			openNotificationWithIcon,
		] );
		const setActiveStep = ( next ) =>
			navigate( `/course-edit/${ courseId }/${ next }` );
		async function uploadMedia( media, type ) {
			if ( type === 'video' ) {
				editor.actions.setCourse( {
					video_id: media.id,
					video_src: media.url,
				} );
			} else {
				editor.actions.setCourse( {
					thumbnail_id: media.id,
					image_src: media.url ?? media.source_url,
				} );
			}
			await editor.actions.setFeaturedImage(
				courseId,
				media,
				type === 'video' ? 'video' : 'image'
			);
		}
		return (
			<Fragment>
				{ pendingNavigation && (
					<Modal
						title={ __( 'Unsaved outline changes', 'ohmylms' ) }
						onRequestClose={ () => setPendingNavigation( null ) }
					>
						<p>
							{ __(
								'Save your course before leaving, or discard these changes.',
								'ohmylms'
							) }
						</p>
						<Button
							variant="secondary"
							onClick={ () => setPendingNavigation( null ) }
						>
							{ __( 'Keep editing', 'ohmylms' ) }
						</Button>
						<Button
							isDestructive
							onClick={ () => {
								const target = pendingNavigation;
								setPendingNavigation( null );
								setOutlineDirty( false );
								navigate( target );
							} }
						>
							{ __( 'Discard and leave', 'ohmylms' ) }
						</Button>
					</Modal>
				) }
				{ contextHolder }
				<div
					className={ `ohmylms-course-builder-wrapper ohmylms-steps-${ totalSteps }` }
					// Leaving a course that was never touched removes it again; the server checks it is truly blank.
					onClickCapture={ ( event ) => {
						if (
							event.target.closest?.(
								'.ohmylms-back-button-wrapper'
							) &&
							! editor.dirty &&
							! editor.saving &&
							course?.status === 'draft' &&
							[ 'Untitled', 'Untitled Course' ].includes(
								course?.name
							) &&
							! ( course?.description || '' ).trim()
						) {
							window.wp
								.apiFetch( {
									path: `/ohmylms/v1/courses/${ courseId }/discard-if-blank`,
									method: 'POST',
								} )
								.catch( () => {} );
						}
					} }
					// A name still holding its "Untitled" default is selected on focus, so typing replaces it.
					onFocusCapture={ ( event ) => {
						const field = event.target;
						if (
							field.matches?.(
								'input[type="text"], input:not([type]), textarea'
							) &&
							[ 'Untitled', 'Untitled Course' ].includes(
								field.value
							)
						) {
							field.select();
						}
					} }
				>
					<Toolbar
						onNavigate={ requestNavigation }
						activeStep={ step }
						setActiveStep={ setActiveStep }
						courseId={ courseId }
						handleAutomation={ dialogs.openAutomation }
						handleIntegration={ dialogs.openIntegration }
						courseDescription={ course?.description || '' }
						setLocalCourse={ ( value ) =>
							editor.updateDescription(
								typeof value === 'string'
									? value
									: value.description || ''
							)
						}
						loading={
							editor.loading || editor.saving || courseSaving
						}
						courseName={ course?.name }
						courseUrl={ course?.course_url }
						onSave={ saveCourse }
						isCommunityEnabled={
							editor.integrations?.community?.is_enable
						}
					/>
					{ ( editor.error || saveError ) && (
						<p role="alert" className="ohmylms-course-save-error">
							{ editor.error || saveError }
						</p>
					) }
					{ ! editor.loading && course && step !== 'content' && (
						<CourseSaveStatus
							dirty={ editor.dirty }
							saving={ editor.saving }
							savedAt={ editor.savedAt }
							status={ course.status }
							onSave={ saveCourse }
						/>
					) }
					<div
						className="ohmylms-course-builder-content"
						aria-busy={ editor.loading || editor.saving }
					>
						{ ( () => {
							if ( editor.loading || syllabusContent ) {
								return (
									<Controls.SkeletonWP active rows={ 10 } />
								);
							}
							if ( course ) {
								return (
									<Fragment>
										{ ( () => {
											if ( step === 'content' ) {
												return (
													<Fragment>
														<div className="ohmylms-outline-frame">
															<CourseOutlineEditor
																skillSourceRevision={
																	skillSourceRevision
																}
																onSaved={
																	setOutlineRevision
																}
																onDirtyChange={
																	setOutlineDirty
																}
																courseId={
																	courseId
																}
																editor={
																	editor
																}
																courseInfo={
																	<InfoHeader
																		handleInputChange={
																			editor.updateTitle
																		}
																		onContentChange={
																			editor.updateDescription
																		}
																		handleRemoveMedia={ (
																			type
																		) =>
																			editor.actions.removeFeaturedImage(
																				courseId,
																				type
																			)
																		}
																		handleUploadComplete={
																			uploadMedia
																		}
																	/>
																}
																alignment={
																	<CourseObjectives
																		subjectControl={
																			<CourseOrganization
																				subjectOnly
																			/>
																		}
																		onSourceChanged={ () =>
																			setSkillSourceRevision(
																				(
																					value
																				) =>
																					value +
																					1
																			)
																		}
																		outlineRevision={
																			outlineRevision
																		}
																		courseId={
																			courseId
																		}
																	/>
																}
															/>
														</div>
													</Fragment>
												);
											}
											if ( step === 'settings' ) {
												return (
													<Visibility isVisible>
														<Settings
															setActiveStep={
																setActiveStep
															}
															activeStep={ step }
															onSave={
																editor.save
															}
														/>
													</Visibility>
												);
											}
											return ( () => {
												if ( step === 'community' ) {
													return (
														<Visibility isVisible>
															<Community
																isCommunityEnabled={
																	course.has_community
																}
																courseId={
																	courseId
																}
																course={
																	course
																}
																onToggleCommunity={
																	editor.toggleCommunity
																}
																onUpdateCourse={
																	editor
																		.actions
																		.setCourse
																}
															/>
														</Visibility>
													);
												}
												if ( step === 'funnel' ) {
													return (
														<Visibility isVisible>
															<Funnel
																setActiveStep={
																	setActiveStep
																}
																activeStep={
																	step
																}
																onSave={
																	editor.save
																}
															/>
														</Visibility>
													);
												}
												return (
													<Visibility
														isVisible={
															step === 'preview'
														}
													>
														<Preview
															completedSteps={
																editor.completedSteps
															}
															onSave={
																editor.save
															}
														/>
													</Visibility>
												);
											} )();
										} )() }
									</Fragment>
								);
							}
							return null;
						} )() }
					</div>
				</div>
				{ dialogs.automation && (
					<AutomationDialog
						isOpen
						onClose={ dialogs.closeAutomation }
						automationFor={ dialogs.automation.forWhat }
						contentId={ dialogs.automation.contentId }
						contentName={ dialogs.automation.contentName }
					/>
				) }
				{ dialogs.integration && (
					<IntegrationDialog
						isOpen
						onClose={ dialogs.closeIntegration }
						integrationFor={ dialogs.integration.forWhat }
						contentId={ dialogs.integration.contentId }
						contentName={ dialogs.integration.contentName }
					/>
				) }
			</Fragment>
		);
	};
}
