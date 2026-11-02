import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import {
	changeLink,
	listTargets,
	type LinkKind,
	type LinkTarget,
	type SkillSummary,
} from './api';

export function SkillLinks( {
	skill,
	onClose,
}: {
	skill: SkillSummary;
	onClose: () => void;
} ) {
	const [ kind, setKind ] = useState< LinkKind >( 'lessons' );
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ linkedOnly, setLinkedOnly ] = useState( true );
	const [ page, setPage ] = useState( 1 );
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< LinkTarget > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listTargets(
			skill.id,
			kind,
			page,
			search,
			linkedOnly,
			controller.signal
		).then(
			( result ) => {
				if ( ! controller.signal.aborted ) {
					if ( page > 1 && page > result.totalPages ) {
						setPage( Math.max( 1, result.totalPages ) );
						return;
					}
					setData( result );
					setLoading( false );
				}
			},
			( failure ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ skill.id, kind, page, search, linkedOnly, version ] );
	async function toggle( target: LinkTarget ) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( null );
		setMessage( null );
		try {
			await changeLink( skill.id, kind, target, controller.signal );
			if ( ! controller.signal.aborted ) {
				setMessage( __( 'Skill link updated.', 'ohmylms' ) );
				setVersion( ( current ) => current + 1 );
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}
	return (
		<Modal
			title={ __( 'Lesson and course links', 'ohmylms' ) }
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			<h3>{ skill.name }</h3>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
					<Button
						onClick={ () =>
							setVersion( ( current ) => current + 1 )
						}
						disabled={ busy }
					>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			<SelectControl
				label={ __( 'Content type', 'ohmylms' ) }
				value={ kind }
				disabled={ busy }
				options={ [
					{ label: __( 'Lessons', 'ohmylms' ), value: 'lessons' },
					{ label: __( 'Courses', 'ohmylms' ), value: 'courses' },
				] }
				onChange={ ( value ) => {
					setKind( value as LinkKind );
					setPage( 1 );
				} }
			/>
			<CheckboxControl
				label={ __( 'Show linked content only', 'ohmylms' ) }
				checked={ linkedOnly }
				disabled={ busy }
				onChange={ ( value ) => {
					setLinkedOnly( value );
					setPage( 1 );
				} }
			/>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSearch( query.trim() );
					setPage( 1 );
					setVersion( ( current ) => current + 1 );
				} }
			>
				<TextControl
					label={ __( 'Search content', 'ohmylms' ) }
					value={ query }
					maxLength={ 200 }
					disabled={ busy }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary" disabled={ busy }>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<div aria-live="polite">
				{ loading && __( 'Loading content…', 'ohmylms' ) }
				{ ! loading &&
					! error &&
					! data.items.length &&
					__( 'No editable content found.', 'ohmylms' ) }
			</div>
			{ ! error && (
				<div aria-busy={ loading }>
					<WidefatTable
						caption={ __( 'Editable content', 'ohmylms' ) }
					>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Title', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Action', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ data.items.map( ( target ) => (
								<tr key={ target.id }>
									<th scope="row">{ target.title }</th>
									<td>
										<Button
											variant="secondary"
											disabled={ busy || loading }
											onClick={ () =>
												void toggle( target )
											}
										>
											{ target.linked
												? __( 'Unlink', 'ohmylms' )
												: __(
														'Link skill',
														'ohmylms'
													) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</WidefatTable>
					<Pagination
						page={ page }
						pages={ data.totalPages }
						busy={ busy || loading }
						onChange={ setPage }
					/>
				</div>
			) }
			<Button variant="secondary" disabled={ busy } onClick={ onClose }>
				{ __( 'Done', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
