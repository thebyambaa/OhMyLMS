/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { MovableTabPanel } from '../navigation/MovableTabPanel';
import { Switcher } from '../../shared/spinner';
export function createEmailTemplateList( readRuntime ) {
	return function EmailTemplateList() {
		const {
			D: Buttons,
			I: Controls,
			React,
			T: StoreModule,
			b: I18n,
			f: Router,
			g: ReactHooks,
			i4,
			y: WordPressData,
		} = readRuntime();
		var e = i4( ( 0, ReactHooks.useState )( 'creator' ), 2 ),
			t = e[ 0 ],
			n = e[ 1 ],
			r = i4( ( 0, ReactHooks.useState )( '' ), 2 ),
			a = r[ 0 ],
			o = ( r[ 1 ], ( 0, Router.Zp )() ),
			i = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getEmails();
			}, [] ),
			l = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getEmailLoading();
			}, [] ),
			c = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			u = c.setEmail,
			s = c.updateSingleEmail,
			d = c.updateEmails,
			m = c.searchEmails,
			p = function ( _eLocal3 ) {
				var _nLocal = i[ t ].find( function ( _tLocal ) {
					return _tLocal.basic.id === _eLocal3;
				} );
				u( _nLocal );
				o( '/emails/'.concat( _eLocal3 ) );
			},
			v = function () {
				var _eLocal4;
				return (
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP
							marginBottom={ 0 }
							marginTop={ 2.5 }
							padding={ 6 }
						>
							{ ( null === ( _eLocal4 = i[ t ] ) ||
							void 0 === _eLocal4
								? void 0
								: _eLocal4.length ) > 0 &&
								i[ t ].map( function ( _eLocal5, _nLocal2 ) {
									var _rLocal,
										_aLocal,
										_oLocal,
										_lLocal,
										_cLocal;
									return (
										<React.Fragment
											key={
												null !==
													( _rLocal =
														_eLocal5 === null ||
														_eLocal5 === void 0 ||
														null ===
															( _aLocal =
																_eLocal5.basic ) ||
														void 0 === _aLocal
															? void 0
															: _aLocal.id ) &&
												void 0 !== _rLocal
													? _rLocal
													: _nLocal2
											}
										>
											{ 0 !== _nLocal2 && (
												<Controls.SpacerWP
													marginTop={ 8 }
													marginBottom={ 8 }
												>
													<Controls.DividerWP
														color={ '#EDF2FB' }
													/>
												</Controls.SpacerWP>
											) }
											<Controls.FlexWP
												gap={ 2 }
												align={ 'center' }
												justify={ 'space-between' }
												key={
													_eLocal5 === null ||
													_eLocal5 === void 0 ||
													null ===
														( _oLocal =
															_eLocal5.basic ) ||
													void 0 === _oLocal
														? void 0
														: _oLocal.id
												}
												className={
													'ohmylms-email-listing-card'
												}
											>
												<Controls.FlexWP
													direction={ 'column' }
													gap={ 2 }
													style={ {
														width: '70%',
													} }
												>
													<Controls.HeadingWP
														level={ 4 }
														color={ '#000D25' }
														size={ '16px' }
														onClick={ function () {
															var _tLocal2;
															return p(
																_eLocal5 ===
																	null ||
																	_eLocal5 ===
																		void 0 ||
																	null ===
																		( _tLocal2 =
																			_eLocal5.basic ) ||
																	void 0 ===
																		_tLocal2
																	? void 0
																	: _tLocal2.id
															);
														} }
														style={ {
															cursor: 'pointer',
														} }
													>
														{ _eLocal5 === null ||
														_eLocal5 === void 0 ||
														null ===
															( _lLocal =
																_eLocal5.basic ) ||
														void 0 === _lLocal
															? void 0
															: _lLocal.title }
													</Controls.HeadingWP>
													<Controls.TextWP
														color={ '#687784' }
														size={ '14px' }
													>
														{ _eLocal5 === null ||
														_eLocal5 === void 0 ||
														null ===
															( _cLocal =
																_eLocal5.basic ) ||
														void 0 === _cLocal
															? void 0
															: _cLocal.tooltip }
													</Controls.TextWP>
												</Controls.FlexWP>
												<Controls.FlexWP
													gap={ 4 }
													align={ 'center' }
													justify={ 'end' }
													className={
														'ohmylms-email-listing-card-actions'
													}
												>
													<Switcher
														onChange={ function () {
															var _nLocal3;
															return ( function (
																_eLocal6
															) {
																var _nLocal4,
																	_rLocal2,
																	_aLocal2 =
																		i[
																			t
																		].find(
																			function (
																				_tLocal3
																			) {
																				return (
																					_tLocal3
																						.basic
																						.id ===
																					_eLocal6
																				);
																			}
																		);
																_aLocal2.enable =
																	! _aLocal2.enable;
																s(
																	_aLocal2 ===
																		null ||
																		_aLocal2 ===
																			void 0 ||
																		null ===
																			( _nLocal4 =
																				_aLocal2.basic ) ||
																		void 0 ===
																			_nLocal4
																		? void 0
																		: _nLocal4.id,
																	_aLocal2
																);
																d(
																	_aLocal2 ===
																		null ||
																		_aLocal2 ===
																			void 0 ||
																		null ===
																			( _rLocal2 =
																				_aLocal2.basic ) ||
																		void 0 ===
																			_rLocal2
																		? void 0
																		: _rLocal2.id,
																	{
																		enable: _aLocal2.enable,
																	},
																	t
																);
															} )(
																_eLocal5 ===
																	null ||
																	_eLocal5 ===
																		void 0 ||
																	null ===
																		( _nLocal3 =
																			_eLocal5.basic ) ||
																	void 0 ===
																		_nLocal3
																	? void 0
																	: _nLocal3.id
															);
														} }
														checked={
															_eLocal5 === null ||
															_eLocal5 === void 0
																? void 0
																: _eLocal5.enable
														}
														isDefaultStyle={ ! 0 }
													/>
													<Buttons.A
														variant={ 'text' }
														onClick={ function () {
															var _tLocal4;
															return p(
																_eLocal5 ===
																	null ||
																	_eLocal5 ===
																		void 0 ||
																	null ===
																		( _tLocal4 =
																			_eLocal5.basic ) ||
																	void 0 ===
																		_tLocal4
																	? void 0
																	: _tLocal4.id
															);
														} }
													>
														<Controls.FlexWP
															align={ 'center' }
															gap={ 2.25 }
														>
															<Controls.TextWP
																color={
																	'#444D5E'
																}
																size={ '14px' }
																fontWeight={
																	400
																}
															>
																{ ( 0,
																I18n.__ )(
																	'Edit Template',
																	'ohmylms'
																) }
															</Controls.TextWP>
															<svg
																xmlns={
																	'http://www.w3.org/2000/svg'
																}
																width={ '14' }
																height={ '16' }
																viewBox={
																	'0 0 14 16'
																}
																fill={ 'none' }
															>
																<path
																	d={
																		'M14 3L11 0L2.5 8.5L1.5 12.5L5.5 11.5L14 3ZM7 14.5H0V16H7V14.5Z'
																	}
																	fill={
																		'#444D5E'
																	}
																/>
															</svg>
														</Controls.FlexWP>
													</Buttons.A>
												</Controls.FlexWP>
											</Controls.FlexWP>
										</React.Fragment>
									);
								} ) }
						</Controls.SpacerWP>
					</Controls.CardWP>
				);
			},
			h = [
				{
					label: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Admin Email', 'ohmylms' ) }
						</React.Fragment>
					),
					key: 'creator',
					children: v(),
				},
				{
					label: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Student Email', 'ohmylms' ) }
						</React.Fragment>
					),
					key: 'student',
					children: v(),
				},
			];
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( a.length > 2 || 0 === a.length ) {
						m( a, i[ t ] );
					}
				},
				[ a ]
			),
			l ? (
				<Controls.SkeletonWP
					active={ ! 0 }
					rows={ 10 }
					style={ {
						position: 'absolute',
						top: '17px',
						left: '15px',
						zIndex: 3,
						background: '#FFFFFF',
						height: 'calc(100% - 32px)',
						padding: '24px',
						width: 'calc(100% - 26px)',
						borderRadius: '8px',
					} }
				/>
			) : (
				<React.Fragment>
					<MovableTabPanel
						scope="emails"
						items={ h }
						className={ 'ohmylms-emails-tabs' }
						onChange={ function ( _eLocal7 ) {
							n( _eLocal7 );
						} }
						activekey={ t }
					/>
				</React.Fragment>
			)
		 );
	};
}
