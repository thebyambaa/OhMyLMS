/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createLearningContentForm( readRuntime ) {
	return function LearningContentForm( props ) {
		const { Cr: LearningMediaField, React, ne } = readRuntime();
		var titleName = props.titleName,
			titleValue = props.titleValue,
			titlePlaceholder = props.titlePlaceholder,
			content = props.content,
			descriptionPlaceholder = props.descriptionPlaceholder,
			imageSrc = props.imageSrc,
			videoSrc = props.videoSrc,
			onInputChange = props.onInputChange,
			onContentChange = props.onContentChange,
			onUploadComplete = props.onUploadComplete,
			onRemoveMedia = props.onRemoveMedia,
			align = props.align,
			p = void 0 === align ? 'left' : align,
			mediaType = props.mediaType,
			v = void 0 === mediaType ? 'image_video' : mediaType,
			audioSrc = props.audioSrc,
			mediaId = props.mediaId,
			onExternalUploadComplete = props.onExternalUploadComplete,
			_ =
				void 0 === onExternalUploadComplete
					? function () {}
					: onExternalUploadComplete,
			commandsConfig = props.commandsConfig,
			E = void 0 === commandsConfig ? {} : commandsConfig,
			showAddButton = props.showAddButton,
			R = void 0 === showAddButton || showAddButton,
			x = ( props.chapterId, props.showTextAlign ),
			C = void 0 === x || x,
			autofocus = props.autofocus,
			O = void 0 === autofocus || autofocus,
			editorFor = props.editorFor;
		return (
			<div className={ 'common-entity-form ohmylms-'.concat( p ) }>
				{ ( 'image_video' === v || 'image' === v ) && (
					<React.Fragment>
						<LearningMediaField
							src={ imageSrc }
							videoSrc={ videoSrc }
							limit={ 2 }
							type={ 'image_video' === v ? 'both' : v }
							supportedTypes={ [
								'.jpg',
								'.jpeg',
								'.png',
								'.mp4',
								'.mov',
								'.webp',
							] }
							onUploadComplete={ onUploadComplete }
							onRemoveMedia={ onRemoveMedia }
							align={ p }
							mediaId={ mediaId }
							onExternalUploadComplete={ _ }
						/>
					</React.Fragment>
				) }
				{ 'video' === v && (
					<LearningMediaField
						src={ imageSrc }
						videoSrc={ videoSrc }
						limit={ 2 }
						type={ 'both' }
						supportedTypes={ [ '.jpg', '.jpeg', '.png', '.webp' ] }
						onUploadComplete={ onUploadComplete }
						onRemoveMedia={ onRemoveMedia }
						align={ 'left' }
						fileType={ 'video' }
						mediaId={ mediaId }
						onExternalUploadComplete={ _ }
					/>
				) }
				{ 'audio' === v && (
					<LearningMediaField
						audioSrc={ audioSrc }
						limit={ 2 }
						type={ 'both' }
						supportedTypes={ [
							'.mp3',
							'.wav',
							'.aac',
							'.flac',
							'.ogg',
							'.m4a',
						] }
						onUploadComplete={ onUploadComplete }
						onRemoveMedia={ onRemoveMedia }
						align={ 'left' }
						fileType={ 'audio' }
						mediaId={ mediaId }
						onExternalUploadComplete={ _ }
					/>
				) }
				<React.Fragment>
					<Controls.SpacerWP marginBottom={ 4 } />
					<div
						className={
							'ohmylms-title-input-wrapper ohmylms-course-title'
						}
					>
						<Controls.InputWP
							type={ 'text' }
							value={ decodeHtmlEntities(
								'Untitled' !== titleValue ? titleValue : ''
							) }
							onChange={ onInputChange }
							placeholder={ titlePlaceholder }
							name={ titleName }
							style={ {
								fontSize: 26,
								fontWeight: '500',
								border: 'none',
								background: 'transparent',
								padding: 0,
								textAlign: 'left',
								lineHeight: 1.2,
								boxShadow: 'none',
							} }
						/>
					</div>
					{ React.createElement( ne, {
						onContentChange( e ) {
							onContentChange( e );
						},
						placeholder: descriptionPlaceholder,
						content,
						commandsConfig: E,
						showAddButton: R,
						showTextAlign: C,
						autofocus: O,
						editorFor,
					} ) }
				</React.Fragment>
			</div>
		);
	};
}
