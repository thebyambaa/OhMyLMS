import {
	createElement,
	Fragment,
	useEffect,
	useState,
	useId,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PeopleTabs } from '../schools/PeopleTabs';
import { useStudents } from './useStudents';
import { ListTableFrame } from './ListTableFrame';
import { PeopleDirectory } from '../schools/PeopleDirectory';
import { AddModal } from '../schools/AddModal';
import { ViewAsModal, canViewAs } from '../schools/ViewAsModal';
import { PreviewHome } from '../schools/PreviewHome';
import { useNotifications } from '../../shared/notices';
import { Table } from '../../shared/table';
import * as Controls from '../../shared/controls';
import { EmptyState, NoDataIllustration, PageHeader } from '../../shared/media';
export function createStudentList( readRuntime ) {
	function StudentTable() {
		const {
			aY: Filters,
			e9: NameCell,
			fN: Pagination,
			hN: BulkActions,
			Ie: Confirm,
			Ne: MenuIcon,
			q: { Icon },
			vG: AnalyticsIcon,
			RZ: BlockIcon,
			f: Router,
			aN: date,
		} = readRuntime();
		const students = useStudents();
		const navigate = Router.Zp();
		const [ selected, setSelected ] = useState( [] );
		const [ confirmation, setConfirmation ] = useState( null );
		const [ viewAs, setViewAs ] = useState( null );
		const { contextHolder, openNotificationWithIcon } = useNotifications();
		const badge = ( value, variant = 'secondary' ) => (
			<Controls.BadgeWP variant={ variant } isBorderLess>
				{ value }
			</Controls.BadgeWP>
		);
		const columns = [
			{
				title: __( 'Student Name', 'ohmylms' ),
				dataIndex: 'student_name',
				key: 'student_name',
				sorter: true,
				size: '320px',
				render: ( _, student ) => <NameCell data={ student } />,
			},
			{
				title: __( 'Reg. Date', 'ohmylms' ),
				dataIndex: 'registration_date',
				key: 'registration_date',
				sorter: true,
				render: ( value ) =>
					badge(
						value ? date()( value ).format( 'MMMM DD, YYYY' ) : '-'
					),
			},
			{
				title: __( 'Email', 'ohmylms' ),
				dataIndex: 'student_email',
				key: 'student_email',
				sorter: true,
				render: ( value ) => badge( value || '-' ),
			},
			{
				title: __( 'Phone', 'ohmylms' ),
				dataIndex: 'student_phone',
				key: 'student_phone',
				render: ( value ) => badge( value || '-' ),
			},
			{
				title: __( 'Course Taken', 'ohmylms' ),
				dataIndex: 'courses_enrolled',
				key: 'courses_enrolled',
				sorter: true,
				render: ( value ) => badge( value || 0 ),
			},
			{
				title: __( 'Membership Taken', 'ohmylms' ),
				dataIndex: 'membership_enrolled',
				key: 'membership_enrolled',
				sorter: true,
				render: ( value ) => badge( value || 0 ),
			},
			{
				title: __( 'Status', 'ohmylms' ),
				dataIndex: 'is_banned',
				key: 'is_banned',
				render: ( blocked ) =>
					badge(
						blocked
							? __( 'Blocked', 'ohmylms' )
							: __( 'Active', 'ohmylms' ),
						blocked ? 'danger' : 'success'
					),
			},
			{
				title: __( 'Action', 'ohmylms' ),
				key: 'action',
				render: ( _, student ) => (
					<Controls.DropdownMenuWP
						icon={ <Icon icon={ MenuIcon.A } /> }
						controls={ [
							...( canViewAs()
								? [
										{
											title: __(
												'View as student',
												'ohmylms'
											),
											key: 'view-as',
											onClick: () =>
												setViewAs( {
													id: student.user_id,
													name:
														student.student_name ||
														student.student_email,
												} ),
										},
									]
								: [] ),
							{
								title: __( 'Analytics', 'ohmylms' ),
								key: 'analytics',
								icon: <AnalyticsIcon />,
								onClick: () =>
									navigate(
										`/students/${ student.user_id }/report`
									),
							},
							{
								title: student.is_banned
									? __( 'Unblock', 'ohmylms' )
									: __( 'Block', 'ohmylms' ),
								key: 'access',
								icon: <BlockIcon />,
								onClick: () =>
									setConfirmation( {
										ids: [ student.user_id ],
										blocked: ! student.is_banned,
									} ),
							},
						] }
					/>
				),
			},
		];
		const dateOptions = [
			[ '', __( 'All Times', 'ohmylms' ) ],
			[ 'last_30_days', __( 'Last 30 days', 'ohmylms' ) ],
			[ 'current_month', __( 'Current month', 'ohmylms' ) ],
			[ 'previous_month', __( 'Previous month', 'ohmylms' ) ],
			[ 'current_year', __( 'Current year', 'ohmylms' ) ],
			[ 'last_12_months', __( 'Last 12 months', 'ohmylms' ) ],
		].map( ( [ value, label ] ) => ( {
			value,
			label,
		} ) );
		async function confirm() {
			if (
				await students.changeAccess(
					confirmation.ids,
					confirmation.blocked
				)
			) {
				openNotificationWithIcon(
					'success',
					confirmation.blocked
						? __( 'Students banned successfully.', 'ohmylms' )
						: __( 'Student unbanned successfully.', 'ohmylms' )
				);
				setConfirmation( null );
				setSelected( [] );
			}
		}
		function sort( field ) {
			const fields = {
				student_name: 'name',
				student_email: 'email',
			};
			const orderby = fields[ field ] || field;
			setSelected( [] );
			students.updateQuery( {
				orderby,
				order:
					students.query.orderby === orderby &&
					students.query.order === 'ASC'
						? 'DESC'
						: 'ASC',
			} );
		}
		// The shared table only sorts locally and does not emit onChange.
		const sortableColumns = columns.map( ( column ) =>
			column.sorter
				? {
						...column,
						sorter: false,
						title: (
							<button
								type="button"
								onClick={ () => sort( column.dataIndex ) }
								style={ {
									border: 0,
									padding: 0,
									background: 'transparent',
									font: 'inherit',
									color: 'inherit',
									cursor: 'pointer',
								} }
							>
								{ column.title }
							</button>
						),
					}
				: column
		);
		return (
			<Fragment>
				{ contextHolder }
				{ viewAs && (
					<ViewAsModal
						role="student"
						user={ viewAs }
						onClose={ () => setViewAs( null ) }
					/>
				) }
				{ students.error && (
					<div role="alert">
						{ students.error }{ ' ' }
						<button type="button" onClick={ students.reload }>
							{ __( 'Retry', 'ohmylms' ) }
						</button>
					</div>
				) }
				<ListTableFrame
					readRuntime={ readRuntime }
					toolbar={
						selected.length ? (
							<BulkActions
								key={ selected.join( ',' ) }
								items={ selected }
								setItems={ setSelected }
								bulksActions={ [
									{
										label: __( 'Block', 'ohmylms' ),
										value: 'block',
										action: () =>
											setConfirmation( {
												ids: selected,
												blocked: true,
											} ),
									},
								] }
							/>
						) : (
							<Filters
								handleSearch={ ( search ) => {
									setSelected( [] );
									students.updateQuery( {
										search,
									} );
								} }
								searchPlaceholder={ __(
									'Search Students',
									'ohmylms'
								) }
								handleFilterByDays={ ( value ) =>
									students.updateQuery( {
										dateFilter: Array.isArray( value )
											? value.map( ( day ) =>
													date()( day ).format(
														'YYYY-MM-DD'
													)
												)
											: value,
									} )
								}
								filterByDays={ students.query.dateFilter }
								filterByDaysOptions={ dateOptions }
								categories={ [] }
								currentPage={ students.query.page }
								totalItems={ students.total }
								showFilterByPriceType={ false }
								showFilterByCategory={ false }
								showFilterByStatus={ false }
							/>
						)
					}
				>
					<Table
						rowKey="user_id"
						columns={ sortableColumns }
						dataSource={ students.students }
						rowSelection={ {
							selectedRowKeys: selected,
							onChange: setSelected,
						} }
						pagination={ false }
						loading={ students.loading }
						className="student-listing-table"
						locale={ {
							emptyText: (
								<EmptyState
									icon={ <NoDataIllustration /> }
									title={ __(
										'No Students yet!',
										'ohmylms'
									) }
									description={ __(
										'Students with enrollments will appear here.',
										'ohmylms'
									) }
								/>
							),
						} }
					/>
					{ ! students.loading &&
						students.total > students.query.perPage && (
							<Pagination
								total={ students.total }
								currentPage={ students.query.page }
								perPage={ students.query.perPage }
								onPageChange={ ( page ) => {
									setSelected( [] );
									students.updateQuery( {
										page,
									} );
								} }
							/>
						) }
				</ListTableFrame>
				{ confirmation && (
					<Confirm
						isOpen
						title={
							confirmation.blocked
								? __( 'Block', 'ohmylms' )
								: __( 'Unblock Student', 'ohmylms' )
						}
						description={
							confirmation.blocked
								? __(
										'Are you sure you want to block these students? They will lose access to all their enrolled courses and memberships.',
										'ohmylms'
									)
								: __(
										'Are you sure you want to unblock this student? They will regain access to their enrolled courses and memberships.',
										'ohmylms'
									)
						}
						onClose={ () => {
							if ( ! students.saving ) {
								setConfirmation( null );
							}
						} }
						onDelete={ confirm }
						isDelete={ confirmation.blocked }
						actionBtnText={
							confirmation.blocked
								? __( 'Block', 'ohmylms' )
								: __( 'Unblock', 'ohmylms' )
						}
					/>
				) }
			</Fragment>
		);
	}
	return function StudentList() {
		const _controlId = useId();
		const {} = readRuntime();
		const currentTab = () => {
			const value = new URLSearchParams(
				window.location.hash.split( '?' )[ 1 ]
			).get( 'tab' );
			return [
				'students',
				'teachers',
				'parents',
				'users',
				'classes',
				'schools',
			].includes( value )
				? value
				: 'home';
		};
		const [ tab, setTab ] = useState( currentTab );
		const [ adding, setAdding ] = useState( false );
		const [ version, setVersion ] = useState( 0 );
		const [ viewRole, setViewRole ] = useState( '' );
		useEffect( () => {
			const sync = () => setTab( currentTab() );
			window.addEventListener( 'hashchange', sync );
			window.addEventListener( 'popstate', sync );
			return () => {
				window.removeEventListener( 'hashchange', sync );
				window.removeEventListener( 'popstate', sync );
			};
		}, [] );
		return (
			<Controls.ContainerWP>
				<PageHeader
					title={ __( 'Account Hub', 'ohmylms' ) }
					showAddButton={ false }
				/>
				{ canViewAs() && tab !== 'home' && (
					<div
						style={ {
							marginBottom: 16,
						} }
					>
						<label htmlFor={ _controlId + '-field-1' }>
							{ __( 'View as', 'ohmylms' ) }{ ' ' }
							<select
								value=""
								onChange={ ( event ) =>
									setViewRole( event.target.value )
								}
								id={ _controlId + '-field-1' }
							>
								<option value="">
									{ __( 'Choose a role…', 'ohmylms' ) }
								</option>
								<option value="student">
									{ __( 'Student', 'ohmylms' ) }
								</option>
								<option value="teacher">
									{ __( 'Teacher', 'ohmylms' ) }
								</option>
								<option value="parent">
									{ __( 'Parent', 'ohmylms' ) }
								</option>
							</select>
						</label>
					</div>
				) }
				{ viewRole && (
					<ViewAsModal
						role={ viewRole }
						onClose={ () => setViewRole( '' ) }
					/>
				) }
				<PeopleTabs
					movable
					withHome
					active={ tab }
					onChange={ ( next ) => {
						window.history.pushState(
							null,
							'',
							`#/accounthub${ next === 'home' ? '' : `?tab=${ next }` }`
						);
						setTab( next );
					} }
				/>
				{ tab === 'home' && <PreviewHome /> }
				{ tab === 'students' && (
					<>
						<div
							style={ {
								marginBottom: 16,
							} }
						>
							<Controls.ButtonWP
								variant="secondary"
								onClick={ () => setAdding( true ) }
								children={ __( 'Add student', 'ohmylms' ) }
							/>
						</div>
						{ adding && (
							<AddModal
								kind="students"
								onClose={ () => setAdding( false ) }
								onSaved={ () => {
									setAdding( false );
									setVersion( ( value ) => value + 1 );
								} }
							/>
						) }
						<StudentTable key={ version } />
					</>
				) }
				{ ! [ 'home', 'students' ].includes( tab ) && (
					<PeopleDirectory
						key={ tab }
						tab={ tab }
						readRuntime={ readRuntime }
					/>
				) }
			</Controls.ContainerWP>
		);
	};
}
