/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement, useId } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCourseImportDialog( readRuntime ) {
	return function CourseImportDialog( props ) {
		const _controlId = useId();
		const { DG, React, WG, b: I18n, g: ReactHooks, zG } = readRuntime();
		var t = props.isOpen;
		var n = props.onClose;
		var r = props.onAction;
		var a = ( props.modalPosition, props.cancelBtnText );
		var o = void 0 === a ? ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) : a;
		var i = props.actionBtnText;
		var l = void 0 === i ? ( 0, I18n.__ )( 'Import', 'ohmylms' ) : i;
		var c = props.loading;
		var u = void 0 !== c && c;
		var s = props.supportScorm;
		var d = void 0 !== s && s;
		var m = props.jsonImportEnabled;
		var p = void 0 === m || m;
		var v = zG( ( 0, ReactHooks.useState )( null ), 2 );
		var y = v[ 0 ];
		var _ = v[ 1 ];
		var w = zG( ( 0, ReactHooks.useState )( '' ), 2 );
		var E = w[ 0 ];
		var S = w[ 1 ];
		var R = zG( ( 0, ReactHooks.useState )( ! 0 ), 2 );
		var x = R[ 0 ];
		var C = R[ 1 ];
		var P = zG( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var O = P[ 0 ];
		var k = P[ 1 ];
		var j = zG( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var A = j[ 0 ];
		var M = j[ 1 ];
		var T = zG( ( 0, ReactHooks.useState )( p ? 'json' : 'scorm' ), 2 );
		var F = T[ 0 ];
		var N = T[ 1 ];
		if ( ! t ) {
			return null;
		}
		var D = function ( e ) {
			var _tLocal3 = e.target.files[ 0 ];
			if ( ! _tLocal3 ) {
				return ( C( ! 1 ), k( ! 1 ), S( '' ), void _( null ) );
			}
			var _nLocal3 = ! 1;
			if ( 'json' === F ) {
				_nLocal3 = 'application/json' === _tLocal3.type;
			} else if ( 'scorm' === F ) {
				_nLocal3 =
					[
						'application/zip',
						'application/x-zip-compressed',
						'multipart/x-zip',
					].includes( _tLocal3.type ) ||
					_tLocal3.name.toLowerCase().endsWith( '.zip' );
			}
			if ( _nLocal3 ) {
				{
					S( _tLocal3.name );
					C( ! 0 );
					k( ! 0 );
					_( _tLocal3 );
				}
			} else {
				{
					C( ! 1 );
					k( ! 1 );
					S( '' );
					_( null );
				}
			}
		};
		var W = function ( e ) {
			if ( 'json' !== e || p ) {
				{
					N( e );
					_( null );
					S( '' );
					C( ! 0 );
					k( ! 1 );
				}
			}
		};
		var f = ( function ( e, _tLocal ) {
			if ( e === null || e === void 0 ) {
				return {};
			}
			var _nLocal;
			var _rLocal;
			var _aLocal = ( function ( _eLocal, _tLocal2 ) {
				if ( _eLocal === null || _eLocal === void 0 ) {
					return {};
				}
				var _nLocal2 = {};
				for ( var _rLocal2 in _eLocal ) {
					if ( {}.hasOwnProperty.call( _eLocal, _rLocal2 ) ) {
						if ( -1 !== _tLocal2.indexOf( _rLocal2 ) ) {
							continue;
						}
						_nLocal2[ _rLocal2 ] = _eLocal[ _rLocal2 ];
					}
				}
				return _nLocal2;
			} )( e, _tLocal );
			if ( Object.getOwnPropertySymbols ) {
				var _oLocal = Object.getOwnPropertySymbols( e );
				for ( _rLocal = 0; _rLocal < _oLocal.length; _rLocal++ ) {
					_nLocal = _oLocal[ _rLocal ];
					if (
						-1 === _tLocal.indexOf( _nLocal ) &&
						{}.propertyIsEnumerable.call( e, _nLocal )
					) {
						_aLocal[ _nLocal ] = e[ _nLocal ];
					}
				}
			}
			return _aLocal;
		} )( props, DG );
		return (
			<React.Fragment>
				{ t && (
					<Controls.ModalWP
						{ ...WG(
							{
								title: ( 0, I18n.__ )(
									'Course Import',
									'ohmylms'
								),
								onRequestClose: n,
								size: 'medium',
							},
							f
						) }
					>
						{ d && (
							<Controls.CardWP
								isBorderless={ ! 0 }
								style={ {
									margin: '0 0 20px',
								} }
							>
								<Controls.TextWP
									weight={ '500' }
									style={ {
										marginBottom: '12px',
									} }
								>
									{ ( 0, I18n.__ )(
										'Select Import Type',
										'ohmylms'
									) }
								</Controls.TextWP>
								<div
									style={ {
										display: 'flex',
										flexDirection: 'column',
										gap: '8px',
									} }
								>
									<label
										style={ {
											display: 'flex',
											alignItems: 'center',
											gap: '8px',
											opacity: p ? 1 : 0.45,
											cursor: p
												? 'pointer'
												: 'not-allowed',
										} }
										htmlFor={ _controlId + '-field-1' }
									>
										<input
											type={ 'radio' }
											value={ 'json' }
											checked={ 'json' === F }
											disabled={ ! p }
											onChange={ function () {
												return W( 'json' );
											} }
											style={ {
												cursor: p
													? 'pointer'
													: 'not-allowed',
											} }
											id={ _controlId + '-field-1' }
										/>
										<span>
											{ ( 0, I18n.__ )(
												'Import from JSON (OhMyLMS Format)',
												'ohmylms'
											) }
											{ ! p && (
												<span
													style={ {
														marginLeft: '6px',
														fontSize: '11px',
														background: '#6e42d3',
														color: '#fff',
														borderRadius: '3px',
														padding: '1px 6px',
														fontWeight: '600',
													} }
												>
													{ ( 0, I18n.__ )(
														'Pro',
														'ohmylms'
													) }
												</span>
											) }
										</span>
									</label>
									<label
										style={ {
											display: 'flex',
											alignItems: 'center',
											gap: '8px',
											cursor: 'pointer',
										} }
										htmlFor={ _controlId + '-field-2' }
									>
										<input
											type={ 'radio' }
											value={ 'scorm' }
											checked={ 'scorm' === F }
											onChange={ function () {
												return W( 'scorm' );
											} }
											style={ {
												cursor: 'pointer',
											} }
											id={ _controlId + '-field-2' }
										/>
										<span>
											{ ( 0, I18n.__ )(
												'Import from SCORM Package',
												'ohmylms'
											) }
										</span>
									</label>
								</div>
							</Controls.CardWP>
						) }
						<Controls.CardWP
							isBorderless={ ! 0 }
							style={ {
								margin: '0 0 20px',
							} }
						>
							<Controls.FlexWP
								align={ 'center' }
								justify={ 'center' }
								direction={ 'column' }
								gap={ '4' }
								onDrop={ function ( e ) {
									e.preventDefault();
									M( ! 1 );
									var _tLocal4 = e.dataTransfer.files;
									if ( _tLocal4.length > 0 ) {
										var _nLocal4 = _tLocal4[ 0 ];
										D( {
											target: {
												files: [ _nLocal4 ],
											},
										} );
									}
								} }
								onDragOver={ function ( e ) {
									e.preventDefault();
									M( ! 0 );
								} }
								onDragLeave={ function () {
									M( ! 1 );
								} }
								style={ {
									border: A
										? '2px dashed #6E42D3'
										: '2px dashed #e1e1e1',
									padding: '20px',
									borderRadius: '8px',
									backgroundColor: A
										? '#f9f5ff'
										: '#f0f0f173',
									transition: 'all 0.2s ease-in-out',
									minHeight: '120px',
								} }
							>
								<Controls.FormFileUploadWP
									label={
										'json' === F
											? ( 0, I18n.__ )(
													'Upload JSON file',
													'ohmylms'
												)
											: ( 0, I18n.__ )(
													'Upload SCORM ZIP file',
													'ohmylms'
												)
									}
									onChange={ D }
									accept={
										'json' === F
											? 'application/json'
											: '.zip'
									}
									style={ {
										border: '1px solid transparent',
										backgroundColor: '#6e42d32b',
									} }
								/>
								{ E && (
									<Controls.TextWP
										align={ 'center' }
										style={ {
											color: '#333',
										} }
									>
										{ ( 0, I18n.__ )(
											'Uploaded:',
											'ohmylms'
										) }{ ' ' }
										<strong>{ E }</strong>
									</Controls.TextWP>
								) }
								{ ! x && O && (
									<Controls.TextWP
										align={ 'center' }
										style={ {
											color: 'red',
										} }
									>
										{ '❌ ' }
										{ 'json' === F
											? ( 0, I18n.__ )(
													'Invalid file format. Please upload a valid JSON file.',
													'ohmylms'
												)
											: ( 0, I18n.__ )(
													'Invalid file format. Please upload a valid ZIP file containing a SCORM package.',
													'ohmylms'
												) }
									</Controls.TextWP>
								) }
								{ ! O && (
									<Controls.TextWP
										align={ 'center' }
										style={ {
											fontSize: '14px',
											color: '#666',
										} }
									>
										{ 'json' === F ? (
											<React.Fragment>
												{ ( 0, I18n.__ )(
													'Drag & drop a JSON file here or',
													'ohmylms'
												) }
												<br />
												{ ( 0, I18n.__ )(
													'use the upload button.',
													'ohmylms'
												) }
											</React.Fragment>
										) : (
											<React.Fragment>
												{ ( 0, I18n.__ )(
													'Drag & drop a SCORM ZIP package here or',
													'ohmylms'
												) }
												<br />
												{ ( 0, I18n.__ )(
													'use the upload button.',
													'ohmylms'
												) }
												<br />
												<span
													style={ {
														fontSize: '12px',
														color: '#999',
													} }
												>
													{ ( 0, I18n.__ )(
														'(SCORM 1.2 and SCORM 2004 supported)',
														'ohmylms'
													) }
												</span>
											</React.Fragment>
										) }
									</Controls.TextWP>
								) }
							</Controls.FlexWP>
						</Controls.CardWP>
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'end' }
							gap={ '2' }
						>
							<Controls.ButtonWP
								onClick={ n }
								variant={ 'secondary' }
								disabled={ u }
							>
								{ o }
							</Controls.ButtonWP>
							<Controls.ButtonWP
								onClick={ function () {
									return r( y, F );
								} }
								disabled={ ! O || u }
								isBusy={ u }
								variant={ 'primary' }
							>
								{ l }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.ModalWP>
				) }
			</React.Fragment>
		);
	};
}
