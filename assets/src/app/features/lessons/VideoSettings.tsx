import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { chooseMedia, mediaLibraryAvailable } from './media';
import {
	VIDEO_PROVIDERS,
	formatDuration,
	type LessonVideo,
	type VideoProvider,
} from './model';

const PROVIDER_LABELS: Record< VideoProvider, () => string > = {
	'self-hosted': () => __( 'Uploaded or self-hosted file', 'ohmylms' ),
	youtube: () => 'YouTube',
	vimeo: () => 'Vimeo',
	bunny: () => 'Bunny Stream',
};

const RATIOS = [ '16/9', '4/3', '1/1', '21/9', '9/16' ];

function clamp( value: string, max: number ): number {
	return Math.min( max, Math.max( 0, Math.trunc( Number( value ) ) || 0 ) );
}

/**
 * Player settings and the completion rule of a video lesson. The source is the uploaded file or the
 * external URL above; the provider tells the player how to embed it.
 *
 * @param props          Component props.
 * @param props.video    Current settings.
 * @param props.onChange Receives the changed fields.
 */
export default function VideoSettings( {
	video,
	onChange,
}: {
	video: LessonVideo;
	onChange: ( patch: Partial< LessonVideo > ) => void;
} ) {
	const minutes = Math.floor( video.duration / 60 );
	const seconds = video.duration % 60;
	const available = mediaLibraryAvailable();
	return (
		<fieldset>
			<legend>{ __( 'Video settings', 'ohmylms' ) }</legend>
			<SelectControl< string >
				label={ __( 'Provider', 'ohmylms' ) }
				value={ video.provider }
				options={ VIDEO_PROVIDERS.map( ( value ) => ( {
					value,
					label: PROVIDER_LABELS[ value ](),
				} ) ) }
				onChange={ ( value ) => {
					const provider = VIDEO_PROVIDERS.find(
						( item ) => item === value
					);
					if ( provider ) {
						onChange( { provider } );
					}
				} }
			/>
			<fieldset>
				<legend>
					{ __( 'Duration', 'ohmylms' ) }
					{ video.duration > 0 &&
						` (${ formatDuration( video.duration ) })` }
				</legend>
				<TextControl
					type="number"
					min={ 0 }
					max={ 1440 }
					label={ __( 'Minutes', 'ohmylms' ) }
					value={ String( minutes ) }
					onChange={ ( value ) =>
						onChange( {
							duration: Math.min(
								86400,
								clamp( value, 1440 ) * 60 + seconds
							),
						} )
					}
				/>
				<TextControl
					type="number"
					min={ 0 }
					max={ 59 }
					label={ __( 'Seconds', 'ohmylms' ) }
					value={ String( seconds ) }
					help={ __(
						'Shown to learners. When it is longer than what the player reports, the watched share is measured against it.',
						'ohmylms'
					) }
					onChange={ ( value ) =>
						onChange( {
							duration: Math.min(
								86400,
								minutes * 60 + clamp( value, 59 )
							),
						} )
					}
				/>
			</fieldset>
			<SelectControl< string >
				label={ __( 'Completion', 'ohmylms' ) }
				value={ video.completion }
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
				onChange={ ( value ) =>
					onChange( {
						completion:
							'watch_percent' === value
								? 'watch_percent'
								: 'none',
					} )
				}
			/>
			{ 'watch_percent' === video.completion && (
				<TextControl
					type="number"
					min={ 1 }
					max={ 100 }
					label={ __( 'Share to watch (%)', 'ohmylms' ) }
					value={ String( video.watch_percent ) }
					help={ __(
						'"Mark as complete" stays locked until the learner watched this share; the lesson completes by itself when they do.',
						'ohmylms'
					) }
					onChange={ ( value ) =>
						onChange( {
							watch_percent: Math.max( 1, clamp( value, 100 ) ),
						} )
					}
				/>
			) }
			<p>
				<strong>{ __( 'Poster', 'ohmylms' ) }</strong>
				{ ': ' }
				{ video.poster_id > 0 ? (
					<a
						href={ video.poster_url || undefined }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ video.poster_url ||
							sprintf(
								/* translators: %d: attachment ID. */
								__( 'Image #%d', 'ohmylms' ),
								video.poster_id
							) }
					</a>
				) : (
					__( 'The cover image', 'ohmylms' )
				) }
			</p>
			<Button
				variant="secondary"
				disabled={ ! available }
				onClick={ () =>
					void chooseMedia(
						'image',
						__( 'Video poster', 'ohmylms' ),
						__( 'Use as poster', 'ohmylms' )
					).then( ( file ) => {
						if ( file ) {
							onChange( {
								poster_id: file.id,
								poster_url: file.url,
							} );
						}
					} )
				}
			>
				{ video.poster_id > 0
					? __( 'Replace poster', 'ohmylms' )
					: __( 'Choose poster', 'ohmylms' ) }
			</Button>
			{ video.poster_id > 0 && (
				<Button
					variant="tertiary"
					isDestructive
					onClick={ () =>
						onChange( { poster_id: 0, poster_url: '' } )
					}
				>
					{ __( 'Remove poster', 'ohmylms' ) }
				</Button>
			) }
			<SelectControl< string >
				label={ __( 'Aspect ratio', 'ohmylms' ) }
				value={ video.aspect_ratio }
				options={ ( RATIOS.includes( video.aspect_ratio )
					? RATIOS
					: [ video.aspect_ratio, ...RATIOS ]
				).map( ( value ) => ( {
					value,
					label: value.replace( '/', ':' ),
				} ) ) }
				onChange={ ( aspectRatio ) =>
					onChange( { aspect_ratio: aspectRatio } )
				}
			/>
			<CheckboxControl
				label={ __( 'Show player controls', 'ohmylms' ) }
				checked={ video.controls }
				onChange={ ( controls ) => onChange( { controls } ) }
			/>
			<CheckboxControl
				label={ __( 'Autoplay (muted)', 'ohmylms' ) }
				checked={ video.autoplay }
				onChange={ ( autoplay ) => onChange( { autoplay } ) }
			/>
			<CheckboxControl
				label={ __( 'Loop', 'ohmylms' ) }
				checked={ video.loop }
				onChange={ ( loop ) => onChange( { loop } ) }
			/>
		</fieldset>
	);
}
