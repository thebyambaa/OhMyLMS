import { ListTable } from '../../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useListQuery } from '../../../components/listQuery';
import { statusViews } from '../../../components/listViews';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../../layout/PageTitleActions';
import { useOperation } from '../useOperation';
import { useRequest } from '../useRequest';
import {
	deleteRule,
	getRule,
	getRuleOptions,
	listLog,
	listRules,
	updateRule,
	type RuleQuery,
} from './api';
import {
	actionLabel,
	eventLabel,
	resultLabel,
	type LogEntry,
	type Rule,
	type RuleOptions,
} from './model';
import { RuleEditor } from './RuleEditor';

const DEFAULTS = { status: 'any', s: '', rule: '', show: '', result: 'any' };

function plainDate( value: string | null ): string {
	return value ? value.slice( 0, 16 ).replace( 'T', ' ' ) : '—';
}

/**
 * Automations (`/memberships?panel=automations`): "when <event> then <action>" rules on purchase,
 * enrolment and learning events, with their run log. `rule=new|<id>` opens the editor and
 * `show=log` the log.
 */
export default function AutomationsPanel() {
	const list = useListQuery( DEFAULTS );
	const options = useRequest( getRuleOptions, 'options' );
	const ruleParam = list.values.rule;

	if ( options.error && ! options.data ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ options.error.message }{ ' ' }
				<Button variant="link" onClick={ options.reload }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	if ( ! options.data ) {
		return <p role="status">{ __( 'Loading automations…', 'ohmylms' ) }</p>;
	}
	if ( ruleParam ) {
		return (
			<EditRule
				key={ ruleParam }
				param={ ruleParam }
				options={ options.data }
				onDone={ () => list.set( { rule: '' } ) }
			/>
		);
	}
	if ( 'log' === list.values.show ) {
		return <RunLog options={ options.data } />;
	}
	return <RuleList options={ options.data } />;
}

function EditRule( {
	param,
	options,
	onDone,
}: {
	param: string;
	options: RuleOptions;
	onDone: () => void;
} ) {
	const id = 'new' === param ? 0 : Number( param );
	const rule = useRequest(
		( signal ) =>
			id > 0 ? getRule( id, signal ) : Promise.resolve( null ),
		param
	);
	if ( ! ( Number.isInteger( id ) && id >= 0 ) ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'That is not a valid rule.', 'ohmylms' ) }
			</Notice>
		);
	}
	if ( rule.error ) {
		return (
			<>
				<Notice status="error" isDismissible={ false }>
					{ rule.error.message }
				</Notice>
				<Button variant="link" onClick={ onDone }>
					{ __( 'Back to rules', 'ohmylms' ) }
				</Button>
			</>
		);
	}
	if ( rule.loading && id > 0 ) {
		return <p role="status">{ __( 'Loading rule…', 'ohmylms' ) }</p>;
	}
	return (
		<RuleEditor
			rule={ rule.data }
			options={ options }
			onSaved={ onDone }
			onCancel={ onDone }
		/>
	);
}

