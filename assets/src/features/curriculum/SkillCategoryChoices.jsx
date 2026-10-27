import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Dashicon } from '@wordpress/components';
import { categoryAppearance } from './categoryAppearance.mjs';
function textColor( color ) {
	const rgb = color.match( /^#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i );
	if ( ! rgb ) {
		return '#fff';
	}
	const channels = rgb.slice( 1 ).map( ( hex ) => {
		const value = parseInt( hex, 16 ) / 255;
		return value <= 0.04045
			? value / 12.92
			: ( ( value + 0.055 ) / 1.055 ) ** 2.4;
	} );
	const luminance =
		channels[ 0 ] * 0.2126 +
		channels[ 1 ] * 0.7152 +
		channels[ 2 ] * 0.0722;
	return luminance > 0.179 ? '#111' : '#fff';
}

/**
 * A single category selection, using the colors configured on syllabus home.
 * @param root0
 * @param root0.settings
 * @param root0.selected
 * @param root0.disabled
 * @param root0.onSelect
 */
export function SkillCategoryChoices( {
	settings,
	selected,
	disabled,
	onSelect,
} ) {
	const categories = settings.categories || [];
	const choices = [ '', ...categories ];
	if ( selected && ! categories.includes( selected ) ) {
		choices.push( selected );
	}
	return (
		<fieldset
			className="ohmylms-skill-category-choices"
			disabled={ disabled }
		>
			<legend>{ __( 'Skill category', 'ohmylms' ) }</legend>
			<div className="ohmylms-skill-category-options">
				{ choices.map( ( category ) => {
					const chosen = selected === category;
					const style = categoryAppearance(
						{
							category,
							category_assigned: true,
						},
						settings
					);
					const color = category ? style.color : '#596273';
					const unavailable =
						category && ! categories.includes( category );
					return (
						<button
							key={ category }
							type="button"
							className={ chosen ? 'is-selected' : undefined }
							aria-pressed={ chosen }
							disabled={ disabled || unavailable }
							onClick={ () => onSelect( category ) }
							style={ {
								'--category-color': color,
								'--category-text': textColor( color ),
							} }
						>
							<Dashicon
								icon={ ( () => {
									if ( chosen ) {
										return 'yes-alt';
									}
									if ( category ) {
										return style.icon;
									}
									return 'minus';
								} )() }
								aria-hidden="true"
							/>
							{ category || __( 'No category', 'ohmylms' ) }
						</button>
					);
				} ) }
			</div>
			<p className="ohmylms-ext-muted">
				{ __(
					'Create and manage categories on the syllabus home.',
					'ohmylms'
				) }
			</p>
		</fieldset>
	);
}
