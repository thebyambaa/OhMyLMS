import { Button } from '@wordpress/components';
import { useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

interface Props {
	/** Folders as picker options (with their paths). */
	folders: ReadonlyArray< { id: number; label: string } >;
	/** Create a folder or tag; resolves true when it was created. */
	onCreate: (
		kind: 'folder' | 'tag',
		name: string,
		parent: number
	) => Promise< boolean >;
}

/**
 * "Folders and tags" form above a Content Bank list: add a folder (at the top or inside another) or a tag.
 *
 * @param props          Component props.
 * @param props.folders  Existing folders.
 * @param props.onCreate Creates one.
 */
export function BankOrganizer( { folders, onCreate }: Props ) {
	const base = useId();
	const [ kind, setKind ] = useState< 'folder' | 'tag' >( 'folder' );
	const [ name, setName ] = useState( '' );
	const [ parent, setParent ] = useState( '0' );
	const [ busy, setBusy ] = useState( false );
	return (
		<details className="ohmylms-bank-organizer">
			<summary>{ __( 'Folders and tags', 'ohmylms' ) }</summary>
			<form
				onSubmit={ async ( event ) => {
					event.preventDefault();
					if ( ! name.trim() || busy ) {
						return;
					}
					setBusy( true );
					if (
						await onCreate( kind, name.trim(), Number( parent ) )
					) {
						setName( '' );
					}
					setBusy( false );
				} }
			>
				<label
					className="screen-reader-text"
					htmlFor={ `${ base }-kind` }
				>
					{ __( 'What to create', 'ohmylms' ) }
				</label>
				<select
					id={ `${ base }-kind` }
					value={ kind }
					onChange={ ( event ) =>
						setKind( event.target.value as 'folder' | 'tag' )
					}
				>
					<option value="folder">
						{ __( 'New folder', 'ohmylms' ) }
					</option>
					<option value="tag">{ __( 'New tag', 'ohmylms' ) }</option>
				</select>{ ' ' }
				<label
					className="screen-reader-text"
					htmlFor={ `${ base }-name` }
				>
					{ __( 'Name', 'ohmylms' ) }
				</label>
				<input
					id={ `${ base }-name` }
					type="text"
					value={ name }
					maxLength={ 100 }
					placeholder={ __( 'Name', 'ohmylms' ) }
					onChange={ ( event ) => setName( event.target.value ) }
				/>{ ' ' }
				{ 'folder' === kind && (
					<>
						<label
							className="screen-reader-text"
							htmlFor={ `${ base }-parent` }
						>
							{ __( 'Inside folder', 'ohmylms' ) }
						</label>
						<select
							id={ `${ base }-parent` }
							value={ parent }
							onChange={ ( event ) =>
								setParent( event.target.value )
							}
						>
							<option value="0">
								{ __( 'Top level', 'ohmylms' ) }
							</option>
							{ folders.map( ( folder ) => (
								<option key={ folder.id } value={ folder.id }>
									{ folder.label }
								</option>
							) ) }
						</select>{ ' ' }
					</>
				) }
				<Button
					type="submit"
					variant="secondary"
					disabled={ busy || ! name.trim() }
				>
					{ __( 'Add', 'ohmylms' ) }
				</Button>
			</form>
		</details>
	);
}
