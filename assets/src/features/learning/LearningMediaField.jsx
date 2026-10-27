import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createLearningMediaField( readRuntime ) {
	return function LearningMediaField( props ) {
		const {
			He,
			I: Controls,
			M,
			Re,
			React,
			Sr,
			T: StoreModule,
			_r,
			ar,
			b: I18n,
			dr,
			g: ReactHooks,
			gr,
			pe,
			ve,
			y: WordPressData,
		} = readRuntime();
		M().noConflict();
		var limit = props.limit;
		var r = void 0 === limit ? 1 : limit;
		var a = props.src;
		var o = void 0 === a ? '' : a;
		var videoSrc = props.videoSrc;
		var l = void 0 === videoSrc ? '' : videoSrc;
		var audioSrc = props.audioSrc;
		var u = void 0 === audioSrc ? '' : audioSrc;
		var type = props.type;
		var d = void 0 === type ? 'both' : type;
		var supportedTypes = props.supportedTypes;
		var p =
			void 0 === supportedTypes
				? [ '.jpg', '.jpeg', '.png', '.mp4', '.mov', '.webp' ]
				: supportedTypes;
		var onUploadComplete = props.onUploadComplete;
		var onRemoveMedia = props.onRemoveMedia;
		var align = props.align;
		var _ = void 0 === align ? 'left' : align;
		var fileType = props.fileType;
		var E = void 0 === fileType ? 'image_video' : fileType;
		var addImgText = props.addImgText;
		var R =
			void 0 === addImgText
				? ( 0, I18n.__ )( 'Add Cover Image', 'ohmylms' )
				: addImgText;
		var x =
			( void 0 === props.addVideoText &&
				( 0, I18n.__ )( 'Add Cover Video', 'ohmylms' ),
			props.mediaId );
		var onExternalUploadComplete = props.onExternalUploadComplete;
		var P =
			void 0 === onExternalUploadComplete
				? function () {}
				: onExternalUploadComplete;
		( 0, WordPressData.useDispatch )( StoreModule.default );
		var k = Sr( ( 0, ReactHooks.useState )( null ), 2 );
		var j = k[ 0 ];
		var A = k[ 1 ];
		var F = Sr( ( 0, ReactHooks.useState )( null ), 2 );
		var N = F[ 0 ];
		var D = F[ 1 ];
		var W = Sr( ( 0, ReactHooks.useState )( null ), 2 );
		var z = W[ 0 ];
		var B = W[ 1 ];
		var V = Sr( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var H = V[ 0 ];
		var U = V[ 1 ];
		var q = Sr( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var Y = q[ 0 ];
		var Q = q[ 1 ];
		var Z = Sr( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var $ = Z[ 0 ];
		var K = Z[ 1 ];
		var J = Sr( ( 0, ReactHooks.useState )( null ), 2 );
		var X = J[ 0 ];
		var ee = J[ 1 ];
		var te = Sr( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var ne = te[ 0 ];
		var re = te[ 1 ];
		var ae = ( 0, ReactHooks.useRef )( null );
		var oe = Sr( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var ie = oe[ 0 ];
		var le = oe[ 1 ];
		var ce = Sr( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var ue = ce[ 0 ];
		var se = ce[ 1 ];
		( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getAllIntegrations();
		}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				if ( o ) {
					{
						A( o );
						if ( ! x ) {
							U( ! 0 );
						}
					}
				}
			},
			[ o ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( l && ! $ ) {
					{
						D( l );
						K( ! 1 );
						if ( ! x ) {
							U( ! 0 );
						}
					}
				}
			},
			[ l ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( u && ! $ ) {
					{
						B( u );
						K( ! 1 );
						if ( ! x ) {
							U( ! 0 );
						}
					}
				}
			},
			[ u ]
		);
		var fe = function ( e ) {
			var _tLocal = [ 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp' ],
				n = [ 'mp4', 'mov', 'avi', 'mkv' ],
				_aLocal = [ 'mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg', 'mpeg' ],
				_oLocal = [
					'image/jpeg',
					'image/png',
					'image/gif',
					'image/svg+xml',
					'image/webp',
				].join( ',' ),
				i = [
					'video/mp4',
					'video/quicktime',
					'video/x-msvideo',
					'video/x-matroska',
				].join( ',' ),
				_lLocal = [
					'audio/mp3',
					'audio/aac',
					'audio/flac',
					'audio/m4a',
					'audio/mpeg',
					'audio/wav',
					'audio/ogg',
				].join( ',' ),
				c = wp.media( {
					title: 'Select or Upload Media',
					button: {
						text: 'Use this media',
					},
					multiple: r > 1,
					library: {
						type: e,
					},
				} );
			c.on( 'open', function () {
				c.content.mode( 'upload' );
				c.on( 'uploader:ready', function () {
					document
						.querySelectorAll(
							'.moxie-shim-html5 input[type="file"]'
						)
						.forEach( function ( _tLocal2 ) {
							_tLocal2.setAttribute( 'tabIndex', '-1' );
							_tLocal2.setAttribute( 'multiple', 'false' );
							_tLocal2.setAttribute( 'aria-hidden', 'true' );
							if ( 'video' === e ) {
								_tLocal2.setAttribute( 'accept', i );
							} else if ( 'audio' === e ) {
								_tLocal2.setAttribute( 'accept', _lLocal );
							} else {
								_tLocal2.setAttribute( 'accept', _oLocal );
							}
						} );
				} );
			} );
			c.on( 'select', function () {
				var _rLocal = c.state().get( 'selection' ).first(),
					_oLocal2 = _rLocal.get( 'filesizeInBytes' ),
					_iLocal = _rLocal.toJSON(),
					_lLocal2 = _tLocal.some( function ( _eLocal ) {
						return _iLocal.url.toLowerCase().endsWith( _eLocal );
					} );
				if ( 'video' === e ) {
					_lLocal2 = n.some( function ( _eLocal2 ) {
						return _iLocal.url.toLowerCase().endsWith( _eLocal2 );
					} );
				}
				if ( 'audio' === e ) {
					_lLocal2 = _aLocal.some( function ( _eLocal3 ) {
						return _iLocal.url.toLowerCase().endsWith( _eLocal3 );
					} );
				}
				var _uLocal =
					( 'image' === e && 'image' === _iLocal.type ) ||
					( 'video' === e && 'video' === _iLocal.type ) ||
					( 'audio' === e && 'audio' === _iLocal.type ) ||
					_iLocal.type === e;
				if ( 'image' === e && _oLocal2 > 5242880 ) {
					showRequestError(
						( 0, I18n.__ )(
							'Image exceeds the 5MB size limit. Please choose a smaller file.',
							'ohmylms'
						)
					);
				} else if ( _lLocal2 && _uLocal ) {
					var s;
					if ( 'image' === e ) {
						A(
							_iLocal === null ||
								_iLocal === void 0 ||
								null === ( s = _iLocal.sizes ) ||
								void 0 === s ||
								null === ( s = s.large ) ||
								void 0 === s
								? void 0
								: s.url
						);
					} else if ( 'video' === e ) {
						{
							D(
								_iLocal === null || _iLocal === void 0
									? void 0
									: _iLocal.url
							);
							K( ! 1 );
						}
					} else if ( 'audio' === e ) {
						{
							B(
								_iLocal === null || _iLocal === void 0
									? void 0
									: _iLocal.url
							);
							K( ! 1 );
						}
					}
					if ( onUploadComplete ) {
						onUploadComplete( _iLocal, e );
					}
				} else {
					showRequestError( 'Invalid file type or media type.' );
				}
			} );
			c.open();
		};
		var ge = function () {
			A( null );
			if ( onRemoveMedia ) {
				onRemoveMedia( 'image' );
			}
		};
		var he = function () {
			D( null );
			Q( ! 1 );
			U( ! 1 );
			K( ! 0 );
			if ( onRemoveMedia ) {
				onRemoveMedia( 'video' );
			}
		};
		var ye = function () {
			B( null );
			U( ! 1 );
			K( ! 0 );
			if ( onRemoveMedia ) {
				onRemoveMedia( 'audio' );
			}
		};
		var be = function ( e, _tLocal3, n ) {
			P( e, _tLocal3 );
			U( ! n );
			Q( ! 1 );
			if ( 'audio' === _tLocal3 ) {
				{
					B( e === null || e === void 0 ? void 0 : e.url );
					K( ! 1 );
				}
			} else {
				{
					D( e === null || e === void 0 ? void 0 : e.url );
					K( ! 1 );
				}
			}
		};
		var _e = function ( e ) {
			if ( H ) {
				Q( ! 0 );
			} else {
				fe( e );
			}
		};
		var Ee = function ( e ) {
			ee( e );
		};
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var e = function ( _eLocal4 ) {
						if ( ! (
							! ie ||
							! ae.current ||
							ae.current.contains( _eLocal4.target ) ||
							_eLocal4.target.closest(
								'.ohmylms-history-list'
							) ||
							_eLocal4.target.closest( '.ohmylms-tooltip-box' ) ||
							_eLocal4.target.closest(
								'.ohmylms-ai-image-prompt'
							)
						) ) {
							{
								le( ! 1 );
								Ee( null );
							}
						}
					};
					return (
						document.addEventListener( 'mousedown', e ),
						function () {
							document.removeEventListener( 'mousedown', e );
						}
					 );
				},
				[ ie ]
			),
			(
				<React.Fragment>
					<div
						className={ 'ohmylms-media-uploader '.concat(
							j || N || z ? 'ohmylms-has-media' : ''
						) }
					>
						<div
							className={ 'ohmylms-media-contents '.concat( E ) }
							style={ {
								position: 'relative',
								maxHeight: '350px',
								overflow: 'hidden',
								borderRadius: '8px',
							} }
							onMouseEnter={ function () {
								return re( ! 0 );
							} }
							onMouseLeave={ function () {
								return re( ! 1 );
							} }
						>
							{ Boolean( X || j ) &&
								! Boolean( N ) &&
								'image_video' === E && (
									<React.Fragment>
										<img
											src={
												X !== null && X !== void 0
													? X
													: j
											}
											alt={ 'Uploaded cover' }
											style={ {
												maxWidth: '100%',
											} }
										/>
									</React.Fragment>
								) }
							{ Boolean( N ) && 'image_video' === E && (
								<video
									src={ N }
									alt={ 'Uploaded cover' }
									poster={ j || '' }
									controls={ ! 0 }
									style={ {
										maxWidth: '100%',
									} }
								/>
							) }
							{ 'video' === E && (
								<React.Fragment>
									{ ( () => {
										if ( Boolean( N ) && ! Y ) {
											if ( H ) {
												return (
													<_r
														mediaUrl={ N }
														sectionType={ 'video' }
														className={
															'ohmylms-external-video'
														}
														setMediaURL={ D }
														handleExternalMedia={
															be
														}
														setIsExternalMedia={ U }
													/>
												);
											}
											return (
												<video
													src={ N }
													alt={ 'Uploaded cover' }
													poster={ j || '' }
													controls={ ! 0 }
													style={ {
														maxWidth: '100%',
													} }
												/>
											);
										}
										return (
											<React.Fragment>
												{ React.createElement( dr, {
													type: 'video',
													onClick: fe,
													buttonLabel: ( 0, I18n.__ )(
														'Select a video',
														'ohmylms'
													),
													icon: React.createElement(
														pe,
														null
													),
													supportedTypes: [
														'.mp4',
														'.mov',
														'.avi',
														'.mkv',
														'.flv',
														'.wmv',
														'.webm',
													],
													handleExternalMedia: be,
													setShowInput: Q,
													showInput: Y,
													value: N,
												} ) }
											</React.Fragment>
										);
									} )() }
								</React.Fragment>
							) }
							{ 'audio' === E && (
								<React.Fragment>
									{ ( () => {
										if ( Boolean( z ) && ! Y ) {
											if ( H ) {
												return (
													<div
														className={
															'ohmylms-media-audio-wrapper'
														}
													>
														<_r
															mediaUrl={ z }
															sectionType={
																'audio'
															}
															setMediaURL={ B }
															handleExternalMedia={
																be
															}
															setIsExternalMedia={
																U
															}
														/>
														<Controls.FlexWP
															gap={ 4 }
															align={ 'center' }
															justify={
																'flex-end'
															}
															className={
																'crlsm-media-controls-audio'
															}
														>
															<Controls.ButtonWP
																variant={
																	'primary'
																}
																icon={ <Re /> }
																onClick={ function () {
																	return _e(
																		'audio'
																	);
																} }
															>
																{ ( 0,
																I18n.__ )(
																	'Replace',
																	'ohmylms'
																) }
															</Controls.ButtonWP>
															{ React.createElement(
																gr,
																{
																	onOK: ye,
																	alertTitle:
																		( 0,
																		I18n.__ )(
																			'Remove the audio',
																			'ohmylms'
																		),
																	alertDescription:
																		( 0,
																		I18n.__ )(
																			'Are you sure you want to remove this audio?',
																			'ohmylms'
																		),
																	modalPosition:
																		'top',
																}
															) }
														</Controls.FlexWP>
													</div>
												);
											}
											return (
												<div
													className={
														'ohmylms-media-audio-wrapper'
													}
												>
													<audio
														src={ z }
														controls={ ! 0 }
													/>
													<Controls.FlexWP
														gap={ 4 }
														align={ 'center' }
														justify={ 'flex-end' }
													>
														<Controls.ButtonWP
															variant={
																'primary'
															}
															icon={ <Re /> }
															onClick={ function () {
																return _e(
																	'audio'
																);
															} }
														>
															{ ( 0, I18n.__ )(
																'Replace',
																'ohmylms'
															) }
														</Controls.ButtonWP>
														{ React.createElement(
															gr,
															{
																onOK: ye,
																alertTitle: ( 0,
																I18n.__ )(
																	'Remove the audio',
																	'ohmylms'
																),
																alertDescription:
																	( 0,
																	I18n.__ )(
																		'Are you sure you want to remove this audio?',
																		'ohmylms'
																	),
															}
														) }
													</Controls.FlexWP>
												</div>
											);
										}
										return (
											<React.Fragment>
												{ React.createElement( dr, {
													type: 'audio',
													onClick: fe,
													buttonLabel: ( 0, I18n.__ )(
														'Select an audio',
														'ohmylms'
													),
													icon: React.createElement(
														ve,
														null
													),
													supportedTypes: p,
													handleExternalMedia: be,
													setShowInput: Q,
													showInput: Y,
													value: z,
												} ) }
											</React.Fragment>
										);
									} )() }
								</React.Fragment>
							) }
							<Controls.FlexWP
								align={ 'center' }
								justify={ 'flex-end' }
								gap={ 4 }
								className={ 'crlsm-media-controls' }
								style={ {
									position: 'absolute',
									bottom: '50%',
									right: '30%',
									opacity: ne && ! X ? '1' : '0',
									visibility:
										ne && ! X ? 'visible' : 'hidden',
									transition: 'opacity 0.3s ease-in-out',
									width: 'auto',
								} }
							>
								{ Boolean( j ) && ! Boolean( N ) && (
									<React.Fragment>
										<Controls.ButtonWP
											variant={ 'primary' }
											icon={ <Re /> }
											onClick={ function () {
												return fe( 'image' );
											} }
										>
											{ ( 0, I18n.__ )(
												'Replace',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										{ React.createElement( gr, {
											onOK: ge,
											alertTitle: ( 0, I18n.__ )(
												'Remove the image',
												'ohmylms'
											),
											alertDescription: ( 0, I18n.__ )(
												'Are you sure you want to remove this image?',
												'ohmylms'
											),
										} ) }
									</React.Fragment>
								) }
								{ Boolean( N ) && Boolean( j ) && (
									<React.Fragment>
										{ React.createElement( gr, {
											buttonText: ( 0, I18n.__ )(
												'Delete Video',
												'ohmylms'
											),
											onOK: he,
											alertTitle: ( 0, I18n.__ )(
												'Remove the video',
												'ohmylms'
											),
											alertDescription: ( 0, I18n.__ )(
												'Are you sure you want to remove this video?',
												'ohmylms'
											),
										} ) }
										{ React.createElement( gr, {
											buttonText: ( 0, I18n.__ )(
												'Delete Image',
												'ohmylms'
											),
											onOK: ge,
											alertTitle: ( 0, I18n.__ )(
												'Remove the image',
												'ohmylms'
											),
											alertDescription: ( 0, I18n.__ )(
												'Are you sure you want to remove this image?',
												'ohmylms'
											),
										} ) }
									</React.Fragment>
								) }
								{ ! Boolean( j ) && Boolean( N ) && ! Y && (
									<React.Fragment>
										<Controls.ButtonWP
											variant={ 'primary' }
											icon={ <Re /> }
											onClick={ function () {
												return _e( 'video' );
											} }
										>
											{ ( 0, I18n.__ )(
												'Replace',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										{ React.createElement( gr, {
											onOK: he,
											alertTitle: ( 0, I18n.__ )(
												'Remove the video',
												'ohmylms'
											),
											alertDescription: ( 0, I18n.__ )(
												'Are you sure you want to remove this video?',
												'ohmylms'
											),
										} ) }
									</React.Fragment>
								) }
							</Controls.FlexWP>
						</div>
						<Controls.SpacerWP marginBottom={ 4 } />
						<Controls.FlexWP
							align={ 'center' }
							justify={ ( () => {
								if ( 'center' === _ ) {
									return 'center';
								}
								if ( 'right' === _ ) {
									return 'flex-end';
								}
								return 'flex-start';
							} )() }
							gap={ 0 }
							className={ 'ohmylms-media-uploader-buttons '.concat(
								j || N ? 'ohmylms-has-media' : ''
							) }
						>
							<Controls.FlexWP
								gap={ 2 }
								justify={ 'flex-start' }
								style={ {
									position: 'relative',
								} }
							>
								{ ( 'image' === d || 'both' === d ) &&
									! Boolean( j ) &&
									'image_video' === E && (
										<React.Fragment>
											<Controls.ButtonWP
												variant={ 'secondary' }
												icon={ React.createElement(
													ar,
													null
												) }
												className={
													'ohmylms-media-uploader-button'
												}
												onClick={ function () {
													return fe( 'image' );
												} }
											>
												{ R }
											</Controls.ButtonWP>
										</React.Fragment>
									) }
							</Controls.FlexWP>
						</Controls.FlexWP>
					</div>
					{ ue && (
						<React.Fragment>
							<He.default isOpen={ ue } onClose={ se } />
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
