/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { EditIcon } from '../../shared/icons';
export function createAssignmentNameCell( readRuntime ) {
	return function AssignmentNameCell( props ) {
		const {
			Ge,
			I: Controls,
			React,
			UG,
			f: Router,
			g: ReactHooks,
			v,
			vG,
		} = readRuntime();
		var record = props.record,
			n = ( props.isHover, ( 0, Router.Zp )() ),
			r = ( 0, ReactHooks.useCallback )(
				function () {
					n(
						'/assignment-edit/'.concat(
							record === null || record === void 0
								? void 0
								: record.id
						)
					);
				},
				[ record === null || record === void 0 ? void 0 : record.id, n ]
			),
			a = ( 0, ReactHooks.useCallback )(
				function () {
					n(
						'/assignment-report/'.concat(
							record === null || record === void 0
								? void 0
								: record.id
						)
					);
				},
				[ record === null || record === void 0 ? void 0 : record.id, n ]
			);
		return (
			<React.Fragment>
				<UG.A
					style={ {
						minHeight: '65px',
					} }
				>
					<Controls.FlexWP
						align={ 'start' }
						justify={ 'start' }
						gap={ '4' }
					>
						<v.Link
							to={ '/assignment-edit/'.concat(
								record === null || record === void 0
									? void 0
									: record.id
							) }
						>
							<svg
								width={ '33' }
								height={ '34' }
								fill={ 'none' }
								viewBox={ '0 0 33 34' }
								xmlns={ 'http://www.w3.org/2000/svg' }
							>
								<rect
									width={ '33' }
									height={ '33' }
									y={ '.5' }
									fill={ '#F4F5F7' }
									rx={ '8' }
								/>
								<path
									fill={ 'var(--ohmylms-primary-color)' }
									d={
										'M17.225 24.663h-6.206c.255-.427.4-.96.4-1.463V11.253c0-1.056.831-1.916 1.852-1.916h8.36c1.021 0 1.852.86 1.852 1.915v4.396c0 .37.29.669.646.669.357 0 .646-.3.646-.668v-4.396c0-1.794-1.41-3.253-3.143-3.253H13.27c-1.734 0-3.143 1.46-3.143 3.252v6.695h-.984C7.41 17.947 6 19.407 6 21.2v1.997c0 1.534 1.2 2.783 2.684 2.797.008 0 .015.005.023.005h8.518c.356 0 .646-.3.646-.668 0-.37-.29-.67-.646-.67zm-9.933-1.465V21.2c0-1.058.83-1.917 1.852-1.917h.984v3.9l-.002.012c0 .809-.636 1.466-1.42 1.466-.78-.002-1.414-.657-1.414-1.464z'
									}
								/>
								<path
									fill={ 'var(--ohmylms-primary-color)' }
									d={
										'M20.959 13.343h-7.008c-.357 0-.646.3-.646.669 0 .369.29.668.646.668h7.008c.357 0 .646-.3.646-.668 0-.37-.29-.669-.646-.669zm0 2.988h-7.008c-.357 0-.646.3-.646.669 0 .369.29.668.646.668h7.008c.357 0 .646-.3.646-.668 0-.37-.29-.669-.646-.669zm-3.504 2.989H13.95c-.357 0-.646.3-.646.668 0 .37.29.669.646.669h3.504c.357 0 .646-.3.646-.669a.657.657 0 00-.646-.668zm9.348-1.385a1.911 1.911 0 00-2.764 0l-4.421 4.574c-.164.17-.27.385-.309.62l-.242 1.484c-.06.367.056.743.31 1.006a1.093 1.093 0 00.972.321l1.433-.25c.23-.04.438-.152.602-.322l4.42-4.573a2.074 2.074 0 000-2.86zm-5.291 6.446l-1.13.197.192-1.167 3.023-3.128.938.97-3.023 3.128zm4.378-4.53l-.443.458-.938-.971.443-.458a.647.647 0 01.938 0 .705.705 0 010 .97z'
									}
								/>
							</svg>
						</v.Link>
						<Controls.FlexWP
							direction={ 'column' }
							className={ 'ohmylms-td-thumbnail-title' }
						>
							<v.Link
								to={ '/assignment-edit/'.concat(
									record === null || record === void 0
										? void 0
										: record.id
								) }
								title={
									record === null || record === void 0
										? void 0
										: record.name
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
										record === null || record === void 0
											? void 0
											: record.name
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
									variant={ 'text' }
									label={ 'Edit Assignment' }
									style={ {
										height: '26px',
									} }
								>
									<EditIcon />
								</Controls.ButtonWP>
								<Controls.ButtonWP
									icon={ React.createElement( vG, null ) }
									onClick={ a }
									variant={ 'text' }
									label={ 'Assignment Submissions Report' }
									style={ {
										height: '26px',
									} }
								/>
							</Controls.FlexWP>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</UG.A>
			</React.Fragment>
		);
	};
}
