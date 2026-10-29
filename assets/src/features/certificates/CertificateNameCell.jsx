/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Avatar } from '../../shared/avatar';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createCertificateNameCell( readRuntime ) {
	return function CertificateNameCell( props ) {
		const {
			Ge,
			React,
			b: I18n,
			f: Router,
			g: ReactHooks,
			v,
		} = readRuntime();
		var t = props.record,
			n = ( props.isHover, ( 0, Router.Zp )() ),
			r = ( 0, ReactHooks.useCallback )(
				function () {
					n(
						'/certificate-edit/'.concat(
							t === null || t === void 0 ? void 0 : t.id
						)
					);
				},
				[ t === null || t === void 0 ? void 0 : t.id, n ]
			);
		return (
			( 0, ReactHooks.useCallback )(
				function () {
					n(
						'/certificate/'.concat(
							t === null || t === void 0 ? void 0 : t.id,
							'/report'
						)
					);
				},
				[ t === null || t === void 0 ? void 0 : t.id, n ]
			),
			(
				<React.Fragment>
					<Controls.FlexWP
						align={ 'start' }
						justify={ 'start' }
						gap={ '4' }
					>
						<v.Link
							to={ '/certificate-edit/'.concat(
								t === null || t === void 0 ? void 0 : t.id
							) }
						>
							{ t !== null && t !== void 0 && t.image_src ? (
								<Avatar
									shape={ 'square' }
									src={ t.image_src }
									size={ 100 }
								/>
							) : (
								<span
									style={ {
										width: 40,
										height: 40,
										display: 'inline-block',
										background: '#eee',
									} }
								/>
							) }
						</v.Link>
						<Controls.FlexWP
							direction={ 'column' }
							className={ 'ohmylms-td-thumbnail-title' }
						>
							<v.Link
								to={ '/certificate-edit/'.concat(
									t === null || t === void 0 ? void 0 : t.id
								) }
								title={
									t === null || t === void 0 ? void 0 : t.name
								}
								style={ {
									textDecoration: 'none',
								} }
							>
								<Controls.TextWP
									as={ 'span' }
									color={ '#000d25' }
									size={ 16 }
									numberOfLines={ 2 }
									truncate={ ! 0 }
								>
									{ Ge(
										t === null || t === void 0
											? void 0
											: t.name
									) }
								</Controls.TextWP>
							</v.Link>
							<Controls.FlexWP
								align={ 'center' }
								justify={ 'flex-start' }
								gap={ 2 }
								className={
									'ohmylms-td-thumbnail-title-actions'
								}
							>
								<Controls.ButtonWP
									onClick={ r }
									label={ ( 0, I18n.__ )(
										'Edit Certificate',
										'ohmylms'
									) }
									variant={ 'text' }
									style={ {
										height: '26px',
									} }
								>
									<EditIcon />
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</React.Fragment>
			 )
		 );
	};
}
