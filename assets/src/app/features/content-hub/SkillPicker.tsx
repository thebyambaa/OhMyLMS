import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { listSkills, type SkillSummary } from './api';

/**
 * Search the catalogue on demand; selected identifiers stay intact across searches.
 *
 * @param props          Picker configuration.
 * @param props.label    Accessible field label.
 * @param props.selected Current skill identifiers.
 * @param props.exclude  Skill being edited, excluded from results.
 * @param props.multiple Whether multiple selections are allowed.
 * @param props.disabled Whether editing is disabled.
 * @param props.onChange Selection change callback.
 */
export function SkillPicker( {
	label,
	selected,
	exclude,
	multiple,
	disabled,
	onChange,
}: {
	label: string;
	selected: number[];
	exclude: number | null;
	multiple: boolean;
	disabled: boolean;
	onChange: ( ids: number[] ) => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ version, setVersion ] = useState( 0 );
	const [ rows, setRows ] = useState< SkillSummary[] >( [] );
	const [ names, setNames ] = useState< Record< number, string > >( {} );
	const [ error, setError ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState( false );
	const selectedKey = selected.join( ',' );
	useEffect( () => {
		const controller = new AbortController();
		const ids = selectedKey ? selectedKey.split( ',' ).map( Number ) : [];
		if ( ! ids.length ) {
			return () => controller.abort();
		}
		const chunks = [];
		for ( let offset = 0; offset < ids.length; offset += 50 ) {
			chunks.push( ids.slice( offset, offset + 50 ) );
		}
		Promise.all(
			chunks.map( ( chunk ) =>
				listSkills( 1, '', controller.signal, chunk )
			)
		).then(
			( results ) => {
				if ( ! controller.signal.aborted ) {
					setNames( ( current ) => ( {
						...current,
						...Object.fromEntries(
							results.flatMap( ( result ) =>
								result.items.map( ( item ) => [
									item.id,
									item.name,
								] )
							)
						),
					} ) );
				}
			},
			( failure ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
				}
			}
		);
		return () => controller.abort();
	}, [ selectedKey ] );
	useEffect( () => {
		if ( ! version ) {
			return;
		}
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listSkills( 1, search, controller.signal ).then(
			( result ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setRows( result.items );
				setLoading( false );
				setNames( ( current ) => ( {
					...current,
					...Object.fromEntries(
						result.items.map( ( item ) => [ item.id, item.name ] )
					),
				} ) );
			},
			( failure ) => {
				if ( ! controller.signal.aborted ) {
					setError( normalizeApiError( failure ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ search, version ] );
	return (
		<fieldset disabled={ disabled }>
			<legend>{ label }</legend>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<ul>
				{ selected.map( ( id ) => (
					<li key={ id }>
						{ names[ id ] ?? `#${ id }` }{ ' ' }
						<Button
							variant="tertiary"
							onClick={ () =>
								onChange(
									selected.filter( ( other ) => other !== id )
								)
							}
							aria-label={ sprintf(
								/* translators: %s: selected skill name. */ __(
									'Remove %s',
									'ohmylms'
								),
								names[ id ] ?? `#${ id }`
							) }
						>
							{ __( 'Remove', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
			<TextControl
				label={ sprintf(
					/* translators: %s: relationship field label. */ __(
						'Search %s by name',
						'ohmylms'
					),
					label
				) }
				value={ query }
				maxLength={ 200 }
				onChange={ setQuery }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						setSearch( query.trim() );
						setVersion( ( value ) => value + 1 );
					}
				} }
			/>
			<Button
				variant="secondary"
				disabled={ loading }
				onClick={ () => {
					setSearch( query.trim() );
					setVersion( ( value ) => value + 1 );
				} }
			>
				{ __( 'Find skills', 'ohmylms' ) }
			</Button>
			<div aria-live="polite">
				{ loading && __( 'Searching…', 'ohmylms' ) }
				{ version > 0 &&
					! loading &&
					! error &&
					! rows.length &&
					__( 'No matching skills.', 'ohmylms' ) }
			</div>
			<ul>
				{ rows
					.filter(
						( row ) =>
							row.id !== exclude &&
							! row.archived &&
							! selected.includes( row.id )
					)
					.map( ( row ) => (
						<li key={ row.id }>
							<Button
								variant="tertiary"
								onClick={ () =>
									onChange(
										multiple
											? [ ...selected, row.id ]
											: [ row.id ]
									)
								}
								disabled={ multiple && selected.length >= 100 }
							>
								{ row.name }
								{ row.code ? ` (${ row.code })` : '' }
							</Button>
						</li>
					) ) }
			</ul>
			<p>
				{ __(
					'Search returns up to 20 matches. Refine the name to find another skill.',
					'ohmylms'
				) }
			</p>
		</fieldset>
	);
}
