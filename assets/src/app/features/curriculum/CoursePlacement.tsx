import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { useRequest } from '../../hooks/useRequest';
import { itemPath, readPlacement, readTree, savePlacement } from './api';

/**
 * Curriculum placement saves independently; no publication, access or bundle changes.
 * @param root0
 * @param root0.course
 */
export function CoursePlacement( { course }: { course: number } ) {
	const load = useCallback(
		async ( signal: AbortSignal ) => {
			const [ items, ids ] = await Promise.all( [
				readTree( signal ),
				readPlacement( course, signal ),
			] );
			return { items, ids };
		},
		[ course ]
	);
	const request = useRequest( load );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	async function change( id: number, checked: boolean ) {
		if ( operation.current || ! request.data ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( '' );
		try {
			await savePlacement(
				course,
				checked
					? [ ...request.data.ids, id ]
					: request.data.ids.filter( ( value ) => value !== id ),
				controller.signal
			);
			if ( ! controller.signal.aborted ) {
				request.retry();
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
		<section aria-label={ __( 'Curriculum placement', 'ohmylms' ) }>
			<h2>{ __( 'Curriculum placement', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Each placement change saves immediately, separately from the outline draft.',
					'ohmylms'
				) }
			</p>
			{ ( request.error || error ) && (
				<Notice status="error" isDismissible={ false }>
					{ request.error || error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.loading && (
				<p role="status">{ __( 'Loading placement…', 'ohmylms' ) }</p>
			) }
			{ busy && (
				<p role="status">{ __( 'Saving placement…', 'ohmylms' ) }</p>
			) }
			<TextControl
				label={ __( 'Filter placement choices', 'ohmylms' ) }
				value={ search }
				onChange={ setSearch }
			/>
			{ request.data?.items
				.filter( ( item ) =>
					itemPath( request.data!.items, item )
						.toLocaleLowerCase()
						.includes( search.toLocaleLowerCase() )
				)
				.map( ( item ) => (
					<CheckboxControl
						key={ item.id }
						label={ itemPath( request.data!.items, item ) }
						checked={ request.data!.ids.includes( item.id ) }
						disabled={ busy || request.loading }
						onChange={ ( checked ) =>
							void change( item.id, checked )
						}
					/>
				) ) }
		</section>
	);
}
