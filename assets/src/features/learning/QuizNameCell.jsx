/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createQuizNameCell( readRuntime ) {
	return function QuizNameCell( props ) {
		const {
			React,
			UG,
			b: I18n,
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
						'/quiz-edit/'.concat(
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
						'/quiz-report/'.concat(
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
					marginBottom={ 0 }
				>
					<Controls.FlexWP
						align={ 'start' }
						justify={ 'start' }
						gap={ '4' }
					>
						<v.Link
							to={ '/quiz-edit/'.concat(
								record === null || record === void 0
									? void 0
									: record.id
							) }
						>
							<svg
								fill={ 'none' }
								width={ '33' }
								height={ '34' }
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
										'M16.6 24.75c0 .413-.352.75-.782.75h-4.69C9.398 25.5 8 24.157 8 22.5v-12c0-1.658 1.4-3 3.127-3h10.946c1.728 0 3.128 1.342 3.128 3v6c0 .413-.352.75-.782.75-.43 0-.782-.337-.782-.75v-6c0-.825-.704-1.5-1.564-1.5H11.127c-.86 0-1.563.675-1.563 1.5v12c0 .825.703 1.5 1.563 1.5h4.691c.43 0 .782.337.782.75zM21.268 12c0-.412-.352-.75-.782-.75h-7.795c-.43 0-.782.338-.782.75s.352.75.782.75h7.795c.43 0 .782-.338.782-.75zm-1.564 3.75c0-.412-.352-.75-.782-.75h-6.23c-.431 0-.783.338-.783.75 0 .413.352.75.782.75h6.231c.43 0 .782-.337.782-.75zm-7.013 3c-.43 0-.782.337-.782.75s.352.75.782.75h2.322c.43 0 .782-.337.782-.75s-.352-.75-.782-.75h-2.322zm13.065.968a.802.802 0 00-1.103 0l-4.136 3.967-1.79-1.717a.802.802 0 00-1.102 0 .726.726 0 000 1.057l2.345 2.25a.819.819 0 001.11 0l4.691-4.5a.726.726 0 000-1.057h-.015z'
									}
								/>
							</svg>
						</v.Link>
						<Controls.FlexWP
							direction={ 'column' }
							className={ 'ohmylms-td-thumbnail-title' }
						>
							<v.Link
								to={ '/quiz-edit/'.concat(
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
									{ decodeHtmlEntities(
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
									label={ ( 0, I18n.__ )(
										'Edit Quiz',
										'ohmylms'
									) }
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
									label={ ( 0, I18n.__ )(
										'Quiz Submissions Report',
										'ohmylms'
									) }
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
