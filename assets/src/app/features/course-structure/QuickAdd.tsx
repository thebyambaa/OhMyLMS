import { Button, SelectControl } from '@wordpress/components';
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { newId } from '../../lib/uuid';
import { createResource, type ResourceType } from './api';

interface Job {
	type: ResourceType;
	name: string;
	requestId: string;
}

/**
 * Type a title, press Enter: a new draft lesson, quiz or assignment is created and placed in the unit. The box
 * is cleared and stays usable at once, so a whole list of titles can be typed in a row; they are created one
 * at a time in the order typed. A title that fails comes back into the box with the reason. Placement is saved
 * with the outline like any other.
 *
 * @param props           Component props.
 * @param props.unitName  Name of the unit, for the labels.
 * @param props.onCreated Called with each new resource so the unit can place it.
 */
export function QuickAdd( {
	unitName,
	onCreated,
}: {
	unitName: string;
	onCreated: (
		type: ResourceType,
		resource: { id: number; title: string }
	) => void;
} ) {
	const base = useId();
	const input = useRef< HTMLInputElement >( null );
	const [ type, setType ] = useState< ResourceType >( 'lesson' );
	const [ title, setTitle ] = useState( '' );
	const [ waiting, setWaiting ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const queue = useRef< Job[] >( [] );
	const running = useRef( false );
	const active = useRef< AbortController | null >( null );
	const alive = useRef( true );
	const created = useRef( onCreated );
	created.current = onCreated;
	useEffect(
		() => () => {
			alive.current = false;
			active.current?.abort();
		},
		[]
	);

	async function drain() {
		if ( running.current ) {
			return;
		}
		running.current = true;
		while ( queue.current.length && alive.current ) {
			const job = queue.current[ 0 ]!;
			const controller = new AbortController();
			active.current = controller;
			try {
				const resource = await createResource(
					job.type,
					job.name,
					controller.signal,
					job.requestId
				);
				if ( controller.signal.aborted ) {
					return;
				}
				created.current( job.type, resource );
			} catch ( failure ) {
				if ( controller.signal.aborted ) {
					return;
				}
				setError(
					sprintf(
						/* translators: 1: title, 2: reason. */
						__( 'Could not create “%1$s”: %2$s', 'ohmylms' ),
						job.name,
						normalizeApiError( failure ).message
					)
				);
				setTitle( ( current ) => current || job.name );
			}
			queue.current.shift();
			setWaiting( queue.current.length );
		}
		running.current = false;
	}

	return (
		<form
			className="oml-unit-quick-add"
			aria-label={ sprintf(
				/* translators: %s: unit name. */
				__( 'Quick add to %s', 'ohmylms' ),
				unitName
			) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				const name = title.trim();
				if ( ! name ) {
					return;
				}
				queue.current.push( { type, name, requestId: newId() } );
				setWaiting( queue.current.length );
				setTitle( '' );
				setError( '' );
				void drain();
				input.current?.focus();
			} }
		>
			<SelectControl< ResourceType >
				__nextHasNoMarginBottom
				hideLabelFromVision
				size="small"
				label={ __( 'Type of new activity', 'ohmylms' ) }
				value={ type }
				options={ [
					{ value: 'lesson', label: __( 'Lesson', 'ohmylms' ) },
					{ value: 'quiz', label: __( 'Quiz', 'ohmylms' ) },
					{
						value: 'assignment',
						label: __( 'Assignment', 'ohmylms' ),
					},
				] }
				onChange={ setType }
			/>
			<label className="screen-reader-text" htmlFor={ `${ base }-title` }>
				{ __( 'Title of new activity', 'ohmylms' ) }
			</label>
			<input
				id={ `${ base }-title` }
				ref={ input }
				type="text"
				value={ title }
				maxLength={ 200 }
				placeholder={ __(
					'New activity title, then Enter',
					'ohmylms'
				) }
				onChange={ ( event ) => setTitle( event.target.value ) }
			/>
			<Button
				type="submit"
				size="small"
				variant="secondary"
				disabled={ ! title.trim() }
			>
				{ __( 'Create and add', 'ohmylms' ) }
			</Button>
			{ waiting > 0 && (
				<span role="status" className="oml-unit-quick-add-status">
					{ sprintf(
						/* translators: %d: number of titles being created. */
						_n(
							'Creating %d…',
							'Creating %d…',
							waiting,
							'ohmylms'
						),
						waiting
					) }
				</span>
			) }
			{ error && (
				<span role="alert" className="oml-unit-quick-add-error">
					{ error }
				</span>
			) }
		</form>
	);
}
