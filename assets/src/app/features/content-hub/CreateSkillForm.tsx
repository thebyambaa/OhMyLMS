import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { saveSkill } from './api';

/**
 * Create a reusable skill before staging its connection to content.
 *
 * @param root0               Component properties.
 * @param root0.disabled      Whether a connection is being saved.
 * @param root0.onDirtyChange Report unsaved fields and pending creation.
 * @param root0.onCreated     Stage the new skill's connection.
 */
export function CreateSkillForm( {
	disabled,
	onCreated,
	onDirtyChange,
}: {
	disabled: boolean;
	onDirtyChange: ( dirty: boolean ) => void;
	onCreated: ( skill: { id: number; name: string; code: string } ) => void;
} ) {
	const [ name, setName ] = useState( '' );
	const [ code, setCode ] = useState( '' );
	const operation = useOperation();
	useEffect( () => {
		onDirtyChange( !! name || !! code || operation.busy );
	}, [ name, code, operation.busy, onDirtyChange ] );
	return (
		<details>
			<summary>{ __( 'Create a new skill', 'ohmylms' ) }</summary>
			<p>
				{ __(
					'The skill is saved to the library immediately. Save its connection below to link it to this content.',
					'ohmylms'
				) }
			</p>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( ! name.trim() ) {
						return;
					}
					void operation.run(
						( signal ) =>
							saveSkill(
								{
									name,
									code,
									description: '',
									parent: 0,
									prerequisites: [],
								},
								null,
								signal
							),
						( id ) => {
							onCreated( {
								id,
								name: name.trim(),
								code: code.trim(),
							} );
							setName( '' );
							setCode( '' );
						}
					);
				} }
			>
				<fieldset disabled={ disabled || operation.busy }>
					<legend className="screen-reader-text">
						{ __( 'New skill', 'ohmylms' ) }
					</legend>
					<TextControl
						label={ __( 'Skill name', 'ohmylms' ) }
						value={ name }
						maxLength={ 200 }
						onChange={ setName }
						required
					/>
					<TextControl
						label={ __( 'Skill code (optional)', 'ohmylms' ) }
						value={ code }
						maxLength={ 40 }
						onChange={ setCode }
					/>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						type="submit"
						variant="secondary"
						disabled={ ! name.trim() }
						isBusy={ operation.busy }
					>
						{ __( 'Create skill and select', 'ohmylms' ) }
					</Button>
				</fieldset>
			</form>
		</details>
	);
}
