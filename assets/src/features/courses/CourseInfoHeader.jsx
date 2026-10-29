import { createElement } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
export function createCourseInfoHeader( readRuntime ) {
	return function CourseInfoHeader( {
		handleInputChange,
		onContentChange,
		handleRemoveMedia,
		handleUploadComplete,
		compact = false,
		afterTitle,
	} ) {
		const {
			Qp: Media,
			re: { A: TitleInput },
			ne: RichText,
			T: { default: store },
			Ge: decode,
		} = readRuntime();
		const course = useSelect(
			( select ) => select( store ).getCourse(),
			[ store ]
		);
		const {
			name,
			description,
			video_src: video,
			image_src: image,
		} = course || {};
		return (
			<div
				className={ `ohmylms-course-info-header ${ compact ? 'is-compact' : '' } ${ video || image ? 'ohmylms-has-media' : '' } ${ video ? 'ohmylms-thumb-video-wrapper' : '' } ${ image ? 'ohmylms-thumb-img-wrapper' : '' }` }
			>
				{ ! compact && (
					<Media
						videoSrc={ video }
						imageSrc={ image }
						handleRemoveMedia={ handleRemoveMedia }
						handleUploadComplete={ handleUploadComplete }
					/>
				) }
				<div className="ohmylms-course-content-info">
					<div className="ohmylms-course-name-row">
						<div
							className="ohmylms-title-input-wrapper ohmylms-course-title"
							role={ compact ? 'heading' : undefined }
							aria-level={ compact ? 1 : undefined }
							aria-label={
								compact
									? decode( name ) ||
										__( 'Untitled course', 'ohmylms' )
									: undefined
							}
						>
							<TitleInput
								value={
									name !== 'Untitled' ? decode( name ) : ''
								}
								onChange={ handleInputChange }
								placeholder={ __(
									'Enter Course Title',
									'ohmylms'
								) }
								name="name"
								aria-label={ __( 'Course name', 'ohmylms' ) }
								className="ohmylms-course-title"
								style={ {
									fontSize: 30,
									fontWeight: 'bold',
									border: 'none',
									background: 'transparent',
									padding: 0,
									textAlign: 'left',
									lineHeight: 1.2,
									boxShadow: 'none',
								} }
								size="large"
								autoComplete="off"
								variant="borderless"
							/>
						</div>
						{ afterTitle }
					</div>
					<RichText
						onContentChange={ onContentChange }
						placeholder={ __(
							'Add course description …',
							'ohmylms'
						) }
						content={ description }
						showAddButton
						showTextAlign
						autofocus={ false }
						editorFor="course"
					/>
				</div>
				{ compact && (
					<details className="ohmylms-course-media-details">
						<summary>
							{ __( 'Course image and video', 'ohmylms' ) }
						</summary>{ ' ' }
						<Media
							videoSrc={ video }
							imageSrc={ image }
							handleRemoveMedia={ handleRemoveMedia }
							handleUploadComplete={ handleUploadComplete }
						/>
					</details>
				) }
			</div>
		);
	};
}
