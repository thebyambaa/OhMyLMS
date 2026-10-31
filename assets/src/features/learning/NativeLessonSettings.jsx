/** @jsx createElement */
import './nativeAuthoringBootstrap';
// Before the question blocks register, so they also receive the paper span attribute.
import '../paper/paperBlocks';
import './LessonChoiceBlock';
import './NativeQuizSettings';
import './NativeQuestionSettings';
import { createElement, useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { useBlankPlaceholderTitle } from './useBlankPlaceholderTitle';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Core exposes the post editor back-button SlotFill under this name; the settings panel retains a return link if unavailable.
import { __experimentalMainDashboardButton as EditorBackButton } from '@wordpress/edit-post';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { SVG, Path } from '@wordpress/primitives';
import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';
import { searchBank } from '../question-bank/api.mjs';
import { searchChapterContents } from './api.mjs';
import { installNativeCanvasRecovery } from './nativeCanvas.mjs';
import { isCourseReturn, isReturnUrl } from './returnLink.mjs';

installNativeCanvasRecovery();

const INLINE_BLOCK = 'ohmylms/shared-multiple-choice';

/**
 * One placed inline check: its question title, an editor link and reorder controls.
 *
 * @param {Object}                      props        Component properties.
 * @param {Object}                      props.check  Placed block (`clientId`, `questionId`, `rootClientId`).
 * @param {number}                      props.index  Position, from 0.
 * @param {number}                      props.count  Number of placed checks.
 * @param {(direction: number) => void} props.onMove Moves the block up (-1) or down (1).
 * @param {() => void}                  props.onPick Selects the block in the canvas.
 * @return {Element} List item.
 */
function PlacedCheck( { check, index, count, onMove, onPick } ) {
	const question = useSelect(
		( select ) =>
			check.questionId
				? select( 'core' ).getEntityRecord(
						'postType',
						'ohmylms-question',
						check.questionId
					)
				: null,
		[ check.questionId ]
	);
	const title =
		question?.title?.raw ||
		question?.title?.rendered ||
		( check.questionId
			? `#${ check.questionId }`
			: __( 'Unknown question', 'ohmylms' ) );
	return (
		<li>
			<Button variant="link" onClick={ onPick }>
				{ `${ index + 1 }. ${ title }` }
			</Button>{ ' ' }
			{ check.questionId > 0 && (
				<a
					href={ `post.php?post=${ check.questionId }&action=edit` }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Edit question', 'ohmylms' ) }
				</a>
			) }
			<Button
				variant="tertiary"
				size="small"
				disabled={ 0 === index }
				label={ __( 'Move up', 'ohmylms' ) }
				onClick={ () => onMove( -1 ) }
			>
				{ __( 'Up', 'ohmylms' ) }
			</Button>
			<Button
				variant="tertiary"
				size="small"
				disabled={ index === count - 1 }
				label={ __( 'Move down', 'ohmylms' ) }
				onClick={ () => onMove( 1 ) }
			>
				{ __( 'Down', 'ohmylms' ) }
			</Button>
		</li>
	);
}

/**
 * Insert approved practice questions using the native block insertion action, and list, select and
 * reorder the inline checks already in the lesson.
 *
 * @return {Element} Inline-check settings panel.
 */
function InlineChecks() {
	const [ search, setSearch ] = useState( '' );
	const [ questions, setQuestions ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const { insertBlocks, moveBlockToPosition, selectBlock } =
		useDispatch( 'core/block-editor' );
	const getBlockIndex = useSelect(
		( select ) => select( 'core/block-editor' ).getBlockIndex,
		[]
	);
	const placed = useSelect( ( select ) => {
		const editor = select( 'core/block-editor' );
		const found = [];
		const walk = ( blocks ) =>
			blocks.forEach( ( block ) => {
				if ( block.name === INLINE_BLOCK ) {
					found.push( {
						clientId: block.clientId,
						questionId: Number( block.attributes.questionId ) || 0,
						rootClientId: editor.getBlockRootClientId(
							block.clientId
						),
					} );
				}
				walk( block.innerBlocks || [] );
			} );
		walk( editor.getBlocks() );
		return found;
	}, [] );
	/**
	 * Swap a check with the previous or next check; the content between them stays in place.
	 *
	 * @param {number} index     Position of the check.
	 * @param {number} direction -1 up, 1 down.
	 */
	const move = ( index, direction ) => {
		const [ first, second ] =
			direction < 0
				? [ placed[ index - 1 ], placed[ index ] ]
				: [ placed[ index ], placed[ index + 1 ] ];
		if ( ! first || ! second ) {
			return;
		}
		const firstIndex = getBlockIndex( first.clientId );
		const secondIndex = getBlockIndex( second.clientId );
		moveBlockToPosition(
			second.clientId,
			second.rootClientId,
			first.rootClientId,
			firstIndex
		);
		moveBlockToPosition(
			first.clientId,
			first.rootClientId,
			second.rootClientId,
			secondIndex
		);
	};
	useEffect( () => {
		let active = true;
		const timer = setTimeout( () => {
			searchBank( {
				search,
				status: 'approved',
				secure: 0,
				per_page: 10,
			} )
				.then( ( result ) => {
					if ( active ) {
						setQuestions( result.items || [] );
						setError( '' );
					}
				} )
				.catch( ( failure ) => active && setError( failure.message ) );
		}, 300 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ search ] );
	return (
		<PluginDocumentSettingPanel
			name="ohmylms-inline-checks"
			title={ __( 'Inline question checks', 'ohmylms' ) }
		>
			<p>
				{ __(
					'Insert an approved practice question. These checks do not block lesson completion.',
					'ohmylms'
				) }
			</p>
			{ placed.length > 0 ? (
				<ol
					aria-label={ __(
						'Inline checks in this lesson',
						'ohmylms'
					) }
				>
					{ placed.map( ( check, index ) => (
						<PlacedCheck
							key={ check.clientId }
							check={ check }
							index={ index }
							count={ placed.length }
							onMove={ ( direction ) => move( index, direction ) }
							onPick={ () => selectBlock( check.clientId ) }
						/>
					) ) }
				</ol>
			) : (
				<p>{ __( 'No inline checks yet.', 'ohmylms' ) }</p>
			) }
			<TextControl
				label={ __( 'Find a question', 'ohmylms' ) }
				value={ search }
				onChange={ setSearch }
			/>
			{ error && <p role="alert">{ error }</p> }
			{ questions.map( ( question ) => (
				<p key={ question.id }>
					{ question.name }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () =>
							insertBlocks(
								createBlock( 'ohmylms/shared-multiple-choice', {
									questionId: Number( question.id ),
									uuid: question.uuid,
								} )
							)
						}
					>
						{ __( 'Insert question', 'ohmylms' ) }
					</Button>
				</p>
			) ) }
		</PluginDocumentSettingPanel>
	);
}

/**
 * Use the native media library without replacing any Gutenberg canvas controls.
 *
 * @param {Object}               props           Component properties.
 * @param {string}               props.label     Media label.
 * @param {number}               props.value     Attachment ID.
 * @param {string}               props.mediaType Media type.
 * @param {(id: number) => void} props.onChange  Selection callback.
 * @return {Element} Media selector.
 */
function LessonMedia( { label, value, mediaType, onChange } ) {
	const media = useSelect(
		( select ) => ( value ? select( 'core' ).getMedia( value ) : null ),
		[ value ]
	);
	return (
		<MediaUploadCheck>
			<p>{ label }</p>
			{ media?.source_url && (
				<p>
					<a
						href={ media.source_url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ media.slug }
					</a>
				</p>
			) }
			<MediaUpload
				value={ value }
				allowedTypes={ [ mediaType ] }
				onSelect={ ( item ) => onChange( item.id ) }
				render={ ( { open } ) => (
					<Button variant="secondary" onClick={ open }>
						{ value
							? __( 'Replace media', 'ohmylms' )
							: __( 'Select media', 'ohmylms' ) }
					</Button>
				) }
			/>
			{ value > 0 && (
				<Button variant="tertiary" onClick={ () => onChange( 0 ) }>
					{ __( 'Remove media', 'ohmylms' ) }
				</Button>
			) }
		</MediaUploadCheck>
	);
}

/**
 * LMS settings saved together with the native post's title and block content.
 *
 * @return {Element|null} Lesson panels.
 */
function LessonSettings() {
	useBlankPlaceholderTitle();
	const { postType, postId, settings } = useSelect(
		( select ) => ( {
			postType: select( 'core/editor' ).getCurrentPostType(),
			postId: select( 'core/editor' ).getCurrentPostId(),
			settings:
				select( 'core/editor' ).getEditedPostAttribute(
					'ohmylms_lesson_settings'
				) || {},
		} ),
		[]
	);
	const { editPost } = useDispatch( 'core/editor' );
	const [ returnUrl, setReturnUrl ] = useState(
		window.ohmylmsNativeLesson.returnUrl
	);
	const backLabel = isCourseReturn( returnUrl )
		? __( 'Back to course', 'ohmylms' )
		: __( 'Back to Content Hub', 'ohmylms' );
	useEffect( () => {
		if ( ! postId ) {
			return;
		}
		const key = `ohmylms:lesson-return:${ postId }`;
		try {
			if ( window.ohmylmsNativeLesson.hasReturn ) {
				window.sessionStorage.setItem(
					key,
					window.ohmylmsNativeLesson.returnUrl
				);
				setReturnUrl( window.ohmylmsNativeLesson.returnUrl );
			} else {
				const saved = window.sessionStorage.getItem( key );
				if ( saved && isReturnUrl( saved, window.location.origin ) ) {
					setReturnUrl( saved );
				}
			}
		} catch {
			// Restricted storage retains the server's safe return link.
		}
	}, [ postId ] );
	const { openGeneralSidebar } = useDispatch( 'core/edit-post' );
	useEffect( () => {
		if ( postType === 'ohmylms-lesson' ) {
			openGeneralSidebar( 'edit-post/block' );
		}
	}, [ postType, openGeneralSidebar ] );
	const [ prerequisites, setPrerequisites ] = useState( [] );
	const [ searchError, setSearchError ] = useState( '' );
	if ( postType !== 'ohmylms-lesson' ) {
		return null;
	}
	const change = ( patch ) =>
		editPost( { ohmylms_lesson_settings: { ...settings, ...patch } } );
	const drip = settings.drip_settings || {};
	const required = settings.prerequisites || {};
	const resources = settings.download_resource || {};
	const video = settings.video_settings || {};
	const lessonType = settings.type || 'text';
	const changeDrip = ( patch ) =>
		change( { drip_settings: { ...drip, ...patch } } );
	const changeRequired = ( patch ) =>
		change( { prerequisites: { ...required, ...patch } } );
	const changeVideo = ( patch ) =>
		change( { video_settings: { ...video, ...patch } } );
	async function findPrerequisites( search ) {
		if ( ! window.ohmylmsNativeLesson.chapterId ) {
			return;
		}
		try {
			const result = await searchChapterContents(
				window.ohmylmsNativeLesson.chapterId,
				search
			);
			setPrerequisites(
				( Array.isArray( result ) ? result : result.data || [] ).filter(
					( item ) => Number( item.value ) !== Number( postId )
				)
			);
			setSearchError( '' );
		} catch ( error ) {
			setSearchError( error.message );
		}
	}
	return (
		<>
			{ EditorBackButton && (
				<EditorBackButton>
					<Button
						href={ returnUrl }
						label={ backLabel }
						icon={
							<SVG viewBox="0 0 24 24">
								<Path d="m12 6-6 6 6 6 1-1-4.5-4.5H18V11H8.5L13 7l-1-1Z" />
							</SVG>
						}
					/>
				</EditorBackButton>
			) }
			<PluginDocumentSettingPanel
				name="ohmylms-lesson"
				title={ __( 'Lesson settings', 'ohmylms' ) }
			>
				<Button variant="link" href={ returnUrl }>
					{ backLabel }
				</Button>
				<ToggleControl
					label={ __( 'Allow lesson preview', 'ohmylms' ) }
					checked={ Boolean( settings.preview_enable ) }
					onChange={ ( previewEnable ) =>
						change( { preview_enable: previewEnable } )
					}
				/>
				<ToggleControl
					label={ __( 'Enable comments', 'ohmylms' ) }
					checked={ Boolean( settings.enable_comments ) }
					onChange={ ( enableComments ) =>
						change( { enable_comments: enableComments } )
					}
				/>
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-media"
				title={ __( 'Lesson media', 'ohmylms' ) }
			>
				<SelectControl
					label={ __( 'Lesson type', 'ohmylms' ) }
					value={ lessonType }
					options={ [
						{
							value: 'text',
							label: __( 'Block lesson', 'ohmylms' ),
						},
						{ value: 'video', label: __( 'Video', 'ohmylms' ) },
						{ value: 'audio', label: __( 'Audio', 'ohmylms' ) },
					] }
					onChange={ ( type ) => change( { type } ) }
				/>
				<LessonMedia
					label={ __( 'Cover image', 'ohmylms' ) }
					value={ settings.cover_image_id || 0 }
					mediaType="image"
					onChange={ ( coverImageId ) =>
						change( { cover_image_id: coverImageId } )
					}
				/>
				{ lessonType !== 'text' && (
					<>
						<LessonMedia
							label={
								lessonType === 'video'
									? __( 'Lesson video', 'ohmylms' )
									: __( 'Lesson audio', 'ohmylms' )
							}
							value={ settings[ `${ lessonType }_id` ] || 0 }
							mediaType={ lessonType }
							onChange={ ( id ) =>
								change( {
									[ `${ lessonType }_id` ]: id,
									external_url: '',
								} )
							}
						/>
						<TextControl
							type="url"
							label={ __( 'External media URL', 'ohmylms' ) }
							value={ settings.external_url || '' }
							onChange={ ( externalUrl ) =>
								change( {
									external_url: externalUrl,
									video_id: 0,
									audio_id: 0,
								} )
							}
						/>
					</>
				) }
				{ lessonType === 'video' && (
					<>
						<SelectControl
							label={ __( 'Video platform', 'ohmylms' ) }
							value={ video.platform || 'self-hosted' }
							options={ [
								{
									value: 'self-hosted',
									label: __( 'Self hosted', 'ohmylms' ),
								},
								{ value: 'youtube', label: 'YouTube' },
								{ value: 'vimeo', label: 'Vimeo' },
								{ value: 'bunny', label: 'Bunny' },
							] }
							onChange={ ( platform ) =>
								changeVideo( { platform } )
							}
						/>
						<TextControl
							type="number"
							min={ 0 }
							max={ 86400 }
							label={ __( 'Duration (seconds)', 'ohmylms' ) }
							help={ __(
								'Shown to learners and used to measure the watched share.',
								'ohmylms'
							) }
							value={ video.duration ?? '' }
							onChange={ ( duration ) =>
								changeVideo( {
									duration: Math.min(
										86400,
										Math.max(
											0,
											Math.trunc( Number( duration ) ) ||
												0
										)
									),
								} )
							}
						/>
						<SelectControl
							label={ __( 'Completion', 'ohmylms' ) }
							value={
								video.completion_rule === 'watch_percent'
									? 'watch_percent'
									: 'none'
							}
							options={ [
								{
									value: 'none',
									label: __(
										'Learners mark the lesson complete',
										'ohmylms'
									),
								},
								{
									value: 'watch_percent',
									label: __(
										'Learners watch part of the video first',
										'ohmylms'
									),
								},
							] }
							onChange={ ( rule ) =>
								changeVideo( {
									completion_rule: rule,
									watch_percent: video.watch_percent || 90,
								} )
							}
						/>
						{ video.completion_rule === 'watch_percent' && (
							<TextControl
								type="number"
								min={ 1 }
								max={ 100 }
								label={ __( 'Share to watch (%)', 'ohmylms' ) }
								value={ video.watch_percent ?? 90 }
								onChange={ ( percent ) =>
									changeVideo( {
										watch_percent: Math.min(
											100,
											Math.max(
												1,
												Math.trunc(
													Number( percent )
												) || 1
											)
										),
									} )
								}
							/>
						) }
						<LessonMedia
							label={ __( 'Video poster', 'ohmylms' ) }
							value={ video.poster_id || 0 }
							mediaType="image"
							onChange={ ( posterId ) =>
								changeVideo( { poster_id: posterId } )
							}
						/>
						<TextControl
							label={ __( 'Aspect ratio', 'ohmylms' ) }
							value={ video.aspect_ratio || '16/9' }
							onChange={ ( aspectRatio ) =>
								changeVideo( { aspect_ratio: aspectRatio } )
							}
						/>
						<ToggleControl
							label={ __( 'Autoplay', 'ohmylms' ) }
							checked={ Boolean( video.autoplay ) }
							onChange={ ( autoplay ) =>
								changeVideo( { autoplay } )
							}
						/>
						<ToggleControl
							label={ __( 'Loop', 'ohmylms' ) }
							checked={ Boolean( video.loop ) }
							onChange={ ( loop ) => changeVideo( { loop } ) }
						/>
						<ToggleControl
							label={ __( 'Show controls', 'ohmylms' ) }
							checked={ video.controls !== false }
							onChange={ ( controls ) =>
								changeVideo( { controls } )
							}
						/>
						<ToggleControl
							label={ __( 'Remove branding', 'ohmylms' ) }
							checked={ Boolean( video.remove_branding ) }
							onChange={ ( removeBranding ) =>
								changeVideo( {
									remove_branding: removeBranding,
								} )
							}
						/>
						<ToggleControl
							label={ __( 'Hide related videos', 'ohmylms' ) }
							checked={ Boolean( video.hide_related_videos ) }
							onChange={ ( hideRelatedVideos ) =>
								changeVideo( {
									hide_related_videos: hideRelatedVideos,
								} )
							}
						/>
						{ [ 'width', 'height' ].map( ( dimension ) => (
							<div key={ dimension }>
								<TextControl
									type="number"
									min={ 0 }
									label={
										dimension === 'width'
											? __( 'Video width', 'ohmylms' )
											: __( 'Video height', 'ohmylms' )
									}
									value={ video[ dimension ] ?? '' }
									onChange={ ( value ) =>
										changeVideo( { [ dimension ]: value } )
									}
								/>
								<SelectControl
									label={
										dimension === 'width'
											? __( 'Width unit', 'ohmylms' )
											: __( 'Height unit', 'ohmylms' )
									}
									value={
										video[ `${ dimension }_unit` ] ||
										( dimension === 'width' ? '%' : 'px' )
									}
									options={ [
										{ value: '%', label: '%' },
										{ value: 'px', label: 'px' },
									] }
									onChange={ ( unit ) =>
										changeVideo( {
											[ `${ dimension }_unit` ]: unit,
										} )
									}
								/>
							</div>
						) ) }
					</>
				) }
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-drip"
				title={ __( 'Drip settings', 'ohmylms' ) }
			>
				<ToggleControl
					label={ __( 'Enable drip schedule', 'ohmylms' ) }
					checked={ Boolean( drip.enable ) }
					onChange={ ( enable ) =>
						changeDrip( {
							enable,
							type: drip.type || 'enrollment-from-x-days',
						} )
					}
				/>
				{ drip.enable && (
					<>
						<SelectControl
							label={ __( 'Release lesson', 'ohmylms' ) }
							value={ drip.type || 'enrollment-from-x-days' }
							options={ [
								{
									value: 'enrollment-from-x-days',
									label: __(
										'Days after enrollment',
										'ohmylms'
									),
								},
								{
									value: 'specific-date',
									label: __(
										'On a specific date',
										'ohmylms'
									),
								},
								{
									value: 'cohort-start',
									label: __( 'At cohort start', 'ohmylms' ),
								},
								{
									value: 'cohort-from-x-days',
									label: __(
										'Days after cohort start',
										'ohmylms'
									),
								},
							] }
							onChange={ ( type ) => changeDrip( { type } ) }
						/>
						{ drip.type === 'specific-date' ? (
							<>
								<TextControl
									type="date"
									label={ __( 'Release date', 'ohmylms' ) }
									value={ ( drip.date || '' ).slice( 0, 10 ) }
									onChange={ ( date ) =>
										changeDrip( { date } )
									}
								/>
								<TextControl
									type="time"
									label={ __( 'Release time', 'ohmylms' ) }
									value={
										( drip.time || '' ).includes( 'T' )
											? drip.time.slice( 11, 16 )
											: drip.time || ''
									}
									onChange={ ( time ) =>
										changeDrip( {
											time: `${ ( drip.date || '' ).slice( 0, 10 ) }T${ time }:00`,
										} )
									}
								/>
							</>
						) : (
							drip.type !== 'cohort-start' && (
								<TextControl
									type="number"
									min={ 0 }
									label={ __( 'Days', 'ohmylms' ) }
									value={ drip.days || 0 }
									onChange={ ( days ) =>
										changeDrip( {
											days: Math.max(
												0,
												Number( days ) || 0
											),
										} )
									}
								/>
							)
						) }
					</>
				) }
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-prerequisites"
				title={ __( 'Prerequisites', 'ohmylms' ) }
			>
				<ToggleControl
					label={ __( 'Require previous content', 'ohmylms' ) }
					checked={ Boolean( required.enable ) }
					onChange={ ( enable ) => changeRequired( { enable } ) }
				/>
				{ required.enable && (
					<>
						{ window.ohmylmsNativeLesson.chapterId > 0 && (
							<ComboboxControl
								label={ __( 'Add prerequisite', 'ohmylms' ) }
								value={ null }
								options={ prerequisites.map( ( item ) => ( {
									label: item.label,
									value: String( item.value ),
								} ) ) }
								onFilterValueChange={ findPrerequisites }
								onChange={ ( value ) => {
									const item = prerequisites.find(
										( candidate ) =>
											String( candidate.value ) === value
									);
									if (
										item &&
										! ( required.data || [] ).some(
											( candidate ) =>
												String( candidate.value ) ===
												value
										)
									) {
										changeRequired( {
											data: [
												...( required.data || [] ),
												item,
											],
										} );
									}
								} }
							/>
						) }
						{ searchError && <p role="alert">{ searchError }</p> }
						{ ( required.data || [] ).map( ( item ) => (
							<p key={ item.value }>
								{ item.label }{ ' ' }
								<Button
									variant="tertiary"
									onClick={ () =>
										changeRequired( {
											data: required.data.filter(
												( candidate ) =>
													candidate.value !==
													item.value
											),
										} )
									}
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</p>
						) ) }
					</>
				) }
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="ohmylms-downloads"
				title={ __( 'Download resources', 'ohmylms' ) }
			>
				<MediaUploadCheck>
					<MediaUpload
						multiple
						value={ ( resources.file || [] ).map(
							( file ) => file.id
						) }
						onSelect={ ( files ) =>
							change( {
								download_resource: {
									...resources,
									file: files.map( ( file ) => ( {
										id: file.id,
										name: file.filename || file.title,
										url: file.url,
										size: file.filesizeHumanReadable || '',
									} ) ),
								},
							} )
						}
						render={ ( { open } ) => (
							<Button variant="secondary" onClick={ open }>
								{ __( 'Select files', 'ohmylms' ) }
							</Button>
						) }
					/>
				</MediaUploadCheck>
				{ ( resources.file || [] ).map( ( file ) => (
					<p key={ file.id }>
						<a
							href={ file.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ file.name }
						</a>{ ' ' }
						<Button
							variant="tertiary"
							onClick={ () =>
								change( {
									download_resource: {
										...resources,
										file: resources.file.filter(
											( item ) => item.id !== file.id
										),
									},
								} )
							}
						>
							{ __( 'Remove', 'ohmylms' ) }
						</Button>
					</p>
				) ) }
			</PluginDocumentSettingPanel>
			<InlineChecks />
		</>
	);
}

registerPlugin( 'ohmylms-native-lesson-settings', { render: LessonSettings } );
