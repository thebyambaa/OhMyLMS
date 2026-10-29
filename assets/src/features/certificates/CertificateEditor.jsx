import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createCertificateEditor( readRuntime ) {
	return function CertificateEditor( props ) {
		const {
			DL: MemoCertificateControls,
			I: Controls,
			React,
			T: StoreModule,
			YL: MemoCertificatePreview,
			_L: MemoCertificateEditorHeader,
			b: I18n,
			g: ReactHooks,
			iV,
			oL,
			oV: MemoCertificateCourseSelector,
			y: WordPressData,
		} = readRuntime();
		var t = props.onClose,
			n = props.componentFrom,
			r = void 0 === n ? '' : n,
			a = ( 0, ReactHooks.useRef )(),
			o = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectCertificate();
			}, [] ),
			i = iV( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			l = i[ 0 ],
			c = i[ 1 ],
			u = useNotifications(),
			s = u.openNotificationWithIcon,
			d = u.contextHolder,
			m = iV( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ];
		return (
			<React.Fragment>
				{ d }
				<Controls.FlexWP
					align={ 'start' }
					justify={ 'start' }
					direction={ 'column' }
					gap={ 0 }
					className={ 'ohmylms-classic-certificate-builder' }
				>
					<MemoCertificateEditorHeader
						saveAsPDF={ function () {
							try {
								c( ! 0 );
								var e = a.current;
								oL()
									.then( function ( _tLocal ) {
										_tLocal()
											.from( e )
											.set( {
												margin: 10,
												filename: ''.concat(
													( o === null || o === void 0
														? void 0
														: o.name ) ||
														'Untitled Template',
													'.pdf'
												),
												image: {
													type: 'jpeg',
													quality: 0.98,
												},
												html2canvas: {
													scale: 2,
													useCORS: ! 0,
												},
												jsPDF: {
													unit: 'mm',
													format: 'a4',
													orientation: 'landscape',
												},
											} )
											.save();
									} )
									.catch( function ( _eLocal ) {
										showRequestError(
											'Failed to load html2pdf:',
											_eLocal
										);
									} );
								s(
									'success',
									( 0, I18n.__ )(
										'Saved Successfully',
										'ohmylms'
									)
								);
							} catch ( _eLocal2 ) {
								showRequestError( _eLocal2 );
								s(
									'error',
									( 0, I18n.__ )( 'Saved failed', 'ohmylms' )
								);
							} finally {
								c( ! 1 );
							}
						} }
						isLoading={ l }
						elementRef={ a }
						onClose={ t }
						setShowCoursesModal={ f }
						componentFrom={ r }
					/>
					<Controls.FlexWP justify={ 'flex-start' } align={ 'start' }>
						<Controls.FlexItemWP
							style={ {
								flex: '1',
							} }
						>
							<MemoCertificateControls />
						</Controls.FlexItemWP>
						<Controls.FlexItemWP
							style={ {
								flex: '4',
								position: 'relative',
							} }
						>
							<MemoCertificatePreview certificateRef={ a } />
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				</Controls.FlexWP>
				{ p && (
					<MemoCertificateCourseSelector
						onClose={ function () {
							return f( ! 1 );
						} }
						isOpen={ p }
					/>
				) }
			</React.Fragment>
		);
	};
}
