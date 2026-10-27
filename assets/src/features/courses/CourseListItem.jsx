/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { courseEditPath } from './model.mjs';
export function createCourseListItem( readRuntime ) {
	return function CourseListItem( props ) {
		const {
			Ge,
			I: Controls,
			React,
			SB,
			b: I18n,
			f: Router,
			g: ReactHooks,
			gG,
			pG,
			v,
			vG,
		} = readRuntime();
		var t,
			course = props.course,
			r = ( props.isHover, ( 0, Router.Zp )() ),
			a = ( 0, ReactHooks.useCallback )(
				function () {
					r( courseEditPath( course ) );
				},
				[ course?.id, course?.syllabus_id, r ]
			),
			o = ( 0, ReactHooks.useCallback )(
				function () {
					r(
						'/course/'.concat(
							course === null || course === void 0
								? void 0
								: course.id,
							'/report'
						)
					);
				},
				[ course === null || course === void 0 ? void 0 : course.id, r ]
			);
		return (
			<React.Fragment>
				<Controls.FlexWP
					align={ 'start' }
					justify={ 'start' }
					gap={ 4 }
				>
					<v.Link
						to={ courseEditPath( course ) }
						className={ 'ohmylms-td-thumbnail' }
					>
						{ ( () => {
							if (
								course !== null &&
								course !== void 0 &&
								course.image_src
							) {
								return (
									<gG.A
										shape={ 'square' }
										src={ course.image_src }
										size={ 100 }
									/>
								);
							}
							if (
								course !== null &&
								course !== void 0 &&
								course.video_src
							) {
								return <video src={ course.video_src } />;
							}
							return (
								<span>
									<SB />
								</span>
							);
						} )() }
					</v.Link>
					<Controls.FlexWP
						direction={ 'column' }
						className={ 'ohmylms-td-thumbnail-title' }
					>
						<v.Link
							to={ courseEditPath( course ) }
							title={
								course === null || course === void 0
									? void 0
									: course.name
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
									( () => {
										if (
											null !==
												( t =
													course === null ||
													course === void 0
														? void 0
														: course.name ) &&
											void 0 !== t
										) {
											return t;
										}
										if (
											course === null ||
											course === void 0
										) {
											return void 0;
										}
										return course.title;
									} )()
								) }
							</Controls.TextWP>
						</v.Link>
						{ Number( course?.syllabus_id ) > 0 && (
							<span className="ohmylms-course-badge">
								{ ( 0, I18n.__ )( 'Syllabus', 'ohmylms' ) }
							</span>
						) }
						{ course?.status === 'draft' &&
							[ 'Untitled', 'Untitled Course' ].includes(
								course?.name
							) && (
								<span className="ohmylms-course-badge is-muted">
									{ ( 0, I18n.__ )(
										'Unnamed draft',
										'ohmylms'
									) }
								</span>
							) }
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'flex-start' }
							gap={ 1 }
							className={ 'ohmylms-td-thumbnail-title-actions' }
						>
							<Controls.ButtonWP
								onClick={ a }
								label={ ( 0, I18n.__ )( 'Edit', 'ohmylms' ) }
								variant={ 'text' }
								style={ {
									height: '26px',
									padding: '5px',
								} }
							>
								<pG.A />
							</Controls.ButtonWP>
							{ course?.syllabus_id > 0 && (
								<Controls.ButtonWP
									variant="text"
									onClick={ () =>
										r(
											`${ courseEditPath( course ) }?view=settings`
										)
									}
								>
									{ I18n.__(
										'Syllabus Settings',
										'ohmylms'
									) }
								</Controls.ButtonWP>
							) }
							<Controls.ButtonWP
								onClick={ o }
								icon={ React.createElement( vG, null ) }
								label={ ( 0, I18n.__ )(
									'Analytics',
									'ohmylms'
								) }
								variant={ 'text' }
								style={ {
									height: '26px',
								} }
							/>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
