export const DEFAULT_PLACEMENT = 'top';

const OWN_PROPS = [ 'children', 'title', 'text', 'placement' ];

/**
 * Split the props given to the tooltip wrapper into the props for the
 * `@wordpress/components` Tooltip. The text falls back to `title`, the
 * placement defaults to `top`, and every other prop is passed through.
 *
 * @param {Object} props Wrapper props.
 * @return {Object} Props for the Tooltip component.
 */
export function getTooltipProps( props ) {
	const rest = {};
	Object.keys( props ).forEach( ( key ) => {
		if ( ! OWN_PROPS.includes( key ) ) {
			rest[ key ] = props[ key ];
		}
	} );

	const { text, title, placement } = props;

	return {
		text: text || title,
		...rest,
		placement: undefined === placement ? DEFAULT_PLACEMENT : placement,
	};
}
