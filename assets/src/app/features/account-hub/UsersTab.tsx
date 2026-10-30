import { ListTableSelect } from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { statusViews } from '../../components/listViews';
import { useRequest } from '../../hooks/useRequest';
import { CreateAccountDialog, EditAccountDialog } from './AccountDialog';
import { formatDate } from './accounts';
import { getAccountOptions, listAccounts } from './accountsApi';
import { HubListTable } from './ListControls';
import type {
	Account,
	AccountFilters,
	AccountStatusFilter,
	RegisteredPeriod,
} from './types';
import { usePagedList } from './usePagedList';

const PERIODS: { value: RegisteredPeriod; label: string }[] = [
	{ value: 'all', label: __( 'Any time', 'ohmylms' ) },
	{ value: 'last_30_days', label: __( 'Last 30 days', 'ohmylms' ) },
	{ value: 'current_month', label: __( 'Current month', 'ohmylms' ) },
	{ value: 'previous_month', label: __( 'Previous month', 'ohmylms' ) },
	{ value: 'current_year', label: __( 'Current year', 'ohmylms' ) },
	{ value: 'last_12_months', label: __( 'Last 12 months', 'ohmylms' ) },
];

const STATUSES: { value: AccountStatusFilter; label: string }[] = [
	{ value: 'all', label: __( 'All', 'ohmylms' ) },
	{ value: 'active', label: __( 'Active', 'ohmylms' ) },
	{ value: 'blocked', label: __( 'Blocked', 'ohmylms' ) },
];

/**
 * All accounts tab: every account of the site with its roles, searchable and filterable, with
 * creating student, teacher and parent accounts and editing names and emails. The server decides
 * which accounts the current user may edit (never their own, never one with more privileges).
 */
export function UsersTab() {
	const [ filters, setFilters ] = useState< AccountFilters >( {
		role: '',
		registered: 'all',
		status: 'all',
	} );
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listAccounts( page, search, filters, signal ),
		[ filters ]
	);
	const list = usePagedList( load );
	const options = useRequest( getAccountOptions );
	const [ creating, setCreating ] = useState( false );
	const [ editing, setEditing ] = useState< Account | null >( null );
	const [ message, setMessage ] = useState( '' );

	function filter( change: Partial< AccountFilters > ) {
		setFilters( ( current ) => ( { ...current, ...change } ) );
		list.setPage( 1 );
	}

	return (
		<>
			{ options.data?.can_create && (
				<Button
					variant="primary"
					onClick={ () => {
						setMessage( '' );
						setCreating( true );
					} }
				>
					{ __( 'Add account', 'ohmylms' ) }
				</Button>
			) }
			{ message && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setMessage( '' ) }
				>
					{ message }
				</Notice>
			) }
			<HubListTable
				caption={ __( 'All accounts', 'ohmylms' ) }
				searchLabel={ __( 'Search accounts', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading accounts…', 'ohmylms' ) }
				emptyText={ __( 'No accounts found.', 'ohmylms' ) }
				views={ statusViews(
					[
						{ value: '', label: __( 'All', 'ohmylms' ) },
						...( options.data?.roles ?? [] ).map( ( role ) => ( {
							value: role.slug,
							label: role.label,
						} ) ),
					],
					filters.role,
					list.data.total,
					( role ) => filter( { role } )
				) }
				filters={
					<>
						<ListTableSelect
							label={ __( 'Registered', 'ohmylms' ) }
							value={ filters.registered }
							options={ PERIODS }
							onChange={ ( registered ) =>
								filter( {
									registered: registered as RegisteredPeriod,
								} )
							}
						/>
						<ListTableSelect
							label={ __( 'Status', 'ohmylms' ) }
							value={ filters.status }
							options={ STATUSES }
							onChange={ ( status ) =>
								filter( {
									status: status as AccountStatusFilter,
								} )
							}
						/>
					</>
				}
				table={ {
					rowKey: ( account ) => account.id,
					columns: [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( account ) => (
								<strong>{ account.display_name }</strong>
							),
						},
						{
							id: 'username',
							label: __( 'Username', 'ohmylms' ),
							render: ( account ) => account.username,
						},
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							render: ( account ) => account.email,
						},
						{
							id: 'roles',
							label: __( 'Roles', 'ohmylms' ),
							render: ( account ) =>
								account.roles
									.map( ( role ) => role.label )
									.join( ', ' ) || '—',
						},
						{
							id: 'registered',
							label: __( 'Registered', 'ohmylms' ),
							render: ( account ) =>
								formatDate( account.registered_at ) || '—',
						},
						{
							id: 'last_login',
							label: __( 'Last sign-in', 'ohmylms' ),
							render: ( account ) =>
								formatDate( account.last_login ) ||
								__( 'Never', 'ohmylms' ),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( account ) =>
								account.blocked
									? __( 'Blocked', 'ohmylms' )
									: __( 'Active', 'ohmylms' ),
						},
					],
					rowActions: ( account ) => [
						account.can_edit && {
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: account name. */
								__( 'Edit %s', 'ohmylms' ),
								account.display_name
							),
							onClick: () => {
								setMessage( '' );
								setEditing( account );
							},
						},
					],
				} }
			/>
			{ creating && options.data && (
				<CreateAccountDialog
					options={ options.data }
					onClose={ () => setCreating( false ) }
					onCreated={ ( created ) => {
						setCreating( false );
						setMessage(
							sprintf(
								/* translators: 1: account name, 2: username. */
								__(
									'Account created for %1$s (username %2$s).',
									'ohmylms'
								),
								created.display_name,
								created.username
							)
						);
						list.reload();
					} }
				/>
			) }
			{ editing && (
				<EditAccountDialog
					account={ editing }
					onClose={ () => setEditing( null ) }
					onSaved={ ( saved ) => {
						setEditing( null );
						setMessage(
							sprintf(
								/* translators: %s: account name. */
								__( 'Saved %s.', 'ohmylms' ),
								saved.display_name
							)
						);
						list.reload();
					} }
				/>
			) }
		</>
	);
}
