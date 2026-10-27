import { createElement } from '@wordpress/element';

/**
 * Shared frame for SDK admin pages so they match the native screens: title row with optional
 * description and actions, then content. Styles live in assets/css/admin-ui.css and read the
 * design tokens, so pages follow the colors and font chosen under Settings → Design.
 * @param root0
 * @param root0.title
 * @param root0.description
 * @param root0.actions
 * @param root0.className
 * @param root0.headingLevel
 * @param root0.children
 */
export function AdminPage( {
	title,
	description,
	actions,
	className = '',
	headingLevel = 1,
	children,
} ) {
	const Heading = `h${ headingLevel }`;
	return (
		<section className={ `ohmylms-ext-page ${ className }`.trim() }>
			<header className="ohmylms-ext-header">
				<div>
					<Heading>{ title }</Heading>
					{ description && <p>{ description }</p> }
				</div>
				{ actions && (
					<div className="ohmylms-ext-actions">{ actions }</div>
				) }
			</header>
			{ children }
		</section>
	);
}

/**
 * White content card; an optional heading.
 * @param root0
 * @param root0.title
 * @param root0.className
 * @param root0.children
 */
export function AdminCard( { title, className = '', children } ) {
	return (
		<div className={ `ohmylms-ext-card ${ className }`.trim() }>
			{ title && <h2>{ title }</h2> }
			{ children }
		</div>
	);
}
