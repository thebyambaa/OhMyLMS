import { Badge } from '../../../../ui/Badge';
import { ListTable } from '../../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { MouseEvent } from 'react';
import { EngagementToolbar } from '../../../components/EngagementToolbar';
import { Pagination } from '../../../components/Pagination';
import { useListQuery } from '../../../components/listQuery';
import { statusViews } from '../../../components/listViews';
import { useOperation } from '../../../hooks/useOperation';
import { useRequest } from '../../../hooks/useRequest';
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
 * Commerce › Automations (`/automations`): "when <event> then <action>" rules on purchase,
 * enrolment and learning events, with their run log. `rule=new|<id>` opens the editor and
 * `show=log` the log.
 */
export default function AutomationsPanel() {
	const list = useListQuery( DEFAULTS );
	const options = useRequest( getRuleOptions, {
		key: 'options',
		keepData: true,
		errorShape: 'object',
	} );
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
		{ key: param, keepData: true, errorShape: 'object' }
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
	const request = useRequest( ( signal ) => listRules( query, signal ), {
		key: list.key,
		keepData: true,
		errorShape: 'object',
	} );
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

	const listLink = (
		patch: Partial< Pick< typeof DEFAULTS, 'rule' | 'show' > >
	) => ( {
		href: list.href( patch ),
		onClick: ( event: MouseEvent ) => {
			if (
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}
			event.preventDefault();
			list.set( patch );
		},
	} );

	return (
		<>
			<EngagementToolbar
				searchLabel={ __( 'Search rules', 'ohmylms' ) }
				search={ term }
				onSearchChange={ setTerm }
				onSearch={ () => list.set( { s: term.trim() } ) }
				status={ query.status }
				statusOptions={ [
					{ value: 'any', label: __( 'Any status', 'ohmylms' ) },
					{ value: 'active', label: __( 'Active', 'ohmylms' ) },
					{ value: 'paused', label: __( 'Paused', 'ohmylms' ) },
				] }
				onStatusChange={ ( value ) => list.set( { status: value } ) }
				count={
					data
						? sprintf(
								/* translators: %d: number of rules. */
								_n(
									'%d rule',
									'%d rules',
									data.total,
									'ohmylms'
								),
								data.total
							)
						: ''
				}
			>
				<Button variant="secondary" { ...listLink( { show: 'log' } ) }>
					{ __( 'View the run log', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => list.set( { rule: 'new' } ) }
				>
					{ __( 'Add rule', 'ohmylms' ) }
				</Button>
			</EngagementToolbar>
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
				<>
					{ data.items.length ? (
						<ul
							className="oml-engagement-grid"
							aria-label={ __( 'Automation rules', 'ohmylms' ) }
							aria-busy={ request.loading }
						>
							{ data.items.map( ( rule ) => {
								const active = 'active' === rule.status;
								const edit = listLink( {
									rule: String( rule.id ),
								} );
								return (
									<li
										className="oml-engagement-card"
										key={ rule.id }
									>
										<div className="oml-engagement-card-heading">
											<h2>
												<a { ...edit }>{ rule.name }</a>
											</h2>
											<Badge
												tone={
													active
														? 'success'
														: undefined
												}
											>
												{ active
													? __( 'Active', 'ohmylms' )
													: __(
															'Paused',
															'ohmylms'
														) }
											</Badge>
										</div>
										<dl className="oml-engagement-rule-summary">
											<dt>{ __( 'When', 'ohmylms' ) }</dt>
											<dd>
												{ eventLabel(
													options,
													rule.event
												) }
											</dd>
											<dt>{ __( 'Then', 'ohmylms' ) }</dt>
											<dd>
												{ rule.actions
													.map( ( action ) =>
														actionLabel(
															options,
															action.type
														)
													)
													.join( ', ' ) }
											</dd>
											<dt>{ __( 'Runs', 'ohmylms' ) }</dt>
											<dd>{ rule.run_count }</dd>
											<dt>
												{ __( 'Last run', 'ohmylms' ) }
											</dt>
											<dd>
												{ plainDate(
													rule.last_run_at
												) }
											</dd>
										</dl>
										<div className="oml-engagement-card-actions">
											<Button
												variant="secondary"
												{ ...edit }
											>
												{ __( 'Edit', 'ohmylms' ) }
											</Button>
											<Button
												variant="tertiary"
												disabled={ operation.busy }
												onClick={ () =>
													void toggle( rule )
												}
											>
												{ active
													? __( 'Pause', 'ohmylms' )
													: __(
															'Activate',
															'ohmylms'
														) }
											</Button>
											<Button
												variant="tertiary"
												isDestructive
												disabled={ operation.busy }
												aria-label={ sprintf(
													/* translators: %s: rule name. */
													__(
														'Delete %s',
														'ohmylms'
													),
													rule.name
												) }
												onClick={ () => {
													operation.clearError();
													setRemoving( rule );
												} }
											>
												{ __( 'Delete', 'ohmylms' ) }
											</Button>
										</div>
									</li>
								);
							} ) }
						</ul>
					) : (
						<p className="oml-engagement-panel">
							{ query.search || query.status !== 'any'
								? __(
										'No rules match your filters.',
										'ohmylms'
									)
								: __( 'No rules yet.', 'ohmylms' ) }
						</p>
					) }
					{ data.totalPages > 1 && (
						<div className="oml-engagement-pagination">
							<Pagination
								page={ list.page }
								pages={ data.totalPages }
								busy={ request.loading || operation.busy }
								onChange={ ( page ) => list.set( { page } ) }
								href={ ( page ) => list.href( { page } ) }
							/>
						</div>
					) }
				</>
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
		{ key: list.key, keepData: true, errorShape: 'object' }
	);
	const data = request.data;
	return (
		<section className="oml-engagement-panel">
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
		</section>
	);
}
