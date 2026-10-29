/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createMigrationFileDropzone( readRuntime ) {
	return function MigrationFileDropzone( props ) {
		const { React, b: I18n, e6, g: ReactHooks } = readRuntime();
		var t = props.onFileChange,
			n = props.jsonImportEnabled,
			r = void 0 === n || n,
			a = e6( ( 0, ReactHooks.useState )( '' ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			l = e6( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = function ( e ) {
				if ( e ) {
					var _nLocal =
							[
								'application/zip',
								'application/x-zip-compressed',
								'multipart/x-zip',
							].includes( e.type ) ||
							e.name.toLowerCase().endsWith( '.zip' ),
						_rLocal =
							'application/json' === e.type ||
							e.name.toLowerCase().endsWith( '.json' );
					if ( _nLocal || _rLocal ) {
						{
							i( e.name );
							if ( t ) {
								t( e, _nLocal ? 'scorm' : 'json' );
							}
						}
					}
				}
			};
		return (
			<div
				onDrop={ function ( e ) {
					e.preventDefault();
					u( ! 1 );
					var _tLocal = e.dataTransfer.files[ 0 ];
					s( _tLocal );
				} }
				onDragOver={ function ( e ) {
					e.preventDefault();
					u( ! 0 );
				} }
				onDragLeave={ function () {
					return u( ! 1 );
				} }
				style={ {
					backgroundColor: c ? '#f0f4ff' : '#fcfcfc',
					border: c
						? '1px dashed #6E42D3'
						: '1px dashed rgba(200, 210, 233, 0.74)',
					borderRadius: '8px',
					padding: '40px 12px',
					width: '100%',
					boxSizing: 'border-box',
					transition: 'all 0.2s ease-in-out',
				} }
			>
				<Controls.FlexWP
					direction={ 'column' }
					gap={ 4 }
					justify={ 'center' }
					align={ 'center' }
					style={ {
						textAlign: 'center',
					} }
				>
					<Controls.FormFileUploadWP
						accept={ r ? '.zip,.json' : '.zip' }
						onChange={ function ( e ) {
							var _tLocal2 = e.target.files[ 0 ];
							s( _tLocal2 );
						} }
						label={
							<Controls.FlexWP
								gap={ 2 }
								align={ 'center' }
								justify={ 'center' }
							>
								<svg
									xmlns={ 'http://www.w3.org/2000/svg' }
									width={ '18' }
									height={ '18' }
									viewBox={ '0 0 18 18' }
									fill={ 'none' }
								>
									<path
										d={
											'M13.875 11.25V13.875H9.75V5.02505L13.125 8.10005L13.875 7.27505L9.225 2.92505L4.875 7.27505L5.625 8.10005L8.625 5.10005V13.875H4.125V11.25H3V15H15V11.25H13.875Z'
										}
										fill={ '#687784' }
									/>
								</svg>
								<span>
									{ ( 0, I18n.__ )( 'Upload', 'ohmylms' ) }
								</span>
							</Controls.FlexWP>
						}
						style={ {
							backgroundColor: '#f4f5f7',
							border: '1px solid #ebebef',
							borderRadius: '2px',
							height: '40px',
							minWidth: '125px',
							padding: '8px 22px',
							cursor: 'pointer',
							fontSize: '14px',
							fontWeight: '500',
							color: '#000d25',
							letterSpacing: '-0.14px',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							gap: '8px',
						} }
					/>
					<Controls.FlexWP
						direction={ 'column' }
						gap={ 1 }
						align={ 'center' }
					>
						{ o ? (
							<Controls.TextWP
								as={ 'p' }
								size={ '14' }
								color={ '#444d5e' }
								align={ 'center' }
								weight={ '500' }
								style={ {
									margin: 0,
								} }
							>
								{ ( 0, I18n.__ )( 'Selected: ', 'ohmylms' ) }
								<strong>{ o }</strong>
							</Controls.TextWP>
						) : (
							<React.Fragment>
								<Controls.TextWP
									as={ 'p' }
									size={ '14' }
									color={ '#687784' }
									align={ 'center' }
									weight={ '400' }
									style={ {
										margin: 0,
									} }
								>
									{ ( 0, I18n.__ )(
										'Drag & drop or upload your file here.',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.TextWP
									as={ 'p' }
									size={ '14' }
									color={ '#687784' }
									align={ 'center' }
									weight={ '400' }
									style={ {
										margin: 0,
									} }
								>
									<span
										style={ {
											marginRight: '4px',
										} }
									>
										<strong>
											{ ( 0, I18n.__ )(
												'ZIP',
												'ohmylms'
											) }
										</strong>
										{ ( 0, I18n.__ )(
											' (SCORM 1.2 / 2004)',
											'ohmylms'
										) }
									</span>
									<span
										style={ {
											background: '#e6f4ea',
											color: '#1e7e34',
											borderRadius: '3px',
											padding: '1px 6px',
											fontSize: '11px',
											fontWeight: '600',
											marginRight: '12px',
										} }
									>
										{ ( 0, I18n.__ )( 'Free', 'ohmylms' ) }
									</span>
									<span
										style={ {
											marginRight: '4px',
										} }
									>
										<strong>
											{ ( 0, I18n.__ )(
												'JSON',
												'ohmylms'
											) }
										</strong>
										{ ( 0, I18n.__ )(
											' (OhMyLMS)',
											'ohmylms'
										) }
									</span>
									<span
										style={ {
											background: r
												? '#e6f4ea'
												: '#6e42d3',
											color: r ? '#1e7e34' : '#fff',
											borderRadius: '3px',
											padding: '1px 6px',
											fontSize: '11px',
											fontWeight: '600',
										} }
									>
										{ r
											? ( 0, I18n.__ )(
													'Free',
													'ohmylms'
												)
											: ( 0, I18n.__ )(
													'Pro',
													'ohmylms'
												) }
									</span>
								</Controls.TextWP>
							</React.Fragment>
						) }
					</Controls.FlexWP>
				</Controls.FlexWP>
			</div>
		);
	};
}
