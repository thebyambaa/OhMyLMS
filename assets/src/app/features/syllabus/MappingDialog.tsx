import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	readMappings,
	removeMapping,
	saveMapping,
	sharedSkills,
	type Mapping,
} from './api';
import type { Skill } from './model';

export function MappingDialog( {
	skill,
	onClose,
}: {
	skill: Skill;
	onClose: () => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => readMappings( skill.term_id, signal ),
		[ skill.term_id ]
	);
	const mappings = useRequest( load );
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const targetsLoad = useCallback(
		( signal: AbortSignal ) => sharedSkills( search, page, signal ),
		[ search, page ]
	);
	const targets = useRequest( targetsLoad );
	const [ target, setTarget ] = useState< {
		id: number;
		name: string;
	} | null >( null );
	const [ relation, setRelation ] = useState( 'equivalent' );
	const [ note, setNote ] = useState( '' );
	const [ confirm, setConfirm ] = useState< 'save' | Mapping | null >( null );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	function select( id: number, name: string ) {
		const current = mappings.data?.find( ( row ) => row.shared_id === id );
		setTarget( { id, name } );
		setRelation( current?.relation ?? 'equivalent' );
		setNote( current?.note ?? '' );
		operation.clearError();
	}
	async function apply() {
		if ( ! confirm || ! mappings.data ) {
			return;
		}
		if ( confirm === 'save' ) {
			if ( ! target ) {
				return;
			}
			const current = mappings.data.find(
				( row ) => row.shared_id === target.id
			);
			await operation.run(
				( signal ) =>
					saveMapping(
						skill.term_id,
						target.id,
						relation,
						note,
						current?.revision ?? '',
						signal
					),
				() => {
					setConfirm( null );
					setTarget( null );
					mappings.retry();
					setMessage( __( 'Mapping saved.', 'ohmylms' ) );
				}
			);
		} else {
			const mapping = confirm;
			await operation.run(
				( signal ) => removeMapping( skill.term_id, mapping, signal ),
				() => {
					setConfirm( null );
					setTarget( null );
					mappings.retry();
					setMessage( __( 'Mapping removed.', 'ohmylms' ) );
				}
			);
		}
	}
	return (
		<Modal
			title={ __( 'Shared-skill mappings', 'ohmylms' ) }
			onRequestClose={ () => {
				if ( ! operation.busy ) {
					onClose();
				}
			} }
		>
			<h2>
				{ skill.code } { skill.name }
			</h2>
			<p>
				{ __(
					'Mappings connect this syllabus objective to a reusable shared skill. They affect combined learner skill views; names are never merged automatically.',
					'ohmylms'
				) }
			</p>
			{ ( mappings.error || targets.error || operation.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ mappings.error || targets.error || operation.error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			{ operation.error && (
				<Button
					disabled={ operation.busy }
					onClick={ () => {
						mappings.retry();
						operation.clearError();
						setConfirm( null );
					} }
				>
					{ __( 'Reload mappings', 'ohmylms' ) }
				</Button>
			) }
			{ mappings.loading && (
				<p role="status">{ __( 'Loading mappings…', 'ohmylms' ) }</p>
			) }
			{ mappings.error && (
				<Button onClick={ mappings.retry }>
					{ __( 'Reload mappings', 'ohmylms' ) }
				</Button>
			) }
			<fieldset
				disabled={
					operation.busy ||
					mappings.loading ||
					!! mappings.error ||
					!! confirm
				}
			>
				<legend>{ __( 'Current mappings', 'ohmylms' ) }</legend>
				<ul>
					{ mappings.data?.map( ( mapping ) => (
						<li key={ mapping.shared_id }>
							{ mapping.name } — { mapping.relation }
							{ mapping.combined ? (
								<p>
									{ __(
										'Combined coverage is managed as a complete group in the existing comparison workspace.',
										'ohmylms'
									) }
								</p>
							) : (
								<>
									<Button
										onClick={ () =>
											select(
												mapping.shared_id,
												mapping.name
											)
										}
									>
										{ __( 'Edit mapping', 'ohmylms' ) }
									</Button>
									<Button
										onClick={ () => setConfirm( mapping ) }
									>
										{ __( 'Remove mapping', 'ohmylms' ) }
									</Button>
								</>
							) }
						</li>
					) ) }
				</ul>
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						setPage( 1 );
						setSearch( query );
					} }
				>
					<TextControl
						label={ __( 'Search shared skills', 'ohmylms' ) }
						value={ query }
						maxLength={ 100 }
						onChange={ setQuery }
					/>
					<Button type="submit">{ __( 'Search', 'ohmylms' ) }</Button>
				</form>
				{ targets.loading && (
					<p role="status">
						{ __( 'Loading shared skills…', 'ohmylms' ) }
					</p>
				) }
				{ targets.error && (
					<Button onClick={ targets.retry }>
						{ __( 'Retry search', 'ohmylms' ) }
					</Button>
				) }
				<ul>
					{ targets.data?.items
						.filter( ( item ) => item.id !== skill.term_id )
						.map( ( item ) => (
							<li key={ item.id }>
								<Button
									onClick={ () =>
										select( item.id, item.name )
									}
								>
									{ item.name }
								</Button>
							</li>
						) ) }
				</ul>
				<Button
					disabled={ page <= 1 }
					onClick={ () => setPage( page - 1 ) }
				>
					{ __( 'Previous', 'ohmylms' ) }
				</Button>
				<span>{ page }</span>
				<Button
					disabled={
						! targets.data || page * 30 >= targets.data.total
					}
					onClick={ () => setPage( page + 1 ) }
				>
					{ __( 'Next', 'ohmylms' ) }
				</Button>
				{ target && (
					<section>
						<h3>{ target.name }</h3>
						<SelectControl
							label={ __( 'Relationship', 'ohmylms' ) }
							value={ relation }
							options={
								[
									{
										value: 'equivalent',
										label: __( 'Equivalent', 'ohmylms' ),
									},
									{
										value: 'broader',
										label: __( 'Broader', 'ohmylms' ),
									},
									{
										value: 'narrower',
										label: __( 'Narrower', 'ohmylms' ),
									},
									{
										value: 'related',
										label: __( 'Related only', 'ohmylms' ),
									},
								] as { value: string; label: string }[]
							}
							onChange={ setRelation }
						/>
						<TextareaControl
							label={ __( 'Mapping note', 'ohmylms' ) }
							value={ note }
							maxLength={ 1000 }
							onChange={ setNote }
						/>
						<Button
							variant="primary"
							disabled={
								!! mappings.data?.find(
									( row ) => row.shared_id === target.id
								)?.combined
							}
							onClick={ () => setConfirm( 'save' ) }
						>
							{ __( 'Review mapping change', 'ohmylms' ) }
						</Button>
					</section>
				) }
			</fieldset>
			{ confirm && (
				<section role="alert">
					<p>
						{ __(
							'Apply this mapping change to the shared learner skill views?',
							'ohmylms'
						) }
					</p>
					<Button
						disabled={ operation.busy }
						onClick={ () => setConfirm( null ) }
					>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button
						disabled={ operation.busy }
						isBusy={ operation.busy }
						variant="primary"
						onClick={ () => void apply() }
					>
						{ __( 'Confirm mapping change', 'ohmylms' ) }
					</Button>
				</section>
			) }
		</Modal>
	);
}
