import { confirmAction } from '../requestFeedback.mjs';
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ListTableFrame } from '../students/ListTableFrame';
import { AddModal, EditModal, addLabel } from './AddModal';
import { ViewAsModal, canViewAs } from './ViewAsModal';
import { Table } from '../../shared/table';
import * as Controls from '../../shared/controls';
import { EmptyState, NoDataIllustration } from '../../shared/media';
const labels = {
	teachers: 'Teachers',
	parents: 'Parents',
	users: 'All accounts',
	classes: 'Classes',
	schools: 'Schools',
};
export function PeopleDirectory( { tab, readRuntime } ) {
	const {
		JU: AvatarPlaceholder,
		aY: Filters,
		fN: Pagination,
		aN: date,
		Ne: MenuIcon,
		q: { Icon },
	} = readRuntime();
	const [ rows, setRows ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ revision, setRevision ] = useState( 0 );
	const [ search, setSearch ] = useState( '' );
	const [ dateFilter, setDateFilter ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState( [] );
	const [ failedAvatars, setFailedAvatars ] = useState( [] );
	const [ creating, setCreating ] = useState( false );
	const [ editing, setEditing ] = useState( 0 );
	const [ viewAs, setViewAs ] = useState( null );
	const accounts = [ 'teachers', 'parents', 'users' ].includes( tab );
	useEffect( () => {
		let active = true;
		setLoading( true );
		setError( '' );
		async function load() {
			const result = [];
			// These existing endpoints return batches of 50, without a total header.
			for ( let batch = 1; ; batch++ ) {
				const role = ( () => {
					if ( tab === 'teachers' ) {
						return 'teacher';
					}
					if ( tab === 'parents' ) {
						return 'parent';
					}
					return '';
				} )();
				const data = await window.wp.apiFetch( {
					path: `/ohmylms/v1/school/${ accounts ? 'users' : tab }?page=${ batch }&role=${ role }`,
				} );
				if ( ! active ) {
					return;
				}
				result.push( ...data );
				if ( data.length < 50 ) {
					break;
				}
			}
			if ( tab === 'classes' ) {
				for ( let batch = 1; ; batch++ ) {
					const schools = await window.wp.apiFetch( {
						path: `/ohmylms/v1/school/schools?page=${ batch }`,
					} );
					if ( ! active ) {
						return;
					}
					for ( const school of schools ) {
						for ( let classPage = 1; ; classPage++ ) {
							const classes = await window.wp.apiFetch( {
								path: `/ohmylms/v1/school/schools/${ school.id }/classes?page=${ classPage }`,
							} );
							if ( ! active ) {
								return;
							}
							result.push(
								...classes.map( ( row ) => ( {
									...row,
									school_name: school.name,
								} ) )
							);
							if ( classes.length < 50 ) {
								break;
							}
						}
					}
					if ( schools.length < 50 ) {
						break;
					}
				}
			}
			if ( active ) {
				setRows( result );
			}
		}
		load()
			.catch( ( failure ) => {
				if ( active ) {
					setError( failure.message );
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ tab, revision, accounts ] );
	const badge = ( value, variant = 'secondary' ) => (
		<Controls.BadgeWP variant={ variant } isBorderLess>
			{ value ?? '-' }
		</Controls.BadgeWP>
	);
	const name = ( row ) => ( accounts ? row.display_name : row.name );
	const columns = [
		{
			title: ( () => {
				if ( accounts ) {
					return __( 'Name', 'ohmylms' );
				}
				if ( tab === 'classes' ) {
					return __( 'Class Name', 'ohmylms' );
				}
				return __( 'School Name', 'ohmylms' );
			} )(),
			key: 'name',
			size: '320px',
			render: ( _, row ) => (
				<Controls.FlexWP align="start" justify="start" gap={ 4 }>
					{ accounts &&
						( row.avatar && ! failedAvatars.includes( row.id ) ? (
							<img
								src={ row.avatar }
								alt=""
								width={ 40 }
								height={ 40 }
								style={ {
									borderRadius: '50%',
									flexShrink: 0,
								} }
								onError={ () =>
									setFailedAvatars( ( previous ) => [
										...previous,
										row.id,
									] )
								}
							/>
						) : (
							<AvatarPlaceholder />
						) ) }
					<Controls.FlexWP direction="column">
						<Controls.TextWP as="span" color="#000d25" size={ 16 }>
							{ name( row ) }
						</Controls.TextWP>
						{ accounts &&
							badge(
								row.last_login
									? `${ __( 'Last login', 'ohmylms' ) } ${ date()( row.last_login ).format( 'MMMM DD, YYYY' ) }`
									: __( 'Not logged in yet', 'ohmylms' )
							) }
					</Controls.FlexWP>
				</Controls.FlexWP>
			),
		},
		{
			title: accounts
				? __( 'Reg. Date', 'ohmylms' )
				: __( 'Created Date', 'ohmylms' ),
			key: 'created',
			render: ( _, row ) =>
				badge(
					row.created_at
						? date()( row.created_at ).format( 'MMMM DD, YYYY' )
						: '-'
				),
		},
		...( () => {
			if ( accounts ) {
				return [
					{
						title: __( 'Email', 'ohmylms' ),
						dataIndex: 'email',
						key: 'email',
						render: ( value ) => badge( value ),
					},
					{
						title: __( 'Username', 'ohmylms' ),
						dataIndex: 'user_login',
						key: 'user_login',
						render: ( value ) => badge( value ),
					},
					{
						title: __( 'Roles', 'ohmylms' ),
						dataIndex: 'roles',
						key: 'roles',
						render: ( value ) => badge( value ),
					},
				];
			}
			if ( tab === 'classes' ) {
				return [
					{
						title: __( 'School', 'ohmylms' ),
						dataIndex: 'school_name',
						key: 'school_name',
						render: ( value ) =>
							badge( value || __( 'Independent', 'ohmylms' ) ),
					},
					{
						title: __( 'Subject', 'ohmylms' ),
						dataIndex: 'subject',
						key: 'subject',
						render: ( value ) => badge( value || '-' ),
					},
					{
						title: __( 'Grade', 'ohmylms' ),
						dataIndex: 'grade',
						key: 'grade',
						render: ( value ) => badge( value || '-' ),
					},
				];
			}
			return [
				{
					title: __( 'Timezone', 'ohmylms' ),
					dataIndex: 'timezone',
					key: 'timezone',
					render: ( value ) => badge( value ),
				},
			];
		} )(),
		{
			title: __( 'Status', 'ohmylms' ),
			key: 'status',
			render: ( _, row ) =>
				badge(
					( () => {
						if ( row.status === 'archived' ) {
							return __( 'Archived', 'ohmylms' );
						}
						if ( row.is_banned ) {
							return __( 'Blocked', 'ohmylms' );
						}
						return __( 'Active', 'ohmylms' );
					} )(),
					( () => {
						if ( row.is_banned ) {
							return 'danger';
						}
						if ( row.status === 'archived' ) {
							return 'secondary';
						}
						return 'success';
					} )()
				),
		},
		{
			title: __( 'Action', 'ohmylms' ),
			key: 'action',
			render: ( _, row ) => (
				<Controls.DropdownMenuWP
					icon={ <Icon icon={ MenuIcon.A } /> }
					controls={ [
						...( canViewAs() &&
						[ 'teachers', 'parents' ].includes( tab )
							? [
									{
										title: ( () => {
											if ( tab === 'teachers' ) {
												return __(
													'View as teacher',
													'ohmylms'
												);
											}
											return __(
												'View as parent',
												'ohmylms'
											);
										} )(),
										key: 'view-as',
										onClick: () =>
											setViewAs( {
												role: ( () => {
													if ( tab === 'teachers' ) {
														return 'teacher';
													}
													return 'parent';
												} )(),
												user: {
													id: row.id,
													name: row.display_name,
												},
											} ),
									},
								]
							: [] ),
						...( canViewAs() && tab === 'classes'
							? [ 'student', 'instructor' ].map( ( role ) => ( {
									title: ( () => {
										if ( role === 'student' ) {
											return __(
												'View as student',
												'ohmylms'
											);
										}
										return __(
											'View as instructor',
											'ohmylms'
										);
									} )(),
									key: `view-as-${ role }`,
									onClick: () =>
										setViewAs( {
											role,
											classId: Number( row.id ),
										} ),
								} ) )
							: [] ),
						...( accounts
							? ( () => {
									if ( row.edit_url ) {
										return [
											{
												title: __(
													'Edit user',
													'ohmylms'
												),
												key: 'edit',
												onClick: () =>
													setEditing( row.id ),
											},
										];
									}
									return [];
								} )()
							: [
									{
										title: __( 'Manage', 'ohmylms' ),
										key: 'manage',
										onClick: () => {
											window.location.href = `admin.php?page=ohmylms-schools&school=${ ( () => {
												if ( tab === 'schools' ) {
													return row.id;
												}
												return row.school_id || '';
											} )() }${ ( () => {
												if ( tab === 'classes' ) {
													return `&class=${ row.id }&tab=classes`;
												}
												return '';
											} )() }`;
										},
									},
									...( () => {
										if (
											tab === 'classes' &&
											row.status === 'active'
										) {
											return [
												{
													title: __(
														'Archive class',
														'ohmylms'
													),
													key: 'archive',
													onClick: async () => {
														if (
															! ( await confirmAction(
																__(
																	'Archive this class?',
																	'ohmylms'
																)
															) )
														) {
															return;
														}
														try {
															await window.wp.apiFetch(
																{
																	path: `/ohmylms/v1/school/classes/${ row.id }/archive`,
																	method: 'POST',
																	data: {},
																}
															);
															setRevision(
																( value ) =>
																	value + 1
															);
														} catch ( failure ) {
															setError(
																failure.message
															);
														}
													},
												},
											];
										}
										return [];
									} )(),
								] ),
					] }
				/>
			),
		},
	];
	const filtered = rows.filter( ( row ) => {
		const match = [
			name( row ),
			row.email,
			row.user_login,
			row.subject,
			row.grade,
			row.timezone,
			row.school_name,
		].some( ( value ) =>
			String( value || '' )
				.toLowerCase()
				.includes( search.toLowerCase() )
		);
		if ( ! match || ! dateFilter ) {
			return match;
		}
		if ( ! row.created_at ) {
			return false;
		}
		const created = date()( row.created_at );
		if ( Array.isArray( dateFilter ) ) {
			return (
				created.isAfter(
					date()( dateFilter[ 0 ] )
						.startOf( 'day' )
						.subtract( 1, 'second' )
				) &&
				created.isBefore( date()( dateFilter[ 1 ] ).endOf( 'day' ) )
			);
		}
		const now = date()();
		const starts = {
			last_30_days: now.clone().subtract( 30, 'days' ),
			current_month: now.clone().startOf( 'month' ),
			previous_month: now
				.clone()
				.subtract( 1, 'month' )
				.startOf( 'month' ),
			current_year: now.clone().startOf( 'year' ),
			last_12_months: now.clone().subtract( 12, 'months' ),
		};
		return (
			! created.isBefore( starts[ dateFilter ] ) &&
			( dateFilter !== 'previous_month' ||
				created.isBefore( now.clone().startOf( 'month' ) ) )
		);
	} );
	const options = [
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
	return (
		<>
			{ viewAs && (
				<ViewAsModal
					{ ...viewAs }
					onClose={ () => setViewAs( null ) }
				/>
			) }
			{ error && (
				<div role="alert">
					{ error }{ ' ' }
					<button onClick={ () => setRevision( revision + 1 ) }>
						{ __( 'Retry', 'ohmylms' ) }
					</button>
				</div>
			) }
			<ListTableFrame
				readRuntime={ readRuntime }
				toolbar={
					<Filters
						handleSearch={ ( value ) => {
							setSearch( value );
							setPage( 1 );
							setSelected( [] );
						} }
						searchPlaceholder={ sprintf(
							/* translators: %s: account or group type. */ __(
								'Search %s',
								'ohmylms'
							),
							labels[ tab ]
						) }
						handleFilterByDays={ ( value ) => {
							setDateFilter( value );
							setPage( 1 );
							setSelected( [] );
						} }
						filterByDays={ dateFilter }
						filterByDaysOptions={ options }
						categories={ [] }
						currentPage={ page }
						totalItems={ filtered.length }
						showFilterByPriceType={ false }
						showFilterByCategory={ false }
						showFilterByStatus={ false }
					/>
				}
			>
				<div
					style={ {
						marginBottom: 16,
					} }
				>
					<Controls.ButtonWP
						variant="secondary"
						onClick={ () => setCreating( true ) }
						children={ addLabel( tab ) }
					/>
				</div>
				{ editing > 0 && (
					<EditModal
						id={ editing }
						onClose={ () => setEditing( 0 ) }
						onSaved={ () => {
							setEditing( 0 );
							setRevision( ( value ) => value + 1 );
						} }
					/>
				) }
				{ creating && (
					<AddModal
						kind={ tab }
						onClose={ () => setCreating( false ) }
						onSaved={ () => {
							setCreating( false );
							setRevision( ( value ) => value + 1 );
						} }
					/>
				) }
				<Table
					rowKey="id"
					columns={ columns }
					dataSource={ filtered.slice( ( page - 1 ) * 5, page * 5 ) }
					rowSelection={ {
						selectedRowKeys: selected,
						onChange: setSelected,
					} }
					pagination={ false }
					loading={ loading }
					className="student-listing-table"
					locale={ {
						emptyText: (
							<EmptyState
								icon={ <NoDataIllustration /> }
								title={ __( 'No records yet.', 'ohmylms' ) }
							/>
						),
					} }
				/>
				{ ! loading && filtered.length > 5 && (
					<Pagination
						total={ filtered.length }
						currentPage={ page }
						perPage={ 5 }
						onPageChange={ ( value ) => {
							setPage( value );
							setSelected( [] );
						} }
					/>
				) }
			</ListTableFrame>
		</>
	);
}
