import { createElement, Fragment } from '@wordpress/element';
import { getFallbackStyle, getImageStyle, getInitials } from './model.mjs';

/**
 * Avatar image, or initials when there is no image, optionally linked.
 *
 * Extra props are passed to the `img` element.
 *
 * @param {Object}        props
 * @param {string}        [props.src]             Image URL.
 * @param {string}        [props.alt='']          Alt text and source of initials.
 * @param {string}        [props.href]            Wrap the avatar in a link.
 * @param {string}        [props.shape='circle']  circle, square or rounded.
 * @param {string}        [props.className='']    Extra wrapper class.
 * @param {string|number} [props.size='80']       Size in pixels.
 * @param {Object}        [props.style={}]        Image or initials style.
 * @param {Object}        [props.wrapperStyle={}] Wrapper style.
 * @return {import('react').ReactElement} Avatar markup.
 */
export function Avatar( {
	src,
	alt = '',
	href,
	shape = 'circle',
	className = '',
	size = '80',
	style = {},
	wrapperStyle = {},
	...rest
} ) {
	const wrapperClass = [ 'ohmylms-avatar', 'ohmylms-avatar-' + shape ];
	if ( className ) {
		wrapperClass.push( className );
	}

	const content = src
		? createElement( 'img', {
				src,
				alt,
				className: 'ohmylms-avatar-img',
				width: size,
				height: size,
				decoding: 'async',
				loading: 'lazy',
				style: getImageStyle( shape, style ),
				...rest,
			} )
		: createElement(
				'span',
				{
					className: 'ohmylms-avatar-fallback',
					style: getFallbackStyle( size, shape, style ),
				},
				getInitials( alt )
			);

	return createElement(
		Fragment,
		null,
		createElement(
			'div',
			{
				className: wrapperClass.join( ' ' ),
				style: { '--size': size + 'px', ...wrapperStyle },
			},
			href
				? createElement(
						'a',
						{
							href,
							'aria-label': alt,
							className: 'ohmylms-avatar-link',
						},
						content
					)
				: content
		)
	);
}
