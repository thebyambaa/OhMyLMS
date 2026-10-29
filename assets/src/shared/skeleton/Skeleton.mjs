import { createElement, memo } from '@wordpress/element';

/**
 * Loading placeholder with optional avatar, title and paragraph lines.
 *
 * Extra props (data attributes, style, ...) are passed to the root element.
 *
 * @param {Object}  props
 * @param {boolean} [props.active=true]    Animate the placeholder.
 * @param {boolean} [props.avatar=false]   Show the avatar block.
 * @param {boolean} [props.title=true]     Show the title line.
 * @param {boolean} [props.paragraph=true] Show the paragraph lines.
 * @param {number}  [props.rows=3]         Number of paragraph lines.
 * @param {string}  [props.className='']   Extra root class.
 * @return {import('react').ReactElement} Placeholder markup.
 */
function SkeletonView( {
	active = true,
	avatar = false,
	title = true,
	paragraph = true,
	rows = 3,
	className = '',
	...rest
} ) {
	const classes = [ 'ohmylms-skeleton', className ];
	if ( active ) {
		classes.push( 'ohmylms-skeleton-active' );
	}

	return createElement(
		'div',
		{ className: classes.filter( Boolean ).join( ' ' ), ...rest },
		avatar &&
			createElement( 'div', { className: 'ohmylms-skeleton-avatar' } ),
		createElement(
			'div',
			{ className: 'ohmylms-skeleton-content' },
			title &&
				createElement( 'div', {
					className: 'ohmylms-skeleton-title',
				} ),
			paragraph &&
				createElement(
					'div',
					{ className: 'ohmylms-skeleton-paragraph' },
					Array.from( { length: rows } ).map( ( unused, index ) =>
						createElement( 'div', {
							key: index,
							className: 'ohmylms-skeleton-line',
						} )
					)
				)
		)
	);
}

export const Skeleton = memo( SkeletonView );