function RuleList( { options }: { options: RuleOptions } ) {
	const list = useListQuery( DEFAULTS );
	const status = ( [ 'active', 'paused' ] as const ).find(
		( value ) => value === list.values.status
	);
	const query: RuleQuery = {
		page: list.page,
		search: list.values.s,
		status: status ?? 'any',
	};
	const request = useRequest(
		( signal ) => listRules( query, signal ),
		list.key
	);
	const [ term, setTerm ] = useState( list.values.s );
	useEffect( () => setTerm( list.values.s ), [ list.values.s ] );
	const operation = useOperation();
	const [ removing, setRemoving ] = useState< Rule | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );

	const toggle = async ( rule: Rule ) => {
		const done = await operation.run( async ( signal ) => {
			await updateRule(
				rule.id,
				{ status: 'active' === rule.status ? 'paused' : 'active' },
				signal
			);
		} );
		if ( done ) {
			request.reload();
		}
	};
	const remove = async ( rule: Rule ) => {
		const done = await operation.run( ( signal ) =>
			deleteRule( rule.id, signal )
		);
		if ( done ) {
			setRemoving( null );
			setMessage( __( 'Rule deleted.', 'ohmylms' ) );
			request.reload();
		}
	};
	const data = request.data;

	return (
		<>
			<PageTitleActions>
				<PageTitleAction onClick={ () => list.set( { rule: 'new' } ) }>
					{ __( 'Add rule', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<p>
				{ __(
					'Rules run when something happens to a learner: when <event> then <actions>. Rules only choose from the events and actions below; every run is logged.',
					'ohmylms'
				) }{ ' ' }
				<a
					href={ list.href( { show: 'log' } ) }
					onClick={ ( event ) => {
						event.preventDefault();
						list.set( { show: 'log' } );
					} }
				>
					{ __( 'View the run log', 'ohmylms' ) }
				</a>
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ ( request.error || ( operation.error && ! removing ) ) && (
				<Notice status="error" isDismissible={ false }>
					{ request.error?.message ?? operation.error }
				</Notice>
			) }
			{ ! data && ! request.error && (
				<p role="status">{ __( 'Loading rules…', 'ohmylms' ) }</p>
			) }
			{ data && (
				<ListTable< Rule >
					caption={ __( 'Automation rules', 'ohmylms' ) }
					heading={ __( 'Automation rules list', 'ohmylms' ) }
					formLabel={ __( 'Search rules', 'ohmylms' ) }
					busy={ request.loading || operation.busy }
					rows={ data.items }
					rowKey={ ( rule ) => rule.id }
					emptyMessage={ __( 'No rules yet.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'any', label: __( 'All', 'ohmylms' ) },
							{
								value: 'active',
								label: __( 'Active', 'ohmylms' ),
							},
							{
								value: 'paused',
								label: __( 'Paused', 'ohmylms' ),
							},
						],
						query.status,
						data.total,
						( value ) => list.set( { status: value } ),
						( value ) => list.href( { status: value } )
					) }
					search={ {
						label: __( 'Search rules', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: term,
						onChange: setTerm,
						onSubmit: () => list.set( { s: term.trim() } ),
					} }
					pagination={ {
						page: list.page,
						totalPages: data.totalPages,
						totalItems: data.total,
						itemsLabel: sprintf(
							/* translators: %d: number of rules. */
							_n( '%d rule', '%d rules', data.total, 'ohmylms' ),
							data.total
						),
						onChange: ( page ) => list.set( { page } ),
						href: ( page ) => list.href( { page } ),
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Rule', 'ohmylms' ),
							primary: true,
							render: ( rule ) => (
								<strong>
									<a
										className="row-title"
										href={ list.href( {
											rule: String( rule.id ),
										} ) }
										onClick={ ( event ) => {
											event.preventDefault();
											list.set( {
												rule: String( rule.id ),
											} );
										} }
									>
										{ rule.name }
									</a>
								</strong>
							),
						},
						{
							id: 'when',
							label: __( 'When', 'ohmylms' ),
							render: ( rule ) =>
								eventLabel( options, rule.event ),
						},
						{
							id: 'then',
							label: __( 'Then', 'ohmylms' ),
							render: ( rule ) =>
								rule.actions
									.map( ( action ) =>
										actionLabel( options, action.type )
									)
									.join( ', ' ),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( rule ) =>
								'active' === rule.status
									? __( 'Active', 'ohmylms' )
									: __( 'Paused', 'ohmylms' ),
						},
						{
							id: 'runs',
							label: __( 'Runs', 'ohmylms' ),
							className: 'num',
							render: ( rule ) => rule.run_count,
						},
						{
							id: 'last',
							label: __( 'Last run', 'ohmylms' ),
							render: ( rule ) => plainDate( rule.last_run_at ),
						},
					] }
					rowActions={ ( rule ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							href: list.href( { rule: String( rule.id ) } ),
							onClick: () =>
								list.set( { rule: String( rule.id ) } ),
						},
						{
							key: 'status',
							label:
								'active' === rule.status
									? __( 'Pause', 'ohmylms' )
									: __( 'Activate', 'ohmylms' ),
							disabled: operation.busy,
							onClick: () => void toggle( rule ),
						},
						{
							key: 'trash',
							label: __( 'Delete', 'ohmylms' ),
							destructive: true,
							ariaLabel: sprintf(
								/* translators: %s: rule name. */
								__( 'Delete %s', 'ohmylms' ),
								rule.name
							),
							onClick: () => {
								operation.clearError();
								setRemoving( rule );
							},
						},
					] }
				/>
			) }
			{ removing && (
				<Modal
					title={ __( 'Delete rule', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setRemoving( null )
					}
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: rule name. */
							__(
								'Delete %s and its run log? Pause it instead to keep the log.',
								'ohmylms'
							),
							removing.name
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ () => void remove( removing ) }
					>
						{ __( 'Delete rule', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setRemoving( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}

function RunLog( { options }: { options: RuleOptions } ) {
	const list = useListQuery( DEFAULTS );
	const result = list.values.result;
	const request = useRequest(
		( signal ) => listLog( list.page, result, 0, signal ),
		list.key
	);
	const data = request.data;
	return (
		<>
			<p>
				<a
					href={ list.href( { show: '', result: 'any' } ) }
					onClick={ ( event ) => {
						event.preventDefault();
						list.set( { show: '', result: 'any' } );
					} }
				>
					{ __( 'Back to rules', 'ohmylms' ) }
				</a>
			</p>
			<h2>{ __( 'Run log', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'One row per action that ran. Entries are kept for 90 days.',
					'ohmylms'
				) }
			</p>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error.message }
				</Notice>
			) }
			{ data && (
				<ListTable< LogEntry >
					caption={ __( 'Automation run log', 'ohmylms' ) }
					rows={ data.items }
					rowKey={ ( entry ) => entry.id }
					busy={ request.loading }
					emptyMessage={ __( 'Nothing has run yet.', 'ohmylms' ) }
					views={ statusViews(
						[
							{ value: 'any', label: __( 'All', 'ohmylms' ) },
							{
								value: 'success',
								label: __( 'Done', 'ohmylms' ),
							},
							{
								value: 'skipped',
								label: __( 'Skipped', 'ohmylms' ),
							},
							{
								value: 'failed',
								label: __( 'Failed', 'ohmylms' ),
							},
						],
						result,
						data.total,
						( value ) => list.set( { result: value } ),
						( value ) => list.href( { result: value } )
					) }
					pagination={ {
						page: list.page,
						totalPages: data.totalPages,
						totalItems: data.total,
						onChange: ( page ) => list.set( { page } ),
						href: ( page ) => list.href( { page } ),
					} }
					columns={ [
						{
							id: 'time',
							label: __( 'Time', 'ohmylms' ),
							primary: true,
							render: ( entry ) => plainDate( entry.created_at ),
						},
						{
							id: 'rule',
							label: __( 'Rule', 'ohmylms' ),
							render: ( entry ) => entry.rule_name,
						},
						{
							id: 'event',
							label: __( 'Event', 'ohmylms' ),
							render: ( entry ) =>
								eventLabel( options, entry.event ),
						},
						{
							id: 'learner',
							label: __( 'Learner', 'ohmylms' ),
							render: ( entry ) => entry.user_name,
						},
						{
							id: 'object',
							label: __( 'On', 'ohmylms' ),
							render: ( entry ) => entry.object_title,
						},
						{
							id: 'action',
							label: __( 'Action', 'ohmylms' ),
							render: ( entry ) =>
								actionLabel( options, entry.action ),
						},
						{
							id: 'result',
							label: __( 'Result', 'ohmylms' ),
							render: ( entry ) =>
								`${ resultLabel( entry.status ) }: ${ entry.message }`,
						},
					] }
				/>
			) }
		</>
	);
}
