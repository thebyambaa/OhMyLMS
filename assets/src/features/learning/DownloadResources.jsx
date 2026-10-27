/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createDownloadResources( readRuntime ) {
	return function DownloadResources( props ) {
		const {
			An,
			He,
			I: Controls,
			Ie,
			In,
			M,
			Mt,
			React,
			T: StoreModule,
			Tn,
			b: I18n,
			g: ReactHooks,
			kn,
			y: WordPressData,
		} = readRuntime();
		var t = true;
		M().noConflict();
		var resources = props.resources,
			handleResources = props.handleResources,
			handleDeleteResource = props.handleDeleteResource,
			tooltipText = props.tooltipText,
			i = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			l = In( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = In( ( 0, ReactHooks.useState )( null ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = In( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = ( 0, ReactHooks.useCallback )(
				function () {
					var e;
					( e = wp.media( {
						title: 'Select or Upload Media',
						button: {
							text: 'Use this media',
						},
						multiple: ! 0,
					} ) ).on( 'select', function () {
						var _tLocal = e
							.state()
							.get( 'selection' )
							.toJSON()
							.map( function ( _eLocal ) {
								return {
									id: _eLocal.id,
									url: _eLocal.url,
									name: _eLocal.filename,
									size:
										_eLocal === null || _eLocal === void 0
											? void 0
											: _eLocal.filesizeHumanReadable,
								};
							} );
						handleResources( _tLocal );
					} );
					e.open();
				},
				[ t, i ]
			);
		return (
			<React.Fragment>
				<Controls.SpacerWP
					marginBottom={ 0 }
					padding={ 4 }
					className={ 'ohmylms-lesson-settings-resources' }
				>
					<Controls.FlexWP
						gap={ 3 }
						align={ 'center' }
						justify={ 'flex-start' }
					>
						<Controls.HeadingWP level={ 4 }>
							{ ( 0, I18n.__ )(
								'Download Resources',
								'ohmylms'
							) }
						</Controls.HeadingWP>
						{ tooltipText && (
							<Controls.TooltipWP
								title={ tooltipText }
								className={ 'ohmylms-tooltip' }
							>
								<React.Fragment>
									<Mt.A />
								</React.Fragment>
							</Controls.TooltipWP>
						) }
					</Controls.FlexWP>
					<Controls.SpacerWP marginBottom={ 2 } />
					<Controls.CardWP>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 4 }>
							<Controls.FlexWP
								justify={ 'center' }
								align={ 'center' }
							>
								<Controls.ButtonWP
									variant={ 'secondary' }
									icon={ <Tn /> }
									onClick={ h }
									className={
										'ohmylms-lesson-settings-resources-upload-button'
									}
									aria-disabled={ 'false' }
									style={ {
										cursor: 'pointer',
									} }
								>
									{ ( 0, I18n.__ )( 'Add files', 'ohmylms' ) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
					{ resources.length > 0 && (
						<React.Fragment>
							{ resources.map( function ( e ) {
								return (
									<div key={ e.id }>
										<Controls.SpacerWP
											marginBottom={ 0 }
											marginTop={ 4 }
										/>
										<Controls.CardWP>
											<Controls.SpacerWP
												marginBottom={ 0 }
												padding={ 2 }
											>
												<Controls.FlexWP
													justify={ 'space-between' }
													gap={ 3 }
												>
													<Controls.FlexWP
														gap={ 3 }
														align={ 'center' }
														justify={ 'flex-start' }
														className={
															'ohmylms-single-resource-info'
														}
													>
														<Controls.CardWP
															className={
																'resource-icon'
															}
														>
															<Controls.FlexWP
																align={
																	'center'
																}
																justify={
																	'center'
																}
																style={ {
																	width: '40px',
																	height: '40px',
																} }
															>
																{ React.createElement(
																	kn,
																	null
																) }
															</Controls.FlexWP>
														</Controls.CardWP>
														<Controls.FlexItemWP
															style={ {
																width: 'calc(100% - 52px)',
															} }
														>
															<Controls.TextWP
																as={ 'span' }
																size={ 14 }
																isBlock={ ! 0 }
																className={
																	'resource-name'
																}
																style={ {
																	wordWrap:
																		'break-word',
																} }
															>
																{ e.name }
															</Controls.TextWP>
															<Controls.TextWP
																as={ 'span' }
																size={ 12 }
																variant={
																	'muted'
																}
																className={
																	'resource-size'
																}
															>
																{ ( 0,
																I18n.__ )(
																	'Size:',
																	'ohmylms'
																) }{ ' ' }
																{ ( e ===
																	null ||
																e === void 0
																	? void 0
																	: e.size ) ||
																	'67 KB' }
															</Controls.TextWP>
														</Controls.FlexItemWP>
													</Controls.FlexWP>
													<Controls.ButtonWP
														className={
															'resource-action'
														}
														type={ 'text' }
														danger={ ! 0 }
														icon={ <An /> }
														onClick={ function () {
															return (
																( _tLocal2 =
																	e.id ),
																u( ! 0 ),
																void m(
																	_tLocal2
																)
															 );
															var _tLocal2;
														} }
													/>
												</Controls.FlexWP>
											</Controls.SpacerWP>
										</Controls.CardWP>
									</div>
								);
							} ) }
						</React.Fragment>
					) }
				</Controls.SpacerWP>
				{ c && (
					<Ie
						title={ ( 0, I18n.__ )(
							'Delete resource item?',
							'ohmylms'
						) }
						description={ ( 0, I18n.__ )(
							'Are you sure you want to delete this resource item?',
							'ohmylms'
						) }
						onClose={ function () {
							u( ! 1 );
							m( null );
						} }
						onDelete={ function () {
							u( ! 1 );
							handleDeleteResource( d );
							m( null );
						} }
						isOpen={ c }
						isDelete={ ! 0 }
					/>
				) }
				{ f && (
					<React.Fragment>
						<He.default isOpen={ f } onClose={ v } />
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
