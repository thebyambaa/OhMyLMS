/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createStudentNameCell( readRuntime ) {
	return function StudentNameCell( props ) {
		const {
			JU,
			React,
			UG,
			aN,
			b: I18n,
			f: Router,
			g: ReactHooks,
			v,
			vG,
		} = readRuntime();
		var data = props.data,
			n = ( props.isHover, ( 0, Router.Zp )() ),
			r = ( 0, ReactHooks.useCallback )(
				function () {
					n(
						'/students/'.concat(
							data === null || data === void 0
								? void 0
								: data.user_id,
							'/report'
						)
					);
				},
				[ data === null || data === void 0 ? void 0 : data.user_id, n ]
			);
		return (
			<UG.A
				style={ {
					minHeight: '65px',
					minWidth: '220px',
				} }
				marginBottom={ 0 }
			>
				<Controls.FlexWP
					align={ 'start' }
					justify={ 'start' }
					gap={ 4 }
				>
					<v.Link
						to={ '/students/'.concat(
							data === null || data === void 0
								? void 0
								: data.user_id,
							'/report'
						) }
					>
						{ data !== null &&
						data !== void 0 &&
						data.student_img ? (
							<Controls.AvatarWP
								shape={ 'circle' }
								alt={
									data === null || data === void 0
										? void 0
										: data.student_name
								}
								src={ data.student_img }
								size={ 40 }
							/>
						) : (
							<JU />
						) }
					</v.Link>
					<Controls.FlexWP
						direction={ 'column' }
						className={ 'ohmylms-td-thumbnail-title' }
					>
						<v.Link
							to={ '/students/'.concat(
								data === null || data === void 0
									? void 0
									: data.user_id,
								'/report'
							) }
							title={
								data === null || data === void 0
									? void 0
									: data.student_name
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
								{ data === null || data === void 0
									? void 0
									: data.student_name }
							</Controls.TextWP>
						</v.Link>
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'start' }
							gap={ '2' }
						>
							<div className={ 'ohmylms-td-action-analytics' }>
								<Controls.ButtonWP
									icon={ React.createElement( vG, null ) }
									onClick={ r }
									variant={ 'text' }
									label={ ( 0, I18n.__ )(
										'Analytics',
										'ohmylms'
									) }
									style={ {
										height: '26px',
									} }
								/>
							</div>
							<div className={ 'ohmylms-td-login-info' }>
								{ data !== null &&
								data !== void 0 &&
								data.last_login ? (
									<Controls.BadgeWP
										variant={ 'secondary' }
										isBorderLess={ ! 0 }
									>
										{ ( 0, I18n.__ )(
											'Last login',
											'ohmylms'
										) }{ ' ' }
										{ aN()(
											data === null || data === void 0
												? void 0
												: data.last_login
										).format( 'MMMM DD, YYYY' ) || '-' }
									</Controls.BadgeWP>
								) : (
									<Controls.BadgeWP
										variant={ 'secondary' }
										isBorderLess={ ! 0 }
									>
										{ ( 0, I18n.__ )(
											'Not logged in yet',
											'ohmylms'
										) }
									</Controls.BadgeWP>
								) }
							</div>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</Controls.FlexWP>
			</UG.A>
		);
	};
}
