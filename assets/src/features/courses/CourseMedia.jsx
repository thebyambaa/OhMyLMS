import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCourseMedia( readRuntime ) {
	return function CourseMedia( props ) {
		const {
			Bp,
			Gp,
			He,
			Ie,
			M,
			Np,
			React,
			T: StoreModule,
			Up,
			Vp,
			Wp,
			b: I18n,
			g: ReactHooks,
			gr,
			y: WordPressData,
		} = readRuntime();
		M().noConflict();
		var videoSrc = props.videoSrc;
		var imageSrc = props.imageSrc;
		var handleRemoveMedia = props.handleRemoveMedia;
		var handleUploadComplete = props.handleUploadComplete;
		( 0, WordPressData.useDispatch )( StoreModule.default );
		var l = Up( ( 0, ReactHooks.useState )( imageSrc ), 2 );
		var c = l[ 0 ];
		var u = l[ 1 ];
		var s = Up( ( 0, ReactHooks.useState )( videoSrc ), 2 );
		var d = s[ 0 ];
		var m = s[ 1 ];
		var p = Up( ( 0, ReactHooks.useState )( null ), 2 );
		var f = p[ 0 ];
		var v = p[ 1 ];
		var h = Up( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var _ = h[ 0 ];
		var w = h[ 1 ];
		var E = Up( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var S = E[ 0 ];
		var R = E[ 1 ];
		var x = Up( ( 0, ReactHooks.useState )( null ), 2 );
		var C = x[ 0 ];
		var P = x[ 1 ];
		var O = ( 0, ReactHooks.useRef )( null );
		var k = ( 0, ReactHooks.useRef )( null );
		var j = Up( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var A = j[ 0 ];
		var F = j[ 1 ];
		var N = Up( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var D = N[ 0 ];
		var W = N[ 1 ];
		( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getAllIntegrations();
		}, [] );
		var V = function () {
			var e;
			w( ! 1 );
			if ( ! ( null === ( e = O.current ) || void 0 === e ) ) {
				e.pause();
			}
			O.current.currentTime = 0;
		};
		var H = function ( e ) {
			var _tLocal = [ 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp' ],
				n = [ 'mp4', 'mov', 'avi', 'mkv' ],
				r = [
					'image/jpeg',
					'image/png',
					'image/gif',
					'image/svg+xml',
					'image/webp',
				].join( ',' ),
				a = [
					'video/mp4',
					'video/quicktime',
					'video/x-msvideo',
					'video/x-matroska',
				].join( ',' ),
				_iLocal = wp.media( {
					title: 'Select or Upload Media',
					button: {
						text: 'Use this media',
					},
					multiple: ! 1,
					library: {
						type: e,
					},
				} );
			_iLocal.on( 'open', function () {
				_iLocal.content.mode( 'upload' );
				_iLocal.on( 'uploader:ready', function () {
					document
						.querySelectorAll(
							'.moxie-shim-html5 input[type="file"]'
						)
						.forEach( function ( _tLocal2 ) {
							_tLocal2.setAttribute( 'tabIndex', '-1' );
							_tLocal2.setAttribute( 'multiple', 'false' );
							_tLocal2.setAttribute( 'aria-hidden', 'true' );
							if ( 'video' === e ) {
								_tLocal2.setAttribute( 'accept', a );
							} else {
								_tLocal2.setAttribute( 'accept', r );
							}
						} );
				} );
			} );
			_iLocal.on( 'select', function () {
				var _rLocal = _iLocal.state().get( 'selection' ).first(),
					_aLocal = _rLocal.get( 'filesizeInBytes' ),
					_lLocal = _rLocal.toJSON(),
					_cLocal = _tLocal.some( function ( _eLocal ) {
						return _lLocal.url.toLowerCase().endsWith( _eLocal );
					} );
				if ( 'video' === e ) {
					_cLocal = n.some( function ( _eLocal2 ) {
						return _lLocal.url.toLowerCase().endsWith( _eLocal2 );
					} );
				}
				var _sLocal =
					( 'image' === e && 'image' === _lLocal.type ) ||
					( 'video' === e && 'video' === _lLocal.type ) ||
					_lLocal.type === e;
				( () => {
					if ( 'image' === e && _aLocal > 5242880 ) {
						return showRequestError(
							( 0, I18n.__ )(
								'Image exceeds the 5MB size limit. Please choose a smaller file.',
								'ohmylms'
							)
						);
					}
					if ( _cLocal && _sLocal ) {
						return (
							'image' === e
								? u( _lLocal.url )
								: 'video' === e && m( _lLocal.url ),
							handleUploadComplete &&
								handleUploadComplete( _lLocal, e )
						 );
					}
					return showRequestError(
						'Invalid file type or media type.'
					);
				} )();
			} );
			_iLocal.open();
		};
		var G = function ( e ) {
			v( e );
		};
		var U = function ( e ) {
			if ( handleRemoveMedia ) {
				handleRemoveMedia( e );
			}
			u( null );
			m( null );
			v( null );
		};
		( 0, ReactHooks.useEffect )(
			function () {
				if ( imageSrc ) {
					u( imageSrc );
				}
				if ( videoSrc ) {
					m( videoSrc );
				}
			},
			[ imageSrc, videoSrc ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				var e = function ( _eLocal3 ) {
					if ( ! (
						! A ||
						! k.current ||
						k.current.contains( _eLocal3.target ) ||
						_eLocal3.target.closest( '.ohmylms-history-list' ) ||
						_eLocal3.target.closest( '.ohmylms-tooltip-box' ) ||
						_eLocal3.target.closest( '.ohmylms-ai-image-prompt' )
					) ) {
						{
							F( ! 1 );
							G( null );
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
			[ A, k ]
		);
		var q = {
			height: '100%',
			width: '100%',
			objectFit: 'cover',
			borderRadius: '8px',
		};
		return (
			<React.Fragment>
				<div className={ 'ohmylms-course-thumb-wrapper' }>
					<div className={ 'ohmylms-course-thumb-media' }>
						{ d ? (
							<React.Fragment>
								<video
									ref={ O }
									src={ d }
									controls={ _ }
									controlsList={ 'nodownload noplaybackrate' }
									poster={ c || '' }
									disablePictureInPicture={ ! 0 }
									onPause={ V }
									onEnded={ V }
								/>
							</React.Fragment>
						) : (
							( () => {
								if ( f ) {
									return (
										<React.Fragment>
											<img
												style={ q }
												src={ f }
												alt={ 'Course Thumb' }
											/>
										</React.Fragment>
									);
								}
								if ( c ) {
									return (
										<React.Fragment>
											<img
												style={ q }
												src={ c }
												alt={ 'Course Thumb' }
											/>
										</React.Fragment>
									);
								}
								return (
									<React.Fragment>
										<div
											className={
												'ohmylms-course-thumb-placeholder'
											}
										>
											<svg
												fill={ 'none' }
												width={ '88' }
												height={ '88' }
												viewBox={ '0 0 88 88' }
												xmlns={
													'http://www.w3.org/2000/svg'
												}
											>
												<g
													clipPath={
														'url(#clip0_3016_26872)'
													}
												>
													<path
														fill={ '#DEE0E4' }
														d={
															'M38.5 13.75a13.75 13.75 0 11-27.5 0 13.75 13.75 0 0127.5 0zm23.237 22.291a2.75 2.75 0 00-3.173.512L38.159 62.458l-14.63-15.246a2.75 2.75 0 00-3.465.341L.01 71.5v11a5.5 5.5 0 005.5 5.5h77a5.5 5.5 0 005.5-5.5V57.75L61.737 36.041z'
														}
													/>
												</g>
												<defs>
													<clipPath
														id={
															'clip0_3016_26872'
														}
													>
														<path
															fill={ '#fff' }
															d={
																'M0 0h88v88H0z'
															}
														/>
													</clipPath>
												</defs>
											</svg>
										</div>
									</React.Fragment>
								);
							} )()
						) }
						{ ! _ && (
							<div className={ 'ohmylms-course-thumb-controls' }>
								{ ( c || d ) && (
									<div
										className={
											'clrms-course-thumb-controls-btns'
										}
									>
										{ c && d ? (
											<Controls.TooltipWP
												text={ ( 0, I18n.__ )(
													'Delete Thumb Image/Video',
													'ohmylms'
												) }
											>
												<Controls.DropdownMenuWP
													controls={ [
														{
															title: ( 0,
															I18n.__ )(
																'Delete Image',
																'ohmylms'
															),
															key: 'delete-image',
															onClick() {
																P( {
																	type: 'image',
																	title: ( 0,
																	I18n.__ )(
																		'Remove Image',
																		'ohmylms'
																	),
																	description:
																		( 0,
																		I18n.__ )(
																			'Are you sure you want to remove this image?',
																			'ohmylms'
																		),
																} );
																R( ! 0 );
															},
														},
														{
															title: ( 0,
															I18n.__ )(
																'Delete Video',
																'ohmylms'
															),
															key: 'delete-video',
															onClick() {
																P( {
																	type: 'video',
																	title: ( 0,
																	I18n.__ )(
																		'Remove Video',
																		'ohmylms'
																	),
																	description:
																		( 0,
																		I18n.__ )(
																			'Are you sure you want to remove this video?',
																			'ohmylms'
																		),
																} );
																R( ! 0 );
															},
														},
													] }
													icon={ <Vp /> }
												/>
											</Controls.TooltipWP>
										) : (
											<React.Fragment>
												<Controls.TooltipWP
													text={ ( 0, I18n.__ )(
														'Delete Thumb '.concat(
															d
																? 'Video'
																: 'Image'
														),
														'ohmylms'
													) }
												>
													{ React.createElement( gr, {
														onOK() {
															return U(
																d
																	? 'video'
																	: 'image'
															);
														},
														alertTitle: ( 0,
														I18n.__ )(
															'Remove the '.concat(
																d
																	? 'video'
																	: 'image'
															),
															'ohmylms'
														),
														alertDescription: ( 0,
														I18n.__ )(
															'Are you sure you want to remove this '.concat(
																d
																	? 'video'
																	: 'image',
																'?'
															),
															'ohmylms'
														),
														iconOnly: ! 0,
														Icon: Vp,
													} ) }
												</Controls.TooltipWP>
											</React.Fragment>
										) }
									</div>
								) }
								{ d && (
									<React.Fragment>
										<Controls.FlexWP
											justify={ 'flex-start' }
											align={ 'center' }
											gap={ 2 }
											className={
												'ohmylms-thumb-video-player'
											}
										>
											<Controls.TooltipWP
												text={ ( 0, I18n.__ )(
													'Play Thumb Video',
													'ohmylms'
												) }
											>
												<Controls.ButtonWP
													variant={ 'text' }
													icon={ <Gp /> }
													onClick={ function () {
														var e;
														w( ! 0 );
														if ( ! (
															null ===
																( e =
																	O.current ) ||
															void 0 === e
														) ) {
															e.play();
														}
													} }
													padding={ '0px' }
												/>
											</Controls.TooltipWP>
											<span
												className={
													'ohmylms-thumb-video-player-line'
												}
											/>
										</Controls.FlexWP>
									</React.Fragment>
								) }
							</div>
						) }
					</div>
					<Controls.SpacerWP marginBottom={ 1.5 } />
					<Controls.FlexWP
						justify={ 'flex-start' }
						gap={ 1 }
						className={ 'ohmylms-course-thumb-actions' }
					>
						{ d && c && (
							<React.Fragment>
								<Controls.TooltipWP
									text={ ( 0, I18n.__ )(
										'Replace Thumbnail Image/Video',
										'ohmylms'
									) }
								>
									<Controls.DropdownMenuWP
										controls={ [
											{
												title: ( 0, I18n.__ )(
													'Replace Image',
													'ohmylms'
												),
												key: 'replace-image',
												onClick() {
													return H( 'image' );
												},
											},
											{
												title: ( 0, I18n.__ )(
													'Replace Video',
													'ohmylms'
												),
												key: 'replace-video',
												onClick() {
													return H( 'video' );
												},
											},
										] }
										icon={ <Bp /> }
									/>
								</Controls.TooltipWP>
							</React.Fragment>
						) }
						{ ( c || d ) && ! ( c && d ) && (
							<Controls.TooltipWP
								text={ ( 0, I18n.__ )(
									'Replace Thumbnail '.concat(
										d ? 'Video' : 'image'
									),
									'ohmylms'
								) }
							>
								<Controls.ButtonWP
									variant={ 'text' }
									icon={ <Bp /> }
									onClick={ function () {
										return H( d ? 'video' : 'image' );
									} }
									padding={ '0px' }
								/>
							</Controls.TooltipWP>
						) }
						{ ! c && (
							<React.Fragment>
								<Controls.TooltipWP
									text={ ( 0, I18n.__ )(
										'Upload Thumbnail Image',
										'ohmylms'
									) }
								>
									<Controls.ButtonWP
										variant={ 'text' }
										icon={ <Np /> }
										onClick={ function () {
											return H( 'image' );
										} }
										padding={ '0px' }
									/>
								</Controls.TooltipWP>
							</React.Fragment>
						) }
						{ ! d && (
							<React.Fragment>
								<Controls.TooltipWP
									text={ ( 0, I18n.__ )(
										'Upload Thumbnail Video',
										'ohmylms'
									) }
								>
									<Controls.ButtonWP
										variant={ 'text' }
										icon={ <Wp /> }
										onClick={ function () {
											return H( 'video' );
										} }
										padding={ '0px' }
									/>
								</Controls.TooltipWP>
							</React.Fragment>
						) }
					</Controls.FlexWP>
				</div>
				{ D && (
					<React.Fragment>
						<He.default isOpen={ D } onClose={ W } />
					</React.Fragment>
				) }
				{ S && (
					<Ie
						title={ C === null || C === void 0 ? void 0 : C.title }
						description={
							C === null || C === void 0 ? void 0 : C.description
						}
						onClose={ function () {
							P( null );
							R( ! 1 );
						} }
						onDelete={ function () {
							U( C === null || C === void 0 ? void 0 : C.type );
							R( ! 1 );
							P( null );
						} }
						isOpen={ S }
						isDelete={ ! 0 }
					/>
				) }
			</React.Fragment>
		);
	};
}
