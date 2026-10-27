/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
// Built-in lessons bypass this form; retain it for custom lesson extensions.
function createLegacyLessonContent( readRuntime ) {
	return function LessonContent( props ) {
		const { Pr: LearningContentForm, React, b: I18n } = readRuntime();
		var t,
			lesson = props.lesson,
			handleInputChange = props.handleInputChange,
			handleEditorContentChange = props.handleEditorContentChange,
			handleUploadComplete = props.handleUploadComplete,
			handleRemoveMedia = props.handleRemoveMedia,
			chapterId = props.chapterId,
			onExternalUploadComplete = props.onExternalUploadComplete;
		return (
			<React.Fragment>
				{ props.customContent || (
					<LearningContentForm
						titleValue={ ( () => {
							if (
								null !==
									( t =
										lesson === null || lesson === void 0
											? void 0
											: lesson.title ) &&
								void 0 !== t
							) {
								return t;
							}
							if ( lesson === null || lesson === void 0 ) {
								return void 0;
							}
							return lesson.name;
						} )() }
						imageSrc={
							lesson === null || lesson === void 0
								? void 0
								: lesson.image_src
						}
						videoSrc={ ( () => {
							if (
								lesson !== null &&
								lesson !== void 0 &&
								lesson.video_id
							) {
								if ( lesson === null || lesson === void 0 ) {
									return void 0;
								}
								return lesson.video_src;
							}
							return ( () => {
								if (
									'video' ===
									( lesson === null || lesson === void 0
										? void 0
										: lesson.type )
								) {
									if (
										lesson === null ||
										lesson === void 0
									) {
										return void 0;
									}
									return lesson.external_url;
								}
								return '';
							} )();
						} )() }
						audioSrc={ ( () => {
							if (
								lesson !== null &&
								lesson !== void 0 &&
								lesson.audio_id
							) {
								if ( lesson === null || lesson === void 0 ) {
									return void 0;
								}
								return lesson.audio_src;
							}
							return ( () => {
								if (
									'audio' ===
									( lesson === null || lesson === void 0
										? void 0
										: lesson.type )
								) {
									if (
										lesson === null ||
										lesson === void 0
									) {
										return void 0;
									}
									return lesson.external_url;
								}
								return '';
							} )();
						} )() }
						titlePlaceholder={ ( 0, I18n.__ )(
							'Enter lesson title',
							'ohmylms'
						) }
						titleName={ 'name' }
						descriptionPlaceholder={ ( 0, I18n.__ )(
							'Enter lesson description...',
							'ohmylms'
						) }
						content={
							lesson === null || lesson === void 0
								? void 0
								: lesson.description
						}
						onInputChange={ handleInputChange }
						onContentChange={ handleEditorContentChange }
						onUploadComplete={ handleUploadComplete }
						onRemoveMedia={ handleRemoveMedia }
						align={ 'left' }
						mediaType={ ( () => {
							if (
								'text' ===
								( lesson === null || lesson === void 0
									? void 0
									: lesson.type )
							) {
								return 'image';
							}
							if ( lesson === null || lesson === void 0 ) {
								return void 0;
							}
							return lesson.type;
						} )() }
						mediaId={ ( () => {
							if (
								'audio' ===
								( lesson === null || lesson === void 0
									? void 0
									: lesson.type )
							) {
								if ( lesson === null || lesson === void 0 ) {
									return void 0;
								}
								return lesson.audio_id;
							}
							if ( lesson === null || lesson === void 0 ) {
								return void 0;
							}
							return lesson.video_id;
						} )() }
						onExternalUploadComplete={ onExternalUploadComplete }
						chapterId={ chapterId }
						autofocus={ ! 1 }
						editorFor={ 'lesson' }
					/>
				) }
				{ props.settings }
			</React.Fragment>
		);
	};
}
export function createLessonContent( readRuntime ) {
	const LegacyLessonContent = createLegacyLessonContent( readRuntime );
	return function LessonContent( props ) {
		return createElement( LegacyLessonContent, {
			...props,
			customContent: window.ohmylms.extensions.lessonEditor(
				props,
				null
			),
		} );
	};
}
