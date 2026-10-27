import { createElement, useState, useEffect, useRef } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useWorkspace } from './context';
import { skillText, skillPatch } from './skillTranslations.mjs';

/**
 * Translate the opposite objective into this column and review before saving.
 * @param {Object}     props                 Component properties.
 * @param {Object}     props.skill           Shared skill record.
 * @param {string}     props.language        Destination language.
 * @param {string}     props.sourceObjective Current opposite field text.
 * @param {() => void} props.onTranslated    Notify the source editor of saved text.
 * @param {Object}     props.children        Manual editor.
 * @return {Object} Translation column.
 */
export function SkillObjectiveTranslation( {
	skill,
	language,
	sourceObjective,
	onTranslated,
	children,
} ) {
	const w = useWorkspace();
	const [ draft, setDraft ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const alive = useRef( true );
	useEffect( () => {
		alive.current = true;
		return () => {
			alive.current = false;
		};
	}, [] );
	const source = language === 'en' ? 'mn' : 'en';
	const label =
		language === 'en'
			? __( 'Translate from Mongolian', 'ohmylms' )
			: __( 'Translate from English', 'ohmylms' );
	const translate = async () => {
		setBusy( true );
		setError( '' );
		try {
			const result = await window.wp.apiFetch( {
				path: `/ohmylms/v1/skills/${ skill.term_id }/translate`,
				method: 'POST',
				data: {
					locale: language,
					source: {
						name: skillText( skill, source, 'name' ),
						objective: sourceObjective,
						// The translation endpoint retains its legacy description contract.
						description: '',
					},
				},
			} );
			if ( alive.current ) {
				setDraft( result.objective ?? result.name );
			}
		} catch ( problem ) {
			if ( alive.current ) {
				setError(
					problem.message || __( 'Translation failed.', 'ohmylms' )
				);
			}
		} finally {
			if ( alive.current ) {
				setBusy( false );
			}
		}
	};
	const save = async () => {
		if (
			( await w.actions.saveSkill(
				skill,
				skillPatch( language, 'objective', draft.trim() )
			) ) &&
			alive.current
		) {
			setDraft( null );
			onTranslated();
		}
	};
	return (
		<div className="ohmylms-ws-objective-column">
			<div className="ohmylms-ws-objective-frame">
				{ draft === null ? (
					children
				) : (
					<div className="ohmylms-ws-field">
						<label
							className="ohmylms-ws-label"
							htmlFor={ `ohmylms-ws-draft-${ skill.term_id }-${ language }` }
						>
							{ language === 'en'
								? __( 'English translation draft', 'ohmylms' )
								: __(
										'Mongolian translation draft',
										'ohmylms'
									) }
						</label>
						<textarea
							id={ `ohmylms-ws-draft-${ skill.term_id }-${ language }` }
							className="ohmylms-ws-objective"
							value={ draft }
							maxLength={ 2000 }
							onChange={ ( event ) =>
								setDraft( event.target.value )
							}
						/>
						<div className="ohmylms-ws-objective-draft-actions">
							<Button
								variant="primary"
								disabled={ w.pending || ! draft.trim() }
								onClick={ save }
							>
								{ __( 'Save translation', 'ohmylms' ) }
							</Button>
							<Button
								variant="tertiary"
								disabled={ w.pending }
								onClick={ () => setDraft( null ) }
							>
								{ __( 'Discard draft', 'ohmylms' ) }
							</Button>
						</div>
					</div>
				) }
				{ draft === null &&
					createElement(
						Button,
						{
							className: 'ohmylms-ws-objective-translate',
							variant: 'secondary',
							onClick: translate,
							disabled:
								busy ||
								w.pending ||
								! sourceObjective.trim() ||
								sourceObjective.length > 2000,
							isBusy: busy,
							title: label,
						},
						<span aria-hidden="true">⇄</span>,
						label
					) }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</div>
	);
}
