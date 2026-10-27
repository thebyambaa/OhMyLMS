import { createElement } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useWorkspace } from './context';

/**
 * Choose the language used by the outline and supporting skill fields.
 * @return {Object} Language buttons.
 */
export function SkillTranslationControls() {
	const w = useWorkspace();
	const languages = [
		{ value: 'original', icon: '🌐', label: __( 'Original', 'ohmylms' ) },
		{ value: 'en', icon: '🇬🇧', label: 'English' },
		{ value: 'mn', icon: '🇲🇳', label: 'Монгол' },
	];
	return (
		<div
			className="ohmylms-ws-language-buttons"
			role="group"
			aria-label={ __( 'Editing language', 'ohmylms' ) }
		>
			{ languages.map( ( language ) =>
				createElement(
					Button,
					{
						key: language.value,
						variant:
							w.skillLanguage === language.value
								? 'primary'
								: 'secondary',
						'aria-pressed': w.skillLanguage === language.value,
						title: language.label,
						disabled: w.pending,
						onClick: () => w.setSkillLanguage( language.value ),
					},
					<span aria-hidden="true">{ language.icon }</span>,
					language.label
				)
			) }
		</div>
	);
}
