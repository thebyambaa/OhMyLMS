import { Table } from '../../../ui/Table';
import { Button, Notice, SelectControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { useRequest } from '../../hooks/useRequest';
import { CreateAccountDialog, EditAccountDialog } from './AccountDialog';
import { formatDate } from './accounts';
import { getAccountOptions, listAccounts } from './accountsApi';
import { ListState, SearchForm } from './ListControls';
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
			<SearchForm
				label={ __( 'Search accounts', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			>
				<SelectControl
					label={ __( 'Role', 'ohmylms' ) }
					value={ filters.role }
					options={ [
						{ value: '', label: __( 'All roles', 'ohmylms' ) },
						...( options.data?.roles ?? [] ).map( ( role ) => ( {
							value: role.slug,
							label: role.label,
						} ) ),
					] }
					onChange={ ( role ) => filter( { role } ) }
				/>
				<SelectControl
					label={ __( 'Registered', 'ohmylms' ) }
					value={ filters.registered }
					options={ PERIODS }
					onChange={ ( registered ) =>
						filter( { registered: registered as RegisteredPeriod } )
					}
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ filters.status }
					options={ STATUSES }
					onChange={ ( status ) =>
						filter( { status: status as AccountStatusFilter } )
					}
				/>
			</SearchForm>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading accounts…', 'ohmylms' ) }
				emptyText={ __( 'No accounts found.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'All accounts', 'ohmylms' ) } striped>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'Username', 'ohmylms' ),
								__( 'Email', 'ohmylms' ),
								__( 'Roles', 'ohmylms' ),
								__( 'Registered', 'ohmylms' ),
								__( 'Last sign-in', 'ohmylms' ),
								__( 'Status', 'ohmylms' ),
								__( 'Actions', 'ohmylms' ),
							].map( ( title ) => (
								<th key={ title } scope="col">
									{ title }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ list.data.items.map( ( account ) => (
							<tr key={ account.id }>
								<th scope="row">{ account.display_name }</th>
								<td>{ account.username }</td>
								<td>{ account.email }</td>
								<td>
									{ account.roles
										.map( ( role ) => role.label )
										.join( ', ' ) || '—' }
								</td>
								<td>
									{ formatDate( account.registered_at ) ||
										'—' }
								</td>
								<td>
									{ formatDate( account.last_login ) ||
										__( 'Never', 'ohmylms' ) }
								</td>
								<td>
									{ account.blocked
										? __( 'Blocked', 'ohmylms' )
										: __( 'Active', 'ohmylms' ) }
								</td>
								<td>
									{ account.can_edit ? (
										<Button
											variant="link"
											aria-label={ sprintf(
												/* translators: %s: account name. */
												__( 'Edit %s', 'ohmylms' ),
												account.display_name
											) }
											onClick={ () => {
												setMessage( '' );
												setEditing( account );
											} }
										>
											{ __( 'Edit', 'ohmylms' ) }
										</Button>
									) : (
										'—'
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</Table>
				<Pagination
					page={ list.page }
					pages={ list.data.totalPages }
					busy={ list.loading }
					onChange={ list.setPage }
				/>
			</ListState>
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
